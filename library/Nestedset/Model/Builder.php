<?php

class NestedSet_Model_Builder
{
    /**
     * Add an element to the end of the tree.
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $name|string              Name of the element
     * @param $reference|int            Id of the reference element
     *
     * @return $this
     */
    public function append(NestedSet_Model $nestedset, $name)
    {
        $db = $nestedset->getDb();

        $select = $db->select();
        $select->from($nestedset->getTableName(), array('max' => "MAX({$nestedset->getStructureRight()})"));

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
                $nestedset->getStructureName() => $name,
                $nestedset->getStructureLeft() => $left,
                $nestedset->getStructureRight() => $right,
            );

            $db->insert($nestedset->getTableName(), $values);
            $db->commit();
        }
        catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * Add an element into another as its child.
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $name|string              Name of the element
     * @param $reference|int            Id of the reference element
     *
     * @return $this
     */
    public function addInto(NestedSet_Model $nestedset, $name, $reference)
    {
        $db = $nestedset->getDb();

        // get parent's right value
        $select = $db->select();
        $select->from($nestedset->getTableName(), $nestedset->getStructureRight());
        $select->where("{$nestedset->getStructureId()} = $reference");

        $stmt   = $db->query($select);
        $result = $stmt->fetch();

        $right = $result[$nestedset->getStructureRight()];

        try {
            $db->beginTransaction();

            // move next elements' right to make room
            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureRight()} = {$nestedset->getStructureRight()} + 2
                 WHERE {$nestedset->getStructureRight()} > $right;
            ");
            $update = $stmt->fetch();

            // move next elements' left
            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureLeft()} = {$nestedset->getStructureLeft()} + 2
                 WHERE {$nestedset->getStructureLeft()} > $right;
            ");
            $update = $stmt->fetch();

            // make room into parent element
            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureRight()} = {$nestedset->getStructureRight()} + 2
                 WHERE {$nestedset->getStructureId()} = :reference;
            ", array(
                'reference' => $reference,
            ));
            $update = $stmt->fetch();

            // insert new element
            $values = array(
                $nestedset->getStructureName() => $name,
                $nestedset->getStructureLeft() => $right,
                $nestedset->getStructureRight() => $right + 1,
            );

            $db->insert($nestedset->getTableName(), $values);
            $db->commit();
        }
        catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * Move an element into another as its child.
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $element|array            Element to move
     * @param $reference|array          Reference element to move into
     *
     * @return $this
     */
    public function moveInto(NestedSet_Model $nestedset, array $element, array $reference)
    {
        $db = $nestedset->getDb();

        try {
            // Check it can be moved into. XXX change when we'll get one level
            if ($element[0][$nestedset->getStructureLeft()] > $reference[0][$nestedset->getStructureLeft()] &&
                $element[0][$nestedset->getStructureLeft()] < $reference[0][$nestedset->getStructureRight()]) {
                // already into
                return false;
            }

            $db->beginTransaction();
            // first make room into reference
            // @TODO make a protected method to make room
            // with must always be a pair number
            $elementWidth = $nestedset->getNodeWidth($element[0][$nestedset->getStructureId()]);

            // move right
            $referenceRight = $reference[0][$nestedset->getStructureRight()];
            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureRight()} = {$nestedset->getStructureRight()} + $elementWidth
                 WHERE {$nestedset->getStructureRight()} >= $referenceRight;
            ");
            $update = $stmt->fetch();

            // move left
            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureLeft()} = {$nestedset->getStructureLeft()} + $elementWidth
                 WHERE {$nestedset->getStructureLeft()} > $referenceRight;
            ");
            $update = $stmt->fetch();

            // then move element (and it's children)
            $element    = $nestedset->getElement($element[0][$nestedset->getStructureId()]);
            $elementIds = array();
            foreach ($element as $one) {
                array_push($elementIds, $one[$nestedset->getStructureId()]);
            }
            $elementIds = implode(', ', $elementIds);

            $difference = $reference[0][$nestedset->getStructureRight()] - $element[0][$nestedset->getStructureLeft()];

            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureLeft()}  = {$nestedset->getStructureLeft()}  + $difference,
                       {$nestedset->getStructureRight()} = {$nestedset->getStructureRight()} + $difference
                 WHERE {$nestedset->getStructureId()} IN ($elementIds);
            ");
            $update = $stmt->fetch();

            // move what was on the right of the element
            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureLeft()} = {$nestedset->getStructureLeft()} - $elementWidth
                 WHERE {$nestedset->getStructureLeft()} > {$element[0][$nestedset->getStructureLeft()]};
            ");
            $update = $stmt->fetch();

            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureRight()} = {$nestedset->getStructureRight()} - $elementWidth
                 WHERE {$nestedset->getStructureRight()} > {$element[0][$nestedset->getStructureRight()]};
            ");
            $update = $stmt->fetch();

            $db->commit();
        }
        catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Recursively delete a node, with all its children
     *
     * @param $model|NestedSet_Model    Nested set model
     * @param $tree|array
     *
     * @return $this
     */
    public function deleteRecursive(NestedSet_Model $nestedset, array $tree)
    {
        $db = $nestedset->getDb();

        // get interval for recursive delete
        $left  = (int) $tree[$nestedset->getStructureLeft()];
        $right = (int) $tree[$nestedset->getStructureRight()];

        try {
            $db->beginTransaction();

            $delete = $db->delete($nestedset->getTableName(), "{$nestedset->getStructureLeft()} BETWEEN $left AND $right");

            // update other elements
            $width = $right - $left + 1;

            // update right
            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureRight()} = {$nestedset->getStructureRight()} - $width
                 WHERE {$nestedset->getStructureRight()} > $right
            ");
            $update = $stmt->fetch();

            // update left
            $stmt = $db->query("
                UPDATE {$nestedset->getTableName()}
                   SET {$nestedset->getStructureLeft()} = {$nestedset->getStructureLeft()} - $width
                 WHERE {$nestedset->getStructureLeft()} > $right
            ");
            $update = $stmt->fetch();

            $db->commit();
        }
        catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $this;
    }
}
