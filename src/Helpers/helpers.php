<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;


/**
 * Helper function for testing.
 * This function simply returns a string confirming that helper functions are working.
 */
function text_helpers()
{
	return 'Helper functions testing successfully completed.';
}


/**
 * Get database password prefix.
 * This function returns a predefined string used as a prefix for database passwords.
 */
function mange_db_pass_prefix()
{
	return 'QBDM';
}

/**
 * Configure a dynamic database connection.
 * This function sets up a new database connection dynamically based on provided configuration.
 *
 * @param string $connectionName The name of the connection to configure.
 * @param array $config An array containing database connection details such as host, port, username, etc.
 */
function connect_to_database($connectionName, $config)
{
    // Add the dynamic database connection
    config([
        "database.connections.$connectionName" => [
            'driver'    => 'mysql',
            'host'      => $config['host'],
            'port'      => $config['port'],
            'database'  => $config['database'],
            'username'  => $config['username'],
            'password'  => $config['password'],
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ],
    ]);

}

/**
 * Connect to the manage database.
 * Captures request environment variables and sets up a connection for the manage database.
 *
 * @return string The name of the configured database connection.
 */
function connect_to_manage_db()
{
	$connectionName = 'manage_db';

	$request = Request::capture();

	connect_to_database($connectionName, [
		'host'     => $request->server('QDB_HOST'),
		'port'     => $request->server('QDB_PORT'),
		'database' => $request->server('QDB_DATABASE'),
		'username' => $request->server('QDB_USERNAME'),
		'password' => $request->server('QDB_PASSWORD'),
	]);

	return $connectionName;
}

/**
 * Connect to the main database.
 * Captures request environment variables and sets up a connection for the main database.
 *
 * @return string The name of the configured database connection.
 */
function connect_to_main_db()
{
	$connectionName = 'mysql';

	$request = Request::capture();

	connect_to_database($connectionName, [
        'host'     => $request->server('DB_HOST'),
        'port'     => $request->server('DB_PORT'),
        'database' => $request->server('DB_DATABASE'),
        'username' => $request->server('DB_USERNAME'),
        'password' => $request->server('DB_PASSWORD'),
    ]);

	return $connectionName;
}

/**
 * Retrieve the name of the database for a given connection.
 * This function returns null if the database name cannot be determined.
 *
 * @param string $connectionName The database connection name.
 * @return string|null The name of the database or null if not found.
 */
function get_database_name($connectionName = 'mysql')
{
	$database = null;
	try {
		$database = DB::connection($connectionName)->getDatabaseName();
		if ( is_null( $database ) || empty( $database ) ) {
			$database = null;
		}
	} catch (\Exception $e) {
		$database = null;
	}
	return $database;
}

/**
 * Get the table prefix of the current database connection.
 * Returns null if there is no prefix.
 *
 * @return string|null The table prefix or null if not found.
 */
function get_table_prefix()
{
	$prefix = null;
	try {
		$prefix = DB::getTablePrefix();
		if ( is_null( $prefix ) || empty( $prefix ) ) {
			$prefix = null;
		}
	} catch (\Exception $e) {
		$prefix = null;
	}
	return $prefix;
}

/**
 * List of database tables that should be skipped (ignored).
 *
 * @return array List of table names to be excluded from operations.
 */
function get_skip_tables()
{
	return [
		'cache',
		'cache_locks',
		'failed_jobs',
		'job_batches',
		'jobs',
		'migrations',
		'password_reset_tokens',
		'personal_access_tokens',
		'sessions',
		'query_forms',
	];
}

/**
 * Retrieve a list of tables from a database connection, excluding the skipped tables.
 *
 * @param string $connectionName The database connection name.
 * @return array List of table names.
 */
