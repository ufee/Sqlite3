<?php
namespace Ufee\Sqlite3\Tests;

use Ufee\Sqlite3\Database;
use Ufee\Sqlite3\Query\Query;
use Ufee\Sqlite3\Queries;
use Ufee\Sqlite3\Sqlite;
use Ufee\Sqlite3\Table;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
	/** @var string */
	protected $dir;
	/** @var string */
	protected $path;
	/** @var Database */
	protected $db;

	/** @var bool|null */
	private static $updateDeleteLimit;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir().'/ufee-sqlite3-tests/'.uniqid('', true);
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0775, true);
		}
		$this->path = $this->dir.'/test.db';
		$this->db = Sqlite::database($this->path);
	}

	protected function tearDown(): void
	{
		if ($this->db) {
			$this->db->close();
		}
		$this->db = null;
		static::resetRegistries();
		gc_collect_cycles();
		static::removeDir($this->dir);
	}

	/**
	 * Clear static registries of Sqlite and Database
	 */
	protected static function resetRegistries()
	{
		\Closure::bind(function () {
			static::$_databases = [];
		}, null, Sqlite::class)();
		\Closure::bind(function () {
			static::$_tables = [];
			static::$_queries = [];
		}, null, Database::class)();
	}

	protected static function removeDir($dir)
	{
		if (!is_dir($dir)) {
			return;
		}
		foreach (scandir($dir) as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir.'/'.$item;
			is_dir($path) ? static::removeDir($path) : @unlink($path);
		}
		@rmdir($dir);
	}

	/**
	 * Table "goods" from README
	 * @return Table
	 */
	protected function createGoods()
	{
		$goods = $this->db->table('goods');
		$goods->create([
			'id' => 'INTEGER PRIMARY KEY',
			'category' => 'INTEGER DEFAULT 1',
			'price' => 'REAL DEFAULT 0.00',
			'title' => 'TEXT',
			'hot' => 'INTEGER DEFAULT NULL',
			'created_at' => 'INTEGER DEFAULT (DATETIME(\'now\'))'
		]);
		return $goods;
	}

	/**
	 * Table "goods_meta" from README
	 * @return Table
	 */
	protected function createGoodsMeta()
	{
		$meta = $this->db->table('goods_meta');
		$meta->create([
			'good_id' => 'INTEGER UNIQUE',
			'sale' => 'REAL DEFAULT 0.00',
			'descr' => 'TEXT',
			'star' => 'INTEGER DEFAULT 0',
		]);
		return $meta;
	}

	/**
	 * Goods with ids 1..6
	 * @return Table
	 */
	protected function seedGoods()
	{
		$goods = $this->createGoods();
		$this->db->exec("INSERT INTO goods (id, category, price, title, hot) VALUES
			(1, 2, 7299.5, 'Notebook Pro', NULL),
			(2, 2, 6999.7, 'Notebook Adv', 1),
			(3, 3, 3799.9, 'Mobile Pro', NULL),
			(4, 4, 0, 'TV 1000', 2),
			(5, 4, 0, 'TV 2000', NULL),
			(6, 4, 0, 'TV 3000', 3)");
		return $goods;
	}

	/**
	 * Rows of raw sql
	 * @param string $sql
	 * @return array
	 */
	protected function fetchAll($sql)
	{
		return $this->db->query($sql)->getRows();
	}

	/**
	 * Column values of raw sql
	 * @param string $sql
	 * @return array
	 */
	protected function fetchColumn($sql)
	{
		return array_map('current', $this->fetchAll($sql));
	}

	/**
	 * Run query and return built sql, even if execution failed
	 * @param Query $query
	 * @param callable $run
	 * @return string
	 */
	protected function sqlOf(Query $query, callable $run)
	{
		try {
			$run($query);
		} catch (\Throwable $e) {
		}
		return $query->sql();
	}

	/**
	 * @return Queries
	 */
	protected function enableLog()
	{
		return $this->db->queries()->listen(true);
	}

	protected static function supportsUpdateDeleteLimit()
	{
		if (self::$updateDeleteLimit === null) {
			$db = new \SQLite3(':memory:');
			$db->exec('CREATE TABLE t (id INTEGER)');
			self::$updateDeleteLimit = (bool)@$db->prepare('DELETE FROM t LIMIT 1');
			$db->close();
		}
		return self::$updateDeleteLimit;
	}

	/**
	 * Skip test without SQLITE_ENABLE_UPDATE_DELETE_LIMIT, fail if REQUIRE_UPDATE_DELETE_LIMIT=1 (docker)
	 */
	protected function requireUpdateDeleteLimit()
	{
		if (static::supportsUpdateDeleteLimit()) {
			return;
		}
		$message = 'SQLite is built without SQLITE_ENABLE_UPDATE_DELETE_LIMIT';
		if (getenv('REQUIRE_UPDATE_DELETE_LIMIT')) {
			$this->fail($message);
		}
		$this->markTestSkipped($message);
	}
}
