<?php

/**
 * Namespace containing controller classes responsible for handling query builder operations.
 */

namespace Webbycrown\QueryBuilder\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Webbycrown\QueryBuilder\Services\ExportService;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class QueryBuilderController extends Controller
{

    protected $conn_key;

    /**
     * Constructor to establish database connection.
     */
    public function __construct()
    {
        $this->conn_key = connect_to_main_db();
    }

    /**
     * Handles listing of repeater query reports.
     * Supports AJAX-based pagination, sorting, and searching.
     */
    public function index(Request $request)
    {

        if ($request->ajax()) {

            // Retrieve datatable request parameters
            $draw = $request->get('draw');
            $start = $request->get("start");
            $rowperpage = $request->get("length"); // Number of records per page

            $columnIndex_arr = $request->get('order');
            $columnName_arr = $request->get('columns');
            $order_arr = $request->get('order');
            $search_arr = $request->get('search');

            // Determine sorting parameters
            $columnIndex = $columnIndex_arr[0]['column']; // Column index
            $columnName = $columnName_arr[$columnIndex]['data']; // Column name
            $columnSortOrder = $order_arr[0]['dir']; // asc or desc
            $searchValue = $search_arr['value']; // Search value

            $database = env('DB_DATABASE');
            // Query the database with filtering and ordering
            $data_arr = DB::connection($this->conn_key)->table('query_forms')->where('database',$database)->orderBy(!is_null($columnName) ? $columnName : 'id', $columnSortOrder)
                ->where( function ($query) use ($searchValue) {
                    $query->where('id', 'like', '%' . strtolower($searchValue) . '%')
                    ->orWhere('title', 'like', '%' . strtolower($searchValue) . '%');
                });

            // Retrieve record counts    
            $totalRecords = $data_arr->count();
            $totalRecordswithFilter = $data_arr->count();

            // Apply pagination
            $data_arr = $data_arr->skip($start)->take($rowperpage)->get();

            // Prepare response for the frontend datatable
            $response = array(
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordswithFilter,
                "aaData" => $data_arr
            );

            return response()->json($response);

        }

        return view( 'wc_querybuilder::query-builders.index' );
    }

    /**
     * Display the query report page.
     *
     * @return \Illuminate\View\View The query report edit view.
     */
    public function add()
    {
        // Initialize query-related variables
        $query_form = null;
        $query_details = [];
        // Fetch the list of tables from the database
        $tables = get_table_list(connect_to_manage_db());

        // Fetch additional table metadata including comments
        $tables_data = get_table_list_with_comment(connect_to_manage_db());

        // Return the view with retrieved data

        return view('wc_querybuilder::query-builders.operation', compact('tables', 'query_form', 'query_details', 'tables_data'));
    }

    /**
     * Edit a query report based on the provided ID.
     */
    public function edit($id)
    {
        // Fetch table lists and metadata
        $tables = get_table_list( connect_to_manage_db() );
        $tables_data = get_table_list_with_comment(connect_to_manage_db());

        // Retrieve the query form record
        $query_form = DB::connection($this->conn_key)->table('query_forms')->where('id', $id)->first();

        // Decode and structure query details
        $inputArray = (array)json_decode( $query_form->query_details );
        $query_details = convert_bracketed_keys_to_array($inputArray);

        // Return view with structured data
        return view('wc_querybuilder::query-builders.operation', compact('tables', 'query_form', 'query_details', 'tables_data'));
    }

    /**
     * View a specific query report by its ID.
     */

    public function view($id)
    {

        // Retrieve query report details
        $query_form = DB::connection($this->conn_key)->table('query_forms')->where('id', $id)->first();
        $query_details = json_encode( json_decode( $query_form->query_details ) );

        return view( 'wc_querybuilder::query-builders.view', compact('query_form', 'query_details') );
    }

