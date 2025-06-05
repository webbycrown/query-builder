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

class AuditLogController extends Controller
{
	/**
     * List all audit logs.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {   $response = array(
            "draw" => 0,
            "iTotalRecords" => 0,
            "iTotalDisplayRecords" =>0,
            "aaData" =>[]
        );
       	 if ($request->ajax()) {
            $log_page_view =  config('querybuilder.log_page_view', false);
            if( $log_page_view ){

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
                $data_arr = DB::connection(connect_to_main_db())->table('audit_logs')->where('database',$database)->select('audit_logs.*',DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"))
                ->where( function ($query) use ($searchValue) {
                    $query->where('id', 'like', '%' . strtolower($searchValue) . '%')
                    ->orWhere('ip_address', 'like', '%' . strtolower($searchValue) . '%')
                    ->orWhere('user_id', 'like', '%' . strtolower($searchValue) . '%')
                    ->orWhere('action', 'like', '%' . strtolower($searchValue) . '%')
                    ->orWhere('model_id', 'like', '%' . strtolower($searchValue) . '%')
                    ->orWhereRaw("DATE_FORMAT(created_at, '%Y-%m-%d') LIKE ?", ["%{$searchValue}%"])
                    ->orWhere('model', 'like', '%' . strtolower($searchValue) . '%');
                })
                ->orderBy('created_at', 'desc'); // Order logs by creation date
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
            }

            return response()->json($response);

        }

        return view('wc_querybuilder::audit-logs.index');
    }
}