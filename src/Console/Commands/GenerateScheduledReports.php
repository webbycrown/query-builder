<?php

namespace Webbycrown\QueryBuilder\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\MessageBag;
use Exception;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Str;
use URL;
use Symfony\Component\Mime\Part\HtmlPart;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

use Webbycrown\QueryBuilder\Services\ExportService;

/**
 * Command to generate and send scheduled reports via email.
 */
class GenerateScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'query-builder:generate-scheduled-reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send scheduled reports';


    /**
     * The database connection key.
     *
     * @var string
     */
    protected $conn_key;

    /**
     * Constructor.
     * Establish the database connection on command initialization.
     */
     public function __construct()
    {
        parent::__construct(); 

        // Custom helper to connect to main DB
        $this->conn_key = connect_to_main_db(); 
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $now = now();
        $database = env('DB_DATABASE');

        // Fetch active scheduled reports for this database
        $reports = DB::connection($this->conn_key)->table('scheduled_reports')->where('database',$database)->where('active', 1)
            ->get();

        foreach ($reports as $report) {
            try {

                // Check if it's the right time to send this report
                if ($this->shouldRunNow($report, $now)) {
                    
                    $this->info("Processing report ID: {$report->id}");

                    // Fetch the report data
                    $response = $this->getReportData($report->report_type, $report->record_limit);

                     $data = $response->getData(true);
                     
                    // Generate the report file
                    $file = $this->generateFile($data, $report->format, $report->record_limit,$report->report_type);

                    // Send the file via email
                    $this->sendEmail($report, $file);

                    $this->info("Sent to: {$report->email}");
                }
            } catch (\Exception $e) {
                // Log any errors without crashing the loop
                \Log::error("Report {$report->id} failed: " . $e->getMessage());
            }
        }
    }


    /**
     * Fetch report data based on report type.
     *
     * @param string $type Report type ('query_lists' or others)
     * @param int $limit Number of records to fetch
     * @return \Illuminate\Support\Collection
     */
    private function getReportData($type, $limit = 100){
    try {
        $database = env('DB_DATABASE');

        // Get query definition from query_forms
        $getDataByQueryDetails = DB::connection($this->conn_key)
            ->table('query_forms')
            ->where('database', $database)
            ->where('title', $type)
            ->first();

        $data = json_decode($getDataByQueryDetails->query_details ?? '{}', true);
        $mainTable = $data['main_table'] ?? null;

        if (!$mainTable) {
            return collect();
        }

        // Start query
        $query = DB::connection(connect_to_manage_db())->table($mainTable);

        $tables = [$mainTable];
        $joins = [];

        // Extract joins
        foreach ($data as $key => $value) {
            if (preg_match('/joins\[(\d+)\]\[(.*?)\]/', $key, $matches)) {
                $joins[$matches[1]][$matches[2]] = $value;
            }
        }

        // Apply joins
        foreach ($joins as $join) {
            if (!empty($join['table']) && !empty($join['type']) && !empty($join['first_column']) && !empty($join['second_column'])) {
                $tables[] = $join['table'];
                $method = match(strtolower($join['type'])) {
                    'left' => 'leftJoin',
                    'right' => 'rightJoin',
                    'inner' => 'join',
                    default => 'leftJoin'
                };
                $query->$method($join['table'], $join['first_column'], '=', $join['second_column']);
            }
        }

        // Select columns
        $selectedColumns = [];
        $rawColumns = $data['columns'] ?? [];
        foreach ($rawColumns as $alias => $column) {
            $selectedColumns[] = "{$column} as `{$alias}`";
        }

        if (!empty($selectedColumns)) {
            $query->selectRaw(implode(', ', $selectedColumns));
        }

        // Apply where conditions
        foreach ($data['conditions'] ?? [] as $condition) {
            if (!empty($condition['column']) && !empty($condition['operator']) && isset($condition['value'])) {
                $query = generateWhereConditionForOperator($query, $condition);
            }
        }

        // Apply filters
        foreach ($data['filters'] ?? [] as $filter) {
            if (!empty($filter['field']) && !empty($filter['type']) && isset($filter['value'])) {
                if ($filter['type'] === 'like') {
                    $query->where($filter['field'], 'LIKE', '%' . $filter['value'] . '%');
                } else {
                    $query->where($filter['field'], $filter['type'], $filter['value']);
                }
            }
        }

        // Total count (if needed)
        $totalCount = $query->count();

        // Get table info for aggregates/having
        $tableInfo = get_multiple_table_info($tables, connect_to_manage_db());

        // Handle groupBy + aggregate columns
        $selectedAlias = [];
        if (!empty($data['groupByColumns'])) {
            $dataForGroup = applyAggregatesToQuery($query, $selectedColumns, $tableInfo, $data['groupByColumns']);
            $selectedColumns = $dataForGroup['selectedColumns'];
            $selectedAlias = $dataForGroup['selectedAlias'];
            $query = $dataForGroup['query'];
        }

        // Having clause
        foreach ($data['havingColumns'] ?? [] as $havingColumn) {
            if (!empty($havingColumn['column']) && !empty($havingColumn['operator']) && $havingColumn['value'] !== '') {
                $aliasKey = $havingColumn['column'];
                if (!empty($selectedAlias[$aliasKey][0]['aliasKey'])) {
                    $aliasKey = $selectedAlias[$aliasKey][0]['aliasKey'];
                }
                $query->having($aliasKey, $havingColumn['operator'], $havingColumn['value']);
            }
        }

        // Order by
        foreach ($data['orderByColumns'] ?? [] as $orderByColumn) {
            if (!empty($orderByColumn['column']) && !empty($orderByColumn['order'])) {
                $query->orderBy($orderByColumn['column'], $orderByColumn['order']);
            }
        }

        // Column metadata (optional)
        $columns = get_columns_for_listing($mainTable, $tableInfo, $selectedColumns);

        $queries = $query->limit($limit)->get();

        // Fetch results
        return response()->json([
            'data' => $queries,
            'columns' => $columns,
        ]);
        // return ['data' => $queries, 'columns' => $columns ];

    } catch (\Exception $e) {
        \Log::error("Failed to fetch report data from {$type}: " . $e->getMessage());
        return collect();
    }
}



    /**
     * Generate a report file in the specified format (CSV, PDF, or XLSX).
     *
     * @param array $result The result set containing 'data' and 'columns' for the report.
     * @param string $format The format of the file (csv, pdf, xlsx).
     * @param int $limit The record limit (used for filename).
     * @param string $report_type The type of report (used for naming and logic).
     * 
     * @return array Returns file path, filename, and mime type based on the selected format.
     * @throws \Exception If an unsupported format is given.
     */
    private function generateFile($result, $format = 'csv', $limit, $report_type){

        // Generate the filename based on current timestamp, limit, and format
        $filename = 'report_' . date('Y-m-d_His') . '_' . $limit . '.' . $format;
        // Define the full path where the file will be saved
        $path = storage_path("app/reports/$filename");

        // Check if the directory exists, create it if it doesn't
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Check if the data is available, return an error if it's empty
        if (!isset($result['data']) || empty($result['data'])) {
            return response()->json(['error' => __('querybuilder::messages.export_no_data')], 400);
        }

        // Extract the data and columns from the result for the export
        $results = $result['data'];
        $columns = $result['columns'];

        // Initialize the ExportService to handle the export logic
        $exportService = new ExportService();

        // Switch based on the requested format (csv, pdf, xlsx)
        switch ($format) {
            case 'csv':
                 // Generate and save the CSV report, return the path, filename, and mime type
                return $exportService->exportCSV($results, $columns, $report_type,1);

            break;

            case 'pdf':
                // Generate and save the PDF report, return the path, filename, and mime type
                return $exportService->exportPDF($results, $columns, $report_type,1);

            break;

            case 'xlsx':
                // Generate and save the Excel report, return the path, filename, and mime type
                return $exportService->exportExcel($results, $columns, $report_type,1);

            break;

            default:
                // Throw an exception if the format is unsupported
                throw new \Exception("Unsupported format: $format");
        }
        // Return the generated file path, filename, and mime type (this line won't be reached due to the return in the switch statement)
        return ['path' => $path, 'filename' => $filename, 'mime' => $mime];
    }



    /**
     * Send the generated report file via email.
     *
     * @param object $report Report details containing email addresses, subject, body, etc.
     * @param array $file Array containing path, filename, and mime type of the generated report
     * @return void
     */
    private function sendEmail($report, $file){
        try {
            Mail::send([], [], function ($message) use ($report, $file) {
                $message->to($report->email);

                // Add CC (carbon copy) recipients if provided
                if (!empty(trim($report->cc_email))) {
                    $message->cc(array_map('trim', explode(',', trim($report->cc_email))));
                }

                // Add BCC (blind carbon copy) recipients if provided
                if (!empty(trim($report->bcc_email))) {
                    $message->bcc(array_map('trim', explode(',', trim($report->bcc_email))));
                }

                // Set the email subject; fallback to default if none provided
                $message->subject(trim($report->subject) ?? 'Scheduled Report');

                 // Set the email body; fallback message if none provided
                $body = $report->body ?? '';
                $message->html($body);  

                // Attach the generated report file to the email
                $message->attach($file['path'], [
                    'as' => $file['filename'],
                    'mime' => $file['mime'],
                ]);
            });

            // Log success info to console
            $this->info('Email sent successfully to: ' . $report->email);

        } catch (Exception $e) {
            // Log error info to console
            $this->error('Failed to send email: ' . $e->getMessage());
        }
    }

    /**
     * Determine if the report should run now based on its frequency and scheduled time.
     *
     * @param object $report  The scheduled report object containing frequency and time.
     * @param \Carbon\Carbon $now Current datetime object.
     * @return bool
     */
    private function shouldRunNow($report, $now){     
        
        return match (trim($report->frequency)) {
            // Run daily reports if current time matches the scheduled time
            'daily' => $now->format('H:i')  == date('H:i', strtotime(trim($report->time))),

            // Run weekly reports if today is Monday and time matches
            'weekly' => $now->isMonday() && $now->format('H:i') === trim($report->time),

            // Run monthly reports if today is the 1st day of the month and time matches
            'monthly' => $now->day === 1 && $now->format('H:i') === trim($report->time),

            // For unknown frequencies, do not run
            default => false,
        };
    }
}
