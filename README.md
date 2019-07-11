## Sqlite3 PHP Class
Класс для работы с Sqlite3

## Установка

```
composer require ufee/Sqlite3
```

## Структура
Объект БД
```php
\Ufee\Sqlite3\Database;
$db = \Ufee\Sqlite3\Sqlite::database(string $path, array $options = [
	'flags' => SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE,
	'encryption_key' => null,
	'busy_timeout' => 15,
	'journal_mode' => 'WAL',
	'synchronous' => 'NORMAL',
	'exceptions' => true
]);
```
Объект Таблица
```php
\Ufee\Sqlite3\Table;
$table = $db->table(string $name);
```
Объект Запрос
```php
\Ufee\Sqlite3\Query\Insert;
$insert = $table->insert(array $columns);

\Ufee\Sqlite3\Query\Select;
$select = $table->insert(array $columns);

\Ufee\Sqlite3\Query\Update;
$update = $table->insert(array $columns);

\Ufee\Sqlite3\Query\Delete;
$delete = $table->insert();
```
Объект Коллекция Запросов
```php
\Ufee\Sqlite3\Queries;
$queries = $db->queries();
$queries = $table->queries();
```

## Работа с классом
Получение объекта БД
```php
$db = Sqlite::database('path/to/file.db');

$temp_db_path = tempnam(sys_get_temp_dir(), 'Sqlite3');
$db = Sqlite::database($temp_db_path);

$db = Sqlite::database(':memory:');
```
Проверка на существование и создание
```php
if (!$db->exists()) {
	$db->create();
}
```
Выполнение произвольных запросов и команд
```php
$result = $db->query($query); // return Query\Result
$rows = $result->getRows($mode = SQLITE3_ASSOC);

$result = $db->single($query, $entire = true); // return array
$result = $db->exec($command); // return bool
$result = $db->pragma($key, $val); // return bool
```
Закрытие соединения с БД
```php
$db->close();
```
Получение объекта Таблицы
```php
$tables = $db->tables();
$table = $db->table('test_table');
```
Проверка на существование и создание
```php
if (!$table->exists()) {
	// с указанием типов данных
	$table->create([
		'id' => 'INTEGER PRIMARY KEY', 
		'amount' => 'REAL', 
		'data1' => 'TEXT', 
		'data2' => 'BLOB', 
		'other' => 'INTEGER DEFAULT 5'
	]);
	// или без привязки к типу данных
	$table->create([
		'id, 
		'data', 
		'other'
	]);
}
```
Получение информации о таблице
```php
	$info = $table->info($key = null);
	// [type, name, tbl_name, rootpage, sql]
```
Получение информации о столбцах
```php
	$culumns = $table->columns($name = null);
	// [name => [cid, name, type, notnull, dflt_value, pk]]
```
Задать свой тип данных столбца для последующих запросов
```php
$table->setColumnType(string $name, string $type); 
// integer, real, text, blob, null
$table->setColumnType('amount', 'integer');
$table->setColumnType('data', 'text');
```
Удаление таблицы
```php
$table->drop();
```
Отладка запросов 
```php
$db->queries()->listen(function($data) {
	echo 'Table: '.$data['table']."\n";
	echo '  Sql: '.$data['sql']."\n";
	echo ' Time: '.$data['time']."\n";
});
```
Запросы выполняются с использованием подготовленных выражений (автоматически)
Операторы условий: [=|>|<|<=|>=|!=|BETWEEN|NOT BETWEEN|IN|NOT IN|LIKE|NOT LIKE|GLOB|NOT GLOB]


