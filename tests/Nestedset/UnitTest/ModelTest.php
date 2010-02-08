<?php
/**
 * ExampleTest.php for Nestedset in tests/
 *
 * @category   Nestedset
 * @package    Nestedset_UnitTest
 * @copyright  Copyright (c) 2009 Nextcode
 * @author     François Pietka
 */

require_once realpath(dirname(__FILE__) . '/../../') . '/TestHelper.php';

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

/**
 * Test class for Nestedset Class
 *
 * @group Nestedset
 */
class Nestedset_UnitTest_ModelTest extends PHPUnit_Framework_TestCase
{
    public static $databaseFilePath;

    public static $db;

    /**
     * Call before all tests and on class test loading
     */
    public function setUp()
    {
        // configure test here
        self::_initUnitTestDb();
    }

    /**
     * Create SqlLite database for UnitTesting and init self::$db
     * has Zend_Db_Adapter ready to use instance
     *
     * @return void
     */
    protected static function _initUnitTestDb()
    {
        // create a new SqlLite database file
        self::$databaseFilePath = TMP_PATH . '/example_database.db';

        // create tmp db conn
        $dbSchema = new PDO('sqlite:' . self::$databaseFilePath);

        // deplare schema
        $query = file_get_contents(SQL_PATH . '/schema/struct.sql');
        $stmt = $dbSchema->query($query);

        // add sample data
        $queries = file(SQL_PATH . '/data/sample/nested.sql');

        foreach ($queries as $query) {
            $stmt = $dbSchema->query($query);
        }

        // init once only
        if (isset(self::$db)) {
            return;
        }

        // create connexion
        self::$db = Zend_Db::factory('Pdo_Sqlite', array(
            'dbname' => self::$databaseFilePath,
        ));
    }

    public function testUpdatePropertyValue()
    {

        $this->markTestSkipped('Still trying to determine a scenario to test this');
    }

    /**
     * Test add function
     */
    public function testAddNode()
    {
        // add into
        // add before
        // add after

        $this->markTestSkipped('Still trying to determine a scenario to test this');
    }

    /**
     * Test delete function
     */
    public function deleteAddNode()
    {
        // delete one
        // delete recursive

        $this->markTestSkipped('Still trying to determine a scenario to test this');
    }

    /**
     * Test move function
     */
    public function testMoveNode()
    {
        // move into
        // move before
        // move after

        $this->markTestSkipped('Still trying to determine a scenario to test this');
    }

    /**
     * Call after all tests and on class test loading
     */
    public function tearDown()
    {
        // clean database or test generated data for example
        unlink(self::$databaseFilePath);
    }
}
