<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Database
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * Oracle database driver
 *
 * @see    https://secure.php.net/pdo
 * @since  12.1
 */
class JDatabaseDriverPdooracle extends JDatabaseDriverPdo
{
	/**
	 * The name of the database driver.
	 *
	 * @var    string
	 * @since  12.1
	 */
	public $name = 'pdooracle';

	/**
	 * The type of the database server family supported by this driver.
	 *
	 * @var    string
	 * @since  CMS 3.5.0
	 */
	public $serverType = 'oracle';

	/**
	 * The character(s) used to quote SQL statement names such as table names or field names,
	 * etc.  The child classes should define this as necessary.  If a single character string the
	 * same character is used for both sides of the quoted name, else the first character will be
	 * used for the opening quote and the second for the closing quote.
	 *
	 * @var    string
	 * @since  12.1
	 */
	protected $nameQuote = '"';

	/**
	 * Returns the current dateformat
	 *
	 * @var   string
	 * @since 12.1
	 */
	protected $dateformat;

	/**
	 * Returns the current character set
	 *
	 * @var   string
	 * @since 12.1
	 */
	protected $charset;

	/**
	 * Constructor.
	 *
	 * @param   array  $options  List of options used to configure the connection
	 *
	 * @since   12.1
	 */
	public function __construct($options)
	{
		$options['driver'] = 'oci';
		$options['charset']    = (isset($options['charset'])) ? $options['charset']   : 'AL32UTF8';
		$options['dateformat'] = (isset($options['dateformat'])) ? $options['dateformat'] : 'RRRR-MM-DD HH24:MI:SS';

		if (empty($options['driverOptions']) || !isset($options['driverOptions'][PDO::ATTR_CASE]))
		{
			// Set Field Casing to Lowercase by Default:
			$options['driverOptions'] = array(
				PDO::ATTR_CASE => PDO::CASE_LOWER
			);
		}

		$this->charset = $options['charset'];
		$this->dateformat = $options['dateformat'];

		// Finalize initialisation
		parent::__construct($options);
	}

	/**
	 * Destructor.
	 *
	 * @since   12.1
	 */
	public function __destruct()
	{
		$this->freeResult();
		unset($this->connection);
	}

	/**
	 * Connects to the database if needed.
	 *
	 * @return  void  Returns void if the database connected successfully.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function connect()
	{
		if ($this->connection)
		{
			return;
		}

		parent::connect();

		if (isset($this->options['schema']))
		{
			$this->setQuery('ALTER SESSION SET CURRENT_SCHEMA = ' . $this->quoteName($this->options['schema']))->execute();
		}

		$this->setDateFormat($this->dateformat);
	}

	/**
	 * Disconnects the database.
	 *
	 * @return  void
	 *
	 * @since   12.1
	 */
	public function disconnect()
	{
		// Close the connection.
		$this->freeResult();
		unset($this->connection);
	}

	/**
	 * Copies a table with/without it's data in the database.
	 *
	 * @param   string   $fromTable  The name of the database table to copy from.
	 * @param   string   $toTable    The name of the database table to create.
	 * @param   boolean  $withData   Optionally include the data in the new table.
	 *
	 * @return  JDatabaseDriverOracle  Returns this object to support chaining.
	 *
	 * @since   12.1
	 */
	public function copyTable($fromTable, $toTable, $withData = false)
	{
		$this->connect();

		$fromTable = strtoupper($fromTable);
		$toTable = strtoupper($toTable);

		$query = $this->getQuery(true);

		// Works as a flag to include/exclude the data in the copied table:
		if ($withData)
		{
			$whereClause = ' where 11 = 11';
		}
		else
		{
			$whereClause = ' where 11 = 1';
		}

		$query->setQuery('CREATE TABLE ' . $this->quoteName($toTable) . ' as SELECT * FROM ' . $this->quoteName($fromTable) . $whereClause);

		$this->setQuery($query);

		try
		{
			$this->execute();
		}
		catch (JDatabaseExceptionExecuting $e)
		{
			/**
			* Code 955 is for when the table already exists
			* so we can safely ignore that code and catch any others.
			*/
			if ($e->getCode() !== 955)
			{
				throw $e;
			}
		}

		return $this;
	}