## Запрос Insert
```php
$insert = $table->insert('id, category, data')
	->or($val); // OLLBACK|ABORT|FAIL|IGNORE|REPLACE
$insert->rows(array $rows); // return bool
// or
$insert->row(array $row); // return bool|integer
```
Вставка одной строки
```php
$increment_id = $table->insert('category, data')->row([$category, $data]);
// or
$increment_id = $table->insert([
	'category' => $category, 
	'data' => $data
]);
```
Вставка нескольких строк
```php
$insert = $table->insert('category, data');
$increment_id1 = $insert->row([$category1, $data1]);
$increment_id2 = $insert->row([$category2, $data2]);
// or
$insert = $table->insert('category, data');
$result = $insert->rows([
	[$category1, $data1],
	[$category2, $data2]
]);
```

## Запрос Select
```php
$select = $table->select()
	->distinct() // for unique rows
	->where($column, $value = false, $operator = '=')
	->orWhere($column, $value = false, $operator = '=')
	->as($short_table_name)
	->join($table, $on, $type = '')
	->leftJoin($table, $on)
	->innerJoin($table, $on)
	->groupBy($columns)
	->having($column, $value = false, $operator = '=')
	->orHaving($column, $value = false, $operator = '=')
	->orderBy($column, $by = 'DESC');
$count = $select->count(); // return integer
$row = $select->row($column = null); // return array row or string|integer column value
// or
$rows = $select->rows($limit = null, $offset = null); // return array
```
Произвольное условие (без подготовленных выражений)
```php
$select->where('id != ssid OR ...')
$select->having('id > ssid AND ...')
```
Получение количества строк
```php
$select = $table->select('COUNT(id) as count');
$count = $select->row('count');
```
Получение строк и их количества с учетом условий
```php
$select = $table->select()
	->where('category', 123)
	->orderBy('id');
$count = $select->count();
$rows = $select->rows();
```
Получение одной строки
```php
$select = $table->select()
	->where('category', 123)
	->orderBy('id');
$row = $select->row();
```
Получение одного значения из одной строки
```php
$select = $table->select('id')
	->where('category', 123)
	->orderBy('id');
$id = $select->row('id');
```
Получение с использованием JOIN
```php
$select = $table->select('p.id, p.category, b.data as post_data, b.author_id')
	->as('p')->innerJoin('posts_data AS b', 'b.post_id=p.id')
	->where('p.category', 5)
	->where('b.rate', 3, '>')
	->groupBy('b.author_id')
	->orderBy('b.rate');
$count = $select->count();
$rows = $select->rows();
```

## Запрос Update
```php
$update = $table->update('category, data')
	->where($column, $value = false, $operator = '=')
	->orWhere($column, $value = false, $operator = '=')
	->orderBy($column, $by = 'DESC')
	->set([$category, $data]);
$update->rows($limit = null, $offset = null); return integer changed rows
// or
$update->row(); return bool
```

## Запрос Delete
```php
$delete = $table->delete()
	->where($column, $value = false, $operator = '=')
	->orWhere($column, $value = false, $operator = '=')
	->orderBy($column, $by = 'DESC');
$delete->rows($limit = null, $offset = null); return integer changed rows
// or
$delete->row(); return bool
```
Удалние одной строки
```php
$delete = $table->delete()
	->where('category', 123)
	->orderBy('id');
$result = $delete->row();
```
Удалние нескольких строк
```php
$delete = $table->delete()
	->where('category', 123)
	->orderBy('id');
$deleted_part = $delete->rows(15);
$deleted_part = $delete->rows(15, 15);
$deleted_part = $delete->rows(15, 30);
```

## Транзакции
```php
// DEFERRED|IMMEDIATE|EXCLUSIVE
$table->database()->transactionBegin($type = 'DEFERRED', $name = '');
$table->database()->transactionCommit($name = '');
$table->database()->transactionRollback($name = '');
$table->database()->transactionEnd($name = '');
```
Вставка с использованием транзакции
```php
$table->database()->transactionBegin('IMMEDIATE');
$table->insert('category, data')
	->row([$category1, $data1])
	->row([$category2, $data2])
	->row([$category3, $data3]);
$table->database()->transactionCommit();
```
