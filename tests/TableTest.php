<?php
namespace Ufee\Sqlite3\Tests;

use Ufee\Sqlite3\Query\Delete;
use Ufee\Sqlite3\Query\Insert;
use Ufee\Sqlite3\Query\Select;
use Ufee\Sqlite3\Query\Update;
use Ufee\Sqlite3\Table;

class TableTest extends TestCase
{
	public function testNameAndDatabase()
	{
		$table = $this->db->table('goods');
		$this->assertSame('goods', $table->name());
		$this->assertSame($this->db, $table->database());
	}

	public function testCreateWithTypes()
	{
		$log = $this->enableLog();
		$this->assertTrue($this->db->table('t')->create(['id' => 'INTEGER PRIMARY KEY', 'title' => 'TEXT']));
		$this->assertSame('CREATE TABLE t (id INTEGER PRIMARY KEY,title TEXT)', $log->last()['sql']);
	}

	public function testCreateWithoutTypes()
	{
		$log = $this->enableLog();
		$this->assertTrue($this->db->table('t')->create(['id', 'data', 'other']));
		$this->assertSame('CREATE TABLE t (id,data,other)', $log->last()['sql']);
		$this->assertSame('', $this->db->table('t')->columns('data')['type']);
	}

	public function testCreateWithEmptyColumnsThrows()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sqlite create table "t" error: empty columns');
		$this->db->table('t')->create();
	}

	public function testCreateExistingTableThrows()
	{
		$this->createGoods();
		$this->expectException(\Exception::class);
		$this->db->table('goods')->create(['id']);
	}

	public function testExists()
	{
		$table = $this->db->table('goods');
		$this->assertFalse($table->exists());
		$this->createGoods();
		$this->assertTrue($table->exists());
	}

	public function testInfo()
	{
		$this->createGoods();
		$info = $this->db->table('goods')->info();
		$this->assertSame(['type', 'name', 'tbl_name', 'rootpage', 'sql'], array_keys($info));
		$this->assertSame('goods', $info['name']);
		$this->assertStringStartsWith('CREATE TABLE goods (id INTEGER PRIMARY KEY,', $this->db->table('goods')->info('sql'));
		$this->assertNull($this->db->table('missing')->info());
	}

	public function testDrop()
	{
		$goods = $this->createGoods();
		$this->assertTrue($goods->drop());
		$this->assertFalse($goods->exists());
	}

	public function testColumns()
	{
		$goods = $this->createGoods();
		$columns = $goods->columns();
		$this->assertSame(['id', 'category', 'price', 'title', 'hot', 'created_at'], array_keys($columns));
		$this->assertSame(['cid', 'name', 'type', 'notnull', 'dflt_value', 'pk'], array_keys($columns['id']));
		$this->assertSame('INTEGER', $goods->columns('category')['type']);
		$this->assertSame('1', $goods->columns('category')['dflt_value']);
		$this->assertSame(1, $goods->columns('id')['pk']);
		$this->assertNull($goods->columns('missing'));
	}

	public function testColumnsOfMissingTableIsNull()
	{
		$this->assertNull($this->db->table('missing')->columns());
	}

	public function testColumnsAreCached()
	{
		$goods = $this->createGoods();
		$goods->columns();
		$this->db->exec('ALTER TABLE goods ADD COLUMN extra TEXT');
		// cached: new column is not visible
		$this->assertNull($goods->columns('extra'));
	}

	public function testColumnTypesFromDeclaration()
	{
		$this->db->table('t')->create([
			'a' => 'INT', 'b' => 'INTEGER NOT NULL', 'c' => 'REAL', 'd' => 'FLOAT',
			'e' => 'TEXT', 'f' => 'BLOB', 'g' => 'varchar(255)', 'h' => 'BOOLEAN', 'i' => 'NUMERIC'
		]);
		$table = $this->db->table('t');
		$this->assertSame(SQLITE3_INTEGER, $table->getColumnType('a', 'x'));
		$this->assertSame(SQLITE3_INTEGER, $table->getColumnType('b', 'x'));
		$this->assertSame(SQLITE3_FLOAT, $table->getColumnType('c', 'x'));
		$this->assertSame(SQLITE3_FLOAT, $table->getColumnType('d', 'x'));
		$this->assertSame(SQLITE3_TEXT, $table->getColumnType('e', 1));
		$this->assertSame(SQLITE3_BLOB, $table->getColumnType('f', 'x'));
		// unknown declarations are bound as TEXT
		$this->assertSame(SQLITE3_TEXT, $table->getColumnType('g', 'x'));
		$this->assertSame(SQLITE3_TEXT, $table->getColumnType('h', true));
		$this->assertSame(SQLITE3_TEXT, $table->getColumnType('i', 1.5));
	}

	public function testColumnTypeOfUntypedColumnIsInferredFromFirstValue()
	{
		$this->db->table('t')->create(['a', 'b', 'c', 'd']);
		$table = $this->db->table('t');
		$this->assertSame(SQLITE3_FLOAT, $table->getColumnType('a', 1.5));
		$this->assertSame(SQLITE3_INTEGER, $table->getColumnType('b', 1));
		$this->assertSame(SQLITE3_NULL, $table->getColumnType('c', null));
		$this->assertSame(SQLITE3_TEXT, $table->getColumnType('d', 'x'));
		// cached after the first call
		$this->assertSame(SQLITE3_FLOAT, $table->getColumnType('a', 'x'));
		$this->assertSame(SQLITE3_NULL, $table->getColumnType('c', 10));
	}

	public function testColumnTypeOfUnknownColumnThrows()
	{
		$goods = $this->createGoods();
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Column "missing" not found in table "goods');
		$goods->getColumnType('missing', 1);
	}

	public function testColumnTypeOfColumnOfMissingTableThrows()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Column "id" not found in table "missing');
		$this->db->table('missing')->getColumnType('id', 1);
	}

	public function testColumnTypeOfUnknownColumnInferredOnDemand()
	{
		$goods = $this->createGoods();
		$this->assertSame(SQLITE3_INTEGER, $goods->getColumnType('cnt', 1, true));
		$this->assertSame(SQLITE3_FLOAT, $goods->getColumnType('cnt', 1.5, true));
		$this->assertSame(SQLITE3_TEXT, $goods->getColumnType('cnt', '1', true));
		// declared columns keep declared type
		$this->assertSame(SQLITE3_INTEGER, $goods->getColumnType('category', 'x', true));
	}

	public function testValueType()
	{
		$this->assertSame(SQLITE3_NULL, Table::getValueType(null));
		$this->assertSame(SQLITE3_INTEGER, Table::getValueType(1));
		$this->assertSame(SQLITE3_FLOAT, Table::getValueType(1.5));
		$this->assertSame(SQLITE3_TEXT, Table::getValueType('1'));
		$this->assertSame(SQLITE3_TEXT, Table::getValueType(true));
	}

	public function testSetColumnType()
	{
		$goods = $this->createGoods();
		$this->assertSame($goods, $goods->setColumnType('title', 'integer'));
		$this->assertSame(SQLITE3_INTEGER, $goods->getColumnType('title', 'x'));
		$goods->setColumnType('category', 'Text');
		$this->assertSame(SQLITE3_TEXT, $goods->getColumnType('category', 1));
	}

	public function testSetInvalidColumnTypeThrows()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid columt type: "VARCHAR", not found in STMT_TYPES');
		$this->db->table('goods')->setColumnType('title', 'varchar');
	}

	public function testQueryFactories()
	{
		$goods = $this->createGoods();
		$this->assertInstanceOf(Insert::class, $goods->insert('title, price'));
		$this->assertInstanceOf(Insert::class, $goods->insert(['title', 'price']));
		$this->assertInstanceOf(Select::class, $goods->select());
		$this->assertInstanceOf(Update::class, $goods->update('title'));
		$this->assertInstanceOf(Delete::class, $goods->delete());
	}

	public function testInsertAssocArrayInsertsImmediately()
	{
		$goods = $this->createGoods();
		$this->assertSame(1, $goods->insert(['title' => 'a', 'price' => 1.5]));
		$this->assertSame(2, $goods->insert(['title' => 'b']));
		$this->assertSame(['a', 'b'], $this->fetchColumn('SELECT title FROM goods ORDER BY id'));
	}

	public function testInsertEmptyColumnsThrows()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sqlite table "goods" insert error: empty columns');
		$this->db->table('goods')->insert();
	}

	public function testInsertMultiRowsInShortCallThrows()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Insert multi unavailable in short call, use insert -> rows');
		$this->db->table('goods')->insert([['title' => 'a'], ['title' => 'b']]);
	}

	public function testUpdateEmptyColumnsThrows()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sqlite table "goods" update error: empty columns');
		$this->db->table('goods')->update();
	}

	public function testQueriesAreFilteredByTable()
	{
		$goods = $this->createGoods();
		$meta = $this->createGoodsMeta();
		$log = $this->enableLog();
		$goods->insert(['title' => 'a']);
		$meta->insert(['good_id' => 1]);
		$goods->select()->rows();

		$this->assertSame(
			['INSERT INTO goods (title) VALUES (:title0)', 'SELECT * FROM goods'],
			array_column($goods->queries()->all(), 'sql')
		);
		$this->assertCount(1, $meta->queries());
		$this->assertGreaterThan(3, count($log));
	}

	public function testPushQuery()
	{
		$goods = $this->createGoods();
		$log = $this->enableLog();
		$select = $goods->select()->where('id', 1);
		$select->rows();
		$this->assertSame($goods, $goods->pushQuery($select));
		$this->assertCount(3, $log);
		$this->assertSame($log->all()[1], $log->last());
		$this->assertSame(['table', 'sql', 'time'], array_keys($log->last()));
	}
}