	/**
	 * Drops an entire database (Use with Caution!).
	 *
	 * Note: The IF EXISTS flag is unused in the Oracle driver.
	 *
	 * @param   string   $databaseName  The name of the database table to drop.
	 * @param   boolean  $ifExists      Optionally specify that the table must exist before it is dropped.
	 *
	 * @return  JDatabaseDriver  Returns this object to support chaining.
	 *
	 * @since   12.1
	 */
	public function dropDatabase($databaseName, $ifExists = true)
	{
		$this->connect();

		$databaseName = strtoupper($databaseName);

		$query = $this->getQuery(true)
			->setQuery('DROP USER ' . $this->quoteName($databaseName) . ' CASCADE');

		$this->setQuery($query);

		try
		{
			$this->execute();
		}
		catch (JDatabaseExceptionExecuting $e)
		{
			/**
			* Code 1918 is for when the database doesn't exist
			* so we can safely ignore that code and catch any others.
			*/
			if ($e->getCode() !== 1918)
			{
				throw $e;
			}
		}

		return $this;
	}

	/**
	 * Drops a table from the database.
	 *
	 * Note: The IF EXISTS flag is unused in the Oracle driver.
	 *
	 * @param   string   $tableName  The name of the database table to drop.
	 * @param   boolean  $ifExists   Optionally specify that the table must exist before it is dropped.
	 *
	 * @return  JDatabaseDriverOracle  Returns this object to support chaining.
	 *
	 * @since   12.1
	 */
	public function dropTable($tableName, $ifExists = true)
	{
		$this->connect();

		$tableName = strtoupper($tableName);

		$query = $this->getQuery(true)
			->setQuery('DROP TABLE ' . $this->quoteName($tableName));

		$this->setQuery($query);

		try
		{
			$this->execute();
		}
		catch (JDatabaseExceptionExecuting $e)
		{
			/**
			* Code 942 is for when the table doesn't exist
			* so we can safely ignore that code and catch any others.
			*/
			if ($e->getCode() !== 942)
			{
				throw $e;
			}
		}

		return $this;
	}

	/**
	 * Method to get the database collation in use by sampling a text field of a table in the database.
	 *
	 * @return  mixed  The collation in use by the database or boolean false if not supported.
	 *
	 * @since   12.1
	 */
	public function getCollation()
	{
		return $this->charset;
	}

	/**
	 * Method to get the database connection collation, as reported by the driver. If the connector doesn't support
	 * reporting this value please return an empty string.
	 *
	 * @return  string
	 */
	public function getConnectionCollation()
	{
		return $this->charset;
	}

	/**
	 * Get a query to run and verify the database is operational.
	 *
	 * @return  string  The query to check the health of the DB.
	 *
	 * @since   12.2
	 */
	public function getConnectedQuery()
	{
		return 'SELECT 1 FROM dual';
	}

	/**
	 * Returns the current date format
	 * This method should be useful in the case that
	 * somebody actually wants to use a different
	 * date format and needs to check what the current
	 * one is to see if it needs to be changed.
	 *
	 * @return string The current date format
	 *
	 * @since 12.1
	 */
	public function getDateFormat()
	{
		return $this->dateformat;
	}