function get_table_list($connectionName = 'mysql')
{
	$tables = [];

	try {
		$tables = DB::connection($connectionName)->select('SHOW TABLES');
	} catch (Exception $e) {
		if ( $databaseName = get_database_name($connectionName) ) {
			$tables = DB::connection($connectionName)->select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', [$databaseName]);
		}
	}

	$tables = array_map(function($table) {
		return reset($table);
	}, $tables);

	$tables = array_values( array_diff( $tables, get_skip_tables() ) );

	return $tables;
}

/**
 * Retrieve table comments from the database schema.
 *
 * @param string $connectionName The database connection name.
 * @return array List of tables with their respective comments.
 */
function get_table_list_with_comment($connectionName = 'mysql')
{
	$tables = [];

	if ( $databaseName = get_database_name($connectionName) ) {
		$tables = DB::connection($connectionName)->select('SELECT table_name As table_name, table_comment AS table_comment FROM information_schema.tables WHERE table_schema = ?', [$databaseName]);
	}

	$tables = collect($tables)->keyBy('table_name');
	$tables = ( $tables && count($tables) > 0 ) ? $tables->toarray() : [];
	return $tables;
}

/**
 * Retrieve column comments for a given table.
 *
 * @param string $table The table name.
 * @param string $connectionName The database connection name.
 * @return array|null Array of column comments or null if not found.
 */
function get_table_columns_comment($table, $connectionName = 'mysql')
{
	$comments = null;
	if ( !is_null($table) && !empty($table) ) {
		$tableInfo = DB::select("
			SELECT 
			COLUMN_NAME as name,
			COLUMN_COMMENT as comment
			FROM information_schema.COLUMNS 
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
			", [get_database_name($connectionName), $table]
		);

		$comments = collect($tableInfo)->keyBy('name');
	}

	return $comments;
}

/**
 * Retrieve column comments for a given table.
 *
 * @param string $table The table name.
 * @param string $connectionName The database connection name.
 * @return array|null Array of column comments or null if not found.
 */
function get_table_info($table, $connectionName = 'mysql')
{
	$comments = [];
	if ( !is_null($table) && !empty($table) ) {
		$tableInfo = DB::select("
			SELECT 
			COLUMN_NAME as name,
			COLUMN_COMMENT as comment,
			TABLE_NAME as table_name
			FROM information_schema.COLUMNS 
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
			ORDER BY ORDINAL_POSITION ASC
			", [get_database_name($connectionName), $table]
		);

		if ( $tableInfo && count( $tableInfo ) > 0 ) {
			foreach ( $tableInfo as $tblInfo) {
				$tblInfoKey = $tblInfo->table_name . '.' .$tblInfo->name;
				$comments[ $tblInfoKey ] =  ( !is_null( $tblInfo?->comment ) && !empty( $tblInfo?->comment ) ) ? $tblInfo->comment : $tblInfoKey;
			}
		}
	}

	return $comments;
}

/* -------------------------------------------------------------------------------------------------------------------------------------------------- */
/**
 * Get multiple table information.
 */
function get_multiple_table_info($tables = [], $connectionName = 'mysql')
{
	$tableInfo = [];

	if ( is_array( $tables ) && count( $tables ) > 0 ) {
		foreach ( $tables as $table ) {
			$tableInfoDetails = get_table_info($table, $connectionName);
			if ( is_array( $tableInfoDetails ) && count( $tableInfoDetails ) > 0 ) {
				$tableInfo = array_merge($tableInfo, $tableInfoDetails);
			}
		}
	}

	return $tableInfo;
}

/**
 * Get columns mappings for listing.
 *
 * This function processes table column information and prepares an array of columns
 * that can be used for display purposes, including sorting and filtering options.
 *
 * @param string|null $mainTable The main table name.
 * @param array $tableInfo An array containing column comments or metadata.
 * @param array $selectedColumns An array of selected columns to be used instead of full table info.
 * @return array An array of formatted column data.
 */
