<?php
/**
 * $Id$
 *
 * @description@
 * This object is a pattern to store hieriarchical data into a SQL database.
 *
 * The objective is to make it easier to get a full or partial tree from the database
 * with a single request. In addition, it adds multiple methods in order to
 * manipulate the nested tree:
 *  - add()
 *  - delete()
 *  - move()
 * methods to get results:
 * - getAll()
 * - getLeafs()
 * methods to get those result to a specific output:
 * - toArray()
 * - toCsv()
 * - toXml()
 * - toJson()
 *
 * @version 0.2
 * @author  François Pietka (fpietka)
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

    /**
     * Database informations required to locate/save the nest
     */
    private $_db;
    private $_tableName;

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
    }

    /**
     * @param $tableName|string
     *
     * @return $this
     */
    public function setTableName($tableName)
    {
        if (!is_null($tableName)) {
            $this->_tableName = (string) $tableName;
        }

        return $this;
    }

    /**
     * @param $db|Zend_Db_Adapter
     *
     * @return $this
     */
    public function setDb($db)
    {
        $this->_db = $db;

        return $this;
    }

    /**
     * @param $fieldName
     *
     * @return $this
     */
    public function setStructureId($fieldName)
    {
        $this->_structure['id'] = (string) $fieldName;
        return $this;
    }

    /**
     * @param $fieldName
     *
     * @return $this
     */
    public function setStructureName($fieldName)
    {
        $this->_structure['name'] = (string) $fieldName;
        return $this;
    }

    /**
     * @param $fieldName
     *
     * @return $this
     */
    public function setStructureLeft($fieldName)
    {
        $this->_structure['left'] = (string) $fieldName;
        return $this;
    }

    /**
     * @param $fieldName
     *
     * @return $this
     */
    public function setStructureRight($fieldName)
    {
        $this->_structure['right'] = (string) $fieldName;
        return $this;
    }

    /**
     * @param $name|string      Name of the element
     * @param $reference|int    Id of the reference element
     * @param $position|string  Position from the reference element. Values are
     *                          'into', 'before', 'after'.
     *
     * @return $this
     */
    public function add($name, $reference = null, $position = 'into')
    {
        $db = $this->_db;

        $name = (string) $name;

        if (is_null($reference)) {
            // In this case, add it to the end of the set
            $select = $db->select();
            $select->from($this->_tableName, "MAX({$this->_structure['right']})");

            $stmt   = $db->query($select);
            $result = $stmt->fetch();

            if (false === $result) {
                $result = 0;
            } else {
                $result = $result['max'];
            }

            $left  = $result + 1;
            $right = $result + 2;

            try {
                $db->beginTransaction();

                // insert at the end of the nest
                $values = array(
                    $this->_structure['name']  => $name,
                    $this->_structure['left']  => $left,
                    $this->_structure['right'] => $right,
                );

                $db->insert($this->_tableName, $values);
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw new Exception($e->getMessage());
            }
        } else {
            $reference = (int) $reference;
            // case INTO

            // get parent's right value
            $select = $db->select();
            $select->from($this->_tableName, $this->_structure['right']);
            $select->where("{$this->_structure['id']} = $reference");

            $stmt   = $db->query($select);
            $result = $stmt->fetch();

            $right = $result[$this->_structure['right']];

            try {
                $db->beginTransaction();

                // move next elements' right to make room
                $stmt = $db->query("
                    UPDATE {$this->_tableName}
                       SET {$this->_structure['right']} = {$this->_structure['right']} + 2
                     WHERE {$this->_structure['right']} > $right;
                ");
                $update = $stmt->fetch();

                // move next elements' left
                $stmt = $db->query("
                    UPDATE {$this->_tableName}
                       SET {$this->_structure['left']} = {$this->_structure['left']} + 2
                     WHERE {$this->_structure['left']} > $right;
                ");
                $update = $stmt->fetch();

                // make room into parent element
                $stmt = $db->query("
                    UPDATE {$this->_tableName}
                       SET {$this->_structure['right']} = {$this->_structure['right']} + 2
                     WHERE {$this->_structure['id']} = $reference;
                ");
                $update = $stmt->fetch();

                // insert new element
                $values = array(
                    $this->_structure['name']  => $name,
                    $this->_structure['left']  => $right,
                    $this->_structure['right'] => $right + 1,
                );

                $db->insert($this->_tableName, $values);
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw new Exception($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * If recursive, delete children, else children move up in the tree
     *
     * @param $id|int               Id of the element to delete
     * @param $recursive|boolean    Delete element's childrens, default is true
     *
     * @return $this
     */
    public function delete($id, $recursive = true)
    {
        // initialize required value from method call
        $isRecursive = (boolean) $recursive;
        $id          = (integer) $id;

        $db = $this->_db;

        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['left'], $this->_structure['right']))
            ->where($this->_structure['id'] . ' = ?', $id);

        $stmt   = $db->query($select);
        $result = $stmt->fetch();

        if (!$result) {
            return false;
        }

        // get interval for recursive delete
        $left  = (int) $result[$this->_structure['left']];
        $right = (int) $result[$this->_structure['right']];

        try {
            $db->beginTransaction();

            $delete = $db->delete($this->_tableName, "{$this->_structure['left']} BETWEEN $left AND $right");

            // update other elements
            $width = $right - $left + 1;

            // update right
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                SET {$this->_structure['right']} = {$this->_structure['right']} - $width
                WHERE {$this->_structure['right']} > $right
            ");
            $update = $stmt->fetch();

            // update left
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                SET {$this->_structure['left']} = {$this->_structure['left']} - $width
                WHERE {$this->_structure['left']} > $right
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
     * @param $elementId|int    Id of the element to move
     * @param $referenceId|int  Id of the reference element
     * @param $position|string  Position from the reference element. Values are
     *                          'into', 'before', 'after'.
     *
     * return $this
     */
    public function move($elementId, $referenceId, $position = 'into')
    {
        $db = $this->_db;

        // XXX
        //
        // One idea might be to get all information about the node (id, left,
        // right) and it's children in an array. Then move as usual to make
        // room.
        //
        // At the end we will have an array to work with: we will change
        // left/right values of all element according to the new place of
        // storage. Then we'll save them using id's (previously stored).
        //
        // XXX

        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['left'], $this->_structure['right']))
            ->where($this->_structure['id'] . ' = ?', $elementId);

        $stmt    = $db->query($select);
        $element = $stmt->fetch();

        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['left'], $this->_structure['right']))
            ->where($this->_structure['id'] . ' = ?', $referenceId);

        $stmt      = $db->query($select);
        $reference = $stmt->fetch();

        // error handling
        if (empty($element) || empty($reference)) {
            return false;
        }

        try {
            // Case INTO
            $db->beginTransaction();
            // first make room into reference
            $elementWidth = $element[$this->_structure['right']] - $element[$this->_structure['left']] + 1;

            // move right
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['right']} = {$this->_structure['right']} + $elementWidth
                 WHERE {$this->_structure['right']} >= {$reference[$this->_structure['right']]};
            ");
            $update = $stmt->fetch();

            // move left
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['left']} = {$this->_structure['left']} + $elementWidth
                 WHERE {$this->_structure['left']} > {$reference[$this->_structure['right']]};
            ");
            $update = $stmt->fetch();

            // then move element (and it's children) XXX
            // XXX works when moving to the left of the nest, not to the right
            // move right
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['right']} = {$this->_structure['right']} + $elementWidth
                 WHERE {$this->_structure['right']} >= {$reference[$this->_structure['right']]};
            ");
            $update = $stmt->fetch();

            // move left
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['left']} = {$this->_structure['left']} + $elementWidth
                 WHERE {$this->_structure['left']} > {$reference[$this->_structure['right']]};
            ");
            $update = $stmt->fetch();

            // fill the hole XXX


            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Get all nodes without children
     *
     * @return array
     */
    public function getLeafs()
    {
        $db = $this->_db;

        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['id'], $this->_structure['name']))
            ->where("{$this->_structure['right']} = {$this->_structure['left']} + 1");

        $stmt   = $db->query($select);
        $result = $stmt->fetchAll();

        return $result;
    }

    /**
     * Get all elements from nested set
     *
     * @param $depth|array      Array of depth wanted. Default is all
     * @param $mode|string      Mode of depth selection: include/exclude
     * @param $order|string     Mode of sort
     *
     * @return array
     */
    private function _getAll($depth = null, $mode = 'include', $order = 'ASC')
    {
        $db = $this->_db;

        $query = "
            SELECT
                node.{$this->_structure['id']},
                node.{$this->_structure['name']},
                COUNT(parent.{$this->_structure['name']}) - 1 AS depth
            FROM
                {$this->_tableName} AS node,
                {$this->_tableName} AS parent
            WHERE node.{$this->_structure['left']} BETWEEN parent.{$this->_structure['left']} AND parent.{$this->_structure['right']}
            GROUP BY node.{$this->_structure['id']}, node.{$this->_structure['name']}, node.{$this->_structure['left']}
        ";

        // Handle depth if required
        if (!is_null($depth)) {
            if (!is_array($depth)) {
                $depth = (int) $depth;

                if ($mode == 'exclude') {
                    $mode = '=';
                } else {
                    $mode = '!=';
                }

                $query .= "HAVING COUNT(parent.{$this->_structure['name']}) - 1 $mode $depth";
            } else {
                foreach ($depth as &$one) {
                    $one = (int) $one;
                }
                $depth = implode(', ', $depth);

                if ($mode == 'exclude') {
                    $mode = 'NOT IN';
                } else {
                    $mode = 'IN';
                }

                $query .= "HAVING COUNT(parent.{$this->_structure['name']}) - 1 $mode ($depth)";
            }
        }

        $query .= "ORDER BY node.{$this->_structure['left']} $order;";

        $stmt  = $db->query($query);
        $nodes = $stmt->fetchAll();

        return $nodes;
    }

    /**
     * Convert a tree array (with depth) into a hierarchical array.
     * XXX not finished
     *
     * @param $tree|array   Array with depth value.
     *
     * @return array
     */
    public function toArray($tree)
    {
        if (empty($tree) || !is_array($tree)) {
            $nodes = $this->_getAll();
        } else {
            $nodes = $tree;
        }

        $result = array();
        $depths = array();

        foreach ($nodes as $key => $value) {
            // detect if depth as increased to include into parent children
            // XXX
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
     * Convert a tree array (with depth) into a hierarchical XML string.
     * XXX work in progress
     *
     * @param $tree|array   Array with depth value.
     *
     * @return string
     */
    public function toXml($tree)
    {
        if (empty($tree) || !is_array($tree)) {
            $nodes = $this->_getAll();
        } else {
            $nodes = $tree;
        }

        $xml  = new DomDocument('1.0');
        $root = $xml->createElement('nested set');

        $depths = array();

        foreach ($nodes as $key => $value) {
            if (0 === $value['depth']) {
                $element = $root->createElement($value);
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

        $root = $xml->appendChild($root);

        return $xml;
    }

    /**
     * Returns all elements as <ul>/<li> structure
     *
     * Possible options:
     *  - list (simple <ul><li>)
     *
     * @return string
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

        }

        $result .= "</ul>\n";

        /** XXX include into test
         *
        $ulStart = substr_count($result, '<ul>');
        $ulEnd   = substr_count($result, '</ul>');
        $liStart = substr_count($result, '<li>');
        $liEnd   = substr_count($result, '</li>');

        if ($ulStart != $ulEnd) {
            echo "Bad count of <ul> ($ulStart/$ulEnd)";
        }

        if ($liStart != $liEnd) {
            echo "Bad count of <li> ($liStart/$liEnd)";
        }
         */

        return $result;
    }

    /**
     * Public method to get an element
     *
     */
    public function getElement($elementId, $depth = null)
    {
        $element = $this->_getElement($elementId, $depth);
        return $element;
    }

    /**
     * Get one element with its children.
     *
     * @param $elementId|int    Element Id
     * @param $depth|int        Optional, depth of the tree. Default null means
     *                          full tree
     *
     * @return array
     */
    private function _getElement($elementId, $depth = null)
    {
        $db        = $this->_db;
        $elementId = (int) $elementId;

        // Get main element left and right
        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['left'], $this->_structure['right']))
            ->where($this->_structure['id'] . ' = ?', $elementId);

        $stmt    = $db->query($select);
        $element = $stmt->fetch();

        // Get the tree
        $query = "
            SELECT
                node.{$this->_structure['id']},
                node.{$this->_structure['name']},
                COUNT(parent.{$this->_structure['name']}) - 1 AS depth
            FROM
                {$this->_tableName} AS node,
                {$this->_tableName} AS parent
            WHERE node.{$this->_structure['left']} BETWEEN parent.{$this->_structure['left']} AND parent.{$this->_structure['right']}
              AND node.{$this->_structure['left']} BETWEEN {$element[$this->_structure['left']]} AND {$element[$this->_structure['right']]}
            GROUP BY node.{$this->_structure['id']}, node.{$this->_structure['name']}, node.{$this->_structure['left']}
            ORDER BY node.{$this->_structure['left']} $order;
        ";

        $stmt  = $this->_db->query($query);
        $nodes = $stmt->fetchAll();

        return $nodes;
    }

    /**
     * Get path of an element
     *
     * @param $elementId|int    Id of the element we want the path of
     *
     * @return array
     */
    public function getPath($elementId)
    {
        $db        = $this->_db;
        $elementId = (int) $elementId;

        $query = "
            SELECT
                node.{$this->_structure['id']},
                node.{$this->_structure['name']},
                COUNT(parent.{$this->_structure['name']}) - 1 AS depth
            FROM
                {$this->_tableName} AS node,
                {$this->_tableName} AS parent
            WHERE node.{$this->_structure['left']} BETWEEN parent.{$this->_structure['left']} AND parent.{$this->_structure['right']}
              AND node.{$this->_structure['id']} = $elementId
            GROUP BY node.{$this->_structure['id']}, node.{$this->_structure['name']}, node.{$this->_structure['left']}
            ORDER BY node.{$this->_structure['left']} $order;
        ";

        $stmt = $this->_db->query($query);
        $path = $stmt->fetchAll();

        return $path;
    }


    /**
     * Get the parent of an element.
     *
     * @param $elementId|int    Element ID
     * @param $depth|int        Depth of the parent, compared to the child.
     *                          Default is 1 (as immediate)
     *
     * @return array|false
     */
    public function getParent($elementId, $depth = 1)
    {
        $db = $this->_db;

        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['left'], $this->_structure['right']))
            ->where($this->_structure['id'] . ' = ?', $elementId);

        $stmt  = $db->query($select);
        $child = $stmt->fetch();

        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['id'], $this->_structure['name']))
            ->where($this->_structure['left'] . ' < ?', $child[$this->_structure['left']])
            ->where($this->_structure['right'] . ' > ?', $child[$this->_structure['right']])
            ->order('(' . $child[$this->_structure['left']] . ' - ' . $this->_structure['left'] . ')')
            ->limitPage($depth, 1);

        $stmt   = $db->query($select);
        $result = $stmt->fetch();

        return $result;
    }

    /**
     * Returns all children of an element. Default is direct children
     *
     * @param $elementId|int    Element ID
     * @param $level|int        Level of children to return
     *
     * @return array
     */
    public function getChildren($elementId, $level = 1)
    {
        // @todo
        // use getElement excluding main one
    }

    /**
     * Returns all elements from this level of depth.
     *
     * @param $level|int        Level of elements.
     *
     * @return array
     */
    public function getLevel($level)
    {
        // @todo
    }

    /**
     * Sort elements. By default sort all the tree by levels. If a parent is
     * set, it will only sort from this parent's children. If a depth is set, it
     * will sort up to this depth, default being sorting all depth from this
     * parent element.
     *
     * @param $parent|int   ID of the parent element.
     * @param $depth|int    Depth of the sorting action.
     *
     * @return $this
     */
    public function sort($parent = null, $depth = null)
    {
        // @todo
    }
}