	/**
	 * Shows the table CREATE statement that creates the given tables.
	 *
	 * Note: You must have the correct privileges before this method
	 * will return usable results!
	 *
	 * @param   mixed  $tables  A table name or a list of table names.
	 *
	 * @return  array  A list of the create SQL for the tables.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function getTableCreate($tables)
	{
		$this->connect();

		$result = array();
		$type = 'TABLE';
		$query = $this->getQuery(true)
			->select('dbms_metadata.get_ddl(:type, :tableName, :schema)')
			->from('dual')
			->bind(':type', $type);

		// Sanitize input to an array and iterate over the list.
		settype($tables, 'array');

		$defaultSchema = strtoupper($this->options['user']);
		foreach ($tables as $table)
		{
			$table = strtoupper($table);
			$parts = explode('.', $table);

			if (count($parts) === 1)
			{
				$query->bind(':tableName', $table);
				$query->bind(':schema', $defaultSchema);
			}
			elseif (count($parts) === 2)
			{
				$query->bind(':tableName', $parts[1]);
				$query->bind(':schema', $parts[0]);
			}

			$this->setQuery($query);
			$statement = $this->loadResult();

			if (is_resource($statement))
			{
				$statement = stream_get_contents($statement);
			}

			$result[$table] = $statement;
		}

		return $result;
	}

	/**
	 * Retrieves field information about a given table.
	 *
	 * @param   string   $table     The name of the database table.
	 * @param   boolean  $typeOnly  True to only return field types.
	 *
	 * @return  array  An array of fields for the database table.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function getTableColumns($table, $typeOnly = true)
	{
		$this->connect();

		$columns = array();
		$query = $this->getQuery(true);

		$query->select('*');
		$query->from('ALL_TAB_COLUMNS');
		$query->where('table_name = :tableName');

		$prefixedTable = strtoupper(str_replace('#__', $this->tablePrefix, $table));
		$query->bind(':tableName', $prefixedTable);
		$this->setQuery($query);
		$fields = $this->loadObjectList();

		if ($typeOnly)
		{
			foreach ($fields as $field)
			{
				if ($this->useLowercaseFieldNames())
				{
					$columns[strtolower($field->column_name)] = $field->data_type;
				}
				else
				{
					$columns[$field->COLUMN_NAME] = $field->DATA_TYPE;
				}
			}
		}
		else
		{
			foreach ($fields as $field)
			{
				if ($this->useLowercaseFieldNames())
				{
					$columns[strtolower($field->column_name)] = $field;
				}
				else
				{
					$columns[$field->COLUMN_NAME] = $field;
				}
			}
		}

		return $columns;
	}

	/**
	 * Get the details list of keys for a table.
	 *
	 * @param   string  $table  The name of the table.
	 *
	 * @return  array  An array of the column specification for the table.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function getTableKeys($table)
	{
		$this->connect();

		$query = $this->getQuery(true);

		$table = strtoupper($table);
		$query->select('*')
			->from('ALL_CONSTRAINTS NATURAL JOIN ALL_CONS_COLUMNS')
			->where('table_name = :tableName')
			->bind(':tableName', $table);

		$this->setQuery($query);
		$keys = $this->loadObjectList();

		return $keys;
	}

	/**
	 * Method to get an array of all tables in the database (schema).
	 *
	 * @param   string   $databaseName         The database (schema) name
	 * @param   boolean  $includeDatabaseName  Whether to include the schema name in the results
	 *
	 * @return  array    An array of all the tables in the database.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function getTableList($databaseName = null, $includeDatabaseName = false)
	{
		$this->connect();

		$query = $this->getQuery(true);

		if ($includeDatabaseName)
		{
			$query->select('owner, table_name');
		}
		else
		{
			$query->select('table_name');
		}

		$query->from('all_tables');

		if ($databaseName)
		{
			$databaseName = strtoupper($databaseName);
			$query->where('owner = :database')
				->bind(':database', $databaseName);
		}

		$query->order('table_name');

		$this->setQuery($query);

		if ($includeDatabaseName)
		{
			$tables = $this->loadAssocList();
		}
		else
		{
			$tables = $this->loadColumn();
		}

		return $tables;
	}

	/**
	 * Get the version of the database connector.
	 *
	 * @return  string  The database connector version.
	 *
	 * @since   12.1
	 */
	public function getVersion()
	{
		$this->connect();

		$this->setQuery("select value from nls_database_parameters where parameter = 'NLS_RDBMS_VERSION'");

		return $this->loadResult();
	}

