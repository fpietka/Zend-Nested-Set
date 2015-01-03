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
            $this->_tableName = $tableName;
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
        $this->_structure['id'] = $fieldName;
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
        $this->_structure['name'] = $fieldName;
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
        $this->_structure['left'] = $fieldName;
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
        $this->_structure['right'] = $fieldName;
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
        $db = $this->getDb();

        $select = $db
            ->select()
            ->from($this->_tableName, array($this->_structure['id'], $this->_structure['left'], $this->_structure['right']))
            ->where($this->_structure['id'] . ' = ?', $id);

        $stmt   = $db->query($select);
        $result = $stmt->fetch();

        if (!$result) {
            return false;
        }

        if ($recursive) {
            (new NestedSet_Model_Builder)->deleteRecursive($this, $result);
        }
        else {
            (new NestedSet_Model_Builder)->deleteNonRecursive($this, $result);
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
        return (new NestedSet_Model_Reader)->getNodeWidth($this, $elementId);
    }

    /**
     * Get all nodes without children
     *
     * @return array
     */
    public function getLeafs()
    {
        return (new NestedSet_Model_Reader)->getLeafs($this);
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
        return (new NestedSet_Model_Reader)->getAll($this, $depth, $mode, $order);
    }

    /**
     * Convert a tree array (with depth) into a hierarchical array.
     *
     * @param $nodes|array   Array with depth value.
     *
     * @return array
     */
    public function toArray(array $nodes = array())
    {
        if (empty($nodes)) {
            $nodes = $this->getAll();
        }

        return (new NestedSet_Model_Output)->toArray($nodes);
    }

    /**
     * Convert a tree array (with depth) into a hierarchical XML string.
     *
     * @param $nodes|array   Array with depth value.
     *
     * @return string
     */
    public function toXml(array $nodes = array())
    {
        if (empty($nodes)) {
            $nodes = $this->getAll();
        }

        return (new NestedSet_Model_Output)->toXml($nodes);
    }

    /**
     * Return nested set as JSON
     *
     * @params $nodes|array          Original 'flat' nested tree
     *
     * @return string
     */
    public function toJson(array $nodes = array())
    {
        if (empty($nodes)) {
            $nodes = $this->getAll();
        }

        return (new NestedSet_Model_Output)->toJson($nodes);
    }

    /**
     * Returns all elements as <ul>/<li> structure
     *
     * Possible options:
     *  - list (simple <ul><li>)
     *
     * @return string
     */
    public function toHtml(array $nodes = array(), $method = 'list')
    {
        if (empty($nodes)) {
            $nodes = $this->getAll();
        }

        return (new NestedSet_Model_Output)->toHtml($nodes, $method);
    }

    /**
     * Public method to get an element
     *
     */
    public function getElement($elementId, $depth = null)
    {
        return (new NestedSet_Model_Reader)->getElement($this, $elementId, $depth);
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
        return (new NestedSet_Model_Reader)->getPath($this, $elementId, $order);
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
        return (new NestedSet_Model_Reader)->getParent($this, $elementId, $depth);
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
        $width = (new NestedSet_Model_Reader)->getNodeWidth($this, $elementId);
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
        return (new NestedSet_Model_Reader)->isRoot($this, $elementId);
    }
}
