<?php
/**
 * Sqlite Database queries collection
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3;

class Queries implements \IteratorAggregate  
{
	protected $items;
	protected $_callback = false;
	
    /**
     * Constructor
	 * @param array $elements
     */
    public function __construct(Array $elements = [])
    {
        $this->items = $elements;
	}
	
    /**
     * Set listen callback
	 * @param callable|bool $callback
	 * @return Queries
     */
    public function listen($callback)
    {
		if (!is_callable($callback) && !is_bool($callback)) {
			throw new \Exception('Invalid callback, must be callable or boolean');
		}
		$this->_callback = $callback;
		return $this;
	}
	
    /**
     * Collection iterator
	 * @return ArrayIterator
     */
	public function getIterator()
	{
		return new \ArrayIterator($this->items);
	}
	
    /**
     * Get count elements
	 * @return integer
     */
    public function count()
    {
		return count($this->items);
	}
	
    /**
     * Get all elements
	 * @return array
     */
    public function all()
    {
		return $this->items;
	}
	
    /**
     * Push new elements
	 * @param mixed $element
	 * @return Colection
     */
    public function push($element)
    {
		if ($this->_callback === false) {
			return $this;
		}
		if (is_callable($this->_callback)) {
			$callback = $this->_callback;
			$callback($element);
		}
		array_push($this->items, $element);
		return $this;
	}
	
    /**
     * Each elements
	 * @param callable $callback (item, key)
	 * @return Collection
     */
    public function each(callable $callback)
    {
		array_walk($this->items, $callback);
		return $this;
	}
	
    /**
     * Sum elements
	 * @param string $key
	 * @return array
     */
    public function sum($key = null)
    {
		$sum = 0;
		if (is_null($key)) {
			return array_sum($this->items);
		}
		foreach	($this->items as $item) {
			$sum+= (float)$item[$key];
		}
		return $sum;
	}
	
    /**
     * Get elements by value
	 * @param mixed $a items element value || key
	 * @param string $b items element value
	 * @return mixed
     */
    public function find($a, $b = null)
    {
		if (is_callable($a) && is_null($b)) {
			return $this->_findCallable($a);
		}
		if (is_null($b)) {
			if (is_callable($a)) {
				return $this->_findCallable($a);
			}
			if (!is_array($a)) {
				$a = [$a];
			}
			return $this->_findArr($a);
		}
		if (is_array($this->first())) {
			return $this->_findArrKey($a, $b);
		}
		return new static();
	}
	
    /**
     * Get elements by value - one array
	 * @param mixed $val items element value
	 * @return mixed
     */
    protected function _findArr($vals)
    {
		$finded = [];
		foreach ($this->items as $item) {
			foreach ($vals as $val) {
				if ($val == $item) {
					$finded[]= $item;
				}
			}
		}
		return new static($finded);
	}
	
    /**
     * Get elements by value - array
	 * @param mixed $val items element value
	 * @return mixed
     */
    protected function _findArrKey($key, $val)
    {
		$finded = [];
		foreach ($this->items as $item) {
			if ($val == $item[$key]) {
				$finded[]= $item;
			}
		}
		return new static($finded);
	}
	
    /**
     * Get elements by callback
	 * @param callable $callback
	 * @return mixed
     */
    protected function _findCallable($callback)
    {
		$finded = [];
		foreach ($this->items as $item) {
			if ($callback($item)) {
				$finded[]= $item;
			}
		}
		return new static($finded);
	}
	
    /**
     * Group collection by key
	 * @param mixed $key items element key
	 * @return Collection
     */
    public function groupBy($key)
    {
		$grouped_items = [];
		foreach ($this->items as $item) {
			if (is_object($item)) {
				$group_value = $item->{$key};
			} else {
				$group_value = $item[$key];
			}
			if (!isset($grouped_items[$group_value])) {
				$grouped_items[$group_value] = [];
			}
			$grouped_items[$group_value][]= $item;
		}
		return new static($grouped_items);
	}
	
    /**
     * Get first elem
	 * @return mixed
     */
    public function first()
    {
		return reset($this->items);
	}
	
    /**
     * Get last elem
	 * @return mixed
     */
    public function last()
    {
		return end($this->items);
	}
	
    /**
     * Get end elem
	 * @return mixed
     */
    public function end()
    {
		return $this->last();
	}
	
	/**
     * Get array data from collection
	 * @return array
     */
    public function toArray()
    {
		$items = [];
		$this->each(function($item, $key) use(&$items) {
			$items[$key]= $item;
		});
		return $items;
	}
}
