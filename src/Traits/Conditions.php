<?php
/**
 * Sqlite trait
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3\Traits;

trait Conditions
{
	protected $where = [];
	protected $order = [];
	protected $limit;
	protected $offset;
	
    /**
	 * Set where condition
	 * @param string $column
	 * @param mixed $value
	 * @param string $operator
	 * @return static
     */
    public function where($column, $value = false, $operator = '=')
    {
		return $this->condition($column, $value, $operator, 'AND', 'where');
	}
	
    /**
	 * Set where OR condition
	 * @param string $column
	 * @param mixed $value
	 * @param string $operator
	 * @return static
     */
    public function orWhere($column, $value = false, $operator = '=')
    {
		return $this->condition($column, $value, $operator, 'OR', 'where');
	}
	
    /**
	 * Set order by
	 * @param string $column
	 * @param string $by
	 * @return static
     */
    public function orderBy($column, $by = 'DESC')
    {
		$this->order[$column] = mb_strtoupper($by);
		return $this;
	}
	
    /**
	 * Set condition
	 * @param string $column
	 * @param mixed $value
	 * @param string $operator - =|>|<|<=|>=|!=|BETWEEN|IN|LIKE|GLOB...
	 * @param string $pref - AND|OR
	 * @param string $type - where|having
	 * @return static
     */
    protected function condition($column, $value = false, $operator = '=', $pref = 'AND', $type = 'where')
    {
		if ($value === false) {
			$sql = $column;
		} elseif (in_array($operator, ['LIKE', 'NOT LIKE', 'GLOB', 'NOT GLOB'])) {
			$stmcol = $this->getColInx($column);
			$sql = $column.' '.$operator." ".$stmcol;
			$this->stmt['values'][$stmcol] = [
				'value' => $value,
				'column' => $column
			];
		} elseif (in_array($operator, ['BETWEEN', 'NOT BETWEEN'])) {
			$stmcol1 = $this->getColInx($column);
			$stmcol2 = $this->getColInx($column);
			$sql = $column.' '.$operator.' '.$stmcol1.' AND '.$stmcol2;
			$this->stmt['values'][$stmcol1] = [
				'value' => $value[0],
				'column' => $column
			];
			$this->stmt['values'][$stmcol2] = [
				'value' => $value[1],
				'column' => $column
			];
		} elseif (in_array($operator, ['IN', 'NOT IN'])) {
			$ins = [];
			foreach ($value as $in_val) {
				$stmcol = $this->getColInx($column);
				$ins[]= $stmcol;
				$this->stmt['values'][$stmcol] = [
					'value' => $in_val,
					'column' => $column
				];
			}
			$sql = $column.' '.$operator.' ('.join(',', $ins).')';
		} elseif (in_array($operator, ['IS', 'IS NOT'])) {
			$sql = $column.' '.$operator.' NULL';
		} elseif (in_array($operator, ['=', '!=', '>', '>=', '<', '<='])) {
			$stmcol = $this->getColInx($column);
			$sql = $column.' '.$operator.' '.$stmcol;
			$this->stmt['values'][$stmcol] = [
				'value' => $value,
				'column' => $column
			];
		} else {
			throw new \Exception('Invalid '.$type.' operator: '.$operator.' for column: '.$column);
		}
		if (count($this->{$type}) == 0) {
			$this->{$type}[]= $sql;
		} else {
			$this->{$type}[]= $pref.' '.$sql;
		}
		return $this;
	}
}
