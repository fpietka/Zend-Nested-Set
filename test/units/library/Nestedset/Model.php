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
        $dbh = new \PDO('sqlite:test/test.db');
        $dbh->query('
            CREATE TABLE nested (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                lft INT,
                rgt INT
            )
        ');

        // Closing connection
        $dbh = null;
    }

    public function afterTestMethod($method)
    {
        $dbh = new \PDO('sqlite:test/test.db');
        $dbh->query('DROP TABLE nested');
        $dbh->query('
            CREATE TABLE nested (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                lft INT,
                rgt INT
            )
        ');

        // Closing connection
        $dbh = null;
    }

    public function tearDown()
    {
        $dbh = new \PDO('sqlite:test/test.db');
        $dbh->query('DROP TABLE nested');

        // Closing connection
        $dbh = null;
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

        $db = \Zend_Db::factory('Pdo_Sqlite', array('dbname' => 'dbtest'));
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
}
