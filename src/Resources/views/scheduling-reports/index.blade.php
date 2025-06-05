@extends('wc_querybuilder::layout')

@section('css')
<style>
#reportsTable {
    table-layout: fixed !important;
    width: 100% !important;
}
#reportsTable th,
#reportsTable td {
    word-wrap: break-word;
    white-space: normal;
}
</style>
@endsection
@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            
            <div class="d-flex justify-content-between">
                <h2>{{ __('querybuilder::messages.scheduled_report_list') }}</h2>
                <div>
                    <a href="{{ route( 'queries.index' ) }}" class="btn btn-secondary">{{ __('querybuilder::messages.back_button') }}</a>
                    <a href="{{ route( 'queries.reports.add' ) }}" class="btn btn-primary"><i class="fa-solid fa-plus"></i>{{ __('querybuilder::messages.scheduled_report_add') }}</a>
                </div>
            </div>

            <table id="reportsTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>{{ __('querybuilder::messages.table_report_type') }}</th>
                        <th>{{ __('querybuilder::messages.table_frequency') }}</th>
                        <th>{{ __('querybuilder::messages.table_schedule') }}</th>
                        <th>{{ __('querybuilder::messages.table_time') }}</th>
                        <th>{{ __('querybuilder::messages.table_to_email') }}</th>
                        <th>{{ __('querybuilder::messages.table_cc_email') }}</th>
                        <th>{{ __('querybuilder::messages.table_bcc_email') }}</th>
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
    fixedHeader: true,
    autoWidth: false,
    serverSide: true,
    searchDelay: 2000,
    stateSave: true,
    order: [[0, 'desc']],
    preDrawCallback: function(settings) {
        if ($.fn.DataTable.isDataTable('#reportsTable')) {
            var dt = $('#reportsTable').DataTable();
            var settings = dt.settings();
            if (settings[0].jqXHR) {
                settings[0].jqXHR.abort();
            }
        }
    },
    ajax: {
        url: `{{route('queries.reports.index')}}`,
        type: "get",
        data: function(d) {
            d._token = '{{ csrf_token() }}';
        },
    },
    columns: [
        { data: 'id' },
        { data: 'report_type' },
        { data: 'frequency' },
        { data: 'time' },
        { data: 'email' },
        { data: 'cc_email' },
        { data: 'bcc_email' },
        {
            data: null,
            orderable: false,
            render: function(data, type, row) {
                let edit_url = "{{ route( 'queries.reports.edit', ['id'=>':id'] ) }}".replace(':id', row.id);
                return `
                    <a class="btn btn-sm btn-info m-1" title="Edit" style="color: white;" href="${edit_url}">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button class="btn btn-sm btn-danger m-1 report-delete" title="Delete" style="color: white;" data-id="${row.id}">
                        <i class="fas fa-trash"></i>
                    </button>`;
            }
        }
    ],
    columnDefs: [
        
        { targets: 4, width: '10%', className: 'dt-center' },
        { targets: 5, width: '20%', className: 'dt-center' },
        { targets: 6, width: '20%', className: 'dt-center' },
    ],
    initComplete: function() {
        this.api().columns.adjust();
    },
    responsive: true
});


        // querySaveForm submission
        $(document).on('click', '.report-delete', function(e) {
            e.preventDefault();

            var $this = $(this);
            var id = $this.attr('data-id');

            toastr.clear();

            if ( confirm('{{ __('querybuilder::messages.delete_confirm_message') }}') ) {

                $.ajax({
                    url: `{{route('queries.reports.delete')}}`,
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