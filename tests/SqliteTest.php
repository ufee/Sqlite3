<?php
namespace Ufee\Sqlite3\Tests;

use Ufee\Sqlite3\Database;
use Ufee\Sqlite3\Sqlite;

class SqliteTest extends TestCase
{
	public function testDatabaseReturnsSameInstanceByName()
	{
		$this->assertInstanceOf(Database::class, $this->db);
		$this->assertSame($this->db, Sqlite::database($this->path));
		$this->assertNotSame($this->db, Sqlite::database($this->dir.'/other.db'));
	}

	public function testOptionsOfRepeatedCallAreIgnored()
	{
		$db = Sqlite::database($this->path, ['journal_mode' => 'DELETE']);
		$this->assertSame('wal', $db->single('PRAGMA journal_mode', false));
	}

	public function testVersion()
	{
		$version = Sqlite::version();
		$this->assertSame(\SQLite3::version(), $version);
		$this->assertArrayHasKey('versionString', $version);
		$this->assertArrayHasKey('versionNumber', $version);
	}
}
