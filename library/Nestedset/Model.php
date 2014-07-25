<?php
/**
 * $Id$
 *
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
        $db = $this->_db;

        $name = (string) $name;

        if (is_null($reference)) {
            // In this case, add it to the end of the set at first level
            $select = $db->select();
            $select->from($this->_tableName, array('max' => "MAX({$this->_structure['right']})"));

            $stmt   = $db->query($select);
            $result = $stmt->fetch();

            if (false === $result) {
                $result = 0;
            }
            else {
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
            }
            catch (Exception $e) {
                $db->rollBack();
                throw new Exception($e->getMessage());
            }
        }
        else {
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
                     WHERE {$this->_structure['id']} = :reference;
                ", array(
                    'reference' => $reference,
                ));
                $update = $stmt->fetch();

                // insert new element
                $values = array(
                    $this->_structure['name']  => $name,
                    $this->_structure['left']  => $right,
                    $this->_structure['right'] => $right + 1,
                );

                $db->insert($this->_tableName, $values);
                $db->commit();
            }
            catch (Exception $e) {
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
        }
        catch (Exception $e) {
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

        $reference = $this->_getElement($referenceId);
        $element   = $this->_getElement($elementId); // @TODO get one level, we don't need all this tree

        // error handling
        if (empty($element) || empty($reference)) {
            return false;
        }

        try {
            // Case INTO

            // Check it can be moved into. XXX change when we'll get one level
            if ($element[0][$this->_structure['left']] > $reference[0][$this->_structure['left']] &&
                $element[0][$this->_structure['left']] < $reference[0][$this->_structure['right']]) {
                // already into
                return false;
            }

            $db->beginTransaction();
            // first make room into reference
            // @TODO make a private method to make room
            // with must always be a pair number
            $elementWidth = $this->_getNodeWidth($elementId);

            // move right
            $referenceRight = $reference[0][$this->_structure['right']];
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['right']} = {$this->_structure['right']} + $elementWidth
                 WHERE {$this->_structure['right']} >= $referenceRight;
            ");
            $update = $stmt->fetch();

            // move left
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['left']} = {$this->_structure['left']} + $elementWidth
                 WHERE {$this->_structure['left']} > $referenceRight;
            ");
            $update = $stmt->fetch();

            // then move element (and it's children)
            $element    = $this->_getElement($elementId);
            $elementIds = array();
            foreach ($element as $one) {
                array_push($elementIds, $one[$this->_structure['id']]);
            }
            $elementIds = implode(', ', $elementIds);

            $difference = $reference[0][$this->_structure['right']] - $element[0][$this->_structure['left']];

            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['left']}  = {$this->_structure['left']}  + $difference,
                       {$this->_structure['right']} = {$this->_structure['right']} + $difference
                 WHERE {$this->_structure['id']} IN ($elementIds);
            ");
            $update = $stmt->fetch();

            // move what was on the right of the element
            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['left']} = {$this->_structure['left']} - $elementWidth
                 WHERE {$this->_structure['left']} > {$element[0][$this->_structure['left']]};
            ");
            $update = $stmt->fetch();

            $stmt = $db->query("
                UPDATE {$this->_tableName}
                   SET {$this->_structure['right']} = {$this->_structure['right']} - $elementWidth
                 WHERE {$this->_structure['right']} > {$element[0][$this->_structure['right']]};
            ");
            $update = $stmt->fetch();

            $db->commit();
        }
        catch (Exception $e) {
            $db->rollBack();
            throw new Exception($e->getMessage());
        }

        return true;
    }

    /**
     * Get width of a node
     *
     * @param $elementId|int    Id of the node
     *
     * @return int
     */
    private function _getNodeWidth($elementId)
    {
        $db = $this->_db;

        $stmt = $db->query("
            SELECT {$this->_structure['right']} - {$this->_structure['left']} + 1
              FROM {$this->_tableName}
             WHERE {$this->_structure['id']} = $elementId;
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
    private function _getAll($depth = null, $mode = 'include', $order = 'ASC')
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

        $query .= "ORDER BY node.{$this->_structure['left']} $order;";

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
        if (empty($tree) || !is_array($tree)) {
            $nodes = $this->_getAll();
        }
        else {
            $nodes = $tree;
        }

        $result     = array();
        $stackLevel = 0;

        if (count($nodes) > 0) {
            // Node Stack. Used to help building the hierarchy
            $stack = array();

            foreach ($nodes as $node) {
                $node['children'] = array();

                // Number of stack items
                $stackLevel = count($stack);

                // Check if we're dealing with different levels
                while ($stackLevel > 0 && $stack[$stackLevel - 1]['depth'] >= $node['depth']) {
                    array_pop($stack);
                    $stackLevel--;
                }

                // Stack is empty (we are inspecting the root)
                if ($stackLevel == 0) {
                    // Assigning the root node
                    $i = count($result);

                    // $result[$i] = $item;
                    $result[$i] = $node;
                    $stack[] =& $result[$i];
                }
                else {
                    // Add node to parent
                    $i = count($stack[$stackLevel - 1]['children']);

                    $stack[$stackLevel - 1]['children'][$i] = $node;
                    $stack[] =& $stack[$stackLevel - 1]['children'][$i];
                }
            }
        }

        return $result;
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
        if (empty($tree) || !is_array($tree)) {
            $nodes = $this->_getAll();
        }
        else {
            $nodes = $tree;
        }

        $xml  = new DomDocument('1.0');
        $xml->formatOutput = true;
        $root = $xml->createElement('root');
        $xml->appendChild($root);

        $depth = 0;
        $currentChildren = array();

        foreach ($nodes as $node) {
            $element = $xml->createElement('element');
            $element->setAttribute('id', $node['id']);
            $element->setAttribute('name', $node['name']);
            $element->setAttribute('lft', $node['lft']);
            $element->setAttribute('rgt', $node['rgt']);

            $children = $xml->createElement('children');
            $element->appendChild($children);

            if ($node['depth'] == 0) {
                // Handle root
                $root->appendChild($element);
                $currentChildren[0] = $children;
            }
            elseif ($node['depth'] > $depth) {
                // is a new sub level
                $currentChildren[$depth]->appendChild($element);
                $currentChildren[$node['depth']] = $children;
            }
            elseif ($node['depth'] == $depth || $node['depth'] < $depth) {
                // is at the same level
                $currentChildren[$node['depth'] - 1]->appendChild($element);
            }

            $depth = $node['depth'];
        }

        return $xml->saveXML();
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
        $nestedArray = $this->toArray($tree);
        $result      = json_encode($nestedArray);

        return $result;
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
        if (empty($tree) || !is_array($tree)) {
            $nodes = $this->_getAll();
        }
        else {
            $nodes = $tree;
        }

        if ($method == 'list') {
            $result = "<ul>\n";
            $depth  = $nodes[0]['depth'];

            foreach ($nodes as $node) {

                if ($depth < $node['depth']) {
                    $result .= "\n<ul>\n";
                }
                elseif ($depth == $node['depth'] && $depth > $nodes[0]['depth']) {
                    $result .= "</li>\n";
                }
                elseif ($depth > $node['depth']) {
                    for ($i = 0; $i < ($depth - $node['depth']); $i++) {
                        $result .= "</li></ul>\n";
                    }
                }

                // XXX Currently it outputs results according to my actual needs
                // for testing purpose.
                $result .= "<li>{$node[$this->_structure['name']]} (id: {$node[$this->_structure['id']]} left: {$node[$this->_structure['left']]} right: {$node[$this->_structure['right']]})";

                $depth = $node['depth'];
            }

            $result .= "</li></ul>\n";
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
    private function _getElement($elementId, $depth = null, $order = 'ASC')
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
     * Returns all children of an element. Default is direct children
     *
     * @param $elementId|int    Element ID
     * @param $level|int        Level of children to return, level 1 mean direct
     *
     * @return array
     */
    public function getChildren($elementId, $level = 1)
    {
        // @todo
        // use getElement excluding main one
        // might look like getAll
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
            SELECT TRUE
              FROM {$this->_tableName}
             WHERE {$this->_structure['id']} = $elementId
               AND {$this->_structure['left']} = (
                       SELECT MIN({$this->_structure['left']})
                       FROM {$this->_tableName}
                   )
               AND {$this->_structure['right']} = (
                       SELECT MAX({$this->_structure['right']})
                         FROM {$this->_tableName}
                   );
        ";

        try {
            $stmt   = $this->_db->query($query);
            $result = $stmt->fetchColumn();
        }
        catch (Exception $e) {
            return false;
        }

        return $result;
    }

    /**
     * Sort elements. By default sort all the tree by levels. If a parent is
     * set, it will only sort from this parent's children. If a depth is set, it
     * will sort up to this depth, default being sorting all depth from this
     * parent element.
     *
     * @param $elementId|int    ID of the element to sort
     * @param $depth|int        Depth of the sorting action
     *
     * @return $this
     */
    public function sortElement($elementId = null, $depth = null)
    {
        // @todo
    }
}

