<?php
/**
 * Sqlite trait
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3\Traits;

trait Stmt
{
	protected $as = [];
	protected $stmt = [
		'values' => [],
		'index' => []
	];
	
    /**
	 * Set table AS name
	 * @param string $short - table name
	 * @return static
     */
    public function short($short)
    {
		$this->short = $short;
		$this->as[$short] = $this->table->name();
		return $this;
	}
	
    /**
	 * Get stmt column indexed name
	 * @param string $column
	 * @return string
     */
    protected function getColInx($column)
    {
		$stcol = str_replace('.', '', $column);
		if (!isset($this->stmt['index'][$stcol])) {
			$this->stmt['index'][$stcol] = 0;
		}
		return ':'.$stcol.$this->stmt['index'][$stcol]++;
	}
	
    /**
	 * Get stmt column type
	 * @param string $column
	 * @param mixed $value
	 * @return integer
     */
    protected function getColType($column, $value)
    {
		$table = $this->table;
		if (strpos($column, '.') !== false) {
			$parts = explode('.', $column);
			$column = $parts[1];
			$table_name = $parts[0];
			if (array_key_exists($table_name, $this->as)) {
				$table_name = $this->as[$table_name];
			}
			$table = $table->database()->table($table_name);
		}
		return $table->getColumnType($column, $value);
	}
}
