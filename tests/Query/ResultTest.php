<?php
namespace Ufee\Sqlite3\Tests\Query;

use Ufee\Sqlite3\Query\Result;
use Ufee\Sqlite3\Tests\TestCase;

class ResultTest extends TestCase
{
	public function testGetRows()
	{
		$this->seedGoods();
		$result = $this->db->query('SELECT id, title FROM goods WHERE id <= 2 ORDER BY id');
		$this->assertSame([
			['id' => 1, 'title' => 'Notebook Pro'],
			['id' => 2, 'title' => 'Notebook Adv'],
		], $result->getRows());
		// SQLite3Result re-executes the statement after the end of rows
		$this->assertCount(2, $result->getRows());
	}

	public function testGetRowsMode()
	{
		$this->seedGoods();
		$sql = 'SELECT id, title FROM goods WHERE id = 1';
		$this->assertSame([['id' => 1, 'title' => 'Notebook Pro']], $this->db->query($sql)->getRows(SQLITE3_ASSOC));
		$this->assertSame([[1, 'Notebook Pro']], $this->db->query($sql)->getRows(SQLITE3_NUM));
		$this->assertSame(
			[[0 => 1, 'id' => 1, 1 => 'Notebook Pro', 'title' => 'Notebook Pro']],
			$this->db->query($sql)->getRows(SQLITE3_BOTH)
		);
	}

	public function testCountDoesNotConsumeRows()
	{
		$this->seedGoods();
		$result = $this->db->query('SELECT id FROM goods WHERE category = 4');
		$this->assertSame(3, $result->count());
		$this->assertCount(3, $result->getRows());
		$this->assertSame(3, $result->count());
		$this->assertSame(0, $this->db->query('SELECT id FROM goods WHERE id = 100')->count());
	}

	public function testNumColumns()
	{
		$this->seedGoods();
		$this->assertSame(3, $this->db->query('SELECT id, title, price FROM goods')->numColumns());
	}

	public function testWrapsSqliteResult()
	{
		$this->db->exec('CREATE TABLE t (id INTEGER)');
		$result = new Result($this->db->connection()->query('SELECT * FROM t'));
		$this->assertSame([], $result->getRows());
	}
}
