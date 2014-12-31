<?php
/**
 * This object is a pattern to store hieriarchical data into a SQL database.
 *
 * The objective is to make it easier to get a full or partial tree from the database
 * with a single request. In addition, it adds multiple methods in order to
 * manipulate the nested tree:
 *  - add()
 *  - delete()
 *  - move()
 *
 * methods to get results:
 * - getAll()
 * - getLeafs()
 * - getChildren()
 *
 * methods to get state of elements:
 * - hasChildren()
 * - isRoot()
 * - getLevel()
 * - numberOfDescendant()
 *
 * methods to get those result to a specific output:
 * - toArray()
 * - toXml()
 * - toJson()
 * - toCsv()
 *
 * Hierarchical data are handled as an array with depth information, but is
 * never outputed that way.
 *
 * @version 0.5
 * @author  François Pietka (fpietka)
 *
 * Powered by Nextcode, 2009
 */

class NestedSet_Model
{
    /**
     * In MySQL and PostgreSQL, 'left' and 'right' are reserved words
     *
     * This represent the default table structure
     */
    protected $_structure = array(
        'id'    => 'id',
        'name'  => 'name',
        'left'  => 'lft',
        'right' => 'rgt',
    );

    /**
     * Database informations required to locate/save the set
     */
    protected $_db;
    protected $_tableName;

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

    public function getTableName()
    {
        return $this->_tableName;
    }

    /**
     * @param $db|Zend_Db_Adapter
     *
     * @return $this
     */
    public function setDb(Zend_Db_Adapter_Abstract $db)
    {
        $this->_db = $db;

        return $this;
    }

