@extends('wc_querybuilder::layout')

@section('css')
@endsection
@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">

            <div class="d-flex justify-content-between">
                <h2>Query Lists</h2>
                <div>
                    <a href="{{ route( 'queries.add' ) }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Query</a>
                </div>
            </div>

            <table id="reportsTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Actions</th>
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

            if ( confirm('Are you sure you want to delete this record?') ) {

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

    });
</script>

@endsection