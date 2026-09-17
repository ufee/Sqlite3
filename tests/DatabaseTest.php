<?php
namespace Ufee\Sqlite3\Tests;

use Ufee\Sqlite3\Query\Result;
use Ufee\Sqlite3\Queries;
use Ufee\Sqlite3\Sqlite;
use Ufee\Sqlite3\Table;

class DatabaseTest extends TestCase
{
	public function testName()
	{
		$this->assertSame($this->path, $this->db->name());
	}

	public function testFileExists()
	{
		$this->assertFalse($this->db->fileExists());
		touch($this->path);
		$this->assertTrue($this->db->fileExists());
	}

	public function testExistsForMissingFile()
	{
		$this->assertFalse($this->db->exists());
	}

	public function testExistsForEmptyFile()
	{
		touch($this->path);
		$this->assertTrue($this->db->exists());
	}

	public function testExistsForDatabaseWithTables()
	{
		$this->createGoods();
		$this->assertTrue($this->db->exists());
	}

	public function testCreateMakesNestedDirsAndEmptyFile()
	{
		$path = $this->dir.'/nested/deep/new.db';
		$db = Sqlite::database($path);
		$this->assertTrue($db->create());
		$this->assertFileExists($path);
		$this->assertSame(0, filesize($path));
		$this->assertTrue($db->exists());
	}

	public function testCreateThrowsWhenDatabaseExists()
	{
		touch($this->path);
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sqlite database "'.$this->path.'" exists');
		$this->db->create();
	}

	public function testCreateThrowsWhenFileIsNotWritten()
	{
		// directory in place of database file
		mkdir($this->path);
		set_error_handler(function () {
			return true;
		});
		try {
			$this->db->create();
			$this->fail('Exception expected');
		} catch (\Exception $e) {
			$this->assertSame('Sqlite database create error: unable to create file: '.$this->path, $e->getMessage());
		} finally {
			restore_error_handler();
		}
	}

	public function testConnectionIsOpenedLazily()
	{
		$this->assertFalse($this->db->hasOpened());
		$this->assertFileDoesNotExist($this->path);
		$this->assertInstanceOf(\SQLite3::class, $this->db->connection());
		$this->assertTrue($this->db->hasOpened());
		$this->assertFileExists($this->path);
		$this->assertSame($this->db->connection(), $this->db->connection());
	}

	public function testOpenReturnsDatabase()
	{
		$this->assertSame($this->db, $this->db->open());
	}

	public function testDefaultOptionsAreApplied()
	{
		$this->assertSame('wal', $this->db->single('PRAGMA journal_mode', false));
		$this->assertSame(1, $this->db->single('PRAGMA synchronous', false));
		$this->assertSame(30000, $this->db->single('PRAGMA busy_timeout', false));
	}

	public function testCustomOptionsAreApplied()
	{
		$db = Sqlite::database($this->dir.'/custom.db', [
			'busy_timeout' => 2,
			'journal_mode' => 'DELETE',
			'synchronous' => 'FULL'
		]);
		$this->assertSame('delete', $db->single('PRAGMA journal_mode', false));
		$this->assertSame(2, $db->single('PRAGMA synchronous', false));
		$this->assertSame(2000, $db->single('PRAGMA busy_timeout', false));
		$db->close();
	}

	public function testOpenErrorThrows()
	{
		$db = Sqlite::database($this->dir.'/missing.db', ['flags' => SQLITE3_OPEN_READONLY]);
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sqlite db '.$this->dir.'/missing.db open error');
		$db->open();
	}

	public function testQueryReturnsResult()
	{
		$this->seedGoods();
		$result = $this->db->query('SELECT id, title FROM goods WHERE id < 3 ORDER BY id');
		$this->assertInstanceOf(Result::class, $result);
		$this->assertSame([
			['id' => 1, 'title' => 'Notebook Pro'],
			['id' => 2, 'title' => 'Notebook Adv'],
		], $result->getRows());
	}

	public function testQueryErrorThrows()
	{
		$this->expectException(\Exception::class);
		$this->db->query('SELECT * FROM missing_table');
	}

	public function testSingle()
	{
		$this->seedGoods();
		$this->assertSame(
			['id' => 1, 'title' => 'Notebook Pro'],
			$this->db->single('SELECT id, title FROM goods ORDER BY id')
		);
		$this->assertSame(1, $this->db->single('SELECT id, title FROM goods ORDER BY id', false));
		$this->assertSame([], $this->db->single('SELECT id FROM goods WHERE id = 100'));
		$this->assertNull($this->db->single('SELECT id FROM goods WHERE id = 100', false));
	}

	public function testExecAndPragma()
	{
		$this->assertTrue($this->db->exec('CREATE TABLE t (id INTEGER)'));
		$this->assertTrue($this->db->pragma('cache_size', 500));
		$this->assertSame(500, $this->db->single('PRAGMA cache_size', false));
	}

	public function testExecErrorThrows()
	{
		$this->expectException(\Exception::class);
		$this->db->exec('DROP TABLE missing_table');
	}

