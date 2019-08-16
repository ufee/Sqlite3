<?php
/**
 * Sqlite Database class
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3;

class Database
{
	protected $_db;
	protected $name;
	protected $options = [
		'flags' => SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE,
		'encryption_key' => null,
		'busy_timeout' => 30,
		'journal_mode' => 'WAL',
		'synchronous' => 'NORMAL',
		'exceptions' => true
	];
	protected static $_tables = [];
	protected static $_queries = [];
	
    /**
	 * Constructor
	 * @param string $name
	 * @param array $options
     */
    public function __construct($name, array $options = [])
    {
		$this->name = $name;
		$this->options = array_merge($this->options, $options);
		static::$_tables[$this->name] = [];
		static::$_queries[$this->name] = new Queries();
	}
	
    /**
     * Get db name
	 * @return string
     */
    public function name()
    {
		return $this->name;
	}
	
    /**
     * Has db exists
	 * @return bool
     */
    public function exists()
    {
		try {
			$db = new \SQLite3($this->name, SQLITE3_OPEN_READONLY, $this->options['encryption_key']);
			$result = $db->querySingle('SELECT * FROM sqlite_master');
			if (is_null($result)) {
				return true;
			}
			return (bool)$result;
		} catch (\Throwable $e) {
			return false;
		}
	}
	
    /**
     * Has db file exists
	 * @return bool
     */
    public function fileExists()
    {
		return file_exists($this->name);
	}
	
    /**
     * Create db
	 * @return bool
     */
    public function create()
    {
		if ($this->exists()) {
			throw new \Exception('Sqlite database "'.$this->name.'" exists');
		}
		$dir = '';
		$file = $this->name;
		if (strpos($this->name, '/') !== false) {
			$path = explode('/', $this->name);
			$file = array_pop($path);
			$dir = implode('/', $path).'/';
			if (!file_exists($dir) && !mkdir($dir, 0775, true)) {
				throw new \Exception('Sqlite database create error: unable to create dir: '.$dir);
			}
			@chmod($dir, 0775);
		}
		return file_put_contents($dir.$file, '');
	}
	
    /**
     * Tables in db
	 * @return array
     */
    public function tables()
    {
		$tables = [];
		$select = $this->table('sqlite_master')
			->select()
			->where('type', 'table');
		$rows = $select->rows();
		foreach ($rows as $row) {
			$tables[$row['name']] = $row;
		}
		return $tables;
	}
	
    /**
     * Table manager
	 * @param string $name
	 * @return Table
     */
    public function table($name)
    {
		if (!array_key_exists($name, static::$_tables[$this->name])) {
			static::$_tables[$this->name][$name] = new Table($this, $name);
		}
		return static::$_tables[$this->name][$name];
	}
	
    /**
     * Has db opened
	 * @return bool
     */
    public function hasOpened()
    {
		return (bool)$this->_db;
	}
	
    /**
     * Db open
	 * @return bool|Database
     */
    public function open()
    {
		try {
			$this->_db = new \SQLite3($this->name, $this->options['flags'], $this->options['encryption_key']);
		} catch(\Exception $e) {
			throw new \Exception('Sqlite db '.$this->name.' open error '.$e->getCode().': '.$e->getMessage());
		}
		if ($this->hasOpened()) {
			$this->_db->enableExceptions($this->options['exceptions']);
			$this->_db->busyTimeout($this->options['busy_timeout']*1000);
			$this->pragma('journal_mode', $this->options['journal_mode']);
			$this->pragma('synchronous', $this->options['synchronous']);
			return $this;
		}
		return false;
    }
	
    /**
     * Get db connection
	 * @return SQLite3
     */
    public function connection()
    {
		if (!$this->hasOpened()) {
			$this->open();
		}
		return $this->_db;
	}
	
    /**
     * Set pragma values
	 * @param string $key
	 * @param string $val
	 * @return bool
     */
    public function pragma($key, $val)
    {
		return $this->connection()->exec('PRAGMA '.$key.'='.$val.';');
	}
	
    /**
     * Exec query
	 * @param string $query
	 * @return Result
     */
    public function query($query)
    {
		$started = microtime(true);
		$resource = $this->connection()->query($query);
		$this->queries()->push([
			'table' => null,
			'sql' => $query,
			'time' => number_format(microtime(true)-$started, 12)
		]);
		if ($this->connection()->lastErrorCode() !== 0) {
			throw new \Exception('Sqlite query error '.$this->connection()->lastErrorCode().': '.$this->connection()->lastErrorMsg());
		}
		return new Query\Result($resource);
	}
	
    /**
     * Single query
	 * @param string $query
	 * @param bool $entire
	 * @return array
     */
    public function single($query, $entire = true)
    {
		$started = microtime(true);
		$result = $this->connection()->querySingle($query, $entire);
		$this->queries()->push([
			'table' => null,
			'sql' => $query,
			'time' => number_format(microtime(true)-$started, 12)
		]);
		return $result;
	}
	
    /**
     * Exec command
	 * @param string $command
	 * @return bool
     */
    public function exec($command)
    {
		$started = microtime(true);
		$result = $this->connection()->exec($command);
		$this->queries()->push([
			'table' => null,
			'sql' => $command,
			'time' => number_format(microtime(true)-$started, 12)
		]);
		return $result;
	}
	
    /**
     * Prepare query
	 * @param string $sql
	 * @return SQLite3Stmt
     */
    public function prepare($sql)
    {
		return $this->connection()->prepare($sql);
	}
	
    /**
     * Transaction begin
	 * @param string $type - DEFERRED|IMMEDIATE|EXCLUSIVE
	 * @param string $name
	 * @return Database
     */
    public function transactionBegin($type = 'DEFERRED', $name = '')
    {
		$this->exec('BEGIN '.trim($type.' TRANSACTION '.$name));
		return $this;
	}
	
    /**
     * Transaction commit
	 * @param string $name
	 * @return Database
     */
    public function transactionEnd($name = '')
    {
		$this->exec('END '.trim('TRANSACTION '.$name));
		return $this;
	}
	
    /**
     * Transaction commit
	 * @param string $name
	 * @return Database
     */
    public function transactionCommit($name = '')
    {
		$this->exec('COMMIT '.trim('TRANSACTION '.$name));
		return $this;
	}
	
    /**
     * Transaction rollback
	 * @param string $name
	 * @return Database
     */
    public function transactionRollback($name = '')
    {
		$this->exec('ROLLBACK '.trim('TRANSACTION '.$name));
		return $this;
	}
	
    /**
     * Get db queries
	 * @return Queries
     */
    public function queries()
    {
		return static::$_queries[$this->name];
	}
	
    /**
     * Close db connection
     */
    public function close()
    {
		if ($this->hasOpened()) {
			$this->connection()->close();
		}
	}
	
    /**
     * Destructor
     */
    public function __destruct()
    {
		$this->close();
	}
}
