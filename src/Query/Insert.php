<?php
/**
 * Sqlite Insert query class
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3\Query;
use Ufee\Sqlite3\Traits;
use Ufee\Sqlite3\Table;

class Insert extends Query
{
	protected $or;
	protected $values;
	
    /**
	 * Set or conflict
	 * @return Insert
     */
    public function orRollback()
    {
		$this->or = ' OR ROLLBACK';
		return $this;
	}
	
    /**
	 * Set or conflict
	 * @return Insert
     */
    public function orAbort()
    {
		$this->or = ' OR ABORT';
		return $this;
	}
	
    /**
	 * Set or conflict
	 * @return Insert
     */
    public function orFail()
    {
		$this->or = ' OR FAIL';
		return $this;
	}
	
    /**
	 * Set or conflict
	 * @return Insert
     */
    public function orIgnore()
    {
		$this->or = ' OR IGNORE';
		return $this;
	}
	
    /**
	 * Set or conflict
	 * @return Insert
     */
    public function orRreplace()
    {
		$this->or = ' OR REPLACE';
		return $this;
	}
	
    /**
	 * Insert rows
	 * @param array $rows
	 * @return bool
     */
    public function rows(array $rows)
    {
		$this->values = $rows;
		$this->execution_time = 0;
		$db = $this->table->database();
		$this->sql = "INSERT".$this->or." INTO ".$this->table->name()." (".join(', ', $this->columns).")";
		$sql_items = [];
		$stmt_values = [];
		
		foreach ($rows as $row) {
			$sql_item = [];
			foreach ($row as $k=>$value) {
				$stmcol = $this->getColInx($this->columns[$k]);
				$sql_item[]= $stmcol;
				$stmt_values[$stmcol] = [
					'value' => $value,
					'column' => $this->columns[$k]
				];
			}
			$sql_items[]= "(".join(', ', $sql_item).")";
		}
		$this->sql .= " VALUES ".join(', ', $sql_items);
		
		$started = microtime(true);
		$stmt = $db->prepare($this->sql);
		
		foreach ($stmt_values as $stcol=>$data) {
			$stmt->bindValue($stcol, $data['value'], $this->getColType($data['column'], $data['value']));
		}
		$this->result = $stmt->execute();
		$this->execution_time = number_format(microtime(true)-$started, 12);
		$this->table->pushQuery($this);
		
		if ($db->connection()->lastErrorCode() !== 0) {
			throw new \Exception('Sqlite insert error '.$db->connection()->lastErrorCode().': '.$db->connection()->lastErrorMsg());
		}
		$stmt->reset();
		$this->stmt['index'] = [];
		$this->result->finalize();
		return $db->connection()->changes() === count($rows);
	}
	
   /**
	 * Insert row
	 * @param array $row
	 * @return bool|integer
     */
    public function row(array $row)
    {
		if ($this->rows([$row])) {
			if ($last_id = $this->table->database()->connection()->lastInsertRowID()) {
				return $last_id;
			}
			return true;
		}
		return false;
	}
}