    /**
     * Retrieve column details for a given table.
     *
     * @param string $table The name of the table.
     * @return \Illuminate\Http\JsonResponse JSON response with column details.
     */
    public function getColumnsByTable($table)
    {
        $is_join_table = request()?->is_join_table == 'yes' ? 'yes' : 'no';

        // Validate if the table exists to prevent SQL injection
        if (!in_array($table, get_table_list(connect_to_manage_db()))) {
            return response()->json(['error' => 'Invalid table name'], 400);
        }

        // Define query to retrieve column details
        $query = "
            SELECT 
                CONCAT(TABLE_NAME, '.', COLUMN_NAME) as full_name,
                TABLE_NAME as table_name,
                COLUMN_NAME as name,
                COLUMN_COMMENT as comment,
                DATA_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ";

        // Exclude 'id' column for join tables
        if ($is_join_table == 'yes') {
            $query .= " AND COLUMN_NAME != 'id'";
        }

        $query .= " ORDER BY ORDINAL_POSITION";

        
        // Execute query and fetch column details
        $columns = DB::select($query, [get_database_name(connect_to_manage_db()), $table]);
        // Get column comments
        $comments = get_table_columns_comment($table, connect_to_manage_db());
        
        // Return response as JSON
        $response = ['columns' => $columns, 'comments' => $comments];

        return response()->json($response);
    }

    /**
     * Retrieve foreign key relationships for a given table.
     *
     * @param string $table The name of the database table.
     * @return \Illuminate\Http\JsonResponse JSON response containing the table relationships.
     */
    public function getRelationsByTable($table)
    {
        // Fetch foreign key relationships for the specified table
        $relations = get_table_relations($table, connect_to_manage_db());

        // Return the relations as a JSON response
        return response()->json($relations);
    }

