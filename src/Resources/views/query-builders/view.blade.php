@extends('wc_querybuilder::layout')

@section('css')
@endsection
@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">

            <div class="card">

                <div class="card-header">

                    <div class="d-flex justify-content-between">
                        <input type="hidden" name="id" id="id" value="{{$query_form?->id ?? 0 }}">
                        <h2>{{ $query_form->title ?? 'View Query'}}</h2>
                        <div>
                            <a href="{{ route( 'queries.index' ) }}" class="btn btn-secondary">Back</a>
                            <a href="{{ route( 'queries.edit', ['id' => ( (int)$query_form?->id ?? 0 )] ) }}" class="btn btn-primary">Edit</a>
                        <button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            Export Options
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item export_csv  "data-format="csv">CSV</a>
                            <a class="dropdown-item export_xlsx "data-format="xlsx">XLSX</a>
                            <a class="dropdown-item export_pdf "data-format="pdf">PDF</a>
                            <a class="dropdown-item export_btn "data-format="json">JSON</a>
                        </div>
                        </div>
                    </div>

                </div>{{-- end card header --}}

                <div class="card-body">

                    <div class="mb-3">
                        <div id="resultsTable"></div>
                    </div>

                </div> {{-- end card body --}}

            </div>


        </div>
    </div>
</div>
@endsection

@section('scripts')

<script>

    $(document).ready(function() {

        var formData = @json($query_details);
        formData = JSON.parse(formData);
        formData.id = $('#id').val();

        var resultsTable = new Tabulator("#resultsTable", {
            layout: "fitColumns",
            ajaxURL: "{{route('api.queries.search')}}", // API endpoint
            ajaxConfig: "GET",
            ajaxParams: formData,
            filterMode: "remote", // Remote filtering
            pagination: true, // Enable pagination
            paginationMode: "remote", // Remote pagination
            paginationInitialPage: 1, // Initial page (default)
            paginationSize: 10, // Number of rows per page
            ajaxResponse: function (url, params, response) {

                // Update columns dynamically
                this.setColumns(response.columns);

                // Return data to Tabulator
                return response;
            },
        });


        $(document).on('click', '.export_csv, .export_pdf, .export_xlsx', function (e) {
            e.preventDefault();
            // Get format from button (csv, xlsx, pdf)
            let format = $(this).data('format'); 
            // let exportData = { ...formData, format: format }; // Preserve original formData
            formData.format = format;
            $.ajax({
                url: "{{route('api.queries.export')}}",
                type: 'GET',
                data: formData,
                xhrFields: {
            // Handle file response properly
                    responseType: 'blob' 
                },
                success: function (response, status, xhr) {
                    let filename = `export.${format}`;
                    let disposition = xhr.getResponseHeader('Content-Disposition');
                    if (disposition && disposition.includes('attachment')) {
                    
                // Try to match UTF-8 encoded filename first
                        let matches = disposition.match(/filename\*?=UTF-8''([^;]+)/);

                        if (!matches || !matches[1]) {
                    // Fallback for standard filename format (without UTF-8 encoding)
                            matches = disposition.match(/filename="?([^"]+)"?/);
                        }

                        console.log('Filename Matches:', matches); 

                        if (matches && matches[1]) {
                            filename = decodeURIComponent(matches[1]);
                        }
                    }

                    let blob = new Blob([response], { type: xhr.getResponseHeader('Content-Type') });
                    let link = document.createElement("a");
                    link.href = window.URL.createObjectURL(blob);
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                },
                error: function (xhr) {
                    let errorMsg = xhr.responseJSON ? xhr.responseJSON.error : "Download failed!";
                    console.log(errorMsg);
                }
            });
        });

        $(document).on('click', '.export_btn', function (e) {
            e.preventDefault();
            let format = $(this).data('format');
            formData.format = 'json';
            $.ajax({
                url: "{{ route('api.queries.export') }}",
                type: 'GET',
                data: formData,
                dataType: 'json', 
                success: function (data, status, xhr) {

                    let filename = "export.json"; 
                    
                    let disposition = xhr.getResponseHeader('Content-Disposition');
                    if (disposition && disposition.includes('attachment')) {
                    
                // Try to match UTF-8 encoded filename first
                        let matches = disposition.match(/filename\*?=UTF-8''([^;]+)/);

                        if (!matches || !matches[1]) {
                    // Fallback for standard filename format (without UTF-8 encoding)
                            matches = disposition.match(/filename="?([^"]+)"?/);
                        }

                        console.log('Filename Matches:', matches); 

                        if (matches && matches[1]) {
                            filename = decodeURIComponent(matches[1]);
                        }
                    }

            // Convert JSON object to string
                    let jsonData = JSON.stringify(data, null, 4);

            // Create a Blob with JSON data
                    let blob = new Blob([jsonData], { type: "application/json" });
            // Create a URL for the blob
                    let url = window.URL.createObjectURL(blob);

            // Create a download link and trigger click
                    let a = document.createElement("a");
                    a.href = url;

            // Set download file name
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

            // Cleanup
                    window.URL.revokeObjectURL(url);
                },
                error: function (xhr, textStatus, errorThrown) {
                    console.error("Download failed!", xhr.responseText || textStatus);
                }
            });
        });


    });
</script>

@endsection