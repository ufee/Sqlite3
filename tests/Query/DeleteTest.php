<?php
namespace Ufee\Sqlite3\Tests\Query;

use Ufee\Sqlite3\Tests\TestCase;

class DeleteTest extends TestCase
{
	public function testRowsWithConditions()
	{
		$goods = $this->seedGoods();
		$delete = $goods->delete()->where('category', 4)->orWhere('id', 1);
		$this->assertSame(4, $delete->rows());
		$this->assertSame('DELETE FROM goods WHERE category = :category0 OR id = :id0', $delete->sql());
		$this->assertSame([2, 3], $this->fetchColumn('SELECT id FROM goods ORDER BY id'));
	}

	public function testRowsWithoutConditionsDeletesAll()
	{
		$goods = $this->seedGoods();
		$delete = $goods->delete();
		$this->assertSame(6, $delete->rows());
		$this->assertSame('DELETE FROM goods', $delete->sql());
		$this->assertSame(0, $this->db->single('SELECT COUNT(*) FROM goods', false));
	}

	public function testRowsReturnsZeroWhenNothingMatched()
	{
		$goods = $this->seedGoods();
		$this->assertSame(0, $goods->delete()->where('id', 100)->rows());
	}

	public function testLimitSql()
	{
		$goods = $this->seedGoods();
		$delete = $goods->delete()->where('category', 4)->orderBy('id');
		$this->assertSame(
			'DELETE FROM goods WHERE category = :category0 ORDER BY id DESC LIMIT 1',
			$this->sqlOf($delete, function ($q) {
				$q->row();
			})
		);
		$this->assertSame(
			'DELETE FROM goods WHERE category = :category0 ORDER BY id DESC LIMIT 3 OFFSET 6',
			$this->sqlOf($delete, function ($q) {
				$q->rows(3, 6);
			})
		);
	}

	public function testRow()
	{
		$this->requireUpdateDeleteLimit();
		$goods = $this->seedGoods();
		$this->assertTrue($goods->delete()->where('id', 5)->row());
		$this->assertFalse($goods->delete()->where('id', 5)->row());

		$delete = $goods->delete()->where('category', 4)->orderBy('id');
		$this->assertTrue($delete->row());
		$this->assertSame([1, 2, 3, 4], $this->fetchColumn('SELECT id FROM goods ORDER BY id'));
	}

	public function testRowsWithLimitAndOffset()
	{
		$this->requireUpdateDeleteLimit();
		$goods = $this->seedGoods();
		$delete = $goods->delete()->orderBy('id');
		$this->assertSame(2, $delete->rows(2));
		$this->assertSame('DELETE FROM goods ORDER BY id DESC LIMIT 2', $delete->sql());
		$this->assertSame(2, $delete->rows(2, 1));
		$this->assertSame('DELETE FROM goods ORDER BY id DESC LIMIT 2 OFFSET 1', $delete->sql());
		$this->assertSame([1, 4], $this->fetchColumn('SELECT id FROM goods ORDER BY id'));
	}

	public function testQueryIsLogged()
	{
		$goods = $this->seedGoods();
		$goods->columns();
		$log = $this->enableLog();
		$delete = $goods->delete()->where('id', 1);
		$delete->rows();
		$this->assertSame(
			[['table' => 'goods', 'sql' => 'DELETE FROM goods WHERE id = :id0', 'time' => $delete->executionTime()]],
			$log->all()
		);
	}
}
