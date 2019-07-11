<?php
/**
 * Sqlite Select query class
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3\Query;
use Ufee\Sqlite3\Traits;
use Ufee\Sqlite3\Table;

class Select extends Query
{
	use Traits\Conditions;
	
	protected $short;
	protected $distinct;
	protected $join = [];
	protected $having = [];
	protected $group;
	
    /**
	 * Constructor
     * @param Table $table
	 * @param array|string $columns
     */
    public function __construct(Table &$table, $columns = ['*'])
    {
		parent::__construct($table, $columns);
	}
	
    /**
	 * Set distinct
	 * @return Select
     */
    public function distinct()
    {
		$this->distinct = ' DISTINCT';
		return $this;
	}
	
    /**
	 * Set join
	 * @param string $table - b
	 * @param string $on - b.id=a.id
	 * @param string $type - LEFT|INNER
	 * @return Select
     */
    public function join($table, $on, $type = '')
    {
		if (strpos($table, ' ') !== false) {
			$parts = explode(' ', $table);
			$this->as[end($parts)] = reset($parts);
		}
		$this->join[]= [
			'type' => mb_strtoupper($type),
			'table' => $table, 
			'on' => $on
		];
		return $this;
	}
	
    /**
	 * Set left join
	 * @param string $table - b
	 * @param string $on - b.id=a.id
	 * @return Select
     */
    public function leftJoin($table, $on)
    {
		return $this->join($table, $on, 'LEFT ');
	}
	
    /**
	 * Set inner join
	 * @param string $table - b
	 * @param string $on - b.id=a.id
	 * @return Select
     */
    public function innerJoin($table, $on)
    {
		return $this->join($table, $on, 'INNER ');
	}
	
    /**
	 * Set having condition
	 * @param string $column
	 * @param mixed $value
	 * @param string $operator
	 * @return Select
     */
    public function having($column, $value = false, $operator = '=')
    {
		return $this->condition($column, $value, $operator, 'AND', 'having');
	}
	
    /**
	 * Set having OR condition
	 * @param string $column
	 * @param mixed $value
	 * @param string $operator
	 * @return Select
     */
    public function orHaving($column, $value = null, $operator = '=')
    {
		return $this->condition($column, $value, $operator, 'OR', 'having');
	}
	
    /**
	 * Set group by
	 * @param string $columns
	 * @return Select
     */
    public function groupBy($columns)
    {
		$this->group = $columns;
		return $this;
	}
	
    /**
	 * Select rows
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return integer
     */
    public function rows($limit = null, $offset = null)
    {
		$this->limit = $limit;
		$this->offset = $offset;
		$this->execution_time = 0;
		
		$db = $this->table->database();
		$this->sql = "SELECT".$this->distinct.' '.join(',', $this->columns)." FROM ".$this->table->name();
		
		if (!empty($this->join)) {
			$this->sql .= ' AS '.$this->short;
			foreach ($this->join as $join) {
				$this->sql .= ' '.$join['type']."JOIN ".$join['table']." ON ".$join['on'];
			}
		}
		if (!empty($this->where)) {
			$this->sql .= " WHERE ".join(' ', $this->where);
		}
		if (!empty($this->group)) {
			$this->sql .= " GROUP BY ".$this->group;
		}
		if (!empty($this->having)) {
			$this->sql .= " HAVING ".join(' ', $this->having);
		}
		if (!empty($this->order)) {
			$orders = [];
			foreach ($this->order as $column=>$by) {
				$orders[]= $column.' '.$by;
			}
			$this->sql .= " ORDER BY ".join(', ', $orders);
		}
		if (is_numeric($this->limit)) {
			$this->sql .= " LIMIT ".$this->limit;
		}
		if (is_numeric($this->offset)) {
			$this->sql .= " OFFSET ".$this->offset;
		}
		$started = microtime(true);
		$stmt = $db->prepare($this->sql);
		
		foreach ($this->stmt['values'] as $stcol=>$data) {
			$stmt->bindValue($stcol, $data['value'], $this->getColType($data['column'], $data['value']));
		}
		$this->result = $stmt->execute();
		$this->execution_time = number_format(microtime(true)-$started, 12);
		$this->table->pushQuery($this);
		
		if ($db->connection()->lastErrorCode() !== 0) {
			throw new \Exception('Sqlite select error '.$db->connection()->lastErrorCode().': '.$db->connection()->lastErrorMsg());
		}
		$rows = [];
		$this->result->reset();
		while ($row = $this->result->fetchArray(SQLITE3_ASSOC)) {
			$rows[]= $row;
		}
		$this->result->finalize();
		return $rows;
	}
	
    /**
	 * Get query row
	 * @param string|null $column
	 * @return mixed
     */
    public function row($column = null)
    {
		$rows = $this->rows(1, null);
		if (!isset($rows[0])) {
			return null;
		}
		if (!is_null($column)) {
			if (!array_key_exists($column, $rows[0])) {
				throw new \Exception('Sqlite column row not found: '.$column);
			}
			return $rows[0][$column];
		}
		return $rows[0];
	}
	
    /**
	 * Get available rows count
	 * @return integer
     */
    public function count()
    {
		$query = clone $this;
		$query->setColumns('COUNT(*) as rows_count');
		return (int)$query->row('rows_count');
	}
}
