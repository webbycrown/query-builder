<!DOCTYPE html>
<html>
<head>
    <title>{{ __('querybuilder::messages.export_page_title') }}</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

    <h2>{{ __('querybuilder::messages.export_page_heading') }}</h2>

    <table>
        <thead>
            <tr>
                @foreach($columns as $column)
                    <th>{{ $column['title'] }}</th> <!-- Add column titles dynamically -->
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    @foreach($columns as $column)
                        <td>{{ $row[$column['field']] ?? '' }}</td> <!-- Match field with data -->
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