	/**
	 * Method to get an array of the result set rows from the database query where each row is an associative array
	 * of ['field_name' => 'row_value'].  The array of rows can optionally be keyed by a field name, but defaults to
	 * a sequential numeric array.
	 *
	 * NOTE: Chosing to key the result array by a non-unique field name can result in unwanted
	 * behavior and should be avoided.
	 *
	 * @param   string  $key     The name of a field on which to key the result array.
	 * @param   string  $column  An optional column name. Instead of the whole row, only this column value will be in
	 * the result array.
	 *
	 * @return  mixed   The return value or null if the query failed.
	 *
	 * @since   11.1
	 * @throws  RuntimeException
	 */
	public function loadAssocList($key = null, $column = null)
	{
		if (!empty($key))
		{
			if ($this->useLowercaseFieldNames())
			{
				$key = strtolower($key);
			}
			else
			{
				$key = strtoupper($key);
			}
		}

		if (!empty($column))
		{
			if ($this->useLowercaseFieldNames())
			{
				$column = strtolower($column);
			}
			else
			{
				$column = strtoupper($column);
			}
		}

		return parent::loadAssocList($key, $column);
	}

	/**
	 * Method to get an array of the result set rows from the database query where each row is an object.  The array
	 * of objects can optionally be keyed by a field name, but defaults to a sequential numeric array.
	 *
	 * NOTE: Choosing to key the result array by a non-unique field name can result in unwanted
	 * behavior and should be avoided.
	 *
	 * @param   string  $key    The name of a field on which to key the result array.
	 * @param   string  $class  The class name to use for the returned row objects.
	 *
	 * @return  mixed   The return value or null if the query failed.
	 *
	 * @since   11.1
	 * @throws  RuntimeException
	 */
	public function loadObjectList($key = '', $class = 'stdClass')
	{
		if (!empty($key))
		{
			if ($this->useLowercaseFieldNames())
			{
				$key = strtolower($key);
			}
			else
			{
				$key = strtoupper($key);
			}
		}

		return parent::loadObjectList($key, $class);
	}

	/**
	 * Select a database for use.
	 *
	 * @param   string  $database  The name of the database to select for use.
	 *
	 * @return  boolean  True if the database was successfully selected.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function select($database)
	{
		$this->connect();

		return true;
	}

	/**
	 * Sets the Oracle Date Format for the session
	 * Default date format for Oracle is = DD-MON-RR
	 * The default date format for this driver is:
	 * 'RRRR-MM-DD HH24:MI:SS' since it is the format
	 * that matches the MySQL one used within most Joomla
	 * tables.
	 *
	 * @param   string  $dateFormat  Oracle Date Format String
	 *
	 * @return boolean
	 *
	 * @since  12.1
	 */
	public function setDateFormat($dateFormat = 'DD-MON-RR')
	{
		$this->connect();

		$this->setQuery("ALTER SESSION SET NLS_DATE_FORMAT = '$dateFormat'");

		if (!$this->execute())
		{
			return false;
		}

		$this->setQuery("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = '$dateFormat'");

		if (!$this->execute())
		{
			return false;
		}

		$this->dateformat = $dateFormat;

		return true;
	}

	/**
	 * Set the connection to use UTF-8 character encoding.
	 *
	 * Returns false automatically for the Oracle driver since
	 * you can only set the character set when the connection
	 * is created.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   12.1
	 */
	public function setUtf()
	{
		return false;
	}

