<?php

class Nestedset_Model_Output
{
    /**
     * Convert a tree array (with depth) into a hierarchical array.
     *
     * @param $nodes|array   Array with depth value.
     *
     * @return array
     */
    public function toArray(array $nodes)
    {
        $result = [];
        $stackLevel = 0;

        // Node Stack. Used to help building the hierarchy
        $stack = [];

        foreach ($nodes as $node) {
            $node['children'] = [];

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

                $result[$i] = $node;
                $stack[] = &$result[$i];
            } else {
                // Add node to parent
                $i = count($stack[$stackLevel - 1]['children']);

                $stack[$stackLevel - 1]['children'][$i] = $node;
                $stack[] = &$stack[$stackLevel - 1]['children'][$i];
            }
        }

        return $result;
    }

    /**
     * Convert a tree array (with depth) into a hierarchical XML string.
     *
     * @param $nodes|array   Array with depth value.
     *
     * @return string
     */
    public function toXml(array $nodes)
    {
        $xml = new DomDocument('1.0');
        $xml->preserveWhiteSpace = false;
        $root = $xml->createElement('root');
        $xml->appendChild($root);

        $depth = 0;
        $currentChildren = [];

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
            } elseif ($node['depth'] > $depth) {
                // is a new sub level
                $currentChildren[$depth]->appendChild($element);
                $currentChildren[$node['depth']] = $children;
            } elseif ($node['depth'] == $depth || $node['depth'] < $depth) {
                // is at the same level
                $currentChildren[$node['depth'] - 1]->appendChild($element);
            }

            $depth = $node['depth'];
        }

        return $xml->saveXML();
    }

    /**
     * Return nested set as JSON.
     *
     * @params $nodes|array          Original 'flat' nested tree
     *
     * @return string
     */
    public function toJson(array $nodes)
    {
        $nestedArray = $this->toArray($nodes);
        $result = json_encode($nestedArray);

        return $result;
    }

    /**
     * Returns all elements as HTML structure.
     *
     * Possible options:
     *  - list (simple <ul><li>)
     *
     * @param $model|NestedSet_Model    Nested set model
     *
     * @return string
     */
    public function toHtml(array $nodes, $method = 'list')
    {
        switch ($method) {
            case 'list':
            default:
                return $this->_toHtmlList($nodes);
        }
    }

    /**
     * Returns all elements as <ul>/<li> structure.
     *
     * @param $nodes|array
     *
     * @return string
     */
    protected function _toHtmlList(array $nodes)
    {
        $result = '<ul>';
        $depth = $nodes[0]['depth'];

        foreach ($nodes as $node) {
            if ($depth < $node['depth']) {
                $result .= '<ul>';
            } elseif ($depth == $node['depth'] && $depth > $nodes[0]['depth']) {
                $result .= '</li>';
            } elseif ($depth > $node['depth']) {
                for ($i = 0; $i < ($depth - $node['depth']); $i++) {
                    $result .= '</li></ul>';
                }
            }

            // XXX Currently it outputs results according to my actual needs
            // for testing purpose.
            $result .= "<li>{$node['name']} (id: {$node['id']} left: {$node['lft']} right: {$node['rgt']})";

            $depth = $node['depth'];
        }

        $result .= '</li></ul>';
        $result .= '</ul>';

        return $result;
    }
}
