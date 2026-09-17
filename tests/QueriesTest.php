<?php
namespace Ufee\Sqlite3\Tests;

use Ufee\Sqlite3\Queries;

class QueriesTest extends \PHPUnit\Framework\TestCase
{
	private function items()
	{
		return [
			['table' => 'goods', 'sql' => 'SELECT 1', 'time' => '0.5'],
			['table' => 'meta', 'sql' => 'SELECT 2', 'time' => '0.25'],
			['table' => 'goods', 'sql' => 'SELECT 3', 'time' => '1'],
		];
	}

	public function testPushIsIgnoredUntilListen()
	{
		$queries = new Queries();
		$this->assertSame($queries, $queries->push(['sql' => 'SELECT 1']));
		$this->assertCount(0, $queries);
	}

	public function testListenTrueStoresElements()
	{
		$queries = (new Queries())->listen(true);
		$queries->push(['sql' => 'SELECT 1'])->push(['sql' => 'SELECT 2']);
		$this->assertSame([['sql' => 'SELECT 1'], ['sql' => 'SELECT 2']], $queries->all());
	}

	public function testListenCallableReceivesElementAndStoresIt()
	{
		$received = [];
		$queries = (new Queries())->listen(function ($data) use (&$received) {
			$received[] = $data;
		});
		$queries->push(['sql' => 'SELECT 1']);
		$this->assertSame([['sql' => 'SELECT 1']], $received);
		$this->assertCount(1, $queries);
	}

	public function testListenFalseStopsStoring()
	{
		$queries = (new Queries())->listen(true);
		$queries->push(1);
		$queries->listen(false)->push(2);
		$this->assertSame([1], $queries->all());
	}

	public function testListenInvalidCallbackThrows()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid callback, must be callable or boolean');
		(new Queries())->listen('not_existing_function_name');
	}

	public function testCountAndIterator()
	{
		$queries = new Queries($this->items());
		$this->assertCount(3, $queries);
		$this->assertInstanceOf(\ArrayIterator::class, $queries->getIterator());
		$this->assertSame($this->items(), iterator_to_array($queries));
	}

	public function testFindByKeyAndValue()
	{
		$found = (new Queries($this->items()))->find('table', 'goods');
		$this->assertInstanceOf(Queries::class, $found);
		$this->assertSame(['SELECT 1', 'SELECT 3'], array_column($found->all(), 'sql'));
	}

	public function testFindByKeyOnScalarCollectionReturnsEmpty()
	{
		$found = (new Queries([1, 2, 3]))->find('table', 'goods');
		$this->assertCount(0, $found);
	}

	public function testFindByValueAndValues()
	{
		$queries = new Queries([1, 2, 3, 2]);
		$this->assertSame([2, 2], $queries->find(2)->all());
		$this->assertSame([1, 3], $queries->find([1, 3])->all());
	}

	public function testFindByCallable()
	{
		$found = (new Queries($this->items()))->find(function ($item) {
			return $item['time'] < 1;
		});
		$this->assertSame(['SELECT 1', 'SELECT 2'], array_column($found->all(), 'sql'));
	}

	public function testSum()
	{
		$this->assertSame(1.75, (new Queries($this->items()))->sum('time'));
		$this->assertSame(6, (new Queries([1, 2, 3]))->sum());
	}

	public function testGroupBy()
	{
		$grouped = (new Queries($this->items()))->groupBy('table');
		$this->assertSame(['goods', 'meta'], array_keys($grouped->all()));
		$this->assertCount(2, $grouped->all()['goods']);

		$objects = new Queries([(object)['type' => 'a'], (object)['type' => 'b'], (object)['type' => 'a']]);
		$this->assertCount(2, $objects->groupBy('type')->all()['a']);
	}

	public function testFirstLastEnd()
	{
		$queries = new Queries([1, 2, 3]);
		$this->assertSame(1, $queries->first());
		$this->assertSame(3, $queries->last());
		$this->assertSame(3, $queries->end());
		$this->assertFalse((new Queries())->first());
	}

	public function testEachAndToArray()
	{
		$queries = new Queries(['a' => 1, 'b' => 2]);
		$seen = [];
		$this->assertSame($queries, $queries->each(function ($item, $key) use (&$seen) {
			$seen[$key] = $item;
		}));
		$this->assertSame(['a' => 1, 'b' => 2], $seen);
		$this->assertSame(['a' => 1, 'b' => 2], $queries->toArray());
	}
}