	public function testPrepare()
	{
		$this->assertInstanceOf(\SQLite3Stmt::class, $this->db->prepare('SELECT 1'));
	}

	public function testTables()
	{
		$this->assertSame([], $this->db->tables());
		$this->createGoods();
		$this->createGoodsMeta();
		$tables = $this->db->tables();
		$this->assertSame(['goods', 'goods_meta'], array_keys($tables));
		$this->assertSame(['type', 'name', 'tbl_name', 'rootpage', 'sql'], array_keys($tables['goods']));
		$this->assertSame('table', $tables['goods']['type']);
	}

	public function testTableReturnsSameInstance()
	{
		$table = $this->db->table('goods');
		$this->assertInstanceOf(Table::class, $table);
		$this->assertSame('goods', $table->name());
		$this->assertSame($table, $this->db->table('goods'));
		$this->assertNotSame($table, $this->db->table('goods_meta'));
	}

	public function testTransactionCommit()
	{
		$this->createGoods();
		$this->assertSame($this->db, $this->db->transactionBegin('IMMEDIATE'));
		$this->db->exec("INSERT INTO goods (title) VALUES ('a')");
		$this->assertSame($this->db, $this->db->transactionCommit());
		$this->assertSame(1, $this->db->single('SELECT COUNT(*) FROM goods', false));
	}

	public function testTransactionRollback()
	{
		$this->createGoods();
		$this->db->transactionBegin();
		$this->db->exec("INSERT INTO goods (title) VALUES ('a')");
		$this->assertSame($this->db, $this->db->transactionRollback());
		$this->assertSame(0, $this->db->single('SELECT COUNT(*) FROM goods', false));
	}

	public function testTransactionEnd()
	{
		$this->createGoods();
		$this->db->transactionBegin('EXCLUSIVE');
		$this->db->exec("INSERT INTO goods (title) VALUES ('a')");
		$this->assertSame($this->db, $this->db->transactionEnd());
		$this->assertSame(1, $this->db->single('SELECT COUNT(*) FROM goods', false));
	}

	public function testNamedTransaction()
	{
		$this->createGoods();
		$log = $this->enableLog();
		$this->db->transactionBegin('DEFERRED', 'tx1');
		$this->db->exec("INSERT INTO goods (title) VALUES ('a')");
		$this->db->transactionCommit('tx1');
		$this->assertSame(1, $this->db->single('SELECT COUNT(*) FROM goods', false));
		$sql = array_column($log->all(), 'sql');
		$this->assertSame('BEGIN DEFERRED TRANSACTION tx1', $sql[0]);
		$this->assertSame('COMMIT TRANSACTION tx1', $sql[2]);
	}

	public function testRawQueriesAreLoggedWithoutTable()
	{
		$this->db->connection();
		$log = $this->enableLog();
		$this->db->exec('CREATE TABLE t (id INTEGER)');
		$this->db->query('SELECT * FROM t');
		$this->db->single('SELECT COUNT(*) FROM t');

		$this->assertInstanceOf(Queries::class, $this->db->queries());
		$this->assertSame(
			['CREATE TABLE t (id INTEGER)', 'SELECT * FROM t', 'SELECT COUNT(*) FROM t'],
			array_column($log->all(), 'sql')
		);
		foreach ($log as $item) {
			$this->assertNull($item['table']);
			$this->assertMatchesRegularExpression('/^\d+\.\d{12}$/', $item['time']);
		}
	}

	public function testPragmasAreNotLogged()
	{
		$log = $this->enableLog();
		$this->db->connection();
		$this->db->pragma('cache_size', 100);
		$this->assertCount(0, $log);
	}

	public function testCloseAndReopenAutomatically()
	{
		$goods = $this->seedGoods();
		$connection = $this->db->connection();
		$this->db->close();
		$this->assertFalse($this->db->hasOpened());

		// next query opens a new connection with options applied
		$this->assertSame(6, $goods->select()->count());
		$this->assertTrue($this->db->hasOpened());
		$this->assertNotSame($connection, $this->db->connection());
		$this->assertSame('wal', $this->db->single('PRAGMA journal_mode', false));
		$this->assertSame($this->db, Sqlite::database($this->path));
	}

	public function testCloseIsSafeToRepeat()
	{
		$this->db->close();
		$this->assertFalse($this->db->hasOpened());
		$this->db->connection();
		$this->db->close();
		$this->db->close();
		$this->assertFalse($this->db->hasOpened());
	}

	public function testBuilderErrorWithExceptionsDisabled()
	{
		$db = Sqlite::database($this->dir.'/noexc.db', ['exceptions' => false]);
		set_error_handler(function () {
			return true;
		});
		try {
			$db->table('missing_table')->select()->rows();
			$this->fail('Error expected');
		} catch (\Error $e) {
			// prepare() returns false, method is called on bool
			$this->assertMatchesRegularExpression('/^Call to a member function execute\(\) on (bool|false)$/', $e->getMessage());
		} finally {
			restore_error_handler();
			$db->close();
		}
	}
}
