<?php

namespace Webbycrown\QueryBuilder\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportService
{
    
    /**
    * Export data to a JSON file.
    *
    * This function converts the given dataset into a structured JSON format 
    * and streams it as a downloadable file.
    *
    * @param array $data The dataset to be exported.
    * @param array $columns The column definitions (each containing 'field' and 'title' keys).
    * @param string $query_title Optional title used in the exported JSON filename.
    *
    * @return \Symfony\Component\HttpFoundation\StreamedResponse Returns a streamed response for downloading the JSON file.
    */
    public function exportJSON($data, $columns, $query_title)
    {
        // Construct filename with timestamp
        $filename = !empty($query_title) 
        ? $query_title . "_export_" . date('YmdHis') . ".json" 
        : "export_" . date('YmdHis') . ".json";

        // Create a mapping of field names to column titles
        $columnMapping = array_column($columns, 'title', 'field');

        // Format data using column mapping
        $formattedData = array_map(fn($row) => 
            array_combine(
            array_values($columnMapping), // Titles as keys
            array_map(fn($field) => $row[$field] ?? '', array_keys($columnMapping)) // Map field values
        ), 
            $data
        );

        // Construct the JSON structure
        $jsonData = [
            'title' => $query_title,
            'data' => $formattedData,
        ];

        // Convert to JSON with pretty formatting
        $jsonString = json_encode($jsonData, JSON_PRETTY_PRINT);

        // Stream the JSON file for download
        return response()->streamDownload(function () use ($jsonString) {
            echo $jsonString;
        }, $filename, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Access-Control-Allow-Origin' => '*', 
        ]);
    }

    /**
    * Export data to a CSV file.
    *
    * This function exports the given dataset to a CSV file with appropriate headers.
    * It ensures proper formatting, handles missing data gracefully, and streams the file 
    * for download without excessive memory usage.
    *
    * @param array|\Illuminate\Http\JsonResponse $data The dataset to be exported (array or JSON response).
    * @param array $columns The column definitions (each containing 'field' and 'title' keys).
    * @param string $query_title Optional title used in the exported CSV filename.
    *
    * @return \Symfony\Component\HttpFoundation\StreamedResponse Returns a streamed response for downloading the CSV file.
    */
    public function exportCSV($data, $columns, $query_title)
    {
        // If data is a JSON response, extract the actual data array
        if ($data instanceof \Illuminate\Http\JsonResponse) {
            $data = $data->getData(true)['data'] ?? [];
        }

        // Return an error response if there's no data to export
        if (empty($data)) {
            return response()->json(['error' => 'No data available for export'], 200);
        }

        // Format query title and construct the filename
        $query_title = !empty($query_title) ? str_replace(' ', '_', $query_title) : 'export';
        $filename = $query_title . "_export_" . date('YmdHis') . ".csv";

        // Set response headers for CSV download
        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
        ];

        // Stream the CSV content to avoid memory overflows for large datasets
        return response()->stream(function () use ($data, $columns) {
            $handle = fopen('php://output', 'w');

            // Extract 'title' for CSV headers and 'field' for data mapping
            $columnTitles = array_column($columns, 'title');
            $columnFields = array_column($columns, 'field');

            // Write column headers to the CSV
            fputcsv($handle, $columnTitles);

            // Map and write each data row based on the defined column fields
            array_map(fn($row) => fputcsv($handle, array_map(fn($field) => $row[$field] ?? '', $columnFields)), $data);

            fclose($handle);
        }, 200, $headers);
    }


    /**
     * Export data to an Excel file.
     *
     * This function generates an Excel file using the provided data and column headers.
     * It utilizes a dynamically created class that implements Laravel Excel's 
     * `FromCollection` and `WithHeadings` interfaces.
     *
     * @param array $data        The dataset to be exported.
     * @param array $columns     The column definitions, where each item contains 'field' and 'title'.
     * @param string $query_title Optional title used in the exported Excel filename.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse Returns the generated Excel file as a downloadable response.
     */
    public function exportExcel($data, $columns, $query_title)
    {
        return Excel::download(new class($data, $columns) implements FromCollection, WithHeadings {
            protected $data;
            protected $columns;

                /**
                 * Constructor to initialize data and columns.
                 *
                 * @param array $data    The dataset to be exported.
                 * @param array $columns The column definitions (with 'field' and 'title' keys).
                 */
                public function __construct($data, $columns) { 
                    $this->data = $data;
                    $this->columns = $columns;
                }

                /**
                 * Prepare and return the collection of data to be exported.
                 *
                 * @return \Illuminate\Support\Collection The formatted collection of data rows.
                 */
                public function collection() { 
                    $columnFields = array_column($this->columns, 'field'); // Extract field names
                    
                    // Format data using array_map to maintain a structured output
                    $formattedData = collect($this->data)->map(
                        fn($row) => array_map(fn($col) => $row[$col] ?? '', $columnFields)
                    );

                    return collect($formattedData);
                }

                /**
                 * Define the column headings for the Excel file.
                 *
                 * @return array The column headers.
                 */
                public function headings(): array {
                    return array_column($this->columns, 'title'); // Extract titles as headers
                }

            }, !empty($query_title) 
            ? $query_title . "_export_" . date('YmdHis') . ".xlsx" 
            : "export_" . date('YmdHis') . ".xlsx"
        );
    }

    /**
    * Export data to a PDF file.
    *
    * This function generates a PDF file using the provided data and column names. 
    * It utilizes a Blade view template (`wc_querybuilder::exports.pdf`) to format the data.
    *
    * @param array $data       The dataset to be exported.
    * @param array $columns    The column headers for the exported data.
    * @param string $query_title Optional title used in the exported PDF filename.
    *
    * @return \Illuminate\Http\Response Returns the generated PDF as a downloadable file.
    */
    public function exportPDF($data, $columns, $query_title)
    {
        // Load the PDF view with data and columns
        $pdf = Pdf::loadView('wc_querybuilder::exports.pdf', [
            'data' => $data,
            'columns' => $columns
        ]);

        // Generate the file name using the query title (if provided) and the current timestamp
        $filename = !empty($query_title) 
        ? $query_title . "_export_" . date('YmdHis') . ".pdf" 
        : "export_" . date('YmdHis') . ".pdf";

        // Return the PDF as a downloadable response
        return $pdf->download($filename);
    }
}