	/**
	 * Locks a table in the database.
	 *
	 * @param   string  $table  The name of the table to unlock.
	 *
	 * @return  JDatabaseDriverOracle  Returns this object to support chaining.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function lockTable($table)
	{
		$table = strtoupper($table);

		$this->setQuery('LOCK TABLE ' . $this->quoteName($table) . ' IN EXCLUSIVE MODE')->execute();

		return $this;
	}

	/**
	 * Renames a table in the database.
	 *
	 * @param   string  $oldTable  The name of the table to be renamed
	 * @param   string  $newTable  The new name for the table.
	 * @param   string  $backup    Not used by Oracle.
	 * @param   string  $prefix    Not used by Oracle.
	 *
	 * @return  JDatabaseDriverOracle  Returns this object to support chaining.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function renameTable($oldTable, $newTable, $backup = null, $prefix = null)
	{
		$this->setQuery('RENAME ' . $oldTable . ' TO ' . $newTable)->execute();

		return $this;
	}

	/**
	 * Unlocks tables in the database.
	 *
	 * @return  JDatabaseDriverOracle  Returns this object to support chaining.
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	public function unlockTables()
	{
		$this->setQuery('COMMIT')->execute();

		return $this;
	}

	/**
	 * Test to see if the PDO ODBC connector is available.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   12.1
	 */
	public static function isSupported()
	{
		return class_exists('PDO') && in_array('oci', PDO::getAvailableDrivers());
	}

	/**
	 * Create a new database using information from $options object, obtaining query string
	 * from protected member.
	 *
	 * For Oracle, it differs compared to MySQL. Instead of creating new databases within
	 * the overall MySQL RDBMS, in Oracle the RDBMS = Database. Within that Database Instance
	 * you can have multiple "schemas" which are equivalent to Oracle Users within the system.
	 * These schemas are basically the same as the different databases that can be created in
	 * MySQL. So here, the db_name provided will be used as the new Oracle USER and db_user
	 * will more or less be ignored. An additional parameter named db_password must be included
	 * in order for the new user to have a password set upon creation.
	 *
	 * @param   stdClass  $options  Object used to pass user and database name to database driver.
	 * 									This object must have "db_name" and "db_password" set for Oracle.
	 * @param   boolean   $utf      True if the database supports the UTF-8 character set.
	 *
	 * @return  JDatabaseDriver  Returns this object to support chaining.
	 *
	 * @since   12.2
	 * @throws  RuntimeException
	 */
	public function createDatabase($options, $utf = true)
	{
		if (is_null($options))
		{
			throw new RuntimeException('$options object must not be null.');
		}
		elseif (empty($options->db_name))
		{
			throw new RuntimeException('$options object must have db_name set.');
		}
		elseif (empty($options->db_password))
		{
			throw new RuntimeException('$options object must have db_password set.');
		}

		$options->db_user = $options->db_name;

		try
		{
			$this->setQuery($this->getCreateDatabaseQuery($options, $utf))->execute();

			$this->setQuery('GRANT create session TO ' . $this->quoteName($options->db_name))->execute();
			$this->setQuery('GRANT create table TO ' . $this->quoteName($options->db_name))->execute();
			$this->setQuery('GRANT create view TO ' . $this->quoteName($options->db_name))->execute();
			$this->setQuery('GRANT create any trigger TO ' . $this->quoteName($options->db_name))->execute();
			$this->setQuery('GRANT create any procedure TO ' . $this->quoteName($options->db_name))->execute();
			$this->setQuery('GRANT create sequence TO ' . $this->quoteName($options->db_name))->execute();
			$this->setQuery('GRANT create synonym TO ' . $this->quoteName($options->db_name))->execute();
		}
		catch (JDatabaseExceptionExecuting $e)
		{
			/**
			* Error 1920 gets thrown when the user already exists:
			*/
			if ($e->getCode() !== 1920)
			{
				throw $e;
			}
		}

		return $this;
	}