function get_columns_for_listing($mainTable = null, $tableInfo = [], $selectedColumns = [])
{
	$columns = $selected_columns = $columnsInfo = [];

	// Ensure the main table is not null or empty
	if ( !is_null( $mainTable ) && !empty( $mainTable ) ) {

		 // If selected columns are provided, store them in a key-value format
		if ( is_array( $selectedColumns ) && count( $selectedColumns ) > 0 ) {
			foreach ( $selectedColumns as $scol_key => $scol_value ) {
				$selected_columns[ $scol_value ] = $scol_key;
			}
		}

		// Use selected columns if available; otherwise, fallback to table info
		if ( is_array( $selected_columns ) && count( $selected_columns ) > 0 ) {
			$columnsInfo = $selected_columns;
		} elseif ( is_array( $tableInfo ) && count( $tableInfo ) > 0 ) {
			$columnsInfo = $tableInfo;
		}
		
		 // Process each column and structure the output array
		if ( is_array( $columnsInfo ) && count( $columnsInfo ) > 0 ) {
			foreach ( $columnsInfo as $field => $title ) {
				// Split the field into table name and column name (if prefixed)
				$field_split = explode('.', $field);

				$field_table = ( is_array( $field_split ) && count( $field_split ) > 0 ) ? $field_split[0] : null;
				$field_name = ( is_array( $field_split ) && count( $field_split ) > 1 ) ? $field_split[1] : null;

				if( is_null($field_name) || empty($field_name) ){
					$field_name = $field_table;
				}

				// Determine if the column should be merged based on the ID check
				$column_merge_flag = ( $field_name == 'id' && $field_table != $mainTable ) ? false : true;
				if ( $column_merge_flag ) {
					$columns[] = [
						'field' => $field_name,   // Column name
                        'title' => $title,        // Display title (from comments or selected columns)
                        'sorter' => 'string',     // Default sorting type
                        'headerSort' => true,     // Enable sorting in UI
                        'headerFilter' => 'input' // Enable filtering
					];
				}
			}
		}

	}

	return $columns;
}

/**
 * Retrieve table relationships from the database schema.
 *
 * This function queries the `information_schema.KEY_COLUMN_USAGE` table to retrieve 
 * foreign key relationships for a given table, along with table and column comments.
 *
 * @param string $table The name of the table for which relationships are retrieved.
 * @param string $connectionName The name of the database connection (default is 'mysql').
 * @return array|null Returns an array of relationships if found, otherwise null.
 */
function get_table_relations($table, $connectionName = 'mysql')
{
	
	$relations = null;
	// Ensure the table name is provided and not empty
	if (!is_null($table) && !empty($table)) {
		$relations = DB::select("
			SELECT 
			IF(kcu.TABLE_NAME = ?, kcu.TABLE_NAME, kcu.REFERENCED_TABLE_NAME) AS table_name,
			IF(kcu.TABLE_NAME = ?, kcu.COLUMN_NAME, kcu.REFERENCED_COLUMN_NAME) AS column_name,
			IF(kcu.TABLE_NAME = ?, kcu.REFERENCED_TABLE_NAME, kcu.TABLE_NAME) AS referenced_table,
			IF(kcu.TABLE_NAME = ?, kcu.REFERENCED_COLUMN_NAME, kcu.COLUMN_NAME) AS referenced_column,

            -- Get table comments
            (SELECT t.TABLE_COMMENT 
            	FROM information_schema.tables t
            	WHERE t.TABLE_SCHEMA = ? AND t.TABLE_NAME = kcu.TABLE_NAME) AS table_comment,

            (SELECT t.TABLE_COMMENT 
            	FROM information_schema.tables t
            	WHERE t.TABLE_SCHEMA = ? AND t.TABLE_NAME = kcu.REFERENCED_TABLE_NAME) AS referenced_table_comment,

            -- Get column comments
            (SELECT c.COLUMN_COMMENT 
            	FROM information_schema.columns c
            	WHERE c.TABLE_SCHEMA = ? AND c.TABLE_NAME = kcu.TABLE_NAME AND c.COLUMN_NAME = kcu.COLUMN_NAME) AS column_comment,

            (SELECT c.COLUMN_COMMENT 
            	FROM information_schema.columns c
            	WHERE c.TABLE_SCHEMA = ? AND c.TABLE_NAME = kcu.REFERENCED_TABLE_NAME AND c.COLUMN_NAME = kcu.REFERENCED_COLUMN_NAME) AS referenced_column_comment

            FROM information_schema.KEY_COLUMN_USAGE kcu
            WHERE 
            kcu.REFERENCED_TABLE_SCHEMA = ? AND 
            (kcu.TABLE_NAME = ? OR kcu.REFERENCED_TABLE_NAME = ?) AND
            kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ", [
            	$table, $table, $table, $table,
            	get_database_name($connectionName), get_database_name($connectionName),
            	get_database_name($connectionName), get_database_name($connectionName),
            	get_database_name($connectionName), $table, $table
            ]);
	}

	return $relations;


}

