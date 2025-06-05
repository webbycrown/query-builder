@extends('wc_querybuilder::layout')

@section('css')
{{-- Include custom query builder CSS --}}
@include('wc_querybuilder::css.style');
@endsection
@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            {{-- Main card wrapper --}}
            <div class="card">


                {{-- Card header with title and buttons --}}
                <div class="card-header">

                    <div class="d-flex justify-content-between">
                        <h2>{{ (int)$query_form?->id > 0 ? __('querybuilder::messages.edit_query') : __('querybuilder::messages.add_query') }}</h2>
                        <div>
                            <a href="{{ route( 'queries.index' ) }}" class="btn btn-secondary">{{ __('querybuilder::messages.back_button') }}</a>
                            <button type="button" class="btn btn-primary btn-saveQuery">{{ (int)$query_form?->id > 0 ? __('querybuilder::messages.update_query_button') : __('querybuilder::messages.save_query_button') }}</button>
                        </div>
                    </div>

                </div> {{-- end card header --}}

                {{-- Card body with all input fields --}}   
                <div class="card-body">

                    {{-- Query title input --}}
                    <div class="mb-3">
                        <h5>{{ __('querybuilder::messages.form_query_title')}}</h5>
                        <div class="card">
                            <div class="card-body">
                                <form id="querySaveForm">
                                    <div class="mb-3">
                                        <label>{{ __('querybuilder::messages.form_query_title_title')}}:</label>
                                        <input type="text" name="title" id="queryReportTitle" placeholder="{{ __('querybuilder::messages.form_query_enter_title') }}" class="form-control mt-1" required value="{{ $query_form?->title ?? '' }}">
                                        <input type="hidden" name="qry_id" value="{{ $query_form?->id ?? 0 }}">
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Main query builder form --}}
                    <div class="mb-3">
                        <h5>{{ __('querybuilder::messages.form_query_details')}}</h5>
                        <div class="card">
                            <div class="card-body">
                                <form id="queryForm">
                                    {{-- Label mode --}}
                                    <!-- This value is dynamically set using getLabelMode() -->
                                    <input type="hidden" name="setting_option" id="setting_option" class="setting_option" value="{{ getLabelMode() }}">
                                    <!-- Main Table Selection -->
                                    <div class="mb-3">
                                        <label>{{ __('querybuilder::messages.form_query_select_main_table') }}:</label>
                                        <select class="form-select main_table" id="mainTableSelect" name="main_table">
                                            <option value="">{{ __('querybuilder::messages.form_query_select_a_table') }}</option>

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
                                        <h6>{{ __('querybuilder::messages.form_query_available_relationships')}}:</h6>
                                        <div id="relationshipsList"></div>
                                    </div>

                                    <!-- Join Tables Section -->
                                    <div class="mb-3">
                                        <label>{{ __('querybuilder::messages.form_query_table_joins')}}:</label>
                                        <div id="joinsContainer"></div>
                                        <button type="button" class="btn btn-secondary btn-sm" id="addJoin" disabled>{{ __('querybuilder::messages.form_query_add_join_button')}}</button>
                                    </div>

                                    <!-- Column Selection -->
                                    <div class="mb-3">
                                        <label>{{ __('querybuilder::messages.form_query_select_columns')}}:</label>
                                        <div id="columnSelect" class="border row p-3  bd-highlight">
                                            <!-- Columns will be populated dynamically -->
                                        </div>
                                    </div>
                                    
                                    <?php
                                    $condition_operator =  generateSqlOperators();
                                    $conditionsAddOr = conditionsAddOr(); 
                                    ?>
                                    {{-- Nested Grouped Conditions --}}
                                    <div id="queryBuilderWrapper">
                                        <button type="button" class="btn btn-secondary btn-sm mb-3" id="addMainGroup">+ Add Condition</button>
                                        <div id="groupBuilder">
                                            {{-- First Group --}}
                                            <div class="group-container">
                                                <div class="group-card mb-2">
                                                    <div class="condition-group mb-4" data-group="0">
                                                        {{-- Group logic dropdown --}}
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <label><strong>{{ __('querybuilder::messages.form_query_group_logic')}}: </strong></label>
                                                            <select class="form-select w-auto group-conditions" name="groups[0][and-or-conditions]">
                                                                @if( isset($conditionsAddOr) && count($conditionsAddOr) > 0 )
                                                                @foreach( $conditionsAddOr as $condition )
                                                                <option value="{{ $condition['value'] }}">{{ $condition['key'] }}</option>
                                                                @endforeach
                                                                @endif
                                                            </select>
                                                            <span class="remove-group" style="font-size: 25px;">×</span>
                                                        </div>

                                                        {{-- First condition in the group --}}
                                                        <div class="nested-group" data-group-conditions="0">

                                                            <div class="condition mb-2">
                                                                <div class="row g-2">
                                                                    <div class="col-md-3">
                                                                        <select class="form-select group_conditions_column" name="groups[0][group_conditions][0][column]">
                                                                            <option value="">{{ __('querybuilder::messages.form_query_select_column') }}</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <select class="form-select group_conditions" name="groups[0][group_conditions][0][operator]">

                                                                            @if( count($condition_operator) > 0 )
                                                                            @foreach($condition_operator as $operator)
                                                                            <option value="{{ $operator['value'] }}" data-notes="{{ $operator['notes'] }}">{{ $operator['key'] }}</option>
                                                                            @endforeach
                                                                            @endif
                                                                        </select>
                                                                        <p class="operator-notes">{{ __('querybuilder::messages.form_query_operator_notes') }}</p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <input type="text" class="form-control" name="groups[0][group_conditions][0][value]" placeholder="{{ __('querybuilder::messages.form_query_value_placeholder') }}" value="">
                                                                    </div> 
                                                                    <div class="col-md-1">
                                                                        <select class="form-select w-auto" name="groups[0][group_conditions][0][conditions]">
                                                                            @if( isset($conditionsAddOr) && count($conditionsAddOr) > 0 )
                                                                            @foreach( $conditionsAddOr as $condition )
                                                                            <option value="{{ $condition['value'] }}">{{ $condition['key'] }}</option>
                                                                            @endforeach
                                                                            @endif
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-1">
                                                                        <span class="remove-group-conditions" style="font-size: 25px;">×</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- Button to add another condition within group --}}
                                                        <button type="button" class="btn btn-secondary btn-sm mt-2 add-condition" data-group="0">
                                                            + {{ __('querybuilder::messages.form_query_add_condition_button')}}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                        {{-- Group By + Aggregation --}}
                                        <div class="mb-3">
                                            <label>{{ __('querybuilder::messages.form_query_group_by')}}:</label>
                                            <div id="groupby-container">
                                                {{-- Group by logic (more can be added dynamically) --}}
                                                <div class="groupby-card mb-2">
                                                    <span class="remove-groupby" style="font-size: 25px;">&times;</span>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <select class="form-select groupby-column" name="groupby[0][column]">
                                                                <option value="">{{ __('querybuilder::messages.form_query_select_column') }}</option>
                                                            </select>
                                                            <span class="warning-message text-danger" style="display: none;"></span>
                                                        </div>
                                                        @php
                                                        $apply_aggregate = applySqlFunctions();
                                                        @endphp
                                                        <div class="col-md-4">
                                                            <select class="form-select groupby-aggregation" name="groupby[0][aggregation]">
                                                                <option value=""></option>
                                                                {{-- Functions --}}
                                                                @if(!empty($apply_aggregate['Functions']))
                                                                <optgroup label="Functions">
                                                                    @foreach($apply_aggregate['Functions'] as $function)
                                                                    <option value="{{ $function['value'] }}" data-notes="{{ $function['notes'] }}">{{ $function['key'] }}</option>
                                                                    @endforeach
                                                                </optgroup>
                                                                @endif
                                                                {{-- Aggregations --}}
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
                                                            <input type="text" class="form-control groupby-alias" name="groupby[0][alias]" placeholder="{{ __('querybuilder::messages.form_query_alias_placeholder')}}">
                                                            <span class="alias-message text-danger" style="display: none;"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-secondary btn-sm" id="addGroupBy">{{ __('querybuilder::messages.form_query_add_group_by_button')}}</button>
                                        </div>

                                        {{-- Having Clause --}}
                                        <div class="mb-3">
                                            <label>{{ __('querybuilder::messages.form_query_alias_placeholder')}}:</label>
                                            <div class="row having">
                                                <div class="col-md-4">
                                                    <select class="form-select having-column" name="having[0][column]">
                                                        <option value="">{{ __('querybuilder::messages.form_query_select_column') }}</option>
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
                                                    <input type="text" class="form-control having-value" name="having[0][value]" placeholder="{{ __('querybuilder::messages.form_query_value_placeholder') }}">
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Order By --}}
                                        <div class="mb-3">
                                            <label>{{ __('querybuilder::messages.form_query_order_by')}}:</label>
                                            <div id="orderby-container">
                                                <div class="orderby-card mb-2">
                                                    <span class="remove-orderby" style="font-size: 25px;">&times;</span>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <select class="form-select orderby-column" name="orderby[0][column]">
                                                                <option value="">{{ __('querybuilder::messages.form_query_select_column') }}</option>
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
                                            <button type="button" class="btn btn-secondary btn-sm" id="addOrderBy">{{ __('querybuilder::messages.form_query_add_order_by_button')}}</button>
                                        </div>

                                        {{-- Limit & Offset --}}
                                        <div class="mb-3 row">
                                            <div class="col-md-4">
                                                <label>{{ __('querybuilder::messages.form_query_limit')}}:</label>
                                                <input type="number" class="form-control query-limit" name="limit" min="0" value="{{ (array_key_exists('limit', $query_details) && $query_details['limit'] > 0) ? $query_details['limit'] : 0 }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label>{{ __('querybuilder::messages.form_query_offset')}}:</label>
                                                <input type="number" class="form-control query-offset" name="offset" min="0" value="{{ (array_key_exists('offset', $query_details) && $query_details['offset'] > 0) ? $query_details['offset'] : 0 }}">
                                            </div>
                                        </div>

                                        

                                        {{-- Submit button --}}
                                        <button type="submit" class="btn btn-primary">{{ __('querybuilder::messages.form_query_search_button') }}</button>
                                    </form>

                                    <!-- Results -->
                                    
                                    {{-- Results table --}}
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
{{-- Include custom query builder scripts --}}
@include('wc_querybuilder::scripts.operation-scripts');
@endsection