<?php
/**
 * Sqlite Table class
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3;

class Table
{
	const STMT_TYPES = [
		'INT' => SQLITE3_INTEGER,
		'INTEGER' => SQLITE3_INTEGER,
		'FLOAT' => SQLITE3_FLOAT,
		'REAL' => SQLITE3_FLOAT,
		'TEXT' => SQLITE3_TEXT,
		'RAW' => SQLITE3_BLOB,
		'BLOB' => SQLITE3_BLOB,
		'NULL' => SQLITE3_NULL
	];
	protected $database;
	protected $name;
	protected $auto_types = true;
	protected $columns;
	protected $column_types = [];
	
	
    /**
	 * Constructor
	 * @param Database $database
	 * @param string $name
     */
    public function __construct(Database &$database, $name)
    {
		$this->database = $database;
		$this->name = $name;
	}

    /**
     * Get table name
	 * @return string
     */
    public function name()
    {
		return $this->name;
	}
	
    /**
     * Get table db
	 * @return Database
     */
    public function database()
    {
		return $this->database;
	}
	
    /**
     * Get table columns
	 * @param string|null $name
	 * @return array
     */
    public function columns($name = null)
    {
		if (is_null($this->columns)) {
			$result = $this->database->query('PRAGMA table_info('.$this->name.')');
			$rows = $result->getRows();
			foreach ($rows as $row) {
				$this->columns[$row['name']] = $row;
			}
		}
		if (!is_null($name)) {
			if (!array_key_exists($name, $this->columns)) {
				return null;
			}
			return $this->columns[$name];
		}
		return $this->columns;
	}
	
    /**
     * Set stmt type of column
	 * @param string $name
	 * @param mixed $value
	 * @return Table
     */
    public function setColumnType($name, $type)
    {
		$type = mb_strtoupper($type);
		if (!array_key_exists($type, static::STMT_TYPES)) {
			throw new \Exception('Invalid columt type: "'.$type.'", not found in STMT_TYPES');
		}
		$this->column_types[$name] = static::STMT_TYPES[$type];
		return $this;
	}
	
    /**
     * Get stmt type of column
	 * @param string $name
	 * @param mixed $value
	 * @return integer
     */
    public function getColumnType($name, $value)
    {
		if (!isset($this->column_types[$name])) {
			if (!$column = $this->columns($name)) {
				throw new \Exception('Column "'.$name.'" not found in table "'.$this->name);
			}
			if (!$type = mb_strtoupper($column['type'])) {
				if (is_null($value)) {
					$type = 'NULL';
				} else if (is_float($value)) {
					$type = 'REAL';
				} else if (is_int($value)) {
					$type = 'INTEGER';
				}
			}
			if (array_key_exists($type, static::STMT_TYPES)) {
				$this->column_types[$name] = static::STMT_TYPES[$type];
			} else {
				$this->column_types[$name] = static::STMT_TYPES['TEXT'];
			}
		}
		return $this->column_types[$name];
	}
	
    /**
     * Get table info
	 * @param string|null $key
	 * @return string
     */
    public function info($key = null)
    {
		$select = $this->database->table('sqlite_master')
			->select()
			->where('type', 'table')
			->where('name', $this->name);
		return $select->row($key);
	}
	
    /**
     * Has table exists
	 * @return bool
     */
    public function exists()
    {
		$select = $this->database->table('sqlite_master')
			->select('COUNT(*) as count')
			->where('type', 'table')
			->where('name', $this->name);
		return (bool)$select->row('count');
	}
	
    /**
     * Create table
	 * @param array $columns - name OR name->type, types: [NULL, INTEGER, REAL, TEXT, BLOB]
	 * @return bool
     */
    public function create(array $columns = [])
    {
		if (empty($columns)) {
			throw new \Exception('Sqlite create table "'.$this->name.'" error: empty columns');
		}
		$structure = [];
		foreach ($columns as $key=>$val) {
			$column = $val;
			if (is_string($key)) {
				$column = $key.' '.$val;
			}
			$structure[]= $column;
		}
		return $this->database->exec('CREATE TABLE '.$this->name.' ('.join(',', $structure).')');
	}
	
    /**
     * Drop table
	 * @return bool
     */
    public function drop()
    {
		return $this->database->exec('DROP TABLE '.$this->name);
	}
	
    /**
     * Insert query
	 * @param string|array $columns
	 * @return mixed
     */
    public function insert($columns = [])
    {
		if (empty($columns)) {
			throw new \Exception('Sqlite table "'.$this->name.'" insert error: empty columns');
		}
		if (is_array($columns)) {
			$keys = array_keys($columns);
			$values = array_values($columns);
			if (isset($columns[0]) && is_array($columns[0])) {
				throw new \Exception('Insert multi unavailable in short call, use insert -> rows');
			}
			if (is_string($keys[0])) {
				$insert = new Query\Insert($this, array_keys($columns));
				return $insert->row($values);
			}
		}
		return new Query\Insert($this, $columns);
	}
	
    /**
     * Select query
	 * @param string|array $columns
	 * @return Query\Select
     */
    public function select($columns = ['*'])
    {
		return new Query\Select($this, $columns);
	}
	
    /**
     * Update query
	 * @param string|array $columns
	 * @return Query\Update
     */
    public function update($columns = [])
    {
		if (empty($columns)) {
			throw new \Exception('Sqlite table "'.$this->name.'" update error: empty columns');
		}
		return new Query\Update($this, $columns);
	}
	
    /**
     * Delete query
	 * @return Query\Delete
     */
    public function delete()
    {
		return new Query\Delete($this);
	}
	
    /**
     * Get queries
	 * @return Queries
     */
    public function queries()
    {
		return $this->database->queries()->find('table', $this->name);
	}
	
    /**
     * Push sql query
	 * @param Query $query
	 * @return Table
     */
    public function pushQuery(Query\Query $query)
    {
		$this->database->queries()->push([
			'table' => $this->name,
			'sql' => $query->sql(),
			'time' => $query->executionTime()
		]);
		return $this;
	}
}
