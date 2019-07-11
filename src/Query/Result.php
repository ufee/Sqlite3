<?php
/**
 * Sqlite query result class
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3\Query;

class Result
{
	protected $_resource;
	
    /**
	 * Constructor
     * @param SQLite3Result $resource
     */
    public function __construct(\SQLite3Result $resource)
    {
		$this->_resource = $resource;
	}
	
    /**
	 * Get rows count
	 * @return integer
     */
    public function count()
    {
		$i = 0;
		$this->_resource->reset();
		while ($this->_resource->fetchArray()) {
			$i++;
		}
		$this->_resource->reset();
		return $i;
	}
	
    /**
	 * Get rows
	 * @param integer $mode
	 * @return array
     */
    public function getRows($mode = SQLITE3_ASSOC)
    {
		$rows = [];
		while ($row = $this->_resource->fetchArray(SQLITE3_ASSOC)) {
			$rows[]= $row;
		}
		return $rows;
	}
	
    /**
	 * Get rows count
	 * @return integer
     */
    public function numColumns()
    {
		return $this->_resource->numColumns();
	}
}
