<?php

class NestedSet_Model_Reader
{
    /**
     * Get all elements from nested set
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $depth|array      Array of depth wanted. Default is all
     * @param $mode|string      Mode of depth selection: include/exclude
     * @param $order|string     Mode of sort
     *
     * @return array
     */
    public function getAll(NestedSet_Model $nestedset, $depth = null, $mode = 'include', $order = 'ASC')
    {
        $db = $nestedset->getDb();

        $query = "
            SELECT
                node.{$nestedset->getStructureId()},
                node.{$nestedset->getStructureName()},
                node.{$nestedset->getStructureLeft()},
                node.{$nestedset->getStructureRight()},
                COUNT(parent.{$nestedset->getStructureName()}) - 1 AS depth
            FROM
                {$nestedset->getTableName()} AS node,
                {$nestedset->getTableName()} AS parent
            WHERE node.{$nestedset->getStructureLeft()} BETWEEN parent.{$nestedset->getStructureLeft()} AND parent.{$nestedset->getStructureRight()}
            GROUP BY node.{$nestedset->getStructureId()}, node.{$nestedset->getStructureName()}, node.{$nestedset->getStructureLeft()}, node.{$nestedset->getStructureRight()}
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

                $query .= "HAVING COUNT(parent.{$nestedset->getStructureName()}) - 1 $mode $depth";
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

                $query .= "HAVING COUNT(parent.{$nestedset->getStructureName()}) - 1 $mode ($depth)";
            }
        }

        $query .= " ORDER BY node.{$nestedset->getStructureLeft()} $order;";

        $stmt  = $db->query($query);
        $nodes = $stmt->fetchAll();

        return $nodes;
    }

    /**
     * Get one element with its children.
     * @TODO depth
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $elementId|int    Element Id
     * @param $depth|int        Optional, depth of the tree. Default null means
     *                          full tree
     *
     * @return array
     */
    public function getElement(NestedSet_Model $nestedset, $elementId, $depth = null, $order = 'ASC')
    {
        // @TODO: test -> if multiple elements with depth 1 are found -> error
        $db = $nestedset->getDb();

        // Get main element left and right
        $select = $db
            ->select()
            ->from($nestedset->getTableName(), array($nestedset->getStructureLeft(), $nestedset->getStructureRight()))
            ->where($nestedset->getStructureId() . ' = ?', $elementId);

        $stmt    = $db->query($select);
        $element = $stmt->fetch();

        // Get the tree
        $query = "
            SELECT
                node.{$nestedset->getStructureId()},
                node.{$nestedset->getStructureName()},
                node.{$nestedset->getStructureLeft()},
                node.{$nestedset->getStructureRight()},
                COUNT(parent.{$nestedset->getStructureName()}) - 1 AS depth
              FROM
                {$nestedset->getTableName()} AS node,
                {$nestedset->getTableName()} AS parent
             WHERE node.{$nestedset->getStructureLeft()} BETWEEN parent.{$nestedset->getStructureLeft()} AND parent.{$nestedset->getStructureRight()}
               AND node.{$nestedset->getStructureLeft()} BETWEEN {$element[$nestedset->getStructureLeft()]} AND {$element[$nestedset->getStructureRight()]}
             GROUP BY node.{$nestedset->getStructureId()}, node.{$nestedset->getStructureName()}, node.{$nestedset->getStructureLeft()}, node.{$nestedset->getStructureRight()}
             ORDER BY node.{$nestedset->getStructureLeft()} $order
        ";

        $stmt  = $db->query($query);
        $nodes = $stmt->fetchAll();

        return $nodes;
    }

    /**
     * Get width of a node
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $elementId|int    Id of the node
     *
     * @return int
     */
    public function getNodeWidth(NestedSet_Model $nestedset, $elementId)
    {
        $db = $nestedset->getDb();

        $stmt = $db->query("
            SELECT {$nestedset->getStructureRight()} - {$nestedset->getStructureLeft()} + 1
              FROM {$nestedset->getTableName()}
             WHERE {$nestedset->getStructureId()} = $elementId
        ");
        $width = $stmt->fetchColumn();

        return $width;
    }

    /**
     * Get all nodes without children
     *
     * @param $model|NestedSet_Model    Nested set model
     *
     * @return array
     */
    public function getLeafs(NestedSet_Model $nestedset)
    {
        $db = $nestedset->getDb();

        $select = $db
            ->select()
            ->from($nestedset->getTableName(), array($nestedset->getStructureId(), $nestedset->getStructureName()))
            ->where("{$nestedset->getStructureRight()} = {$nestedset->getStructureLeft()} + 1");

        $stmt   = $db->query($select);
        $result = $stmt->fetchAll();

        return $result;
    }

    /**
     * Get path of an element
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $elementId|int    Id of the element we want the path of
     *
     * @return array
     */
    public function getPath(NestedSet_Model $nestedset, $elementId, $order = 'ASC')
    {
        $db = $nestedset->getDb();

        $query = "
            SELECT
                node.{$nestedset->getStructureId()},
                node.{$nestedset->getStructureName()},
                COUNT(parent.{$nestedset->getStructureName()}) - 1 AS depth
            FROM
                {$nestedset->getTableName()} AS node,
                {$nestedset->getTableName()} AS parent
            WHERE node.{$nestedset->getStructureLeft()} BETWEEN parent.{$nestedset->getStructureLeft()} AND parent.{$nestedset->getStructureRight()}
              AND node.{$nestedset->getStructureId()} = $elementId
            GROUP BY node.{$nestedset->getStructureId()}, node.{$nestedset->getStructureName()}, node.{$nestedset->getStructureLeft()}
            ORDER BY node.{$nestedset->getStructureLeft()} $order;
        ";

        $stmt = $db->query($query);
        $path = $stmt->fetchAll();

        return $path;
    }

    /**
     * Get the parent of an element.
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $elementId|int    Element ID
     * @param $depth|int        Depth of the parent, compared to the child.
     *                          Default is 1 (as immediate)
     *
     * @return array|false
     */
    public function getParent(NestedSet_Model $nestedset, $elementId, $depth = 1)
    {
        $db = $nestedset->getDb();

        $select = $db
            ->select()
            ->from($nestedset->getTableName(), array($nestedset->getStructureLeft(), $nestedset->getStructureRight()))
            ->where($nestedset->getStructureId() . ' = ?', $elementId);

        $stmt  = $db->query($select);
        $child = $stmt->fetch();

        $select = $db
            ->select()
            ->from($nestedset->getTableName(), array($nestedset->getStructureId(), $nestedset->getStructureName()))
            ->where($nestedset->getStructureLeft() . ' < ?', $child[$nestedset->getStructureLeft()])
            ->where($nestedset->getStructureRight() . ' > ?', $child[$nestedset->getStructureRight()])
            ->order('(' . $child[$nestedset->getStructureLeft()] . ' - ' . $nestedset->getStructureLeft() . ')')
            ->limitPage($depth, 1);

        $stmt   = $db->query($select);
        $result = $stmt->fetch();

        return $result;
    }

    /**
     * Returns if the element is root.
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $elementId|int    Element ID
     *
     * @return boolean
     */
    public function isRoot(NestedSet_Model $nestedset, $elementId)
    {
        $db = $nestedset->getDb();

        $query = "
            SELECT 1
              FROM {$nestedset->getTableName()}
             WHERE {$nestedset->getStructureId()} = $elementId
               AND {$nestedset->getStructureLeft()} = (
                       SELECT MIN({$nestedset->getStructureLeft()})
                       FROM {$nestedset->getTableName()}
                   )
               AND {$nestedset->getStructureRight()} = (
                       SELECT MAX({$nestedset->getStructureRight()})
                         FROM {$nestedset->getTableName()}
                   )
        ";

        $stmt   = $db->query($query);
        $result = $stmt->fetchColumn();

        return (boolean) $result;
    }
}