/**
 * Arrange records column comments.
 *
 * This function modifies an array of selected columns by appending alias information
 * using the provided column comments.
 *
 * @param array $selectedColumns Array of selected columns with their comments.
 * @return array Modified array of selected columns with alias formatting.
 */
function arrange_records_column_comment($selectedColumns)
{
	$selected_columns = $selectedColumns;
	if ( isset( $selectedColumns ) && is_array( $selectedColumns ) && count( $selectedColumns ) > 0 ) {
		foreach ($selectedColumns as $comment => $selectedColumn) {
			if ( isset( $comment ) && !empty( $comment ) && !is_null( $comment ) ) {
				$selected_columns[ $comment ] = $selectedColumn . ' AS ' . $comment;
			}
		}
	}

	return $selected_columns;
}

/**
 * Convert bracketed keys into a nested array structure.
 *
 * This function processes an input array where keys might contain bracketed notation
 * (e.g., "joins[table][column]") and converts them into a proper nested array.
 *
 * @param array $inputArray The array with bracketed keys.
 * @return array The converted nested array.
 */
function convert_bracketed_keys_to_array($inputArray)
{
	$query_details = [];

    foreach ($inputArray as $key => $value) {
        if (preg_match('/^([^\[]+)\[(.+)\]$/', $key, $matches)) {
            $baseKey = $matches[1]; // e.g., "joins" or "columns"
            $nestedKeys = explode('][', trim($matches[2], '[]')); // Extract keys inside brackets

            // Build nested array dynamically
            $current = &$query_details[$baseKey];
            foreach ($nestedKeys as $nestedKey) {
                if (!isset($current[$nestedKey])) {
                    $current[$nestedKey] = [];
                }
                $current = &$current[$nestedKey];
            }
            $current = $value; // Assign value to the final nested key
        } else {
            // If the key has no brackets, assign it directly
            $query_details[$key] = $value;
        }
    }

	return $query_details;
}

/**
 * Generate an array of SQL operators for use in a dropdown or query selection.
 *
 * @param string $selectedOperator (Optional) The operator to be pre-selected.
 * @return array Returns an associative array of SQL operators.
 */
