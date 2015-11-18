<?php

// Add library to include path (where ZF should also be located)
set_include_path(__DIR__ . '/../../../../library');

require_once 'Nestedset/Model.php';
require_once 'Nestedset/Model/Builder.php';
require_once 'Nestedset/Model/Output.php';
require_once 'Nestedset/Model/Reader.php';

// Include needed ZendFramework classes
require_once 'Zend/Db.php';
require_once 'Zend/Db/Table.php';
require_once 'Zend/Db/Adapter/Abstract.php';

class Nestedset_ModelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $db->query('
            CREATE TABLE nested (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                lft INT,
                rgt INT
            )
        ');
    }

    public function afterTestMethod($method)
    {
        switch ($method) {
            case 'testAddASimpleElement':
            case 'testDeleteElement':
            case 'testDeleteRecursiveElement':
            case 'testGetElement':
            case 'testIsRoot':
            case 'testIsNotRoot':
            case 'testGetLeafs':
            case 'testGetPath':
            case 'testMove':
            case 'testToXml':
            case 'testToXmlWithTree':
            case 'testToHtml':
            case 'testNumberOfDescendant':
            case 'testGetParent':
            case 'testGetAll':
                $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
                $db->query('DROP TABLE nested');
                $db->query('
                    CREATE TABLE nested (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT,
                        lft INT,
                        rgt INT
                    )
                ');
        }
    }

    public function tearDown()
    {
        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $db->query('DROP TABLE nested');
    }

    /**
     * Test setters
     */

    public function testSetTableName()
    {
        $nestedset = new NestedSet_Model();

        $nestedset->setTableName('foo');
        $this->assertEquals($nestedset->getTableName(), 'foo');
    }

    public function testSetDb()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $this->assertInstanceOf('Zend_Db_Adapter_Abstract', $nestedset->getDb());
    }

    public function testSetStructureId()
    {
        $nestedset = new NestedSet_Model();

        $nestedset->setStructureId('foo');
        $this->assertEquals($nestedset->getStructureId(), 'foo');
    }

    public function testSetStructureName()
    {
        $nestedset = new NestedSet_Model();

        $nestedset->setStructureName('foo');
        $this->assertEquals($nestedset->getStructureName(), 'foo');
    }

    public function testSetStructureLeft()
    {
        $nestedset = new NestedSet_Model();

        $nestedset->setStructureLeft('foo');
        $this->assertEquals($nestedset->getStructureLeft(), 'foo');
    }

    public function testSetStructureRight()
    {
        $nestedset = new NestedSet_Model();

        $nestedset->setStructureRight('foo');
        $this->assertEquals($nestedset->getStructureRight(), 'foo');
    }

    public function testAddASimpleElement()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->add('foobar', 1);

        $expected_result = file_get_contents('tests/expected_result.json');

        $this->assertEquals($nestedset->toJson($nestedset->getElement(1)), $expected_result);
    }

    public function testDeleteElement()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->delete(1);

        $expected_result = '[]';

        $this->assertEquals($nestedset->toJson(), $expected_result);
    }

    public function testDeleteRecursiveElement()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->delete(1, true);

        $expected_result = '[]';

        $this->assertEquals($nestedset->toJson(), $expected_result);
    }

    public function testDeleteNonRecursiveElement()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->delete(1, false);

        $expected_result = file_get_contents('tests/expected_result_simple_non_recursive_delete.json');

        $this->assertEquals($nestedset->toJson(), $expected_result);

        // clear it up
        $nestedset->delete(2, true);

        // More complex case
        $nestedset->add('main');

        $nestedset->add('foo', 3);
        $nestedset->add('bar', 3);

        $nestedset->add('one', 4);
        $nestedset->add('two', 4);
        $nestedset->add('three', 4);

        $nestedset->add('one', 5);
        $nestedset->add('two', 5);

        $nestedset->delete(5, false);

        $expected_result = file_get_contents('tests/expected_result_complex_non_recursive_delete.json');

        $this->assertEquals($nestedset->toJson(), $expected_result);
    }

    public function testGetElement()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');

        $expected_result = '[{"id":"1","name":"foo","lft":"1","rgt":"2","depth":"0","children":[]}]';

        $this->assertEquals($nestedset->toJson(), $expected_result);
    }

    public function testIsRoot()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');

        $this->assertTrue($nestedset->isRoot(1));
    }

    public function testIsNotRoot()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar');

        $this->assertFalse($nestedset->isRoot(1));
    }

    public function testGetLeafs()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);

        $expected_result = array(
            array(
                'name' => 'bar',
                'id' => '2',
            )
        );

        $this->assertEquals($nestedset->getLeafs(), $expected_result);
    }

    public function testMove()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar');

        $result = $nestedset->move(2, 1);
        $this->assertTrue($result);

        $expected_result = '[{"id":"1","name":"foo","lft":"1","rgt":"4","depth":"0","children":[{"id":"2","name":"bar","lft":"2","rgt":"3","depth":"1","children":[]}]}]';
        $this->assertEquals($nestedset->toJson(), $expected_result);
    }

    public function testGetPath()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);

        $expected_result = array(
            0 => array(
                'id' => '2',
                'name' => 'bar',
                'depth' => '1'
            )
        );

        $this->assertEquals($nestedset->getPath(2), $expected_result);
    }

    public function testToXml()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->add('foobar', 1);

        $xml = new DomDocument(1.0);
        $xml->load('tests/expected_result.xml');
        $this->assertEquals($nestedset->toXml(), $xml->saveXML());
    }

    public function testToXmlWithTree()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->add('foobar', 1);
        $tree = $nestedset->getElement(1);

        $xml = new DomDocument(1.0);
        $xml->load('tests/expected_result.xml');
        $this->assertEquals($nestedset->toXml($tree), $xml->saveXML());
    }

    public function testToHtml()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->add('foobar', 1);

        $expected_result = file_get_contents('tests/expected_result.html');
        $this->assertEquals($nestedset->toHtml(), $expected_result);
    }

    public function testNumberOfDescendant()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $this->assertEquals($nestedset->numberOfDescendant(1), 1);
    }

    public function testGetParent()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $expected_result = array('id' => '1', 'name' => 'foo');

        $this->assertEquals($nestedset->getParent(2), $expected_result);
    }

    public function testGetAll()
    {
        $nestedset = new NestedSet_Model();

        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $expected_result = array(
            array(
                'id' => "1",
                'name' => "foo",
                'lft' => "1",
                'rgt' => "4",
                'depth' => "0"
            ),
            array(
                'id' => "2",
                'name' => "bar",
                'lft' => "2",
                'rgt' => "3",
                'depth' => "1"
            )
        );

        $this->assertEquals($nestedset->getAll(2), $expected_result);
    }
}
