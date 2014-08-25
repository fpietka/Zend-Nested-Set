<?php

namespace Tests\Units;

// Add library to include path (where ZF should also be located)
set_include_path(__DIR__ . '/../../../../library');

require_once 'Nestedset/Model.php';

// Include needed ZendFramework classes
require_once 'Zend/Db.php';
require_once 'Zend/Db/Table.php';
require_once 'Zend/Db/Adapter/Abstract.php';

use mageekguy\atoum;

class NestedSet_Model extends atoum\test
{
    public function setUp()
    {
        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
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
        switch ($method)
        {
            case 'testAddASimpleElement':
            case 'testDeleteElement':
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
                $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
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
        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $db->query('DROP TABLE nested');
    }

    /**
     * Test setters
     */

    public function testSetTableName()
    {
        $nestedset = new \NestedSet_Model();

        $nestedset->setTableName('foo');
        $this->assert->string($nestedset->getTableName())
            ->isEqualTo('foo');
    }

    public function testSetDb()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $this->assert->object($nestedset->getDb())
            ->isInstanceOf('\Zend_Db_Adapter_Abstract');
    }

    public function testSetStructureId()
    {
        $nestedset = new \NestedSet_Model();

        $nestedset->setStructureId('foo');
        $this->assert->string($nestedset->getStructureId())
            ->isEqualTo('foo');
    }

    public function testSetStructureName()
    {
        $nestedset = new \NestedSet_Model();

        $nestedset->setStructureName('foo');
        $this->assert->string($nestedset->getStructureName())
            ->isEqualTo('foo');
    }

    public function testSetStructureLeft()
    {
        $nestedset = new \NestedSet_Model();

        $nestedset->setStructureLeft('foo');
        $this->assert->string($nestedset->getStructureLeft())
            ->isEqualTo('foo');
    }

    public function testSetStructureRight()
    {
        $nestedset = new \NestedSet_Model();

        $nestedset->setStructureRight('foo');
        $this->assert->string($nestedset->getStructureRight())
            ->isEqualTo('foo');
    }

    public function testAddASimpleElement()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->add('foobar', 1);

        $expected_result = file_get_contents('tests/expected_result.json');

        $this->assert->string($nestedset->toJson($nestedset->getElement(1)))->isEqualTo($expected_result);
    }

    public function testDeleteElement()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->delete(1);

        $expected_result = '[]';

        $this->assert->string($nestedset->toJson())->isEqualTo($expected_result);
    }

    public function testGetElement()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');

        $expected_result = '[{"id":"1","name":"foo","lft":"1","rgt":"2","depth":"0","children":[]}]';

        $this->assert->string($nestedset->toJson())->isEqualTo($expected_result);
    }

    public function testIsRoot()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');

        $this->assert->boolean($nestedset->isRoot(1))->isTrue();
    }

    public function testIsNotRoot()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar');

        $this->assert->boolean($nestedset->isRoot(1))->isFalse();
    }

    public function testGetLeafs()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
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

        $this->assert->array($nestedset->getLeafs())->isEqualTo($expected_result);
    }

    public function testMove()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar');

        $result = $nestedset->move(2, 1);
        $this->assert->boolean($result)->isTrue();

        $expected_result = '[{"id":"1","name":"foo","lft":"1","rgt":"4","depth":"0","children":[{"id":"2","name":"bar","lft":"2","rgt":"3","depth":"1","children":[]}]}]';
        $this->assert->string($nestedset->toJson())->isEqualTo($expected_result);
    }

    public function testGetPath()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
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

        $this->assert->array($nestedset->getPath(2))->isEqualTo($expected_result);
    }

    public function testToXml()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->add('foobar', 1);

        $xml = new \DomDocument(1.0);
        $xml->load('tests/expected_result.xml');
        $this->assert->string($nestedset->toXml())->isEqualTo($xml->saveXML());
    }

    public function testToXmlWithTree()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->add('foobar', 1);
        $tree = $nestedset->getElement(1);

        $xml = new \DomDocument(1.0);
        $xml->load('tests/expected_result.xml');
        $this->assert->string($nestedset->toXml($tree))->isEqualTo($xml->saveXML());
    }

    public function testToHtml()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $nestedset->add('foobar', 1);

        $expected_result = file_get_contents('tests/expected_result.html');
        $this->assert->string($nestedset->toHtml())->isEqualTo($expected_result);
    }

    public function testNumberOfDescendant()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $this->assert->integer($nestedset->numberOfDescendant(1))->isEqualTo(1);
    }

    public function testGetParent()
    {
        $nestedset = new \NestedSet_Model();

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'tests/test.db'));
        $nestedset->setDb($db);
        $nestedset->setTableName('nested');

        $nestedset->add('foo');
        $nestedset->add('bar', 1);
        $expected_result = array('id' => '1', 'name' => 'foo');

        $this->assert->array($nestedset->getParent(2))->isEqualTo($expected_result);
    }
}