    /**
     * Perform a database search based on provided filters, conditions, and joins.
     *
     * @param \Illuminate\Http\Request $request The request containing search parameters.
     * @return \Illuminate\Http\JsonResponse JSON response with search results.
     */
    public function getDataByQueryDetails(Request $request)
    {
        // Retrieve request parameters

        $mainTable = $request->input('main_table');

        $joins = $request->input('joins', []);
        $selectedColumns = $request->input('columns', []);
        $conditions = $request->input('conditions', []);
        $groupByColumns = $request->input('groupby', []);
        $havingColumns = $request->input('having', []);
        $orderByColumns = $request->input('orderby', []);
        $limit = $request->input('limit', 0);
        $offset = $request->input('offset', 0);
        $filters = $request->input('filter', []);
        $format = $request->format;
        if( request()->route()?->getName() == 'api.queries.search'){
            $format = '';
        }
        $page = $request->input('page', 1);
        $perPage = ($limit > 0) ? $limit : $request->input('size', 10);
        $skip = ( $page - 1 ) * $perPage;

        if ($offset > 0) {
            $skip = ($page > 1) ? ($skip + $offset) : $offset;
        }

        try {
            // Initialize query builder
            $query = DB::connection(connect_to_manage_db())->table($mainTable);

           // Apply joins
            $tables = [];
            $tables[] = $mainTable;
            foreach ($joins as $join) {
                if (empty($join['table']) || empty($join['type']) || 
                    empty($join['first_column']) || empty($join['second_column'])) {
                    continue;
                }

                $tables[] = $join['table'];

                $joinType = strtolower($join['type']);
                $method = match($joinType) {
                    'left' => 'leftJoin',
                    'right' => 'rightJoin',
                    'inner' => 'join',
                    default => 'leftJoin'
                };

                $query->$method($join['table'], $join['first_column'], '=', $join['second_column']);
            }

            // Apply conditions
            foreach ($conditions as $condition) {
                if (!empty($condition['column']) && !empty($condition['operator']) && isset($condition['value'])) {
                   $query = generateWhereConditionForOperator( $query, $condition );
                }
            }
           

            // Apply filters
            foreach ($filters as $filter) {
                if (!empty($filter['field']) && !empty($filter['type']) && isset($filter['value'])) {
                    if ($filter['type'] === 'like') {
                        $query->where($filter['field'], 'LIKE', '%' . $filter['value'] . '%');
                    } else {
                        $query->where($filter['field'], $filter['type'], $filter['value']);
                    }
                }
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Fetch table information
            $tableInfo = get_multiple_table_info($tables, connect_to_manage_db());

            // Apply groupBy
            if (!empty($groupByColumns)) {
                $dataForGroup = applyAggregatesToQuery($query, $selectedColumns, $tableInfo, $groupByColumns);

                $selectedColumns = $dataForGroup['selectedColumns'];
                $selectedAlias = $dataForGroup['selectedAlias'];
                $query = $dataForGroup['query'];
    
            }else{

                // Apply column selection
                if (!empty($selectedColumns)) {
                    $query->select($selectedColumns);
                }
            }

            // Apply having
            if (!empty($havingColumns)) {
                $havingColumns = array_filter($havingColumns, function ($condition) {
                    return !empty($condition['column']) && !empty($condition['operator']) && $condition['value'] !== '';
                });
                if( !empty($havingColumns) ){
                    foreach ($havingColumns as $havingColumn) {
                        if (isset($havingColumn['column']) && isset($havingColumn['operator']) && isset($havingColumn['value'])) {
                            
                            $columnName = $havingColumn['column'];
                         // Default to column name
                            $aliasKey = $columnName; 

                            // Find the correct aliasKey (database alias, not label)
                            if (isset($selectedAlias[$columnName]) && !empty($selectedAlias[$columnName])) {
                                $aliasData = $selectedAlias[$columnName];

                                // If multiple aliases exist, choose the first one
                                if (is_array($aliasData) && isset($aliasData[0]['aliasKey'])) {
                                    // Use aliasKey, not aliasLabel
                                    $aliasKey = $aliasData[0]['aliasKey']; 
                                }
                            }

                            // Apply HAVING using aliasKey (which matches the SQL alias)
                            $query->having($aliasKey, $havingColumn['operator'], $havingColumn['value']);
                        }
                    }
                }
            }

            // Apply groupBy
            if (!empty($orderByColumns)) {
                foreach ($orderByColumns as $orderByColumn) {
                    $query->orderBy($orderByColumn['column'], $orderByColumn['order']);
                }
            }

           // Fetch column information based on the selected table and columns
            $columns = get_columns_for_listing($mainTable, $tableInfo, $selectedColumns);

            // Check if the requested format is one of the allowed export formats
            if( in_array($format,['csv','xlsx','pdf','json']) ){
                // Execute the query and retrieve the results
                $results = $query->get();

                // Return the data and column information in JSON format
                return response()->json([
                    'data' => $results,
                    'columns' => $columns,
                ]);
            }

            // Apply pagination logic
            $total = $query->get()->count(); // Get total records before pagination
            $total = (int)$total - (int)$offset; // Ensure total is not negative

            // Get paginated results
            $results = $query->skip($skip)->take($perPage)->get();

            // Return paginated response
            return response()->json([
                'data' => $results,
                'last_page' => (int)ceil($total / $perPage),
                'current_page' => (int)$page,
                'total' => (int)$total,
                'columns' => $columns,
                'tableInfo' => $tableInfo,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Query error',
                'message' => $e->getMessage()
            ], 500);
        }

    }


    /**
    * Export data in various formats (CSV, Excel, PDF, JSON).
    *
    * This function fetches query details based on the provided ID, retrieves the associated data, 
    * and exports it in the requested format. It supports CSV, Excel (XLSX), PDF, and JSON formats.
    *
    * @param \Illuminate\Http\Request $request The request object containing export parameters.
    * 
    * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    * Returns a downloadable file or JSON response in case of errors.
    */
    public function exportData(Request $request)
    {
        try{
            // Retrieve data ID from request, default to 0 if not provided
            $data_id = $request->get('id') ? $request->get('id') : 0;
            $query_title = '';

            // If a valid data ID is provided, fetch query details
            if( $data_id != 0 ){
                $query_form = DB::connection($this->conn_key)->table('query_forms')->where('id', $data_id)->first();
                if ($query_form) {
                    $query_title = $query_form->title;
                    $query_details = json_decode($query_form->query_details, true);

                    // Merge query details into the request object
                    $request->merge($query_details);
                }
            }

            // Get export format, default to CSV if not specified
            $format = $request->get('format', 'csv');
            $request->merge(['format' => $format]);

            // Allowed export formats
            $allowedFormats = ['csv', 'xlsx', 'pdf','json'];

            // Validate the requested format
            if (!in_array($format, $allowedFormats)) {
                return response()->json(['error' => 'Invalid export format'], 200);
            }

            // Fetch data based on query details
            $response = $this->getDataByQueryDetails($request);
            $data = $response->getData(true); // Convert JSON response to array

            // Validate if data is available for export
            if (!isset($data['data']) || empty($data['data'])) {
                return response()->json(['error' => 'No data available for export'], 400);
            }

            $results = $data['data'];
            $columns = $data['columns'];

            // Initialize export service
            $exportService = new ExportService();
            // Handle export based on format
            switch ($format) {
                case 'csv':
                return $exportService->exportCSV($results, $columns, $query_title);
                case 'xlsx':
                return $exportService->exportExcel($results, $columns, $query_title);
                case 'pdf':
                return $exportService->exportPDF($results, $columns, $query_title);
                case 'json':
                return $exportService->exportJSON($results, $columns, $query_title);
                default:
                return response()->json(['error' => 'Unexpected error'], 200);
            }
        } catch (\Exception $e) {
             // Handle any unexpected errors gracefully
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 200);
        }
    }


