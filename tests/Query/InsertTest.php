<?php
namespace Ufee\Sqlite3\Tests\Query;

use Ufee\Sqlite3\Tests\TestCase;

class InsertTest extends TestCase
{
	public function testRowReturnsLastInsertId()
	{
		$goods = $this->createGoods();
		$insert = $goods->insert('category, price, title');
		$this->assertSame(1, $insert->row([2, 7299.50, 'Notebook Pro']));
		$this->assertSame('INSERT INTO goods (category, price, title) VALUES (:category0, :price0, :title0)', $insert->sql());
		$this->assertSame(
			[['id' => 1, 'category' => 2, 'price' => 7299.5, 'title' => 'Notebook Pro']],
			$this->fetchAll('SELECT id, category, price, title FROM goods')
		);
	}

	public function testRowWithExplicitIdReturnsIt()
	{
		$goods = $this->createGoods();
		$this->assertSame(10, $goods->insert('id, title')->row([10, 'a']));
	}

	public function testInsertIsReusable()
	{
		$goods = $this->createGoods();
		$insert = $goods->insert(['category', 'title']);
		$this->assertSame(1, $insert->row([5, 'PC Intel']));
		$this->assertSame(2, $insert->row([5, 'PC AMD']));
		$this->assertSame('INSERT INTO goods (category, title) VALUES (:category0, :title0)', $insert->sql());
		$this->assertSame(['PC Intel', 'PC AMD'], $this->fetchColumn('SELECT title FROM goods ORDER BY id'));
	}

	public function testRows()
	{
		$goods = $this->createGoods();
		$insert = $goods->insert(' category ,title ');
		$this->assertSame(3, $insert->rows([
			[4, 'TV 1000'],
			[4, 'TV 2000'],
			[4, 'TV 3000']
		]));
		$this->assertSame(
			'INSERT INTO goods (category, title) VALUES (:category0, :title0), (:category1, :title1), (:category2, :title2)',
			$insert->sql()
		);
		$this->assertSame(['TV 1000', 'TV 2000', 'TV 3000'], $this->fetchColumn('SELECT title FROM goods ORDER BY id'));
		$this->assertSame(1, $insert->rows([[4, 'TV 4000']]));
		$this->assertSame('INSERT INTO goods (category, title) VALUES (:category0, :title0)', $insert->sql());
	}

	/**
	 * @dataProvider conflictProvider
	 */
	public function testConflictClauseSql($method, $clause)
	{
		$goods = $this->createGoods();
		$insert = $goods->insert('title');
		$this->assertSame($insert, $insert->{$method}());
		$insert->row(['a']);
		$this->assertSame('INSERT'.$clause.' INTO goods (title) VALUES (:title0)', $insert->sql());
	}

	public function conflictProvider()
	{
		return [
			['orRollback', ' OR ROLLBACK'],
			['orAbort', ' OR ABORT'],
			['orFail', ' OR FAIL'],
			['orIgnore', ' OR IGNORE'],
			// method name has a typo, kept as is
			['orRreplace', ' OR REPLACE'],
		];
	}

	public function testLastConflictClauseWins()
	{
		$goods = $this->createGoods();
		$insert = $goods->insert('title')->orFail()->orIgnore();
		$insert->row(['a']);
		$this->assertSame('INSERT OR IGNORE INTO goods (title) VALUES (:title0)', $insert->sql());
	}

	public function testUniqueViolationThrows()
	{
		$meta = $this->createGoodsMeta();
		$meta->insert(['good_id' => 1]);
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('UNIQUE constraint failed');
		$meta->insert('good_id')->row([1]);
	}

	public function testOrIgnoreDuplicateRowReturnsFalse()
	{
		$meta = $this->createGoodsMeta();
		$insert = $meta->insert('good_id, descr')->orIgnore();
		$this->assertSame(1, $insert->row([1, 'first']));
		$this->assertFalse($insert->row([1, 'second']));
		$this->assertSame(['first'], $this->fetchColumn('SELECT descr FROM goods_meta'));
	}

	public function testOrIgnoreRowsReturnsInsertedCount()
	{
		$meta = $this->createGoodsMeta();
		$meta->insert(['good_id' => 1]);
		$insert = $meta->insert('good_id')->orIgnore();
		$this->assertSame(2, $insert->rows([[1], [2], [3]]));
		$this->assertSame([1, 2, 3], $this->fetchColumn('SELECT good_id FROM goods_meta ORDER BY good_id'));
		$this->assertSame(0, $insert->rows([[1], [2]]));
	}

	public function testOrReplaceRowsReturnsCount()
	{
		$meta = $this->createGoodsMeta();
		$meta->insert(['good_id' => 1, 'descr' => 'first']);
		$this->assertSame(2, $meta->insert('good_id, descr')->orRreplace()->rows([[1, 'second'], [2, 'new']]));
		$this->assertSame(['second', 'new'], $this->fetchColumn('SELECT descr FROM goods_meta ORDER BY good_id'));
	}

	public function testOrReplaceReplacesRow()
	{
		$meta = $this->createGoodsMeta();
		$meta->insert(['good_id' => 1, 'descr' => 'first']);
		$this->assertSame(2, $meta->insert('good_id, descr')->orRreplace()->row([1, 'second']));
		$this->assertSame([['good_id' => 1, 'descr' => 'second']], $this->fetchAll('SELECT good_id, descr FROM goods_meta'));
	}

	public function testValuesAreBoundByDeclaredColumnType()
	{
		$goods = $this->createGoods();
		$goods->insert('category, price, title, hot')->row(['5', '7.5', 123, null]);
		$this->assertSame(
			[['category' => 'integer', 'price' => 'real', 'title' => 'text', 'hot' => 'null']],
			$this->fetchAll('SELECT typeof(category) AS category, typeof(price) AS price, typeof(title) AS title, typeof(hot) AS hot FROM goods')
		);
		$this->assertSame([['category' => 5, 'price' => 7.5, 'title' => '123']], $this->fetchAll('SELECT category, price, title FROM goods'));
	}

	public function testValuesOfUntypedColumnsAreBoundByFirstValue()
	{
		$this->db->table('t')->create(['a', 'b', 'c']);
		$insert = $this->db->table('t')->insert('a, b, c');
		$insert->row([1, 1.5, true]);
		$insert->row(['2', '2.5', false]);
		$this->assertSame(
			[
				['a' => 'integer', 'b' => 'real', 'c' => 'text', 'va' => 1, 'vb' => 1.5, 'vc' => '1'],
				['a' => 'integer', 'b' => 'real', 'c' => 'text', 'va' => 2, 'vb' => 2.5, 'vc' => ''],
			],
			$this->fetchAll('SELECT typeof(a) AS a, typeof(b) AS b, typeof(c) AS c, a AS va, b AS vb, c AS vc FROM t ORDER BY rowid')
		);
	}

	public function testUnknownColumnThrows()
	{
		$goods = $this->createGoods();
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('table goods has no column named missing');
		$goods->insert('missing')->row([1]);
	}

	public function testQueryIsLogged()
	{
		$goods = $this->createGoods();
		$log = $this->enableLog();
		$insert = $goods->insert('title');
		$insert->row(['a']);
		$this->assertSame('goods', $log->last()['table']);
		$this->assertSame('INSERT INTO goods (title) VALUES (:title0)', $log->last()['sql']);
		$this->assertSame($insert->executionTime(), $log->last()['time']);
		$this->assertMatchesRegularExpression('/^\d+\.\d{12}$/', $insert->executionTime());
	}
}