	/**
	 * This function replaces a string identifier <var>$prefix</var> with the string held is the
	 * <var>tablePrefix</var> class variable.
	 *
	 * @param   string  $query   The SQL statement to prepare.
	 * @param   string  $prefix  The common table prefix.
	 *
	 * @return  string  The processed SQL statement.
	 *
	 * @since   11.1
	 */
	public function replacePrefix($query, $prefix = '#__')
	{
		$startPos = 0;
		$quoteChar = "'";
		$literal = '';

		$query = trim($query);
		$n = strlen($query);

		while ($startPos < $n)
		{
			$ip = strpos($query, $prefix, $startPos);

			if ($ip === false)
			{
				break;
			}

			$j = strpos($query, "'", $startPos);

			if ($j === false)
			{
				$j = $n;
			}

			$literal .= str_replace($prefix, $this->tablePrefix, substr($query, $startPos, $j - $startPos));
			$startPos = $j;

			$j = $startPos + 1;

			if ($j >= $n)
			{
				break;
			}

			// Quote comes first, find end of quote
			while (true)
			{
				$k = strpos($query, $quoteChar, $j);
				$escaped = false;

				if ($k === false)
				{
					break;
				}

				$l = $k - 1;

				while ($l >= 0 && $query{$l} == '\\')
				{
					$l--;
					$escaped = !$escaped;
				}

				if ($escaped)
				{
					$j = $k + 1;
					continue;
				}

				break;
			}

			if ($k === false)
			{
				// Error in the query - no end quote; ignore it
				break;
			}

			$literal .= substr($query, $startPos, $k - $startPos + 1);
			$startPos = $k + 1;
		}

		if ($startPos < $n)
		{
			$literal .= substr($query, $startPos, $n - $startPos);
		}

		return $literal;
	}

	/**
    * Sets the $tolower variable to true
    * so that field names will be created
    * using lowercase values.
    *
    * @return void
    */
	public function toLower()
	{
		$this->setOption(PDO::ATTR_CASE, PDO::CASE_LOWER);
	}

	/**
	* Sets the $tolower variable to false
	* so that field names will be created
	* using uppercase values.
	*
	* @return void
	*/
	public function toUpper()
	{
		$this->setOption(PDO::ATTR_CASE, PDO::CASE_UPPER);
	}

	/**
	 * Method to commit a transaction.
	 *
	 * @param   boolean  $toSavepoint  If true, commit to the last savepoint.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 * @throws  RuntimeException
	 */
	public function transactionCommit($toSavepoint = false)
	{
		$this->connect();

		if (!$toSavepoint || $this->transactionDepth <= 1)
		{
			parent::transactionCommit($toSavepoint);
		}
		else
		{
			$this->transactionDepth--;
		}
	}

	/**
	 * Method to roll back a transaction.
	 *
	 * @param   boolean  $toSavepoint  If true, rollback to the last savepoint.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 * @throws  RuntimeException
	 */
	public function transactionRollback($toSavepoint = false)
	{
		$this->connect();

		if (!$toSavepoint || $this->transactionDepth <= 1)
		{
			parent::transactionRollback($toSavepoint);
		}
		else
		{
			$savepoint = 'SP_' . ($this->transactionDepth - 1);
			$this->setQuery('ROLLBACK TO SAVEPOINT ' . $this->quoteName($savepoint));

			if ($this->execute())
			{
				$this->transactionDepth--;
			}
		}
	}

	/**
	 * Method to initialize a transaction.
	 *
	 * @param   boolean  $asSavepoint  If true and a transaction is already active, a savepoint will be created.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 * @throws  RuntimeException
	 */
	public function transactionStart($asSavepoint = false)
	{
		$this->connect();

		if (!$asSavepoint || !$this->transactionDepth)
		{
			return parent::transactionStart($asSavepoint);
		}

		$savepoint = 'SP_' . $this->transactionDepth;
		$this->setQuery('SAVEPOINT ' . $this->quoteName($savepoint));

		if ($this->execute())
		{
			$this->transactionDepth++;
		}
	}

	/**
	* Indicates whether to use lowercase
	* field names throughout the class or not.
	*
	* @return bool
	*/
	public function useLowercaseFieldNames()
	{
		$this->connect();

		$mode = $this->connection->getAttribute(PDO::ATTR_CASE);

		return ($mode === PDO::CASE_LOWER);
	}

