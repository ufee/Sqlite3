<?php
/**
 * Sqlite Delete query class
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3\Query;
use Ufee\Sqlite3\Traits;
use Ufee\Sqlite3\Table;

class Delete extends Query
{
	use Traits\Conditions;

    /**
	 * Delete rows
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
		$this->sql = "DELETE FROM ".$this->table->name();
		
		if (!empty($this->where)) {
			$this->sql .= " WHERE ".join(' ', $this->where);
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
		$stmt->reset();
		$this->result->finalize();
		return $db->connection()->changes();
	}
	
    /**
	 * Delete row
	 * @return bool
     */
    public function row()
    {
		return (bool)$this->rows(1, null);
	}
}
