<?php
/**
 * Sqlite query class
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3\Query;
use Ufee\Sqlite3\Table;
use Ufee\Sqlite3\Traits;

class Query
{
	use Traits\Stmt;
	
	protected $table;
	protected $sql;
	protected $columns = [];
	protected $result;
	protected $execution_time;
	
    /**
	 * Constructor
     * @param Table $table
	 * @param array|string $columns
     */
    public function __construct(Table &$table, $columns = [])
    {
		$this->table = $table;
		$this->short = mb_substr($table->name(), 0, 1);
		$this->setColumns($columns);
	}
	
    /**
	 * Set columns
	 * @return Query
     */
    public function setColumns($columns = [])
    {
		if (is_string($columns)) {
			$columns = explode(',', $columns);
		}
		$this->columns = array_map('trim', $columns);
		return $this;
	}

    /**
	 * Get result sql
	 * @return string
     */
    public function sql()
    {
		return $this->sql;
	}
	
    /**
	 * Get execution time
	 * @return float
     */
    public function executionTime()
    {
		return $this->execution_time;
	}
}
