<?php
namespace Ufee\Sqlite3\Tests;

use Ufee\Sqlite3\Sqlite;

/**
 * End-to-end scenario of README examples
 */
class ReadmeScenarioTest extends TestCase
{
	public function testScenario()
	{
		$db = Sqlite::database($this->path);
		if (!$db->fileExists()) {
			$db->create();
		}
		$goods = $db->table('goods');
		$meta = $db->table('goods_meta');
		$this->assertFalse($goods->exists());
		$this->createGoods();
		$this->createGoodsMeta();

		// insert
		$insert = $goods->insert('category, price, title');
		$id = $insert->orFail()->row([2, 7299.50, 'Notebook Pro']);
		$this->assertSame(1, $meta->insert(['good_id' => $id, 'descr' => 'Professional device', 'star' => 4]));
		$id = $insert->orAbort()->row([2, 6999.70, 'Notebook Adv']);
		$meta->insert(['good_id' => $id, 'descr' => 'Advanced device', 'star' => 5]);
		$id = $goods->insert(['category' => 3, 'title' => 'Mobile Pro', 'price' => 3799.90]);
		$meta->insert(['good_id' => $id, 'sale' => 499.90, 'descr' => 'Professional device', 'star' => 3]);
		$this->assertSame(3, $goods->insert('category, title')->rows([[4, 'TV 1000'], [4, 'TV 2000'], [4, 'TV 3000']]));
		$this->assertSame(6, $goods->select()->count());

		// select with join
		$select = $goods->select('g.id, g.category, g.title, m.descr, m.sale, g.hot, m.star')
			->short('g')->innerJoin('goods_meta AS m', 'm.good_id=g.id')
			->where('g.category', [1, 2, 3, 4, 5], 'IN')
			->where('m.star', 1, '>')
			->orderBy('m.star');
		$this->assertSame(3, $select->count());
		$this->assertSame([2, 1, 3], array_column($select->rows(), 'id'));

		// update
		$update = $goods->update('price, title, hot')->where('id', 1)->set([6950.10, 'Notebook Pro (hot)', 1]);
		$this->assertSame(1, $update->rows());
		$this->assertSame(3, $goods->update('hot')->where('category', 3)->orWhere('price', 5000, '>')->set(1)->rows());
		$this->assertSame('Notebook Pro (hot)', $goods->select('title')->where('id', 1)->row('title'));

		// delete
		$this->assertSame(1, $goods->delete()->where('id', 5)->rows());
		$this->assertSame(5, $goods->select()->count());

		// transaction
		$insert = $goods->insert('category, title');
		$db->transactionBegin('IMMEDIATE');
		$insert->row([5, 'PC Intel']);
		$insert->row([5, 'PC AMD']);
		$db->transactionCommit();
		$this->assertSame(2, $goods->select()->where('category', 5)->count());

		$db->transactionBegin();
		$insert->row([5, 'PC Other']);
		$db->transactionRollback();
		$this->assertSame(2, $goods->select()->where('category', 5)->count());

		$this->assertSame(['goods', 'goods_meta'], array_keys($db->tables()));
	}

	public function testScenarioWithLimits()
	{
		$this->requireUpdateDeleteLimit();
		$goods = $this->seedGoods();

		$this->assertTrue($goods->update('price, title, hot')->where('id', 1)->set([6950.10, 'Notebook Pro (hot)', 1])->row());
		$this->assertSame(2, $goods->update('hot')->where('category', 4)->set(9)->rows(3, 1));

		$delete = $goods->delete()->where('category', 4)->orderBy('id');
		$this->assertTrue($delete->row());
		$this->assertSame(2, $delete->rows(3));
		$this->assertSame([1, 2, 3], $this->fetchColumn('SELECT id FROM goods ORDER BY id'));
	}
}
