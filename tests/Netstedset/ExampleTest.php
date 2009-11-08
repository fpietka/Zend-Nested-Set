<?php
/**
 * ExampleTest.php for Nestedset in tests/
 *
 * @category   Nestedset
 * @package    Nestedset_UnitTest
 * @copyright  Copyright (c) 2009 Nextcode
 * @author     Francois Pietka
 */

require_once dirname(__FILE__) . '/../TestHelper.php';

/**
 * Test class for Example
 *
 * @group Nestedset
 * @group Nestedset_Example
 */
class Nestedset_ExampleTest extends PHPUnit_Framework_TestCase
{
    protected $_example = null;

    /**
     * Call before all test and on class test loading
     */
    public function setUp()
    {
        // configure test here
    }

    public function testUpdatePropertyValue()
    {
        // use time to have floating value
        $value = time();

        $example = new Nestedset_Example();

        $example->updateProperty($value);

        // compare waiting results with results
        $this->assertEquals($value, $example->getProperty());

        /*
        assertArrayHasKey()
        assertClassHasAttribute()
        assertClassHasStaticAttribute()
        assertContains()
        assertContainsOnly()
        assertEqualXMLStructure()
        assertEquals()
        assertFalse()
        assertFileEquals()
        assertFileExists()
        assertGreaterThan()
        assertGreaterThanOrEqual()
        assertLessThan()
        assertLessThanOrEqual()
        assertNotNull()
        assertObjectHasAttribute()
        assertRegExp()
        assertSame()
        assertSelectCount()
        assertSelectEquals()
        assertSelectRegExp()
        assertStringEqualsFile()
        assertTag()
        assertThat()
        assertTrue()
        assertType()
        assertXmlFileEqualsXmlFile()
        assertXmlStringEqualsXmlFile()
        assertXmlStringEqualsXmlString()
        */
    }

    /**
     * Call after all test and on class test loading
     */
    public function tearDown()
    {
        // clean database or test generated data for example
    }
}
