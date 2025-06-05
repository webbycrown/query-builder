<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PDF Report</title>

    <style>
        @page {
            margin: 20px;
        }
        body {
            font-family: DejaVu Sans, sans-serif; /* Use DejaVu Sans for dompdf compatibility */
            font-size: 12px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 20px;
            word-break: break-word;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
        }
        td p, td pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: DejaVu Sans, sans-serif;
        }
    </style>
</head>
<body>

<h1>Query Report</h1>

<table>
    <thead>
        <tr>
            @foreach(array_keys($data[0]) as $key)
                <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($data as $row)
            <tr>
                @foreach($row as $column)
                    <td>
                        @if(is_string($column) && (strlen($column) > 100 || str_contains($column, '{')))
                            <pre>{{ $column }}</pre>
                        @else
                            <p>{{ $column }}</p>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
