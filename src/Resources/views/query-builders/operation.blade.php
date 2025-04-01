@extends('wc_querybuilder::layout')

@section('css')
@include('wc_querybuilder::css.style');
@endsection
@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">

            <div class="card">

                <div class="card-header">

                    <div class="d-flex justify-content-between">
                        <h2>{{ (int)$query_form?->id > 0 ? 'Edit' : 'Add' }} Query</h2>
                        <div>
                            <a href="{{ route( 'queries.index' ) }}" class="btn btn-secondary">Back</a>
                            <button type="button" class="btn btn-primary btn-saveQuery">{{ (int)$query_form?->id > 0 ? 'Update' : 'Save' }} Query</button>
                        </div>
                    </div>

                </div> {{-- end card header --}}

                <div class="card-body">

                    <div class="mb-3">
                        <h5>Query Title</h5>
                        <div class="card">
                            <div class="card-body">
                                <form id="querySaveForm">
                                    <div class="mb-3">
                                        <label>Title:</label>
                                        <input type="text" name="title" id="queryReportTitle" placeholder="Enter Title" class="form-control mt-1" required value="{{ $query_form?->title ?? '' }}">
                                        <input type="hidden" name="qry_id" value="{{ $query_form?->id ?? 0 }}">
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h5>Query Details</h5>
                        <div class="card">
                            <div class="card-body">
                                <form id="queryForm">
                                    <!-- This value is dynamically set using getLabelMode() -->
                                    <input type="hidden" name="setting_option" id="setting_option" class="setting_option" value="{{ getLabelMode() }}">
                                    <!-- Main Table Selection -->
                                    <div class="mb-3">
                                        <label>Select Main Table:</label>
                                        <select class="form-select main_table" id="mainTableSelect" name="main_table">
                                            <option value="">Select a table</option>

                                            @foreach($tables as $table)
                                            @if(is_array($tables_data) && array_key_exists($table, $tables_data) && !is_null($tables_data[$table]->table_comment) && !empty($tables_data[$table]->table_comment))
                                            <option value="{{ $table }}">
                                                {{ getLabelMode() == 'Label' 
                                                ? ($tables_data[$table]?->table_comment ?? $table)  
                                                : (getLabelMode() == 'Key' 
                                                    ? $table 
                                                    : (getLabelMode() == 'Both' 
                                                        ? ( $tables_data[$table]->table_comment ? $tables_data[$table]->table_comment . ' (' . $table . ')'  : $table )
                                                        : $table
                                                    )
                                                ) 
                                        }}</option>
                                            @else
                                            <option value="{{ $table }}">{{ $table }}</option>
                                            @endif
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Table Relationships -->
                                    <div id="tableRelationships" class="table-relationships" style="display: none;">
                                        <h6>Available Relationships:</h6>
                                        <div id="relationshipsList"></div>
                                    </div>

                                    <!-- Join Tables Section -->
                                    <div class="mb-3">
                                        <label>Table Joins:</label>
                                        <div id="joinsContainer"></div>
                                        <button type="button" class="btn btn-secondary btn-sm" id="addJoin" disabled>Add Join</button>
                                    </div>

                                    <!-- Column Selection -->
                                    <div class="mb-3">
                                        <label>Select Columns:</label>
                                        <div id="columnSelect" class="border row p-3  bd-highlight">
                                            <!-- Columns will be populated dynamically -->
                                        </div>
                                    </div>

                                    <!-- Conditions -->
                                    <div class="mb-3">
                                        <label>Conditions:</label>
                                        <div id="conditions">
                                            <div class="condition-card mb-2">
                                                <span class="remove-condition" style="font-size: 25px;">&times;</span>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <select class="form-select condition-column" name="conditions[0][column]">
                                                            <option value="">Select Column</option>
                                                        </select>
                                                    </div>
                                                    @php
                                                    $condition_operator =  generateSqlOperators();
                                                    @endphp
                                                    <div class="col-md-4">
                                                        <select class="form-select condition-operator" name="conditions[0][operator]">
                                                            @if( count($condition_operator) > 0 )
                                                            @foreach($condition_operator as $operator)
                                                            <option value="{{ $operator['value'] }}" data-notes="{{ $operator['notes'] }}">{{ $operator['key'] }}</option>
                                                            @endforeach
                                                            @endif
                                                           
                                                            </select>
                                                             <p class="operator-notes">Select an operator to see details</p>
                                                        </div>
                                                       
                                                        <div class="col-md-4">
                                                            <input type="text" class="form-control condition-value" name="conditions[0][value]" placeholder="Value">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-secondary btn-sm" id="addCondition">Add Condition</button>
                                        </div>

                                        <div class="mb-3">
                                            <label>Group By:</label>
                                            <div id="groupby-container">
                                                <div class="groupby-card mb-2">
                                                    <span class="remove-groupby" style="font-size: 25px;">&times;</span>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <select class="form-select groupby-column" name="groupby[0][column]">
                                                                <option value="">Select Column</option>
                                                            </select>
                                                            <span class="warning-message text-danger" style="display: none;"></span>
                                                        </div>
                                                        @php
                                                        $apply_aggregate = applySqlFunctions();
                                                        @endphp
                                                        <div class="col-md-4">
                                                            <select class="form-select groupby-aggregation" name="groupby[0][aggregation]">
                                                                <option value=""></option>
                                                                @if(!empty($apply_aggregate['Functions']))
                                                                <optgroup label="Functions">
                                                                    @foreach($apply_aggregate['Functions'] as $function)
                                                                    <option value="{{ $function['value'] }}" data-notes="{{ $function['notes'] }}">{{ $function['key'] }}</option>
                                                                    @endforeach
                                                                </optgroup>
                                                                @endif

                                                                @if(!empty($apply_aggregate['Aggregation']))
                                                                <optgroup label="Aggregation">
                                                                    @foreach($apply_aggregate['Aggregation'] as $aggregate)
                                                                    <option value="{{ $aggregate['value'] }}" data-notes="{{ $aggregate['notes'] }}">{{ $aggregate['key'] }}</option>
                                                                    @endforeach
                                                                </optgroup>
                                                                @endif
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <input type="text" class="form-control groupby-alias" name="groupby[0][alias]" placeholder="Alias Name">
                                                            <span class="alias-message text-danger" style="display: none;"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-secondary btn-sm" id="addGroupBy">Add Group By</button>
                                        </div>

                                        <div class="mb-3">
                                            <label>Having:</label>
                                            <div class="row having">
                                                <div class="col-md-4">
                                                    <select class="form-select having-column" name="having[0][column]">
                                                        <option value="">Select Column</option>
                                                    </select>
                                                </div>

                                                @php
                                                    $having_operators = havingOperator();
                                                @endphp
                                                
                                                <div class="col-md-4">
                                                    <select class="form-select having-operator" name="having[0][operator]">
                                                        @if( isset($having_operators) && count($having_operators) > 0 )
                                                            @foreach( $having_operators as $having_operator )
                                                            <option value={{ $having_operator['value'] }} data-notes="{{ $having_operator['notes'] }}">{{ $having_operator['key'] }}</option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                    <p class="having-notes"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control having-value" name="having[0][value]" placeholder="Value">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label>Order By:</label>
                                            <div id="orderby-container">
                                                <div class="orderby-card mb-2">
                                                    <span class="remove-orderby" style="font-size: 25px;">&times;</span>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <select class="form-select orderby-column" name="orderby[0][column]">
                                                                <option value="">Select Column</option>
                                                            </select>
                                                            <span class="warning-message text-danger" style="display: none;"></span>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <select class="form-select orderby-order" name="orderby[0][order]">
                                                                <option value="ASC">ASC</option>
                                                                <option value="DESC">DESC</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-secondary btn-sm" id="addOrderBy">Add Order By</button>
                                        </div>

                                        <div class="mb-3 row">
                                            <div class="col-md-4">
                                                <label>Limit:</label>
                                                <input type="number" class="form-control query-limit" name="limit" min="0" value="{{ (array_key_exists('limit', $query_details) && $query_details['limit'] > 0) ? $query_details['limit'] : 0 }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label>Offset:</label>
                                                <input type="number" class="form-control query-offset" name="offset" min="0" value="{{ (array_key_exists('offset', $query_details) && $query_details['offset'] > 0) ? $query_details['offset'] : 0 }}">
                                            </div>
                                        </div>


                                        <button type="submit" class="btn btn-primary">Search</button>
                                    </form>

                                    <!-- Results -->
                                    
                                    <div class="table-responsive mt-4 resultsTable_mainDiv">
                                        
                                        <div id="resultsTable"></div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div> {{-- end card body --}}
                </div> {{-- end card main --}}

            </div>
        </div>
    </div>
    @endsection

@section('scripts')
@include('wc_querybuilder::scripts.operation-scripts');
@endsection