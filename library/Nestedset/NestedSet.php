<?php
/**
 * $Id$
 *
 * @description@
 *
 * @version $Revision$
 * @author  $Author$
 *
 * Powered by Nextcode, 2009
 */

class Nextcode_Model_NestedSet
{
    /**
     * In MySQL and PostgreSQL, 'left' and 'right' are reserved words
     *
     * This represent table structure
     */
    private $_structure = array(
        'id'    => 'id',
        'name'  => 'name',
        'left'  => 'lft',
        'right' => 'rgt',
    );

    /**
     * Basic required informations for nested objects
     */
    private $_id;
    private $_name;
    private $_right;
    private $_left;

    private $_tableName;
/*
    // Query with depth
    $depth = '
        SELECT node.name, (COUNT(parent.name) - 1) AS depth
        FROM nested_category AS node,
        nested_category AS parent
        WHERE node.lft BETWEEN parent.lft AND parent.rgt
        GROUP BY node.name
        ORDER BY node.lft;
    ';
 */

    /** Findind leafs **/
/*
    $leafs = '
        SELECT name
        FROM nested_category
        WHERE rgt = lft + 1;
    ';
 */
    /** Retrieving single path **/
/*
    $path = '
        SELECT parent.name
        FROM nested_category AS node,
        nested_category AS parent
        WHERE node.lft BETWEEN parent.lft AND parent.rgt
        AND node.name = \'' . 'test' . '\'
        ORDER BY parent.lft;
    ';
 */
    public function __construct()
    {
        // XXX temporary db connection creation
        $db = new Zend_Db_Adapter_Pdo_Pgsql(
            array(
                'host'     => 'localhost',
                'username' => 'postgres',
                'password' => '',
                'dbname'   => 'nested_set',
            )
        );

        $this->setDb($db);
        $this->setTableName('nested');
    }

    public function setTableName($tableName)
    {
        if (!is_null($tableName)) {
            $this->_tableName = (string) $tableName;
        }
    }

    public function setDb($db)
    {
        $this->_db = $db;
    }

    /**
     * @param $name|string      Name of the element
     * @param $parent|string    Name of the parent, default no parent
     * @param $position         Position in the child list, if null then last
     *
     * @return $this
     */
    public function add($name, $parent = null, $position = null)
    {
        // with or without parent
        // without = then on top of nest
        // with    = become a children
        //
        // Position = array($elementi_id, $position)
        // this position = before/after
    }

    /**
     * If recursive, delete children, else children move up!
     * xxx allow a third behavior?
     */
    public function delete($id, $recursive = true)
    {
        // initialize required value from method call
        $isRecursive = (boolean) $recursive;
        $id          = (integer) $id;

        // initialize other variables
        $db             = $this->_db;
        $structureLeft  = (string) $this->_structure['left'];
        $structureRight = (string) $this->_structure['right'];

        // construct select query
        $select = $db->select()
            ->from($this->_tableName, array($structureLeft, $structureRight))
            ->where($this->_structure['id'] . ' = ?', $id);

        // prepare and execute
        $stmt   = $db->query($select);
        $result = $stmt->fetch();

        if (!$result) {
            return false;
        }

        // @todo recursive off

        // get interval for recursive delete
        $left  = (int) $result[$structureLeft];
        $right = (int) $result[$structureRight];

        try {
            $db->beginTransaction();

            // prepare delete query
            $delete = $db->delete($this->_tableName, "$structureLeft BETWEEN $left AND $right");

            // update other elements
            $width = $right - $left + 1;

            // update right
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                SET $structureRight = $structureRight - $width
                WHERE $structureRight > $right
            ");
            $update = $stmt->fetch();

            // update left
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                SET $structureLeft = $structureLeft - $width
                WHERE $structureLeft > $right
            ");
            $update = $stmt->fetch();

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception($e->getMessage());
        }

        return $this;
    }

    /**
     * Get all nodes without children
     */
    public function getLeafs($level)
    {
        //
    }

    /**
     * Get all elements in an array
     *
     * @todo: no parameters = get them all
     *        parent_id     = get all children from this parent
     *        limit         = get all children from parent/main up to this depth
     */
    public function toArray($parent_id = null, $limit = null)
    {
        // XXX this will depends on options:
        $nodes = $this->_getAll();

        $result = array();
        $depths = array();

        foreach ($nodes as $key => $value) {
            if (0 === $value['depth']) {
                $result[$key] = $value;
                $depths[$value['depth'] + 1] = $key;
            } else {
                $parent = &$result;
                for ($i = 0; $i < $value['depth']; $i++) {
                    $parent = &$parent[$depths[$i]];
                }

                $parent[$key] = $value;
                $depths[$value['depth'] + 1] = $key;
            }
        }

        return $result;
    }

    /**
     * Get all elements from nested set
     *
     * @param $depth|array      Array of depth wanted. Default is all
     * @param $mode|string      Mode of depth selection: include/exclude
     *
     * @return array
     */
    private function _getAll($depth = null, $mode = 'include', $order = 'ASC')
    {
        // XXX get to Zend query
        // with variable for column names
        $query = '
            SELECT
                node.id,
                node.name,
                COUNT(parent.name) - 1 AS depth
            FROM
                nested AS node,
                nested AS parent
            WHERE node.lft BETWEEN parent.lft AND parent.rgt
            GROUP BY node.name, node.lft, node.rgt, node.id
        ';

        // Handle depth if required
        if (!is_null($depth)) {
            if (!is_array($depth)) {
                $depth = (int) $depth;
                $query .= 'HAVING COUNT(parent.name) - 1 = ' . $depth;
            } else {
                foreach ($depth as &$one) {
                    $one = (int) $one;
                }
                $depth = implode(', ', $depth);
                $query .= 'HAVING COUNT(parent.name) - 1 IN (' . $depth . ')';
            }

        }

        // Order query results
        $query .= 'ORDER BY node.lft ' . $order . ';';

        // Fetch results
        $stmt  = $this->_db->query($query);
        $nodes = $stmt->fetchAll();

        return $nodes;
    }

    /**
     * Get all elements to display them in HTML
     *
     * Possible options:
     *  - list (simple <ul><li>)
     */
    public function toHtml($method = 'list')
    {
        $nodes = $this->_getAll();

        if ($method == 'list') {
            $result = "<ul>\n";
            $depth = 0;

            foreach ($nodes as $node) {

                if ($depth < $node['depth']) {
                    $result .= "\n<ul>\n";
                } elseif ($depth == $node['depth'] && $depth > 0) {
                    $result .= "</li>\n";
                } elseif ($depth > $node['depth']) {
                    for ($i = 0; $i < ($depth - $node['depth']); $i++) {
                        $result .= "</li></ul>\n";
                    }
                }

                $result .= "<li>{$node['name']} (id: {$node['id']})";

                $depth = $node['depth'];
            }

            $result .= "</li></ul>\n";

            return $result;
        }
    }
}
