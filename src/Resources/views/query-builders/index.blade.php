@extends('wc_querybuilder::layout')

@section('css')
@endsection
@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            
            <div class="d-flex justify-content-between">
                <h2>{{__('querybuilder::messages.query_lists')}}</h2>
                <div>
                    <?php
                    $log_page_view =  config('querybuilder.log_page_view', false);
                    $reports_page_view =  config('querybuilder.reports_page_view', false);
                    ?>
                   @if( $reports_page_view ) <a href="{{ route( 'queries.reports.index' ) }}" class="btn btn-primary">{{__('querybuilder::messages.report_btn')}}</a>@endif
                   @if( $log_page_view ) <a href="{{ route( 'queries.log.index' ) }}" class="btn btn-primary">{{__('querybuilder::messages.log_btn')}}</a>@endif
                    <a href="{{ route( 'queries.add' ) }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> {{__('querybuilder::messages.add_query')}}</a>
                </div>
            </div>

            <table id="reportsTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>{{ __('querybuilder::messages.table_id') }}</th>
                        <th>{{ __('querybuilder::messages.table_title') }}</th>
                        <th>{{ __('querybuilder::messages.table_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>

        </div>
    </div>
</div>
@endsection

@section('scripts')

<script>

    @if(Session::has('success'))
    toastr.success("{{ Session::get('success') }}");
    @endif

    @if(Session::has('error'))
    toastr.error("{{ Session::get('error') }}");
    @endif


    $(document).ready(function() {

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        var reportDataTable = $('#reportsTable').DataTable({
            processing: true,
            bSort: true,
            fixedHeder: true,
            serverSide: true,
            searchDelay: 2000,
            stateSave: true,
            order: [ [0, 'desc'] ],
            preDrawCallback: function(settings) {
                if ($.fn.DataTable.isDataTable('#reportsTable')) {
                    var dt = $('#reportsTable').DataTable();

                    //Abort previous ajax request if it is still in process.
                    var settings = dt.settings();
                    if (settings[0].jqXHR) {
                        settings[0].jqXHR.abort();
                    }
                }
            },
            ajax: {
                url: `{{route('queries.index')}}`,
                type: "get",
                data: function(d) {
                    d._token = '{{ csrf_token() }}';
                },
            },
            columns: [{
                data: 'id',
            },
            {
                data: 'title',
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    let btns = "";

                    var view_url = "{{ route( 'queries.view', ['id'=>':id'] ) }}".replace(':id', row.id);
                    var edit_url = "{{ route( 'queries.edit', ['id'=>':id'] ) }}".replace(':id', row.id);

                    btns += `<a class="btn btn-sm btn-primary m-1" title="View Reports" style="color: white;" href="${view_url}"><i class="fa-solid fa-right-to-bracket"></i></i></a>`;

                    btns += `<a class="btn btn-sm btn-info m-1" title="Edit" style="color: white;"  href="${edit_url}"><i class="fas fa-edit"></i></a>`;
                    
                    btns += `<button class="btn btn-sm btn-danger m-1 report-delete" title="Delete" style="color: white;" data-id="${row.id}"><i class="fas fa-trash"></i></button>`;

                    btns += `<div class="btn-group">
                    <button class="btn btn-sm btn-secondary dropdown-toggle m-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-file-export"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item export_csv" data-format="csv" data-id="${row.id}">CSV</a></li>
                        <li><a class="dropdown-item export_xlsx" data-format="xlsx" data-id="${row.id}">XLSX</a></li>
                        <li><a class="dropdown-item export_pdf" data-format="pdf" data-id="${row.id}">PDF</a></li>
                        <li><a class="dropdown-item export_btn" data-format="json" data-id="${row.id}">JSON</a></li>
                    </ul>
                    </div>`;

                    return btns;
                }
            }
            ],
            responsive: true,
            stateSave: true
        }).order([0, 'desc']);

        // querySaveForm submission
        $(document).on('click', '.report-delete', function(e) {
            e.preventDefault();

            var $this = $(this);
            var id = $this.attr('data-id');

            toastr.clear();

            if ( confirm('{{ __('querybuilder::messages.delete_confirm_message') }}') ) {

                $.ajax({
                    url: `{{route('api.queries.delete')}}`,
                    type: 'POST',
                    data: { id: id },
                    success: function (response) {
                        if ( response.result ) {
                            toastr.success( response.message );
                            reportDataTable.ajax.reload(null, false);

                        } else if( !response.result ) {
                            toastr.error( response.message );
                        }
                    },
                    error: function (error) {
                        toastr.error( error.responseJSON.message );
                    }
                });

            }

        });

         let formData = {}; 
         $(document).on('click', '.export_csv, .export_pdf, .export_xlsx', function (e) {
            e.preventDefault();
            // Get export format (csv, xlsx, pdf) from button
            var format = $(this).attr('data-format'); 
            var id = $(this).attr('data-id'); 
            // Assign format and ID to formData
            formData.format = format;
            formData.id = id;
            // Send AJAX request to export data
            $.ajax({
                url: "{{route('api.queries.export')}}",
                type: 'GET',
                data: formData,
                xhrFields: {
                    // Ensure binary response for file downloads
                    responseType: 'blob' 
                },
                success: function (response, status, xhr) {

                    let filename = `export.${format}`; // Default filename
                    let disposition = xhr.getResponseHeader('Content-Disposition');

                    if (disposition && disposition.includes('attachment')) {
                    
                        // Try to match UTF-8 encoded filename first
                        let matches = disposition.match(/filename\*?=UTF-8''([^;]+)/); 

                        if (!matches || !matches[1]) {
                            // Fallback for standard filename format
                            matches = disposition.match(/filename="?([^"]+)"?/);
                        }

                        if (matches && matches[1]) {
                            filename = decodeURIComponent(matches[1]); // Decode and set filename
                        }
                    }

                    // Create a downloadable blob object
                    let blob = new Blob([response], { type: xhr.getResponseHeader('Content-Type') });
                    let link = document.createElement("a");
                    link.href = window.URL.createObjectURL(blob);
                    link.download = filename; // Set the filename
                    // Append the link to the document, trigger the download, and remove the link
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                },
                error: function (xhr) {
                     // Handle error response and log an error message
                    let errorMsg = xhr.responseJSON ? xhr.responseJSON.error : "{{ __('querybuilder::messages.error_download_failed') }}";
                    console.log(errorMsg);
                }
            });
        });

         // Get export format json from button
        $(document).on('click', '.export_btn', function (e) {
            e.preventDefault(); // Prevent default button behavior
            let format = $(this).data('format'); // Get export format from button
             var id = $(this).attr('data-id'); // Get the ID of the query to export

             // Set the export format and ID in formData
            formData.format = 'json';
            formData.id = id;
            // Send AJAX request to fetch the export data
            $.ajax({
                url: "{{ route('api.queries.export') }}",
                type: 'GET',
                data: formData,
                dataType: 'json',  // Expect JSON response
                success: function (data,status, xhr) {

                    let filename = "export.json";  // Default filename
                    
                    // Extract filename from Content-Disposition header if available
                    let disposition = xhr.getResponseHeader('Content-Disposition');
                    if (disposition && disposition.includes('attachment')) {

                        // Try to match UTF-8 encoded filename first
                        let matches = disposition.match(/filename\*?=UTF-8''([^;]+)/);

                        if (!matches || !matches[1]) {
                            // Fallback for standard filename format (without UTF-8 encoding)
                            matches = disposition.match(/filename="?([^"]+)"?/);
                        }
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
                    console.error("{{ __('querybuilder::messages.error_download_failed') }}", xhr.responseText || textStatus);
                }
            });
        });

    });
</script>

@endsection