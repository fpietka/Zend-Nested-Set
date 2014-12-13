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
}
