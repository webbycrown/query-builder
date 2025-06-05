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
use Illuminate\Support\Facades\Validator;
use Webbycrown\QueryBuilder\Services\ExportService;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateScheduledReportsConroller extends Controller
{

    protected $conn_key;

    /**
     * Constructor to establish database connection.
     */
    public function __construct()
    {   
        $this->conn_key = connect_to_main_db();
    }

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
            $data_arr = DB::connection($this->conn_key)->table('scheduled_reports')->where('database',$database)->orderBy(!is_null($columnName) ? $columnName : 'id', $columnSortOrder)
                ->where( function ($query) use ($searchValue) {
                    $query->where('id', 'like', '%' . strtolower($searchValue) . '%')
                    ->orWhere('report_type', 'like', '%' . strtolower($searchValue) . '%');
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
    	 return view('wc_querybuilder::scheduling-reports.index');
    }

    public function add(Request $request)
    {   
        $scheduled_reports = null;
        $database = env('DB_DATABASE');
        $query_forms = DB::connection($this->conn_key)->table('query_forms')->where('database',$database)->select('id','title')->get();
        return view('wc_querybuilder::scheduling-reports.schedule',compact('scheduled_reports','query_forms'));
    }

    public function edit($id){
        $database = env('DB_DATABASE');
        $scheduled_reports = DB::connection($this->conn_key)->table('scheduled_reports')->where('id',$id)->first();
         $query_forms = DB::connection($this->conn_key)->table('query_forms')->where('database',$database)->select('id','title')->get();
        return view('wc_querybuilder::scheduling-reports.schedule',compact('scheduled_reports','query_forms'));
    }

    public function storeScheduledReport(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'report_type'   => 'required',
            'frequency'     => 'required|in:daily,weekly,monthly',
            'time'          => 'required|date_format:H:i',
            'email'         => 'required|email',
            'cc_email'      => 'nullable|string',
            'bcc_email'     => 'nullable|string',
            'subject'       => 'nullable|string|max:255',
            'body'          => 'nullable|string',
            'format'        => 'required|in:pdf,xlsx,csv',
            'record_limit'  => 'nullable|integer|min:1',
            'active'        => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false,'message' => $validator->errors()], 200);
        }

        $database = env('DB_DATABASE');

        $scheduled_report_id = $request->get('id') ? $request->get('id') : 0;

        $store_arr = [
            'report_type'   => $request->get('report_type'),
            'frequency'     => $request->get('frequency'),
            'time'          => $request->get('time'),
            'email'         => $request->get('email'),
            'cc_email'      => $request->get('cc_email') ?? null,
            'bcc_email'     => $request->get('bcc_email') ?? null,
            'subject'       => $request->get('subject') ?? 'Scheduled Report',
            'body'          => $request->get('body') ?? 'Please find your report attached.',
            'format'        => $request->get('format'),
            'record_limit'  => $request->get('record_limit') ?? 2000,
            'database'      => $database,
            'active'        => $request->get('active') ?? 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];

        $if_exists = DB::connection( $this->conn_key )->table( 'scheduled_reports' )->where( 'id', $scheduled_report_id )->first();
        if( $if_exists ){
            $originalData = (array) $if_exists; // Cast to array for logging

            $in_query_forms = DB::connection( $this->conn_key )->table( 'scheduled_reports' )->where( 'id', $scheduled_report_id )->update( $store_arr );

            $newData = array_merge($originalData, $store_arr); // Simulate post-update state
            logAudit('update', 'scheduled_reports', $scheduled_report_id,$originalData,$newData);
        }else{
            $qry_id = DB::connection( $this->conn_key )->table( 'scheduled_reports' )->insertGetId( $store_arr );
                // Optionally fetch the full inserted record (recommended for audit logs)
            $newData = DB::connection($this->conn_key)->table('scheduled_reports')->where('id', $scheduled_report_id)->first();
            logAudit('create', 'scheduled_reports', $scheduled_report_id, [], (array) $newData);
        }
        session()->flash('success', __('querybuilder::messages.scheduled_report_success'));

        return response()->json([
            'result'    => true,
            'message'   => __('querybuilder::messages.scheduled_report_success'),
        ], 200);
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
            $query_form = DB::connection($this->conn_key)->table('scheduled_reports')->where('id', $id)->first();

            if ( $query_form ) {
                // Delete the record if found

                // Fetch the model data before deleting it (so you can log the original values)
                $model = DB::connection($this->conn_key)->table('scheduled_reports')->where('id', $id)->first();

                // Log the delete action with the old values
                logAudit('delete', 'scheduled_reports', $id, (array) $model, []);
                
                DB::connection($this->conn_key)->table('scheduled_reports')->where('id', $id)->delete();
                return response()->json([
                    'result'    => true,
                    'message'   => __('querybuilder::messages.scheduled_report_delete_success'),
                ], 200);

            } else {

                return response()->json([
                    'result'    => false,
                    'message'   => __('querybuilder::messages.scheduled_report_delete_error_not_found'),
                ], 200);

            }


        } catch (\Exception $e) {
            return response()->json([
                'result'    => false,
                'message'   => __('querybuilder::messages.scheduled_report_delete_error_failed'),
                'messages'  => $e->getMessage()
            ], 500);
        }

    }
}