    /**
     * Save a query builder section based on the provided data.
     *
     * @param \Illuminate\Http\Request $request The request containing form data.
     * @return \Illuminate\Http\JsonResponse JSON response indicating success or failure.
     */
    public function saveQueryDetails(Request $request)
    {

        try {

            $req_data = $request->all();

            $qry_id = ( array_key_exists( 'qry_id', $req_data ) && (int)$req_data[ 'qry_id' ] > 0 ) ? (int)$req_data[ 'qry_id' ] : 0;

            $query_details = array();
            if ( is_array( $req_data ) && array_key_exists( 'query_details', $req_data ) ) {
                $query_details = (array)json_decode( $req_data[ 'query_details' ] );
            }

            // Get database credentials
            $db_data = config( "database.connections.$this->conn_key" );
            $password = $db_data[ 'password' ] ?? null;
            if ( !is_null( $password ) && !empty( $password ) ) {
                $password = base64_encode( mange_db_pass_prefix() . '-v-' . $password );
            }
             // Prepare data for saving
            $store_data = [ 
                'title'             => $req_data[ 'title' ] ?? null,
                'query_details'     => json_encode( $query_details ),
                'host'              => $db_data[ 'host' ] ?? null,
                'port'              => $db_data[ 'port' ] ?? null,
                'database'          => $db_data[ 'database' ] ?? null,
                'username'          => $db_data[ 'username' ] ?? null,
                'password'          => $password,
            ];

            $db_key = connect_to_main_db();
            
            // Insert or update query form
            $if_exists = DB::connection( $db_key )->table( 'query_forms' )->where( 'id', $qry_id )->first();
            if ( $if_exists ) {
                $in_query_forms = DB::connection( $db_key )->table( 'query_forms' )->where( 'id', $qry_id )->update( $store_data );
            } else {
                $qry_id = DB::connection( $db_key )->table( 'query_forms' )->insertGetId( $store_data );
            }

            $query_forms = DB::connection( $db_key )->table( 'query_forms' )->where( 'id', $qry_id )->first();

            session()->flash('success', 'The query details were successfully saved.');

            return response()->json([
                'result'    => true,
                'message'   => 'The query details were successfully saved.',
                'data'      => $query_forms,
            ], 200);
        } catch (\Exception $e) {
                // Improved error handling with better exception catching
            session()->flash('error', 'Failed to save the query details.');
            
            return response()->json([
                'result'    => false,
                'message'   => 'Failed to save the query details.',
                'messages'  => $e->getMessage()
            ], 500);
        }

    }

    /**
     * Delete a query report by its ID.
     * Returns a JSON response indicating success or failure.
     */
    public function delete()
    {

        try {

            // Retrieve ID from request
            $id = request('id', 0);

            // Find the query report in the database
            $query_form = DB::connection($this->conn_key)->table('query_forms')->where('id', $id)->first();

            if ( $query_form ) {
                // Delete the record if found
                DB::connection($this->conn_key)->table('query_forms')->where('id', $id)->delete();

                return response()->json([
                    'result'    => true,
                    'message'   => 'The query report was successfully deleted.',
                ], 200);

            } else {

                return response()->json([
                    'result'    => false,
                    'message'   => 'The query report was not found.',
                ], 200);

            }


        } catch (\Exception $e) {
            return response()->json([
                'result'    => false,
                'message'   => 'Failed to delete the query report.',
                'messages'  => $e->getMessage()
            ], 500);
        }

    }

}
