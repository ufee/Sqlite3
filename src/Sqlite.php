<?php
/**
 * Sqlite class
 * @author Vlad Ionov <vlad@f5.com.ru>
 */
namespace Ufee\Sqlite3;

abstract class Sqlite
{
	protected static $_databases = [];

    /**
     * Database manager
	 * @param string $name
	 * @param array $options
	 * @return Database
     */
    public static function database($name, array $options = [])
    {
		if (!array_key_exists($name, static::$_databases)) {
			static::$_databases[$name] = new Database($name, $options);
		}
		return static::$_databases[$name];
	}
	
    /**
     * Get Sqlite version
	 * @return array
     */
    public static function version()
    {
		return \SQLite3::version();
	}
}
