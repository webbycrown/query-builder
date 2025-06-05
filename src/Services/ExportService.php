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
    public function exportCSV($data, $columns, $query_title, $type = 0){

        // Extract data from JsonResponse if needed
        if ($data instanceof \Illuminate\Http\JsonResponse) {
            $data = $data->getData(true)['data'] ?? ['test'];
        }
        if (empty($data)) {
            return response()->json(['error' => 'No data available for export'], 200);
        }

        // Prepare filename and title
        $query_title = $query_title ? str_replace(' ', '_', $query_title) : 'export';
        $filename = "{$query_title}_export_" . date('YmdHis') . ".csv";

        // Extract column headers and fields once
        $columnTitles = array_column($columns, 'title');
        $columnFields = array_column($columns, 'field');

        if ($type == 1) {
            $path = storage_path("app/exports/{$filename}");
            is_dir(dirname($path)) || mkdir(dirname($path), 0755, true);

            $handle = fopen($path, 'w');
            fputcsv($handle, $columnTitles);

            foreach ($data as $row) {
                fputcsv($handle, array_map(fn($field) => $row[$field] ?? '', $columnFields));
            }

            fclose($handle);

            return [
                'path' => $path,
                'filename' => $filename,
                'mime' => 'text/csv',
            ];
        }

        // Stream to browser
        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename={$filename}",
        ];

        return response()->stream(function () use ($data, $columnTitles, $columnFields) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columnTitles);

            foreach ($data as $row) {
                fputcsv($handle, array_map(fn($field) => $row[$field] ?? '', $columnFields));
            }

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
    public function exportExcel($data, $columns, $query_title, $type = 0)
{
    $filename = (!empty($query_title) ? str_replace(' ', '_', $query_title) : 'export') . '_export_' . date('YmdHis') . '.xlsx';
    $columnTitles = array_column($columns, 'title');
    $columnFields = array_column($columns, 'field');

    if ($type === 1) {
        // Ensure the export directory exists
        $exportPath = storage_path('app/exports');
        if (!file_exists($exportPath)) {
            mkdir($exportPath, 0755, true);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Write headers
        foreach ($columnTitles as $colIndex => $title) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $title);
        }

        // Write data
        foreach ($data as $rowIndex => $row) {
            foreach ($columnFields as $colIndex => $field) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $row[$field] ?? '');
            }
        }

        $fullPath = $exportPath . '/' . $filename;
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($fullPath);

        return [
            'path' => $fullPath,
            'filename' => $filename,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    // Stream the file directly via HTTP response
    return response()->stream(function () use ($data, $columnFields, $columnTitles) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        foreach ($columnTitles as $colIndex => $title) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $title);
        }

        // Data rows
        foreach ($data as $rowIndex => $row) {
            foreach ($columnFields as $colIndex => $field) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $row[$field] ?? '');
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }, 200, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => "attachment; filename={$filename}",
    ]);
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
  public function exportPDF($data, $columns, $query_title, $type = 0)
{
    $filename = (!empty($query_title) ? str_replace(' ', '_', $query_title) : 'export') . '_export_' . date('YmdHis') . '.pdf';

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('wc_querybuilder::exports.pdf', [
        'data' => $data,
        'columns' => $columns
    ]);

    if ($type === 1) {
        $exportPath = storage_path('app/exports');
        if (!file_exists($exportPath)) {
            mkdir($exportPath, 0755, true);
        }

        $fullPath = $exportPath . '/' . $filename;
        $pdf->save($fullPath);

        return [
            'path' => $fullPath,
            'filename' => $filename,
            'mime' => 'application/pdf',
        ];
    }

    // Stream directly to browser
    return response()->stream(function () use ($pdf) {
        echo $pdf->output();
    }, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
}

}
