<script>
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        let tablesComments = @json($tables_data);
        let conditionOperators = @json(generateSqlOperators());
        let apply_aggregate = @json(applySqlFunctions());
        let having_operators = @json(havingOperator());
        let conditionsAddOr = @json(conditionsAddOr());
        let availableColumns = {};
        let selectedColumns = [];
        let tableRelations = [];
        let tableRelationsColumns = [];
        let conditionCount = 1;
        let groupByCount = 1; // Initialize counter
        let orderByCount = 1; // Initialize counter

        let queryDetails = @json($query_details);
        let queryDetailMainTable = queryDetails?.main_table || '';
        let queryDetailColumns = queryDetails?.columns || [];
        let queryDetailJoins = queryDetails?.joins || [];
        let queryDetailConditions = queryDetails?.conditions ? Object.values(queryDetails.conditions) : [];
        let queryDetailGroupby = queryDetails?.groupby || [];
        let queryDetailOrderby = queryDetails?.orderby || [];
        let queryDetailHaving = queryDetails?.having ? Object.values(queryDetails.having) : [];
        let queryDetailGroupConditions = queryDetails?.groups ? Object.values(queryDetails.groups) : [];
        let setting_option_val = $('.setting_option').val();

        {{-- byDefaultConditionOperator(); --}}

        $('#mainTableSelect').val(queryDetailMainTable);
        setTimeout(function(){
            $('#mainTableSelect').change();
        }, 100);

        // Handle main table selection
        $('#mainTableSelect').change(function() {
            const table = $(this).val();
            resetAllData();
            if (table) {
                loadTableRelations(table);
                loadTableColumns(table);

                setTimeout(function(){
                    Object.entries(queryDetailJoins).forEach(([qryJoinId, qryJoin]) => {
                        var qryType = qryJoin?.type || null;
                        var qryTable = qryJoin?.table || null;
                        var qryFirstColumn = qryJoin?.first_column || null;
                        var qrySecondColumn = qryJoin?.second_column || null;
                        
                        appendJoinHtmlContent(qryJoinId, qryType, qryTable, qryFirstColumn, qrySecondColumn);

                        if ( qryTable ) {
                            $('select[name="joins[' + qryJoinId + '][table]]"').val(qryTable);
                            setTimeout(function(){
                                $('.join-table').change();
                            }, 200);
                        }
                    });
                    queryDetailJoins = [];
                }, 200);

            } else {
                resetColumnSelection();
                updateColumnSelection(setting_option_val);
            }
        });

        function loadTableRelations(table) {
            $.get("{{ route( 'api.queries.relations', ['table'=>':table'] ) }}".replace(':table', table), function(relations) {

                $('#tableRelationships').addClass( 'd-none' );
                $('.join-card').remove();

                tableRelations = relations;
                let relationshipHtml = '';
                let relationShowFlag = false;
            
                relations.forEach(relation => {
                    relationShowFlag = true;
                    tableRelationsColumns[relation.referenced_table] = relation;
                    relationshipHtml += `
                        <div class="relationship-item">
                            ${
                                setting_option_val === 'Label' 
                                ?( tablesComments[relation.table_name]?.table_comment ? tablesComments[relation.table_name]?.table_comment : relation.table_name )
                                : setting_option_val === 'Key' 
                                    ? relation.table_name 
                                    : setting_option_val === 'Both' 
                                        ? (tablesComments[relation.table_name]?.table_comment  ? `${tablesComments[relation.table_name]?.table_comment} [${relation.table_name }]` : relation.table_name )
                                        : relation.table_name
                            }.${
                                    setting_option_val === 'Label' 
                                    ? (relation.column_comment ? relation.column_comment : relation.column_name)
                                    : setting_option_val === 'Key' 
                                        ? relation.column_name
                                        : setting_option_val === 'Both' 
                                            ? (relation.column_comment  ? `${relation.column_comment} [${relation.column_name }]` : relation.column_name )
                                            : relation.column_name
                                } → 
                                    ${
                                        setting_option_val === 'Label' 
                                        ?( tablesComments[relation.referenced_table]?.table_comment ? tablesComments[relation.referenced_table]?.table_comment : relation.referenced_table )
                                        : setting_option_val === 'Key' 
                                            ? relation.referenced_table 
                                            : setting_option_val === 'Both' 
                                                ? (tablesComments[relation.referenced_table]?.table_comment  ? `${tablesComments[relation.referenced_table]?.table_comment} [${relation.referenced_table }]` : relation.referenced_table )
                                                : relation.referenced_table
                                    }.${
                                            setting_option_val === 'Label' 
                                            ? (relation.referenced_column_comment ? relation.referenced_column_comment : relation.referenced_column)
                                            : setting_option_val === 'Key' 
                                                ? relation.referenced_column
                                                : setting_option_val === 'Both' 
                                                    ? (relation.referenced_column_comment  ? `${relation.referenced_column_comment} [${relation.referenced_column }]` : relation.referenced_column )
                                                    : relation.referenced_column
                                        }
                        </div>
                    `;
                });

                $('#relationshipsList').html(relationshipHtml);
                if ( relationShowFlag ) {
                    $('#tableRelationships').removeClass( 'd-none' );
                    $('#tableRelationships').show();
                }

                let rel_length = relations.length;
                $('#addJoin').prop('disabled', ( rel_length > 0 ? false : true ));

            });
        }

        function loadTableColumns(table , is_join_table = 'no' ) {
            $.get("{{ route( 'api.queries.columns', ['table'=>':table'] ) }}?is_join_table=:is_join_table".replace(':table', table).replace(':is_join_table', is_join_table), function(columns) {
                availableColumns[table] = columns;
                resetColumnSelection();
                updateColumnSelection(setting_option_val);
            });
        }

        function resetAllData() {
            availableColumns = {};
            selectedColumns = [];
            tableRelations = [];
            tableRelationsColumns = [];

            $('#addJoin').prop('disabled', true);
            $('#tableRelationships').css('display', 'none');
            $('#resultsSummary').css('display', 'none');
            $('#relationshipsList').html('');
            $('#joinsContainer').html('');
            $('#resultsSummary').html('');
            $('#resultsHeader').html('');
            $('#resultsBody').html('');

            resetConditionSection();
        }

        function resetConditionSection() {
            // Clear all condition-related containers
            $('#conditions').html('');
            $('#groupby-container').html('');
            $('#orderby-container').html('');

            // Reset counters
            conditionCount = 0;
            groupByCount = 0;
            orderByCount = 0;

            // Populate GROUP BY section if available
            if ( queryDetailGroupby.length > 0 ) {
                setTimeout(function(){
                    Object.entries(queryDetailGroupby).forEach(([qryGroupKey, qryGroupby]) => {
                        var qryGrpColumn = qryGroupby?.column || null;
                        var qryGrpAggregation = qryGroupby?.aggregation || null;
                        var qryGrpAlias = qryGroupby?.alias || null;

                        // Append Group By HTML dynamically
                        appendGroupByHtmlContent('edit', qryGrpColumn, qryGrpAggregation, qryGrpAlias);
                    });
                    queryDetailColumns = [];
                }, 500);
            } else {
                // If no group by exists, append a default input
                appendGroupByHtmlContent('edit')
            } 
             // Populate GROUP Conditions section if available
            if ( queryDetailGroupConditions.length > 0 ) {
                loadGroupedConditionsFromQuery(queryDetailGroupConditions);
            }else{
                loadGroupedConditionsFromQuery();
            }

            // Populate ORDER BY section if available
            if ( queryDetailOrderby.length > 0 ) {
                setTimeout(function(){
                    Object.entries(queryDetailOrderby).forEach(([qryOrderKey, qryOrderby]) => {
                        var qryOrderColumn = qryOrderby?.column || null;
                        var qryOrderOrder = qryOrderby?.order || null;

                        // Append Order By HTML dynamically
                        appendOrderByHtmlContent('edit', qryOrderColumn, qryOrderOrder);
                    });
                    queryDetailColumns = [];
                }, 500);
            } else {
                // If no order by exists, append a default input
                appendOrderByHtmlContent('edit')
            }

            // Populate HAVING section if available
            if ( queryDetailHaving.length > 0 ) {
                setTimeout(function(){
                    Object.entries(queryDetailHaving).forEach(([qryHavingKey, qryHavingby]) => {
                        var qryHavingColumn = qryHavingby?.column || null;
                        var qryHavingOperator = qryHavingby?.operator || null;
                        var qryHavingValue = qryHavingby?.value || null;

                        // Use setTimeout to ensure the elements are available before setting values
                        setTimeout(function () {

                            // Set the selected operator dynamically
                            var selectElement = document.querySelector(`select[name="having[${qryHavingKey}][operator]"]`);
                            if (selectElement && qryHavingOperator) {
                                selectElement.value = qryHavingOperator; // Set selected value dynamically
                            }
                            // Set the selected column dynamically
                            var selectColumn = document.querySelector(`select[name="having[${qryHavingKey}][column]"]`);
                            if (selectColumn && qryHavingColumn) {
                                selectColumn.value = qryHavingColumn; // Set selected value dynamically
                            }

                            // Set the selected value dynamically
                            var selectValue = document.querySelector(`input[name="having[0][value]"]`);
                            if (selectValue && qryHavingValue) {
                                selectValue.value = qryHavingValue; // Set selected value dynamically
                            }
                            // Apply default settings for HAVING operators
                            byDefaultHavingOptions();
                    }, 100);
                    });
                    queryDetailColumns = [];
                }, 500);
            }

        }

        // Run byDefaultHavingOptions() on page load to set default values for all "having-operator" dropdowns
        byDefaultHavingOptions();

        function byDefaultHavingOptions(){
            // Loop through each ".having-operator" dropdown
            setTimeout(function(){
                $(".having-operator").each(function () {
                    if (!$(this).val()) {
                        $(this).val(""); // Set default value if none is selected
                    }
                    $(this).trigger("change"); // Trigger change event to update dependent elements
                });
            }, 100);
        }

        // Listen for changes in the ".having-operator" dropdowns
        $(document).on("change", ".having-operator", function () {
            updatehavingbyNotes(this); // Call function to update notes
        });

        // Function to update the notes section when the operator changes
        function updatehavingbyNotes(selectElement) {
            let selectedOption = $(selectElement).find(":selected"); // Get the selected option
            let notes = selectedOption.attr("data-notes") || ""; // Retrieve "data-notes" attribute or set empty string if not found
            $(selectElement).closest(".having").find(".having-notes").text(notes); // Update the corresponding notes section
        }

        function resetColumnSelection() {
            let selectTablesName = [];
            
            let mainTblName = $('#mainTableSelect').val();
            selectTablesName.push(mainTblName);
            
            $('.join-table').each(function() {
                let relTblName = $(this).val();
                selectTablesName.push(relTblName);
            });

            selectTablesName = $.unique(selectTablesName.sort());

            let resetAvailableColumns = {};
            Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                if (Object.values(selectTablesName).includes(table)) {
                    resetAvailableColumns[table] = tableInfo;
                }
            });
            availableColumns = resetAvailableColumns;


            Object.entries(queryDetailColumns).forEach(([queryDetailComment, queryDetailColumn]) => {
                selectedColumns.push(queryDetailColumn);
            });
            queryDetailColumns = [];
        }

        // manage column selection
        $(document).on('click', '.column-select-checkbox', function() {
            let check_this = $(this);
            let table = check_this.attr( 'data-table_name' );
            let column = check_this.val();
            if (check_this.is(':checked')) {
                selectedColumns.push(column);
            } else {
                if (Object.values(selectedColumns).includes(column)) {
                    selectedColumns = selectedColumns.filter(function(selectedColumnsValue) {
                        return selectedColumnsValue !== column;
                    });
                }
            }
            selectedColumns = $.unique(selectedColumns.sort());

            $('.groupby-column').each(function () {
                if ($(this).val() == column) {
                    $(this).parents('.groupby-card').remove();
                }
            });

            if ($('.groupby-column').length <= 0) {
                appendGroupByHtmlContent();
            }
            updateGroupByDropdowns(); // Refresh GroupBy dropdowns when columns change
            updateHavingDropdowns();


            $('.orderby-column').each(function () {
                if ($(this).val() == column) {
                    $(this).parents('.orderby-card').remove();
                }
            });

            if ($('.orderby-column').length <= 0) {
                appendOrderByHtmlContent();
            }
            updateOrderByDropdowns(); // Refresh OrderBy dropdowns when columns change
            updateGroupConditionsDropdowns();

        });

        // Add Join
        $('#addJoin').click(function() {

            let notAddJoinFlag = false;
            $('.join-table').each(function() {
                let relTblName = $(this).val();
                if ( !relTblName || relTblName == '' || relTblName == undefined || relTblName == 'undefined' ) {
                    notAddJoinFlag = true;
                }
            });

            if ( notAddJoinFlag ) {
                toastr.error('{{ __('querybuilder::messages.error_add_join') }}');
                return;
            }

            appendJoinHtmlContent();
        });

        function appendJoinHtmlContent(qryJoinId = null, qryType = null, qryTable = null, qryFirstColumn = null, qrySecondColumn = null) {
            const joinId = qryJoinId || Date.now();

            const joinHtml = `
                <div class="join-card" data-join-id="${joinId}">
                    <span class="remove-join" style="font-size: 25px;">&times;</span>
                    <div class="row">
                        <div class="col-md-2 d-none">
                            <select class="form-select d-none" name="joins[${joinId}][type]">
                                <option value="left" ${ (qryType == 'left') ? 'selected' : '' }>LEFT JOIN</option>
                                <option value="right" ${ (qryType == 'right') ? 'selected' : '' }>RIGHT JOIN</option>
                                <option value="inner" ${ (qryType == 'inner') ? 'selected' : '' }>INNER JOIN</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select join-table" name="joins[${joinId}][table]">
                                <option value="">Select Table</option>
                                ${tableRelations.map(relation => 
                                    `<option value="${relation.referenced_table}" 
                                        ${qryTable == relation.referenced_table ? 'selected' : ''}>
                                        ${
                                            setting_option_val === 'Label' 
                                            ?( tablesComments[relation.referenced_table]?.table_comment ? tablesComments[relation.referenced_table]?.table_comment : relation.referenced_table )
                                            : setting_option_val === 'Key' 
                                                ? relation.referenced_table 
                                                : setting_option_val === 'Both' 
                                                    ? (tablesComments[relation.referenced_table]?.table_comment 
                                                        ? `${tablesComments[relation.referenced_table]?.table_comment} [${relation.referenced_table }]`
                                                        : relation.referenced_table )
                                                    : relation.referenced_table
                                        }
                                    </option>`

                                ).join('')}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control first_column" name="joins[${joinId}][first_column]" 
                                placeholder="First Column" readonly value="${qryFirstColumn || ''}">
                        </div>
                        <div class="col-md-1 text-center">
                            <span>=</span>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control second_column" name="joins[${joinId}][second_column]" 
                                placeholder="Second Column" readonly value="${qrySecondColumn || ''}">
                        </div>
                    </div>
                </div>
            `;
            $('#joinsContainer').append(joinHtml);
        }

        // Remove Join
        $(document).on('click', '.remove-join', function() {
            var remove_table = $(this).attr('data-table');
            var removeSelectedColumns = [];
            let checkboxes = $('#columnSelect').find('#columnSelectTable_' + remove_table).find('.column-select-checkbox[data-table_name="' + remove_table + '"]');

            if( checkboxes.is(':checked') ){
                checkboxes.each(function() {
                    removeSelectedColumns.push($(this).val());
                });
            }

            selectedColumns = selectedColumns.filter(function(selectedColumnsValue) {
                return Object.values(removeSelectedColumns).includes(selectedColumnsValue) ? false : true;
            });
        
            $(this).closest('.join-card').remove();
            
            resetColumnSelection()
            updateColumnSelection(setting_option_val);
        });

        // Handle join table selection
        $(document).on('change', '.join-table', function() {
            const join_table_this = $(this);
            const table = join_table_this.val();
            $('.join-table').removeClass( '.current-join-table-selection' )
            join_table_this.addClass( '.current-join-table-selection' )
            let is_disable = false;
            $('.join-table').each(function() {
                if ( 
                    !$(this).hasClass( '.current-join-table-selection' ) && 
                    $(this).val() == table && 
                    ( 
                        table != null && 
                        table != undefined && 
                        table != '' 
                    ) 
                ) {
                    is_disable = true;
                }
            });

            if ( is_disable ) {
                join_table_this.val('');
                toastr.error('{{ __('querybuilder::messages.error_join_table') }}');
            } else {
                if (table) {
                    let first_column = `${tableRelationsColumns[table]?.table_name}.${tableRelationsColumns[table]?.column_name}`;
                    let second_column = `${tableRelationsColumns[table]?.referenced_table}.${tableRelationsColumns[table]?.referenced_column}`;
                    join_table_this.closest('.join-card').find('.first_column').val( first_column );
                    join_table_this.closest('.join-card').find('.second_column').val( second_column );
                    join_table_this.closest('.join-card').find('.remove-join').attr('data-table', tableRelationsColumns[table]?.referenced_table);
                    var is_join_table = 'yes';
                    loadTableColumns(table,is_join_table);
                } else {
                    join_table_this.closest('.join-card').find('.first_column').val('');
                    join_table_this.closest('.join-card').find('.second_column').val('');
                    join_table_this.closest('.join-card').find('.remove-join').attr('data-table', '');
                    resetColumnSelection();
                    updateColumnSelection(setting_option_val);
                }
            }

        });

        function updateColumnSelection(setting_option_val ='') {
            let columnHtml = '';

            Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                let columns = tableInfo.columns;
                let comments = tableInfo.comments;

                columnHtml += `<div class="columnSelectTableWise mb-4 flex-fill bd-highlight col-md-3 col-sm-2" id="columnSelectTable_${table}">`;
                columnHtml += `<h6>
                                    ${
                                        setting_option_val === 'Label' 
                                        ?( tablesComments[table]?.table_comment ? tablesComments[table]?.table_comment : table )
                                        : setting_option_val === 'Key' 
                                            ? table 
                                            : setting_option_val === 'Both' 
                                                ? (tablesComments[table]?.table_comment  ? `${tablesComments[table]?.table_comment} [${table }]` : table )
                                                : table
                                    }
                                </h6>`;
                columnHtml += `<div 
                                    class="" 
                                    style="
                                            overflow-y: auto;
                                            height: auto;
                                            max-height: 300px;"
                >`;

                columns.forEach(column => {
                    let columnId = `col_${column.full_name}`;
                    let columnLabel = column.comment ? column.comment : column.name;
                    columnHtml += `
                            <div class="form-check column-select-main">
                            <input class="form-check-input column-select-checkbox" type="checkbox" 
                            name="columns[${column.comment ? column.comment : column.name}]" 
                            value="${column.full_name}" 
                            id="col_${column.full_name}"
                            data-table_name="${table}"
                            ${Object.values(selectedColumns).includes(column.full_name) ? 'checked' : ''}
                            >
                            <label class="form-check-label column-select-label" for="col_${column.full_name}">
                            ${
                                setting_option_val == 'Key' 
                                ? (column.name ? column.name : '') 
                                : setting_option_val == 'Label' 
                                ? (column.comment ? column.comment : column.name) 
                                : setting_option_val == 'Both' 
                                ? (column.comment ? column.comment + ' [' + column.name + ']' : column.name) 
                                : (column.comment ? column.comment : column.name)
                            }
                            </label>
                            </div>
                            `;

                });
                columnHtml += `</div>`;
                columnHtml += `</div>`;
               
            });
            $('#columnSelect').html(columnHtml);



            // Update condition dropdowns
            updateConditionDropdowns();
            updateGroupByDropdowns();
            updateHavingDropdowns();
            updateOrderByDropdowns();
            updateGroupConditionsDropdowns();
        }


            // Add Group By row
        $('#addGroupBy').click(function() {
            let notAddGroupByFlag = false;

            $('.groupby-card').each(function() {
                let groupByColumn = $(this).find('.groupby-column').val();
                let groupByAggregation = $(this).find('.groupby-aggregation').val();

                if (!groupByColumn || groupByColumn === '' || groupByColumn === undefined || groupByColumn === 'undefined') {
                    notAddGroupByFlag = true;
                }
            });

            if (notAddGroupByFlag) {
                toastr.error('{{ __('querybuilder::messages.error_groupby') }}');
                return;
            }

            appendGroupByHtmlContent();
            updateGroupByDropdowns();
        });

            // Remove Group By row
        $(document).on('click', '.remove-groupby', function() {
            $(this).closest('.groupby-card').remove();
        });

        // 🏷️ Returns the appropriate table label based on the current display setting (Label / Key / Both)
        function getTableLabel(table) {
            if (setting_option_val === 'Label') {
                return tablesComments[table]?.table_comment || table;
            } else if (setting_option_val === 'Key') {
                return table;
            } else if (setting_option_val === 'Both') {
                return tablesComments[table]?.table_comment
                ? `${tablesComments[table].table_comment} [${table}]`
                : table;
            }
            return table;
        }

        // 🏷️ Returns the appropriate column label based on the current display setting (Label / Key / Both)
        function getColumnLabel(column) {
            if (setting_option_val === 'Key') {
                return column?.name || '';
            } else if (setting_option_val === 'Label') {
                return column?.comment || column.name;
            } else if (setting_option_val === 'Both') {
                return column?.comment
                ? `${column.comment} [${column.name}]`
                : column.name;
            }
            return column?.comment || column.name;
        }

        // 🔁 Generates HTML <option> list for columns based on setting_option_val
        function generateColumnOptionHTML(selectedColumn = '') {
            let columnOptions = '<option value="">{{ __('querybuilder::messages.form_query_select_column') }}</option>';

            Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                tableInfo.columns.forEach(col => {
                    const isSelected = col.full_name === selectedColumn ? 'selected' : '';
                    const tableLabel = getTableLabel(table);
                    const columnLabel = getColumnLabel(col);

                    columnOptions += `<option value="${col.full_name}" ${isSelected}>(${tableLabel}) ${columnLabel}</option>`;
                });
            });

            return columnOptions;
        }

        // Function to append a new Group By row

        function appendGroupByHtmlContent(type = 'normal', qryGrpColumn = null, qryGrpAggregation = null, qryGrpAlias = '') {
            const columns = generateColumnOptionHTML(qryGrpColumn);

            const newRow = `
            <div class="groupby-card mb-2">
                <span class="remove-groupby" style="font-size: 25px;">&times;</span>
                <div class="row">
                    <div class="col-md-4">
                        <select class="form-select groupby-column" name="groupby[${groupByCount}][column]">
                            ${columns}
                        </select>
                        <span class="warning-message text-danger" style="display: none;"></span>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select groupby-aggregation" name="groupby[${groupByCount}][aggregation]">
                            ${generateAggregateOptions(qryGrpAggregation)}
                        </select>
                        <p class="groupby-notes"></p>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control groupby-alias" name="groupby[${groupByCount}][alias]" placeholder="{{ __('querybuilder::messages.form_query_alias_placeholder')}}" value="${qryGrpAlias || ''}">
                        <span class="alias-message text-danger" style="display: none;"></span>
                    </div>
                </div>
            </div>
            `;

            byDefaultAggregateOptions();
            $('#groupby-container').append(newRow);
            groupByCount++;
        }

        function byDefaultAggregateOptions(){
           // Run on page load for all condition-operator selects
            setTimeout(function(){
                $(".groupby-aggregation").each(function () {
                    if (!$(this).val()) {
                        $(this).val(""); // Set default if none is selected
                    }
                    $(this).trigger("change"); // Ensure change event fires
                });
            }, 100);
        }

        function generateAggregateOptions(selectedOperator = '') {
            // Default empty option
            let options = '<option value=""></option>'; 

            // Ensure apply_aggregate exists
            if (typeof apply_aggregate !== 'undefined') {
                // Loop through each category (Functions & Aggregation)
                for (const [category, operators] of Object.entries(apply_aggregate)) {
                    if (operators && Object.keys(operators).length > 0) {
                        options += `<optgroup label="${category}">`;
                        for (const [key, operator] of Object.entries(operators)) {
                            const selected = (selectedOperator === operator.value) ? 'selected' : '';
                            options += `<option value="${operator.value}" ${selected} data-notes="${operator.notes}">${operator.key}</option>`;
                        }
                        options += `</optgroup>`;
                    }
                }
            }

            return options;
        }

         // groupby Update on change
        $(document).on("change", ".groupby-aggregation", function () {
            updateGroupbyNotes(this);
        });

        function updateGroupbyNotes(selectElement) {
            let selectedOption = $(selectElement).find(":selected");
            let notes = selectedOption.attr("data-notes") || "";
            $(selectElement).closest(".groupby-card").find(".groupby-notes").text(notes);
        }

        // Add Group By row
        $('#addOrderBy').click(function() {
            let notAddOrderByFlag = false;

            $('.orderby-card').each(function() {
                let orderByColumn = $(this).find('.orderby-column').val();
                let orderByOrder = $(this).find('.orderby-order').val();

                if (!orderByColumn || orderByColumn === '' || orderByColumn === undefined || orderByColumn === 'undefined') {
                    notAddOrderByFlag = true;
                }
            });

            if (notAddOrderByFlag) {
                toastr.error('{{ __('querybuilder::messages.error_orderby') }}');
                return;
            }

            appendOrderByHtmlContent();
            updateOrderByDropdowns();
        });

        // Remove Group By row
        $(document).on('click', '.remove-orderby', function() {
            $(this).closest('.orderby-card').remove();
        });

        $(document).on('change', '.orderby-column', function() {
            const orderbycolumn_this = $(this);
            const orderbycolumn = orderbycolumn_this.val();
            $('.orderby-column').removeClass( 'current-orderby-column-selection' )
            orderbycolumn_this.addClass( 'current-orderby-column-selection' )
            let is_disable = false;
            $('.orderby-column').each(function() {
                if ( 
                    !$(this).hasClass( 'current-orderby-column-selection' ) && 
                    $(this).val() == orderbycolumn && 
                    ( 
                        orderbycolumn != null && 
                        orderbycolumn != undefined && 
                        orderbycolumn != '' 
                    ) 
                ) {
                    is_disable = true;
                }
            });

            if ( is_disable ) {
                orderbycolumn_this.val('');
                toastr.error('{{ __('querybuilder::messages.error_orderbycolumn') }}');
            }
        });

        // Function to append a new Order By row
        function appendOrderByHtmlContent(type = 'normal', qryOrderColumn = null, qryOrderOrder = null) {
            const columns = generateColumnOptionHTML(qryOrderColumn);

            const newRow = `
                    <div class="orderby-card mb-2">
                        <span class="remove-orderby" style="font-size: 25px;">&times;</span>
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-select orderby-column" name="orderby[${orderByCount}][column]">
                                    ${columns}
                                </select>
                                <span class="warning-message text-danger" style="display: none;"></span>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select orderby-order" name="orderby[${orderByCount}][order]">
                                    <option value="ASC" ${qryOrderOrder != null ? (qryOrderOrder == "ASC" ? 'selected' : '') : ''}>ASC</option>
                                    <option value="DESC" ${qryOrderOrder != null ? (qryOrderOrder == "DESC" ? 'selected' : '') : ''}>DESC</option>
                                </select>
                            </div>
                        </div>
                    </div>
            `;

            $('#orderby-container').append(newRow);

            orderByCount++;
        }

        function generateOperatorOptions(selectedOperator = '') {
            let options = '';
            for (const [key, operator] of Object.entries(conditionOperators)) {
                const selected = (selectedOperator === operator.value) ? 'selected' : '';
                options += `<option value="${operator.value}" data-notes="${operator.notes}" ${selected}>${operator.key}</option>`;
            }
            return options;
        }

        // 🔁 Generates <option> elements for a dropdown, based on selected columns and current label settings
        function generateColumnOptions(selected_column = '') {
            // Default placeholder option
            let columnOptions = '<option value="">{{ __('querybuilder::messages.form_query_select_column') }}</option>';

            // Loop through all tables and their columns
            Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                tableInfo.columns.forEach(column => {
                    const columnValue = column.full_name;

                    // Check if the column checkbox is checked (used when filtering by selected columns)
                    const isChecked = $(`.column-select-checkbox[value="${columnValue}"]`).prop('checked');

                    // Check if this column should be selected by default in the dropdown
                    const isSelected = columnValue === selected_column ? 'selected' : '';

                    // Only show checked columns if selection filter is active, otherwise show all
                    if (selectedColumns.length === 0 || isChecked) {
                        columnOptions += `<option value="${columnValue}" data-option_key="${columnValue}" ${isSelected}>(${getTableLabel(table)}) ${getColumnLabel(column)}</option>`;
                    }
                });
            });

            return columnOptions;
        }

        // 🔄 Refreshes the list of available columns for all condition dropdowns
        function updateConditionDropdowns() {
            $('.condition-column').each(function () {
                // Preserve the selected column if any
                const selected_column = $(this).val();
                // Regenerate options
                $(this).html(generateColumnOptions(selected_column));
            });
        }

        // 🔄 Refreshes the list of available columns for all GROUP BY dropdowns
        function updateGroupByDropdowns() {
            $('.groupby-column').each(function () {
                // Preserve the selected column if any
                const selected_column = $(this).val();
                // Regenerate options
                $(this).html(generateColumnOptions(selected_column));
            });
        }

        // 🔄 Refreshes the list of available columns for all HAVING clause dropdowns
        function updateHavingDropdowns() {
            $('.having-column').each(function () {
                // Preserve the selected column if any
                const selected_column = $(this).val();
                // Regenerate options
                $(this).html(generateColumnOptions(selected_column));
            });
        }

        // 🔄 Refreshes the list of available columns for all ORDER BY dropdowns
        function updateOrderByDropdowns() {
            $('.orderby-column').each(function () {
                // Preserve the selected column if any
                const selected_column = $(this).val();
                // Regenerate options
                $(this).html(generateColumnOptions(selected_column));
            });
        }

        function checkCombineUniqueGroupByRowDetails() {
            
            let selections = aliasSelections = [];
            let isDuplicate = false;
            let message = 'GroupBy selections are valid.';

            $('.groupby-card').each(function() {
                let column = $(this).find('.groupby-column').val();
                let aggregation = $(this).find('.groupby-aggregation').val();
                let alias = $(this).find('.groupby-alias').val();
                let combination = column + '|' + aggregation;

                if (selections.includes(combination)) {
                    isDuplicate = true;
                    $(this).find('.warning-message').text('Duplicate GroupBy combination detected!').show();
                } else {
                    selections.push(combination);
                    $(this).find('.warning-message').text('').hide();
                }

                if (aliasSelections.includes(alias)) {
                    isDuplicate = true;
                    $(this).find('.alias-message').text('Duplicate alias name detected!').show();
                } else {
                    if (alias != '' && alias != null) {
                        aliasSelections.push(alias);
                        $(this).find('.alias-message').text('').hide();
                    }
                }
            });

            if (isDuplicate) {
                message = 'Duplicate data detected in the groupby section!';
            }
            
            return {
                result: isDuplicate,
                message: message
            };
        }


        let groupIndex = 1;

        // Update column dropdowns for all conditions based on selected columns
        function updateGroupConditionsDropdowns() {
            $('.group_conditions_column').each(function () {
                const selected_column = $(this).val();
                $(this).html(generateColumnOptions(selected_column));
            });
            byDefaultGroupConditions(); // Still call this as needed
        }
        // Load existing grouped conditions into the UI
        function loadGroupedConditionsFromQuery(queryDetailGroupConditions = []) {
            if (queryDetailGroupConditions.length > 0) {
                setTimeout(() => {
                    $('#groupBuilder').html(''); // Clear any previous groups

                    // Iterate over each group from query data
                    queryDetailGroupConditions.forEach((group, groupIdx) => {
                        $('#groupBuilder').append(generateGroupHTML(groupIdx));  // Generate group wrapper
                        $(`select[name="groups[${groupIdx}][and-or-conditions]"]`).val(group['and-or-conditions']); // Set group logic

                        const container = $(`[data-group-conditions="${groupIdx}"]`);
                        container.html(''); // Clear default condition

                        // Add each condition inside the group
                        group.group_conditions?.forEach((condition, condIdx) => {
                            const html = generateConditionHTML(groupIdx, condIdx, 'normal', condition?.column, condition?.operator, condition?.value, condition?.conditions);
                            container.append(html); 
                        });
                    });

                    groupIndex = queryDetailGroupConditions.length; // Update global index
                }, 300);
            } else {
                generateGroupHTML(0); // Create default group if none present
            }
        }

        

        // Generate HTML for a single condition row
        function generateConditionHTML(groupId = 0, index = 1, type = 'normal', qryColumn = null, qryOperator = null, qryValue = '', conditions = null) {
            const columns = generateColumnOptionHTML(qryColumn);

            return `
                <div class="condition mb-2">
                    <div class="row g-2">
                        <div class="col-md-3">
                        <select class="form-select group_conditions_column" name="groups[${groupId}][group_conditions][${index}][column]">
                                ${columns}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select group_conditions"
                                name="groups[${groupId}][group_conditions][${index}][operator]">
                               ${generateOperatorOptions(qryOperator)}
                            </select>
                            <p class="operator-notes">Select an operator to see details</p>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control"
                                name="groups[${groupId}][group_conditions][${index}][value]"
                                placeholder="Value" value="${qryValue || ''}">
                        </div> 
                        <div class="col-md-1">
                            <select class="form-select w-auto" name="groups[${groupId}][group_conditions][${index}][conditions]">
                                ${ConditionsAddOr(conditions)}
                            </select>
                        </div>
                        <div class="col-md-1">
                            <span class="remove-group-conditions" style="font-size: 25px;">&times;</span>
                        </div>
                    </div>
                </div>`;

        }

            // Generate HTML for a group wrapper
        function generateGroupHTML(groupId) {
            return `
                <div class="group-container">
                    <div class="group-card mb-2">
                        <div class="condition-group mb-4" data-group="${groupId}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label><strong>Group Logic:</strong></label>
                                <select class="form-select w-auto group-conditions" name="groups[${groupId}][and-or-conditions]">
                                   ${ConditionsAddOr()}
                                </select>
                                <span class="remove-group" style="font-size: 25px;">&times;</span>
                            </div>

                            <div class="nested-group" data-group-conditions="${groupId}">
                                ${generateConditionHTML(groupId, 0)}
                            </div>

                            <button type="button" class="btn btn-secondary btn-sm mt-2 add-condition" data-group="${groupId}">
                                + Add Condition
                            </button>
                        </div>
                    </div>
                </div>`;
        }

        // Validate that all required condition fields in a group are filled
        function isGroupValid(groupEl) {
            let isValid = true;

            const groupLogicSelect = $(groupEl).find('select[name$="[and-or-conditions]"]');
            const groupLogicValue = groupLogicSelect.val();

            if (!groupLogicValue || groupLogicValue === '' || groupLogicValue === 'undefined') {
                isValid = false;
                groupLogicSelect.addClass('border border-danger');
            } else {
                groupLogicSelect.removeClass('border border-danger');
            }

            $(groupEl).find('.condition').each(function () {
                const column = $(this).find('select[name$="[column]"]').val();
                const operator = $(this).find('select[name$="[operator]"]').val();
                const value = $(this).find('input[name$="[value]"]').val();
                const conditions = $(this).find('select[name$="[conditions]"]').val();

                if (!column || !operator || !value || !conditions ||
                    column === 'undefined' || operator === 'undefined' || value === 'undefined' || conditions === 'undefined') {
                    isValid = false;
                $(this).addClass('border border-danger p-2 rounded');
            } else {
                $(this).removeClass('border border-danger p-2 rounded');
            }
        });

            return isValid;
        }


        /**
        * Set default operator and trigger change for operator notes.
        */
        function byDefaultGroupConditions(){
               // Run on page load for all gorup condition selects
            setTimeout(function(){
                $(".group_conditions").each(function () {
                    if (!$(this).val()) {
                        // Set default if none is selected
                        $(this).val("="); 
                    }
                    // Ensure change event fires
                    $(this).trigger("change"); 
                });
            }, 100);
        }

        /**
        * Show operator notes on dropdown change.
        */
        $(document).on("change", ".group_conditions", function () {
            updateGroupConditionsNotes(this);
        });

        /**
        * Display selected operator note beside dropdown.
        */
        function updateGroupConditionsNotes(selectElement) {
            let selectedOption = $(selectElement).find(":selected");
            let notes = selectedOption.attr("data-notes") || "Equal to";
            $(selectElement).closest(".condition").find(".operator-notes").text(notes);
        }

        /**
        * Generate AND/OR options.
        */
        function ConditionsAddOr(selectedConditions = '') {
            let options = '';
            for (const [key, operator] of Object.entries(conditionsAddOr)) {
                const selected = (selectedConditions === operator.value) ? 'selected' : '';
                options += `<option value="${operator.value}"  ${selected}>${operator.key}</option>`;
            }
            return options;
        }


        /** ---------------------------------------
        *  Event Handlers for Dynamic UI
        * --------------------------------------*/

        // Add first group button click
        $('#addMainGroup').on('click', function () {
            const lastGroup = $('.condition-group').last();

            if (lastGroup.length && !isGroupValid(lastGroup)) {
                toastr.error('{{ __('querybuilder::messages.error_add_group_condition') }}');
                return;
            }
            const groupId = `${groupIndex++}`;
            $('#groupBuilder').append(generateGroupHTML(groupId));
            updateGroupConditionsDropdowns();
            byDefaultGroupConditions();
        });


        // Add condition button in a group
        $(document).on('click', '.add-condition', function () {
            const groupId = $(this).data('group');
            const groupEl = $(`.condition-group[data-group="${groupId}"]`);
            if ( !isGroupValid(groupEl)) {
                toastr.error('{{ __('querybuilder::messages.error_add_one_condition') }}');
                return;
            }

            updateGroupConditionsDropdowns();
            const container = $(`[data-group-conditions="${groupId}"]`);
            const conditionCount = container.find('.condition').length;
            container.append(generateConditionHTML(groupId, conditionCount));
            updateGroupConditionsDropdowns();
        });

        // Remove entire group
        $(document).on('click', '.remove-group', function () {
            $(this).closest('.group-container').remove();
        });

        // Remove single condition row
        $(document).on('click', '.remove-group-conditions', function () {
            $(this).closest('.condition').remove();
        });

        // Auto add new group on group logic change if last one
        $(document).on('change', '.group-conditions', function () {
            const parentGroup = $(this).closest('.group-container');
            const nextGroupExists = parentGroup.next('.group-container').length > 0;
             const groupEl = parentGroup.find('.condition-group');

            if ( !isGroupValid(groupEl)) {
                toastr.error('{{ __('querybuilder::messages.error_group_conditions') }}');
                return;
            }

            if (!nextGroupExists) {
                const groupId = `${groupIndex++}`;
                $('#groupBuilder').append(generateGroupHTML(groupId));
            }
        });

        $(document).on('change keyup', '.group-container select, .group-container input', function () {
            $(this).removeClass('border border-danger');
            $(this).closest('.condition').removeClass('border border-danger p-2 rounded');
        });


        // Form submission
        $('#queryForm').submit(function(e) {
            e.preventDefault();

            $('.resultsTable_mainDiv').html('<div id="resultsTable"></div>');

            var main_table = $('#mainTableSelect').val()
            if ( !main_table || main_table == '' || main_table == undefined || main_table == 'undefined' ) {
                toastr.error('{{ __('querybuilder::messages.error_main_table_select') }}');
                return;
            }

            var groupbyCheck = checkCombineUniqueGroupByRowDetails();
            if (groupbyCheck.result) {
                showSweetalert('warning', groupbyCheck.message, '', 'OK');
                return;
            }

            if (selectedColumns.length > 0) {
                let groupbyColumns = [];
                $('.groupby-column').each(function() {
                    let groupbyColumn = $(this).val();
                    if (groupbyColumn != '' && groupbyColumn != null) {
                        groupbyColumns.push(groupbyColumn);
                    }
                });

                groupbyColumns = $.unique(groupbyColumns.sort());
                if ( groupbyColumns.length > 0 && selectedColumns.length != groupbyColumns.length ) {
                    $('.resultsTable_mainDiv').html(`<div class="alert alert-danger" role="alert">Your selected fields are not grouped correctly. Please ensure that all non-aggregated fields are included in the GroupBy selection or use an aggregate function.</div>`);
                    return;
                }
            }

            $('.orderby-card').each(function() {
                let orderbyColumn = $(this).find('.orderby-column').val();
                if (orderbyColumn == '' || orderbyColumn == null) {
                    $(this).remove();
                }
            });
            
            const form_details = $(this).serializeArray();
            let formData = {};
            form_details.forEach(form_detail => {
                formData[form_detail.name] = form_detail.value;
            });

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

        $('.btn-saveQuery').click(function() {
            $('#querySaveForm').submit();
        });

        // querySaveForm submission
        $('#querySaveForm').submit(function(e) {
            e.preventDefault();

            var queryReportTitle = $('#queryReportTitle').val()
            if ( !queryReportTitle || queryReportTitle == '' || queryReportTitle == undefined || queryReportTitle == 'undefined' ) {
                toastr.error('{{ __('querybuilder::messages.error_query_report_title') }}');
                return;
            }
            
            var main_table = null;
            var query_details = {};

            const queryFormData = $('#queryForm').serializeArray();
            
            Object.entries(queryFormData).forEach(([index, query_detail]) => {
                query_details[query_detail.name] = query_detail.value;
                if ( query_detail.name == 'main_table' ) {
                    main_table = query_detail.value
                }
            });

            if ( !main_table || main_table == '' || main_table == undefined || main_table == 'undefined' ) {
                toastr.error('{{ __('querybuilder::messages.error_main_table') }}');
            } else {

                var formData = new FormData(this);
                formData.append('query_details', JSON.stringify(query_details));

                toastr.clear();

                $.ajax({
                    url: `{{route('api.queries.save')}}`,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if ( response.result ) {
                            $('#saveQueryModal').modal('hide');
                            toastr.success( response.message );
                            window.location.href = '{{ route( 'queries.index' ) }}';

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