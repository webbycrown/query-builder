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
                        <h2>{{ $query_form->title ?? 'View Query'}} Reports</h2>
                        <div>
                            <a href="{{ route( 'queries.index' ) }}" class="btn btn-secondary">Back</a>
                            <a href="{{ route( 'queries.edit', ['id' => ( (int)$query_form?->id ?? 0 )] ) }}" class="btn btn-primary">Edit</a>
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

    });
</script>

@endsection