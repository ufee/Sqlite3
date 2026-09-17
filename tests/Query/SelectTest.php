<?php
namespace Ufee\Sqlite3\Tests\Query;

use Ufee\Sqlite3\Tests\TestCase;

class SelectTest extends TestCase
{
	private function ids(array $rows)
	{
		return array_column($rows, 'id');
	}

	public function testSelectAll()
	{
		$goods = $this->seedGoods();
		$select = $goods->select();
		$rows = $select->rows();
		$this->assertSame('SELECT * FROM goods', $select->sql());
		$this->assertCount(6, $rows);
		$this->assertSame(['id', 'category', 'price', 'title', 'hot', 'created_at'], array_keys($rows[0]));
	}

	public function testColumnsAsStringAndArray()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id, title');
		$this->assertSame(['id' => 1, 'title' => 'Notebook Pro'], $select->rows()[0]);
		$this->assertSame('SELECT id,title FROM goods', $select->sql());

		$select = $goods->select([' id', 'title ']);
		$select->rows();
		$this->assertSame('SELECT id,title FROM goods', $select->sql());
	}

	/**
	 * @dataProvider operatorProvider
	 */
	public function testWhereOperators($column, $value, $operator, $sql, array $ids)
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id')->where($column, $value, $operator)->orderBy('id', 'ASC');
		$this->assertSame($ids, $this->ids($select->rows()));
		$this->assertSame('SELECT id FROM goods WHERE '.$sql.' ORDER BY id ASC', $select->sql());
	}

	public function operatorProvider()
	{
		return [
			'=' => ['category', 4, '=', 'category = :category0', [4, 5, 6]],
			'!=' => ['category', 4, '!=', 'category != :category0', [1, 2, 3]],
			'>' => ['id', 4, '>', 'id > :id0', [5, 6]],
			'>=' => ['id', 4, '>=', 'id >= :id0', [4, 5, 6]],
			'<' => ['id', 2, '<', 'id < :id0', [1]],
			'<=' => ['id', 2, '<=', 'id <= :id0', [1, 2]],
			'string value' => ['id', '2', '=', 'id = :id0', [2]],
			'IN' => ['id', [1, 3, 5], 'IN', 'id IN (:id0,:id1,:id2)', [1, 3, 5]],
			'NOT IN' => ['id', [1, 3, 5], 'NOT IN', 'id NOT IN (:id0,:id1,:id2)', [2, 4, 6]],
			'BETWEEN' => ['price', [3000, 7000], 'BETWEEN', 'price BETWEEN :price0 AND :price1', [2, 3]],
			'NOT BETWEEN' => ['price', [3000, 7000], 'NOT BETWEEN', 'price NOT BETWEEN :price0 AND :price1', [1, 4, 5, 6]],
			'LIKE' => ['title', 'notebook%', 'LIKE', 'title LIKE :title0', [1, 2]],
			'NOT LIKE' => ['title', '%Pro', 'NOT LIKE', 'title NOT LIKE :title0', [2, 4, 5, 6]],
			'GLOB' => ['title', 'TV *', 'GLOB', 'title GLOB :title0', [4, 5, 6]],
			'NOT GLOB' => ['title', 'TV *', 'NOT GLOB', 'title NOT GLOB :title0', [1, 2, 3]],
			'IS' => ['hot', null, 'IS', 'hot IS NULL', [1, 3, 5]],
			'IS NOT' => ['hot', null, 'IS NOT', 'hot IS NOT NULL', [2, 4, 6]],
		];
	}

	public function testInvalidOperatorThrows()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid where operator: in for column: id');
		$this->db->table('goods')->select()->where('id', [1], 'in');
	}

	public function testRawWhere()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id')->where('id = category OR id > 5');
		$this->assertSame([2, 3, 4, 6], $this->ids($select->rows()));
		$this->assertSame('SELECT id FROM goods WHERE id = category OR id > 5', $select->sql());
	}

	public function testWhereAndOrWhere()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id')
			->where('category', 2)
			->where('id', 1, '>')
			->orWhere('id', 6)
			->orderBy('id', 'asc');
		$this->assertSame([2, 6], $this->ids($select->rows()));
		$this->assertSame('SELECT id FROM goods WHERE category = :category0 AND id > :id0 OR id = :id1 ORDER BY id ASC', $select->sql());
	}

	public function testOrWhereAsFirstCondition()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id')->orWhere('id', 3);
		$this->assertSame([3], $this->ids($select->rows()));
		$this->assertSame('SELECT id FROM goods WHERE id = :id0', $select->sql());
	}

	public function testOrderBy()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id')->where('category', 4)->orderBy('id');
		$this->assertSame([6, 5, 4], $this->ids($select->rows()));
		$this->assertSame('SELECT id FROM goods WHERE category = :category0 ORDER BY id DESC', $select->sql());

		$select = $goods->select('id')->orderBy('category', 'asc')->orderBy('id', 'desc');
		$this->assertSame([2, 1, 3, 6, 5, 4], $this->ids($select->rows()));
		$this->assertSame('SELECT id FROM goods ORDER BY category ASC, id DESC', $select->sql());

		// same column is overwritten, position is kept
		$select->orderBy('category', 'desc');
		$this->assertSame([6, 5, 4, 3, 2, 1], $this->ids($select->rows()));
		$this->assertSame('SELECT id FROM goods ORDER BY category DESC, id DESC', $select->sql());
	}

	public function testLimitAndOffset()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id, category, title')->where('hot', null, 'IS NOT')->orderBy('hot');
		$this->assertSame([4, 2], $this->ids($select->rows(2, 1)));
		$this->assertSame('SELECT id,category,title FROM goods WHERE hot IS NOT NULL ORDER BY hot DESC LIMIT 2 OFFSET 1', $select->sql());
		$this->assertSame([6, 4], $this->ids($select->rows(2)));
		$this->assertSame('SELECT id,category,title FROM goods WHERE hot IS NOT NULL ORDER BY hot DESC LIMIT 2', $select->sql());
		$this->assertSame([6, 4, 2], $this->ids($select->rows('10', '0')));
		$this->assertSame('SELECT id,category,title FROM goods WHERE hot IS NOT NULL ORDER BY hot DESC LIMIT 10 OFFSET 0', $select->sql());
	}

	public function testOffsetWithoutLimitThrows()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id');
		$this->assertSame('SELECT id FROM goods OFFSET 2', $this->sqlOf($select, function ($q) {
			$q->rows(null, 2);
		}));
		$this->expectException(\Exception::class);
		$select->rows(null, 2);
	}

	public function testRepeatedRowsReturnSameResult()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id')->where('category', 4)->orderBy('id');
		$this->assertSame($select->rows(), $select->rows());
	}

	public function testRow()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('id, title')->where('id', 2);
		$this->assertSame(['id' => 2, 'title' => 'Notebook Adv'], $select->row());
		$this->assertSame('SELECT id,title FROM goods WHERE id = :id0 LIMIT 1', $select->sql());
		$this->assertSame('Notebook Adv', $select->row('title'));
		$this->assertNull($goods->select()->where('id', 100)->row());
		$this->assertNull($goods->select()->where('id', 100)->row('title'));
	}

	public function testRowWithMissingColumnThrows()
	{
		$goods = $this->seedGoods();
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sqlite column row not found: title');
		$goods->select('id')->row('title');
	}

	public function testCount()
	{
		$goods = $this->seedGoods();
		$this->assertSame(6, $goods->select()->count());

		$log = $this->enableLog();
		$select = $goods->select('id, title')->where('id', 1, '>')->orderBy('hot');
		$this->assertSame(5, $select->count());
		$this->assertSame('SELECT COUNT(*) as rows_count FROM goods WHERE id > :id0 ORDER BY hot DESC LIMIT 1', $log->last()['sql']);

		// original query is not changed
		$this->assertNull($select->sql());
		$this->assertCount(3, $select->rows(3));
		$this->assertSame('SELECT id,title FROM goods WHERE id > :id0 ORDER BY hot DESC LIMIT 3', $select->sql());
		$this->assertSame(0, $goods->select()->where('id', 100)->count());
	}

	public function testDistinct()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('category')->distinct()->orderBy('category', 'ASC');
		$this->assertSame([2, 3, 4], array_column($select->rows(), 'category'));
		$this->assertSame('SELECT DISTINCT category FROM goods ORDER BY category ASC', $select->sql());
	}

	public function testGroupByAndHaving()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('category, COUNT(*) AS cnt')
			->groupBy('category')
			->having('COUNT(*) > 1')
			->having('category', 3, '>')
			->orderBy('category', 'ASC');
		$this->assertSame([['category' => 4, 'cnt' => 3]], $select->rows());
		$this->assertSame(
			'SELECT category,COUNT(*) AS cnt FROM goods GROUP BY category HAVING COUNT(*) > 1 AND category > :category0 ORDER BY category ASC',
			$select->sql()
		);
	}

	public function testOrHavingWithValue()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('category')
			->groupBy('category')
			->having('category', 2)
			->orHaving('category', 3)
			->orderBy('category', 'ASC');
		$this->assertSame([2, 3], array_column($select->rows(), 'category'));
		$this->assertSame(
			'SELECT category FROM goods GROUP BY category HAVING category = :category0 OR category = :category1 ORDER BY category ASC',
			$select->sql()
		);
	}

	public function testHavingByAliasWithValue()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('category, COUNT(*) AS cnt, SUM(price) AS total')
			->groupBy('category')
			->having('cnt', 1, '>')
			->orHaving('total', [3000, 4000], 'BETWEEN')
			->orderBy('category', 'ASC');
		$this->assertSame([2, 3, 4], array_column($select->rows(), 'category'));
		$this->assertSame(
			'SELECT category,COUNT(*) AS cnt,SUM(price) AS total FROM goods GROUP BY category HAVING cnt > :cnt0 OR total BETWEEN :total0 AND :total1 ORDER BY category ASC',
			$select->sql()
		);
	}

	public function testHavingByAliasBindsTypeOfValue()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('category, COUNT(*) AS cnt')->groupBy('category')->having('cnt', 2, '>=')->orderBy('category', 'ASC');
		$this->assertSame([2, 4], array_column($select->rows(), 'category'));
		// string is bound as TEXT: integer is always less than text in SQLite
		$select = $goods->select('category, COUNT(*) AS cnt')->groupBy('category')->having('cnt', '2', '>=');
		$this->assertSame([], $select->rows());
	}

	public function testHavingByAliasUsesColumnTypeOverride()
	{
		$goods = $this->seedGoods();
		$goods->setColumnType('cnt', 'integer');
		$select = $goods->select('category, COUNT(*) AS cnt')->groupBy('category')->having('cnt', '2', '>=')->orderBy('category', 'ASC');
		$this->assertSame([2, 4], array_column($select->rows(), 'category'));
	}

	public function testWhereByUnknownColumnThrows()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('category')->where('cnt', 1, '>');
		// SQL is validated by SQLite before value binding
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('no such column: cnt');
		$select->rows();
	}

	public function testRawOrHaving()
	{
		$goods = $this->seedGoods();
		$select = $goods->select('category')
			->groupBy('category')
			->having('category', 2)
			->orHaving('COUNT(*) > 1')
			->orderBy('category', 'ASC');
		$this->assertSame([2, 4], array_column($select->rows(), 'category'));
		$this->assertSame(
			'SELECT category FROM goods GROUP BY category HAVING category = :category0 OR COUNT(*) > 1 ORDER BY category ASC',
			$select->sql()
		);
	}

	public function testInnerJoinWithShort()
	{
		$goods = $this->seedGoods();
		$meta = $this->createGoodsMeta();
		$meta->insert('good_id, sale, descr, star')->rows([
			[1, 0, 'Professional device', 4],
			[2, 0, 'Advanced device', 5],
			[3, 499.9, 'Professional device', 1],
		]);
		$select = $goods->select('g.id, g.title, m.descr, m.star')
			->short('g')->innerJoin('goods_meta AS m', 'm.good_id=g.id')
			->where('g.category', [1, 2, 3, 4, 5], 'IN')
			->where('m.star', '1', '>')
			->orderBy('m.star');
		$this->assertSame(2, $select->count());
		$this->assertSame([
			['id' => 2, 'title' => 'Notebook Adv', 'descr' => 'Advanced device', 'star' => 5],
			['id' => 1, 'title' => 'Notebook Pro', 'descr' => 'Professional device', 'star' => 4],
		], $select->rows());
		$this->assertSame(
			'SELECT g.id,g.title,m.descr,m.star FROM goods AS g INNER JOIN goods_meta AS m ON m.good_id=g.id'
			.' WHERE g.category IN (:gcategory0,:gcategory1,:gcategory2,:gcategory3,:gcategory4) AND m.star > :mstar0 ORDER BY m.star DESC',
			$select->sql()
		);
	}

	public function testLeftJoinWithDefaultShort()
	{
		$goods = $this->seedGoods();
		$meta = $this->createGoodsMeta();
		$meta->insert(['good_id' => 1, 'star' => 4]);
		$select = $goods->select('g.id, m.star')
			->leftJoin('goods_meta AS m', 'm.good_id=g.id')
			->where('m.good_id IS NOT NULL OR g.id <= 3')
			->orderBy('g.id', 'ASC');
		$this->assertSame([
			['id' => 1, 'star' => 4],
			['id' => 2, 'star' => null],
			['id' => 3, 'star' => null],
		], $select->rows());
		// default short is the first letter of the table name
		$this->assertSame(
			'SELECT g.id,m.star FROM goods AS g LEFT JOIN goods_meta AS m ON m.good_id=g.id WHERE m.good_id IS NOT NULL OR g.id <= 3 ORDER BY g.id ASC',
			$select->sql()
		);
	}

	public function testBoundConditionOnDefaultShort()
	{
		$goods = $this->seedGoods();
		$meta = $this->createGoodsMeta();
		$meta->insert(['good_id' => 2, 'star' => 5]);
		$select = $goods->select('g.id, g.title, m.star')
			->leftJoin('goods_meta AS m', 'm.good_id=g.id')
			->where('g.id', '3', '<=')
			->where('g.title', 'Notebook%', 'LIKE')
			->orderBy('g.id', 'ASC');
		$this->assertSame([
			['id' => 1, 'title' => 'Notebook Pro', 'star' => null],
			['id' => 2, 'title' => 'Notebook Adv', 'star' => 5],
		], $select->rows());
		$this->assertSame(
			'SELECT g.id,g.title,m.star FROM goods AS g LEFT JOIN goods_meta AS m ON m.good_id=g.id WHERE g.id <= :gid0 AND g.title LIKE :gtitle0 ORDER BY g.id ASC',
			$select->sql()
		);
		// value is bound by the column type of the main table
		$this->assertSame(SQLITE3_INTEGER, $goods->getColumnType('id', '3'));
	}

	public function testJoinWithoutType()
	{
		$goods = $this->seedGoods();
		$meta = $this->createGoodsMeta();
		$meta->insert(['good_id' => 3, 'star' => 1]);
		$select = $goods->select('g.id')->join('goods_meta', 'goods_meta.good_id=g.id');
		$this->assertSame([['id' => 3]], $select->rows());
		$this->assertSame('SELECT g.id FROM goods AS g JOIN goods_meta ON goods_meta.good_id=g.id', $select->sql());
	}

	public function testJoinedColumnWithoutAliasIsTypedByMainTable()
	{
		$goods = $this->seedGoods();
		$this->createGoodsMeta();
		$select = $goods->select('g.id')->innerJoin('goods_meta AS m', 'm.good_id=g.id')->where('star', 1);
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Column "star" not found in table "goods');
		$select->rows();
	}

	public function testSelectFromSqliteMaster()
	{
		$this->createGoods();
		$rows = $this->db->table('sqlite_master')->select('name')->where('type', 'table')->rows();
		$this->assertSame([['name' => 'goods']], $rows);
	}

	public function testQueryIsLogged()
	{
		$goods = $this->seedGoods();
		$log = $this->enableLog();
		$select = $goods->select()->where('id', 1);
		$select->rows();
		// the first binding of a column loads table columns
		$this->assertSame(['table' => null, 'sql' => 'PRAGMA table_info(goods)'], array_slice($log->all()[0], 0, 2));
		$this->assertSame(['table' => 'goods', 'sql' => 'SELECT * FROM goods WHERE id = :id0', 'time' => $select->executionTime()], $log->all()[1]);
		$this->assertCount(2, $log);
		$select->rows();
		$this->assertCount(3, $log);
	}
}
