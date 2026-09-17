<?php
namespace Ufee\Sqlite3\Tests\Query;

use Ufee\Sqlite3\Tests\TestCase;

class UpdateTest extends TestCase
{
	public function testRowsWithConditions()
	{
		$goods = $this->seedGoods();
		$update = $goods->update('hot')
			->where('category', 3)
			->orWhere('price', 5000, '>')
			->set(1);
		$this->assertSame(3, $update->rows());
		$this->assertSame('UPDATE goods SET hot = :hot0 WHERE category = :category0 OR price > :price0', $update->sql());
		$this->assertSame([1, 2, 3], $this->fetchColumn('SELECT id FROM goods WHERE hot = 1 ORDER BY id'));
	}

	public function testRowsWithoutConditionsUpdatesAll()
	{
		$goods = $this->seedGoods();
		$update = $goods->update(['price'])->set([10.5]);
		$this->assertSame(6, $update->rows());
		$this->assertSame('UPDATE goods SET price = :price0', $update->sql());
		$this->assertSame([10.5], $this->fetchColumn('SELECT DISTINCT price FROM goods'));
	}

	public function testRowsReturnsZeroWhenNothingMatched()
	{
		$goods = $this->seedGoods();
		$this->assertSame(0, $goods->update('hot')->where('id', 100)->set(1)->rows());
	}

	public function testSetSeveralColumns()
	{
		$goods = $this->seedGoods();
		$update = $goods->update('price, title, hot')
			->where('id', 1)
			->set([6950.10, 'Notebook Pro (hot)', 1]);
		$this->assertSame(1, $update->rows());
		$this->assertSame('UPDATE goods SET price = :price0, title = :title0, hot = :hot0 WHERE id = :id0', $update->sql());
		$this->assertSame(
			[['price' => 6950.1, 'title' => 'Notebook Pro (hot)', 'hot' => 1]],
			$this->fetchAll('SELECT price, title, hot FROM goods WHERE id = 1')
		);
	}

	public function testSameColumnInSetAndWhere()
	{
		$goods = $this->seedGoods();
		$update = $goods->update('category')->where('category', 4)->set('5');
		$this->assertSame(3, $update->rows());
		$this->assertSame('UPDATE goods SET category = :category1 WHERE category = :category0', $update->sql());
		$this->assertSame([2, 3, 5], $this->fetchColumn('SELECT DISTINCT category FROM goods ORDER BY category'));
		$this->assertSame(['integer'], $this->fetchColumn('SELECT DISTINCT typeof(category) FROM goods WHERE id > 3'));
	}

	public function testRepeatedSetAccumulates()
	{
		$goods = $this->seedGoods();
		$update = $goods->update('hot')->where('id', 1)->set(1)->set(2);
		$this->assertSame(1, $update->rows());
		// SQLite uses the rightmost assignment
		$this->assertSame('UPDATE goods SET hot = :hot0, hot = :hot1 WHERE id = :id0', $update->sql());
		$this->assertSame([2], $this->fetchColumn('SELECT hot FROM goods WHERE id = 1'));
	}

	public function testRowsWithoutSetThrows()
	{
		$goods = $this->seedGoods();
		$update = $goods->update('hot');
		$this->assertSame('UPDATE goods SET ', $this->sqlOf($update, function ($q) {
			$q->rows();
		}));
		$this->expectException(\Exception::class);
		$update->rows();
	}

	public function testRepeatedRowsExecutesAgain()
	{
		$goods = $this->seedGoods();
		$update = $goods->update('hot')->where('category', 4)->set(7);
		$this->assertSame(3, $update->rows());
		$this->assertSame(3, $update->rows());
	}

	public function testLimitSql()
	{
		$goods = $this->seedGoods();
		$update = $goods->update('hot')->where('category', 4)->orderBy('id')->set(1);
		$this->assertSame(
			'UPDATE goods SET hot = :hot0 WHERE category = :category0 ORDER BY id DESC LIMIT 1',
			$this->sqlOf($update, function ($q) {
				$q->row();
			})
		);
		$this->assertSame(
			'UPDATE goods SET hot = :hot0 WHERE category = :category0 ORDER BY id DESC LIMIT 2 OFFSET 1',
			$this->sqlOf($update, function ($q) {
				$q->rows(2, 1);
			})
		);
	}

	public function testRow()
	{
		$this->requireUpdateDeleteLimit();
		$goods = $this->seedGoods();
		$update = $goods->update('hot')->where('category', 4)->orderBy('id')->set(9);
		$this->assertTrue($update->row());
		$this->assertSame([6], $this->fetchColumn('SELECT id FROM goods WHERE hot = 9'));
		$this->assertFalse($goods->update('hot')->where('id', 100)->set(9)->row());
	}

	public function testRowsWithLimitAndOffset()
	{
		$this->requireUpdateDeleteLimit();
		$goods = $this->seedGoods();
		$update = $goods->update('hot')->orderBy('id', 'ASC')->set(9);
		$this->assertSame(2, $update->rows(2, 3));
		$this->assertSame('UPDATE goods SET hot = :hot0 ORDER BY id ASC LIMIT 2 OFFSET 3', $update->sql());
		$this->assertSame([4, 5], $this->fetchColumn('SELECT id FROM goods WHERE hot = 9 ORDER BY id'));
	}

	public function testQueryIsLogged()
	{
		$goods = $this->seedGoods();
		$goods->columns();
		$log = $this->enableLog();
		$update = $goods->update('hot')->where('id', 1)->set(1);
		$update->rows();
		$this->assertSame(
			[['table' => 'goods', 'sql' => 'UPDATE goods SET hot = :hot0 WHERE id = :id0', 'time' => $update->executionTime()]],
			$log->all()
		);
	}
}