	/**
	 * Get the query strings to alter the character set and collation of a table.
	 *
	 * @param   string  $tableName  The name of the table
	 *
	 * @return  string[]  The queries required to alter the table's character set and collation
	 *
	 * @since   CMS 3.5.0
	 */
	public function getAlterTableCharacterSet($tableName)
	{
		return array();
	}

	/**
	 * Return the query string to create new User/Database in Oracle.
	 *
	 * For the Oracle drivers, db_user is ignored and db_name is the main field
	 * that is used. Simply set db_user to be the same as db_name when passing in
	 * the $options object.
	 *
	 * Optionally, you may also include the "db_default_tablespace" and "db_temporary_tablespace"
	 * attributes and those will be used when creating the user (these must already be created in
	 * the Oracle RDBMS before being used!). A quota for the permanent tablespace may also be optionally set
	 * using "db_default_tablespace_quota".
	 *
	 * @param   stdClass  $options  Object used to pass user and database name to database driver.
	 *                   This object must have "db_name" and "db_user" set.
	 * @param   boolean   $utf      True if the database supports the UTF-8 character set.
	 *
	 * @return  string  The query that creates database
	 *
	 * @since   12.2
	 */
	protected function getCreateDatabaseQuery($options, $utf)
	{
		$options->db_name = strtoupper($options->db_name);
		$options->db_user = $options->db_name;

		$defaultPermanentTablespaceQuery = "select PROPERTY_VALUE
											  from database_properties
											  where property_name = 'DEFAULT_PERMANENT_TABLESPACE'";

		$defaultTemporaryTablespaceQuery = "select PROPERTY_VALUE
											  from database_properties
											  where property_name = 'DEFAULT_TEMP_TABLESPACE'";

		$defaultPermanentTablespace = $this->setQuery($defaultPermanentTablespaceQuery)->loadResult();
		$defaultTemporaryTablespace = $this->setQuery($defaultTemporaryTablespaceQuery)->loadResult();

		// Set Tablespace Options with defaults if needed:
		$options->db_default_tablespace = (isset($options->db_default_tablespace)) ? $options->db_default_tablespace : $defaultPermanentTablespace;
		$options->db_temporary_tablespace = (isset($options->db_temporary_tablespace)) ? $options->db_temporary_tablespace : $defaultTemporaryTablespace;

		// Set Tablespace Quota Options with defaults if needed:
		$options->db_default_tablespace_quota = (isset($options->db_default_tablespace_quota)) ? $options->db_default_tablespace_quota : 'UNLIMITED';

		// Setup the clauses to be added into the query:
		$defaultTablespaceClause = ' DEFAULT TABLESPACE ' . $this->quoteName($options->db_default_tablespace);
		$temporaryTablespaceClause = ' TEMPORARY TABLESPACE ' . $this->quoteName($options->db_temporary_tablespace);
		$defaultTablespaceQuotaClause = ' QUOTA  ' . $options->db_default_tablespace_quota . ' ON ' . $this->quoteName($options->db_default_tablespace);

		return 'CREATE USER ' . $this->quoteName($options->db_name) .
					' IDENTIFIED BY ' . $this->quoteName($options->db_password) .
					$defaultTablespaceClause .
					$temporaryTablespaceClause .
					$defaultTablespaceQuotaClause;
	}

	/**
	 * Return the actual SQL Error number
	 *
	 * @return  integer  The SQL Error number
	 *
	 * @since   3.4.6
	 */
	protected function getErrorNumber()
	{
		// The SQL Error Information
		$errorInfo = $this->connection->errorInfo();

		// Error Number Info is Actually Contained Here:
		if (isset($errorInfo[1]) && is_int($errorInfo[1]))
		{
			return $errorInfo[1];
		}

		// Fallback option (less reliable info):
		return (int) $this->connection->errorCode();
	}
}
