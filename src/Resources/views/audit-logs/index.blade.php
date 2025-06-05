@extends('wc_querybuilder::layout')

@section('css')
<style>

</style>
@endsection
@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            
            <div class="d-flex justify-content-between">
                <h2>{{__('querybuilder::messages.log_lists')}}</h2>
                <div>
                     <a href="{{ route( 'queries.index' ) }}" class="btn btn-secondary">{{ __('querybuilder::messages.back_button') }}</a>
                </div>
            </div>

            <table id="reportsTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>{{__('querybuilder::messages.table_log_id')}}</th>
                        <th>{{__('querybuilder::messages.table_log_user_id')}}</th>
                        <th>{{__('querybuilder::messages.table_log_ip_address')}}</th>
                        <th>{{__('querybuilder::messages.table_log_user_agent')}}</th>
                        <th>{{__('querybuilder::messages.table_log_action')}}</th>
                        <th>{{__('querybuilder::messages.table_log_model')}}</th>
                        <th>{{__('querybuilder::messages.table_log_model_id')}}</th>
                        <th>{{__('querybuilder::messages.table_log_values')}}</th>
                        <th>{{__('querybuilder::messages.table_log_created_at')}}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>

        </div>
    </div>
</div>

<!-- Modal Structure -->
<div class="modal fade" id="jsonModal" tabindex="-1" role="dialog" aria-labelledby="jsonModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="jsonModalTitle">{{__('querybuilder::messages.log_popup_title')}}</h5>
                <button type="button" class="close btn_close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="jsonModalBody">
                <!-- JSON differences will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn_close" data-dismiss="modal">{{__('querybuilder::messages.close_btn')}}</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/diff@5.1.0/dist/diff.min.js"></script>
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
                url: `{{route('queries.log.index')}}`,
                type: "get",
                data: function(d) {
                    d._token = '{{ csrf_token() }}';
                },
            },
            columns: [{
                data: 'id',
            },
            {
                data: 'user_id',
            },
            { data: 'ip_address' },
            { data: 'user_agent' },
            { data: 'action' },
            { data: 'model' },
            { data: 'model_id' },
            {
                data: 'new_values',
                render: function(data, type, row) {
                    try {
                        let oldValue = row.old_values;  // Get old values from the row
                        let newValue = data;  // The new value from the current row

                        // Compare old and new values using custom comparison function
                        let differences = compareValues(oldValue, newValue);

                        // Set the button color based on whether there are differences
                        let colorClass = differences.length > 0 ? 'text-danger' : 'text-success';

                        // Create a button with the comparison results
                        return `<button 
                            type="button"
                            class="btn btn-sm btn-info show-json ${colorClass}" 
                            data-bs-toggle="modal" 
                            data-bs-target="#jsonModal"
                            data-title="New Values" 
                            data-json='${JSON.stringify(newValue)}' 
                            data-old='${JSON.stringify(oldValue)}' 
                            data-differences='${JSON.stringify(differences)}'>
                            View
                        </button>`;
                    } catch (e) {
                        console.log(e);
                        return data;
                    }
                }
            },
            { data: 'date' }
            ],
            responsive: true,
            stateSave: true
        }).order([0, 'desc']);

    });

    function escapeHtml(text) {
    if (typeof text !== 'string') {
        return text;
    }
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

   // Handle button click to show JSON diff in modal
$(document).on('click', '.show-json', function () {
    let rawJson = $(this).data('json');
    let rawOld = $(this).data('old');
    let rawDiff = $(this).data('differences');

    let newValue, oldValue, differences;

    try {
        newValue = typeof rawJson === 'string' ? JSON.parse(rawJson) : rawJson;
        oldValue = typeof rawOld === 'string' ? JSON.parse(rawOld) : rawOld;
        differences = typeof rawDiff === 'string' ? JSON.parse(rawDiff) : rawDiff;
    } catch (e) {
        console.error("Error parsing JSON:", e);
        $('#jsonModalTitle').text("JSON Error");
        $('#jsonModalBody').html('<p class="text-danger">Invalid JSON format.</p>');
        $('#jsonModal').modal('show');
        return;
    }

    let diffDisplay = '';

    if (differences && differences.length > 0) {
    differences.forEach(function (diffItem) {
    const oldText = JSON.stringify(diffItem.old ?? '', null, 2);
    const newText = JSON.stringify(diffItem.new ?? '', null, 2);

    const wordDiff = Diff.diffWords(oldText, newText);

    let highlighted = '';
    wordDiff.forEach(part => {
        const color = part.added ? 'text-success' : part.removed ? 'text-danger' : '';
        highlighted += `<span class="${color}">${escapeHtml(part.value)}</span>`;
    });

    diffDisplay += `
        <div class="mb-3">
            <strong>${escapeHtml(diffItem.key)}:</strong>
            <div class="bg-light border rounded p-2 mb-1">
                <pre style="white-space: pre-wrap; word-break: break-word;">${highlighted}</pre>
            </div>
        </div>
    `;
});
} else {
        diffDisplay = `
            <p class="text-success">No changes detected.</p>
            <pre class="bg-light p-3 rounded border">${JSON.stringify(newValue, null, 4)}</pre>
        `;
    }

    $('#jsonModalTitle').text("JSON View");
    $('#jsonModalBody').html(diffDisplay);
    $('#jsonModal').modal('show');
});

$(document).on('click', '.btn_close', function () {
    $('#jsonModal').modal('hide');
});

// Function to compare old and new values
function compareValues(oldValue, newValue) {
    let differences = [];

    // Check if oldValue or newValue is not an object
    if (typeof oldValue != 'object' || typeof newValue != 'object' ) {
        if (oldValue != newValue) {
            differences.push({ key: 'value', old: oldValue, new: newValue });
        }
        console.log('test'+differences);
        return differences;
    }

    // Iterate over the keys in oldValue
    for (let key in oldValue) {
        if (oldValue.hasOwnProperty(key)) {
            if (JSON.stringify(oldValue[key]) !== JSON.stringify(newValue[key])) {
                differences.push({ key: key, old: oldValue[key], new: newValue[key] });
            }
        }
    }

    // Check for new keys in the new value that are not in old value
    for (let key in newValue) {
        if (newValue.hasOwnProperty(key) && !oldValue.hasOwnProperty(key)) {
            differences.push({ key: key, old: null, new: newValue[key] });
        }
    }
console.log(differences);
    return differences;
}



</script>

@endsection