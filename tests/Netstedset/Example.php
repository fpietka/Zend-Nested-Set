<?php
/**
 * Example.php for Nestedset in tests/
 *
 * @category   Nestedset
 * @package    Nestedset_UnitTest
 * @copyright  Copyright (c) 2009 Nextcode
 * @author     Francois Pietka
 */

class Nestedset_Example
{
    /**
     * Property value of Example class
     */
    protected $_property = null;

    /**
     * Update property value
     *
     * @param void $property new value of property
     *
     * @return $this for more fluent interface
     */
    public function updateProperty($property)
    {
        $this->_property = $property;

        return $this;
    }

    /**
     * Retreive property value
     *
     * @return void $this->_property value
     */
    public function getProperty()
    {
        return $this->_property;
    }
}