    public function getDb()
    {
        return $this->_db;
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

    public function getStructureId()
    {
        return $this->_structure['id'];
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

    public function getStructureName()
    {
        return $this->_structure['name'];
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

    public function getStructureLeft()
    {
        return $this->_structure['left'];
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

    public function getStructureRight()
    {
        return $this->_structure['right'];
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
        $name = (string) $name;

        if (is_null($reference)) {
            (new NestedSet_Model_Builder)->append($this, $name);
        }
        else {
            $reference = (int) $reference;

            (new NestedSet_Model_Builder)->addInto($this, $name, $reference);
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

        $db = $this->getDb();

        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['left'], $this->_structure['right']))
            ->where($this->_structure['id'] . ' = ?', $id);

        $stmt   = $db->query($select);
        $result = $stmt->fetch();

        if (!$result) {
            return false;
        }

        if ($isRecursive) {
            (new NestedSet_Model_Builder)->deleteRecursive($this, $result);
        } else {
            // @TODO
        }

        return $this;
    }

    /**
     * @param $elementId|int    Id of the element to move
     * @param $referenceId|int  Id of the reference element
     * @param $position|string  Position from the reference element. Values are
     *                          'into', 'before', 'after'.
     *
     * @return $this
     */
    public function move($elementId, $referenceId, $position = 'into')
    {
        $db = $this->getDb();

        $reference = $this->getElement($referenceId);
        $element   = $this->getElement($elementId); // @TODO get one level, we don't need all this tree

        // error handling
        if (empty($element) || empty($reference)) {
            return false;
        }

        switch ($position) {
            case 'into':
            default:
                (new NestedSet_Model_Builder)->moveInto($this, $element, $reference);
        }

        return true;
    }

    /**
     * Get width of a node
     */
    public function getNodeWidth($elementId)
    {
        return $this->_getNodeWidth($elementId);
    }

    /**
     * Get width of a node
     *
     * @param $elementId|int    Id of the node
     *
     * @return int
     */
    protected function _getNodeWidth($elementId)
    {
        $db = $this->_db;

        $stmt = $db->query("
            SELECT {$this->_structure['right']} - {$this->_structure['left']} + 1
              FROM {$this->_tableName}
             WHERE {$this->_structure['id']} = $elementId
        ");
        $width = $stmt->fetchColumn();

        return $width;
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
    public function getAll($depth = null, $mode = 'include', $order = 'ASC')
    {
        $db = $this->_db;

        $query = "
            SELECT
                node.{$this->_structure['id']},
                node.{$this->_structure['name']},
                node.{$this->_structure['left']},
                node.{$this->_structure['right']},
                COUNT(parent.{$this->_structure['name']}) - 1 AS depth
            FROM
                {$this->_tableName} AS node,
                {$this->_tableName} AS parent
            WHERE node.{$this->_structure['left']} BETWEEN parent.{$this->_structure['left']} AND parent.{$this->_structure['right']}
            GROUP BY node.{$this->_structure['id']}, node.{$this->_structure['name']}, node.{$this->_structure['left']}, node.{$this->_structure['right']}
        ";

        // Handle depth if required
        if (!is_null($depth)) {
            if (!is_array($depth)) {
                $depth = (int) $depth;

                if ($mode == 'exclude') {
                    $mode = '=';
                }
                else {
                    $mode = '!=';
                }

                $query .= "HAVING COUNT(parent.{$this->_structure['name']}) - 1 $mode $depth";
            }
            else {
                foreach ($depth as &$one) {
                    $one = (int) $one;
                }
                $depth = implode(', ', $depth);

                if ($mode == 'exclude') {
                    $mode = 'NOT IN';
                }
                else {
                    $mode = 'IN';
                }

                $query .= "HAVING COUNT(parent.{$this->_structure['name']}) - 1 $mode ($depth)";
            }
        }

        $query .= " ORDER BY node.{$this->_structure['left']} $order;";

        $stmt  = $db->query($query);
        $nodes = $stmt->fetchAll();

        return $nodes;
    }

    /**
     * Convert a tree array (with depth) into a hierarchical array.
     *
     * @param $tree|array   Array with depth value.
     *
     * @return array
     */
    public function toArray($tree = null)
    {
        return (new NestedSet_Model_Output)->toArray($this, $tree);
    }

    /**
     * Convert a tree array (with depth) into a hierarchical XML string.
     *
     * @param $tree|array   Array with depth value.
     *
     * @return string
     */
    public function toXml($tree = null)
    {
        return (new NestedSet_Model_Output)->toXml($this, $tree);
    }

    /**
     * Return nested set as JSON
     *
     * @params $tree|array          Original 'flat' nested tree
     *
     * @return string
     */
    public function toJson($tree = null)
    {
        return (new NestedSet_Model_Output)->toJson($this, $tree);
    }

    /**
     * Returns all elements as <ul>/<li> structure
     *
     * Possible options:
     *  - list (simple <ul><li>)
     *
     * @return string
     */
    public function toHtml($tree = null, $method = 'list')
    {
        return (new NestedSet_Model_Output)->toHtml($this, $tree, $method);
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
     * @TODO depth
     *
     * @param $elementId|int    Element Id
     * @param $depth|int        Optional, depth of the tree. Default null means
     *                          full tree
     *
     * @return array
     */
    protected function _getElement($elementId, $depth = null, $order = 'ASC')
    {
        // @TODO: test -> if multiple elements with depth 1 are found -> error
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
                node.{$this->_structure['left']},
                node.{$this->_structure['right']},
                COUNT(parent.{$this->_structure['name']}) - 1 AS depth
              FROM
                {$this->_tableName} AS node,
                {$this->_tableName} AS parent
             WHERE node.{$this->_structure['left']} BETWEEN parent.{$this->_structure['left']} AND parent.{$this->_structure['right']}
               AND node.{$this->_structure['left']} BETWEEN {$element[$this->_structure['left']]} AND {$element[$this->_structure['right']]}
             GROUP BY node.{$this->_structure['id']}, node.{$this->_structure['name']}, node.{$this->_structure['left']}, node.{$this->_structure['right']}
             ORDER BY node.{$this->_structure['left']} $order
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
    public function getPath($elementId, $order = 'ASC')
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
     * Returns the number of descendant of an element.
     *
     * @params $elementId|int   ID of the element
     *
     * @return int
     */
    public function numberOfDescendant($elementId)
    {
        $width = $this->_getNodeWidth($elementId);
        $result = ($width - 2) / 2;

        return $result;
    }

    /**
     * Returns if the element is root.
     *
     * @param $elementId|int    Element ID
     *
     * @return boolean
     */
    public function isRoot($elementId)
    {
        $db        = $this->_db;
        $elementId = (int) $elementId;

        $query = "
            SELECT 1
              FROM {$this->_tableName}
             WHERE {$this->_structure['id']} = $elementId
               AND {$this->_structure['left']} = (
                       SELECT MIN({$this->_structure['left']})
                       FROM {$this->_tableName}
                   )
               AND {$this->_structure['right']} = (
                       SELECT MAX({$this->_structure['right']})
                         FROM {$this->_tableName}
                   )
        ";

        $stmt   = $this->_db->query($query);
        $result = $stmt->fetchColumn();

        return (boolean) $result;
    }
}