function generateSqlOperators($selectedOperator = '') {
    // Define a list of SQL operators with additional notes for each operator
    $operators = [
        "=" => [
            'key' => "=",
            'value' => "=",
            'notes' => "Find customers whose country is 'USA'."
        ],               // Equal to
        "<" => [
            'key' => "<",
            'value' => "<",
            'notes' => "Find products priced below 50."
        ],               // Less than
        ">" => [
            'key' => ">",
            'value' => ">",
            'notes' => "Find employees older than 30."
        ],               // Greater than
        "<=" => [
            'key' => "<=",
            'value' => "<=",
            'notes' => "Find orders with a quantity of 5 or less."
        ],             // Less than or equal to
        ">=" => [
            'key' => ">=",
            'value' => ">=",
            'notes' => "Find students with grades 90 or above."
        ],             // Greater than or equal to
        "!=" => [
            'key' => "!=",
            'value' => "!=",
            'notes' => "Find customers who are NOT from 'USA'."
        ],             // Not equal to
        "LIKE" => [
            'key' => "LIKE",
            'value' => "LIKE",
            'notes' => "Find customers whose name starts with 'A'."
        ],         // Pattern matching (case-sensitive)
        "LIKE%%" => [
            'key' => "LIKE%%",
            'value' => "LIKE%%",
            'notes' => "Find customers whose name contains 'John'."
        ],     // Possible typo? Should be "LIKE" with wildcards
        "REGEXP" => [
            'key' => "REGEXP",
            'value' => "REGEXP",
            'notes' => "Find emails ending in .com or .net."
        ],     // Regular expression matching
        "IN" => [
            'key' => "IN",
            'value' => "IN",
            'notes' => "Find employees in departments 1, 2, or 3."
        ],             // Matches any value in a list
        "FIND_IN_SET" => [
            'key' => "FIND_IN_SET",
            'value' => "FIND_IN_SET",
            'notes' => "Find users with role 'admin' in a comma-separated list."
        ], // Checks if a value exists in a comma-separated list
        "IS NULL" => [
            'key' => "IS NULL",
            'value' => "IS NULL",
            'notes' => "Find customers who haven't provided an email."
        ],   // Checks if a value is NULL
        "NOT LIKE" => [
            'key' => "NOT LIKE",
            'value' => "NOT LIKE",
            'notes' => "Find customers whose name does NOT start with 'A'."
        ], // Negated pattern matching
        "NOT REGEXP" => [
            'key' => "NOT REGEXP",
            'value' => "NOT REGEXP",
            'notes' => "Find emails that do NOT end in .com or .net."
        ], // Negated regular expression matching
        "NOT IN" => [
            'key' => "NOT IN",
            'value' => "NOT IN",
            'notes' => "Find employees NOT in departments 1, 2, or 3."
        ],     // Matches any value NOT in a list
        "IS NOT NULL" => [
            'key' => "IS NOT NULL",
            'value' => "IS NOT NULL",
            'notes' => "Find customers who have an email address."
        ], // Checks if a value is NOT NULL
        "SQL" => [
            'key' => "SQL",
            'value' => "SQL",
            'notes' => "Depends on implementation, used for advanced filtering"
        ],            // Possible placeholder for raw SQL conditions
    ];
    
    return $operators; // Return the array of operators
}


function generateWhereConditionForOperator( $query, $condition ){
	switch ($condition['operator']) {
            case 'LIKE':
                $query->where($condition['column'], 'LIKE', '%' . $condition['value'] . '%');
                break;

            case 'LIKE%%': // Custom LIKE for double wildcards
                $query->where($condition['column'], 'LIKE', '%%' . $condition['value'] . '%%');
                break;

            case 'IN':
                $values = explode(',', $condition['value']); // Convert string to array
                $query->whereIn($condition['column'], $values);
                break;

            case 'NOT IN':
                $values = explode(',', $condition['value']);
                $query->whereNotIn($condition['column'], $values);
                break;

            case 'IS NULL':
                $query->whereNull($condition['column']);
                break;

            case 'IS NOT NULL':
                $query->whereNotNull($condition['column']);
                break;

            case 'FIND_IN_SET': // FIND_IN_SET equivalent
                $query->whereRaw("FIND_IN_SET(?, {$condition['column']})", [$condition['value']]);
                break;

            case 'REGEXP':
                $query->where($condition['column'], 'REGEXP', $condition['value']);
                break;

            case 'NOT REGEXP':
                $query->where($condition['column'], 'NOT REGEXP', $condition['value']);
                break;

            default:
                $query->where($condition['column'], $condition['operator'], $condition['value']);
                break;
        }

        return $query;
}

function applyAggregatesToQuery($query, $selectedColumns, $tableInfo, $groupByColumns) {
    $groupByFields = [];

    $selectedColumnsForSelect = (is_array($selectedColumns) && count($selectedColumns) > 0) ? $selectedColumns : array_flip($tableInfo);

    foreach ($groupByColumns as $groupByColumn) {
    	if (!empty($groupByColumn['column'])) {

    		$isAlias = (!is_null($groupByColumn['alias']) && !empty($groupByColumn['alias']));

    		$tableColumnKey = explode('.',$groupByColumn['column']);
    		$columnsWithComment = get_table_columns_comment($tableColumnKey[0], 'manage_db');
    		$columnsWithComment = $columnsWithComment->toarray();
    		$groupByComment = $columnsWithComment[$tableColumnKey[1]]->comment;

    		if (!empty($groupByColumn['aggregation'])) {

    			$aliasKey = strtolower(str_replace('.', '_', "{$groupByColumn['aggregation']}_{$groupByColumn['column']}"));
    			$aliaslabel = ucwords(str_replace('_', ' ', $aliasKey));
    			$aliaslabel = (!is_null($groupByColumn['alias']) && !empty($groupByColumn['alias'])) ? $groupByColumn['alias'] : $aliaslabel;

    			$isAggregationFlag = false;
    			
    			switch (strtoupper($groupByColumn['aggregation'])) {
    				
    				case 'SUM':
	    				$selectedColumns[] = DB::raw("SUM({$groupByColumn['column']}) AS {$aliasKey}");
	    				$isAggregationFlag = true;
    				break;

    				case 'GROUP_CONCAT':
	    				$selectedColumns[] = DB::raw("GROUP_CONCAT({$groupByColumn['column']}) AS {$aliasKey}");
	    				$isAggregationFlag = true;
    				break;
    			}

    			if ($isAggregationFlag) {
    				$selectedColumnsForSelect[$aliaslabel] = $aliasKey;
    				if( array_key_exists($groupByComment, $selectedColumns) ){
    					unset($selectedColumns[$groupByComment]);
    				}
    				if( array_key_exists($groupByComment, $selectedColumnsForSelect) ){
    					unset($selectedColumnsForSelect[$groupByComment]);
    				}
    			}

    		}else{
    			$aliaslabel = $isAlias ? $groupByColumn['alias'] : $groupByComment;

    			$aliasKey = $isAlias ? ucwords(str_replace(' ', '_', $aliaslabel)) : $groupByColumn['column'];

    			$selectedColumns[] = $isAlias ? "{$groupByColumn['column']} AS {$aliasKey}" : $groupByColumn['column'];

    			$groupByFields[] = $isAlias ? $aliasKey : $groupByColumn['column'];

				if ($isAlias) {
    				if( array_key_exists($groupByComment, $selectedColumnsForSelect) ){
    					unset($selectedColumnsForSelect[$groupByComment]);
    				}
					$selectedColumnsForSelect[$aliaslabel] = $aliasKey;
				}
    		}


    	}
    }

    if (!empty($selectedColumns)) {
    	$query->select($selectedColumns);
    }

    // Ensure GROUP BY includes all non-aggregated columns
    if (!empty($groupByFields)) {
        $query->groupBy($groupByFields); // Use spread operator for Laravel compatibility
    }
    $selectedColumns = $selectedColumnsForSelect;
    
     return [
        'query' => $query,
        'selectedColumns' => $selectedColumns
    ];
}

/**
 * Retrieve and format the setting selection option from the environment configuration.
 *
 * This function fetches the 'SETTING_SELECT_OPTION' value from the environment variables,
 * converts it to lowercase to ensure consistency, and capitalizes the first letter 
 * before returning it.
 *
 * @return string The formatted setting selection option.
 */
function getLabelMode() {
    // Retrieve the setting option from the environment variables
    $env_option = env('QDB_LABEL_MODE');

    // Assigns the default value 'Both' if $env_option is null or empty
    if (is_null($env_option) || empty($env_option)) {
    	$env_option = 'Both';
    }

    // Convert the option to lowercase for consistency and capitalize the first letter
    return ucfirst(strtolower($env_option));
}



/* -------------------------------------------------------------------------------------------------------------------------------------------------- */