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
        let setting_option_val = $('.setting_option').val();

        byDefaultConditionOperator();

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
            $('#conditions').html('');
            $('#groupby-container').html('');
            $('#orderby-container').html('');
            conditionCount = 0;
            groupByCount = 0;
            orderByCount = 0;
            if ( queryDetailConditions.length > 0 ) {
                setTimeout(function(){
                    Object.entries(queryDetailConditions).forEach(([qryConditionKey, qryCondition]) => {
                        var qryColumn = qryCondition?.column || null;
                        var qryOperator = qryCondition?.operator || null;
                        var qryValue = qryCondition?.value || null;
                        appendConditionHtmlContent('edit', qryColumn, qryOperator, qryValue);
                    });
                    queryDetailColumns = [];
                }, 500);
            }else{

                appendConditionHtmlContent('edit')
            }
            if ( queryDetailGroupby.length > 0 ) {
                setTimeout(function(){
                    Object.entries(queryDetailGroupby).forEach(([qryGroupKey, qryGroupby]) => {
                        var qryGrpColumn = qryGroupby?.column || null;
                        var qryGrpAggregation = qryGroupby?.aggregation || null;
                        var qryGrpAlias = qryGroupby?.alias || null;
                        appendGroupByHtmlContent('edit', qryGrpColumn, qryGrpAggregation, qryGrpAlias);
                    });
                    queryDetailColumns = [];
                }, 500);
            } else {
                appendGroupByHtmlContent('edit')
            }
            if ( queryDetailOrderby.length > 0 ) {
                setTimeout(function(){
                    Object.entries(queryDetailOrderby).forEach(([qryOrderKey, qryOrderby]) => {
                        var qryOrderColumn = qryOrderby?.column || null;
                        var qryOrderOrder = qryOrderby?.order || null;
                        appendOrderByHtmlContent('edit', qryOrderColumn, qryOrderOrder);
                    });
                    queryDetailColumns = [];
                }, 500);
            } else {
                appendOrderByHtmlContent('edit')
            }

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
                toastr.error('You cannot join the add because old is not selected.');
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
                toastr.error('This table is already selected. Please choose a different join.');
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
                toastr.error('You cannot add a new Group By column because an existing one is not selected.');
                return;
            }

            appendGroupByHtmlContent();
            updateGroupByDropdowns();
        });

            // Remove Group By row
        $(document).on('click', '.remove-groupby', function() {
            $(this).closest('.groupby-card').remove();
        });

        // Function to append a new Group By row
        function appendGroupByHtmlContent(type = 'normal', qryGrpColumn = null, qryGrpAggregation = null, qryGrpAlias = '') {
            let columns = $('.groupby-column').first().html() || '';
            let columnOptions = '<option value="">Select Column</option>';
            Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                let tableColumns = tableInfo.columns;
                tableColumns.forEach(tableColumn => {
                    columnOptions += `<option value="${tableColumn.full_name}" 
                                ${tableColumn.full_name == qryGrpColumn 
                                    ? 'selected' 
                                    : ''
                                    }>(
                                        ${
                                            setting_option_val === 'Label' 
                                            ?( tablesComments[table]?.table_comment ? tablesComments[table]?.table_comment : table )
                                            : setting_option_val === 'Key' 
                                                ? table 
                                                : setting_option_val === 'Both' 
                                                    ? (tablesComments[table]?.table_comment  ? `${tablesComments[table]?.table_comment} [${table }]` : table )
                                                    : table
                                        }
                                    ) ${
                                        setting_option_val == 'Key' 
                                        ? (tableColumn?.name ? tableColumn.name : '') 
                                        : setting_option_val == 'Label' 
                                        ? (tableColumn?.comment ? tableColumn.comment : tableColumn.name) 
                                        : setting_option_val == 'Both' 
                                        ? (tableColumn?.comment ? tableColumn.comment + ' [' + tableColumn.name + ']' : tableColumn.name) 
                                        : (tableColumn?.comment ? tableColumn.comment : tableColumn.name)
                                    }</option>`;
                                });
            });

            columns = columnOptions;

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
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control groupby-alias" name="groupby[${groupByCount}][alias]" placeholder="Alias Name" value="${qryGrpAlias ? qryGrpAlias : ''}">
                                <span class="alias-message text-danger" style="display: none;"></span>
                            </div>
                        </div>
                    </div>
            `;

            $('#groupby-container').append(newRow);

            groupByCount++;
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
                            options += `<option value="${operator.value}" ${selected}>${operator.key}</option>`;
                        }
                        options += `</optgroup>`;
                    }
                }
            }

            return options;
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
                toastr.error('You cannot add a new Order By column because an existing one is not selected.');
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
                toastr.error('You have already selected this column. Please choose a different column for ordering.');
            }
        });

        // Function to append a new Order By row
        function appendOrderByHtmlContent(type = 'normal', qryOrderColumn = null, qryOrderOrder = null) {
            let columns = $('.orderby-column').first().html() || '';
            let columnOptions = '<option value="">Select Column</option>';
            Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                let tableColumns = tableInfo.columns;
                tableColumns.forEach(tableColumn => {
                    columnOptions += `<option value="${tableColumn.full_name}" 
                                ${tableColumn.full_name == qryOrderColumn 
                                    ? 'selected' 
                                    : ''
                                    }>(
                                        ${
                                            setting_option_val === 'Label' 
                                            ?( tablesComments[table]?.table_comment ? tablesComments[table]?.table_comment : table )
                                            : setting_option_val === 'Key' 
                                                ? table 
                                                : setting_option_val === 'Both' 
                                                    ? (tablesComments[table]?.table_comment  ? `${tablesComments[table]?.table_comment} [${table }]` : table )
                                                    : table
                                        }
                                    ) ${
                                        setting_option_val == 'Key' 
                                        ? (tableColumn?.name ? tableColumn.name : '') 
                                        : setting_option_val == 'Label' 
                                        ? (tableColumn?.comment ? tableColumn.comment : tableColumn.name) 
                                        : setting_option_val == 'Both' 
                                        ? (tableColumn?.comment ? tableColumn.comment + ' [' + tableColumn.name + ']' : tableColumn.name) 
                                        : (tableColumn?.comment ? tableColumn.comment : tableColumn.name)
                                    }</option>`;
                                });
            });

            columns = columnOptions;

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

        // Add condition row
        $('#addCondition').click(function() {

            let notAddConditionFlag = false;
            $('.condition-card').each(function() {
                let conditionColumn = $(this).find('.condition-column').val();
                let conditionOperator = $(this).find('.condition-operator').val();
                let conditionValue = $(this).find('.condition-value').val();

                if ( 
                    !conditionColumn || conditionColumn == '' || conditionColumn == undefined || conditionColumn == 'undefined' ||
                    !conditionOperator || conditionOperator == '' || conditionOperator == undefined || conditionOperator == 'undefined' ||
                    !conditionValue || conditionValue == '' || conditionValue == undefined || conditionValue == 'undefined' 
                ) {
                    notAddConditionFlag = true;
                }
            });

            if ( notAddConditionFlag ) {
                toastr.error('You cannot condition the add because old is not selected.');
                return;
            }

            appendConditionHtmlContent();
        });

        // Remove condition row
        $(document).on('click', '.remove-condition', function() {
            $(this).closest('.condition-card').remove();
        });

        function appendConditionHtmlContent(type = 'normal', qryColumn = null, qryOperator = null, qryValue = '') {
            let columns = $('.condition-column').first().html() || '';

            let columnOptions = '<option value="">Select Column</option>';
            Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                let tableColumns = tableInfo.columns;
                tableColumns.forEach(tableColumn => {
                    columnOptions += `<option value="${tableColumn.full_name}" 
                                            ${tableColumn.full_name == qryColumn 
                                                ? 'selected' 
                                                : ''
                                                }>(
                                                    ${
                                                        setting_option_val === 'Label' 
                                                        ?( tablesComments[table]?.table_comment ? tablesComments[table]?.table_comment : table )
                                                        : setting_option_val === 'Key' 
                                                            ? table 
                                                            : setting_option_val === 'Both' 
                                                                ? (tablesComments[table]?.table_comment  ? `${tablesComments[table]?.table_comment} [${table }]` : table )
                                                                : table
                                                    }
                                                ) ${
                                                    setting_option_val == 'Key' 
                                                    ? (tableColumn?.name ? tableColumn?.name : '') 
                                                    : setting_option_val == 'Label' 
                                                    ? (tableColumn?.comment ? tableColumn?.comment : tableColumn?.name) 
                                                    : setting_option_val == 'Both' 
                                                    ? (tableColumn?.comment ? tableColumn?.comment + ' [' + tableColumn?.name + ']' : tableColumn?.name) 
                                                    : (tableColumn?.comment ? tableColumn?.comment : tableColumn?.name)
                                                }</option>`;
                                            });

                 
            });
            columns = columnOptions;

            const newRow = `
                <div class="condition-card mb-2">
                    <span class="remove-condition" style="font-size: 25px;">&times;</span>
                    <div class="row">
                        <div class="col-md-4">
                            <select class="form-select condition-column" name="conditions[${conditionCount}][column]">
                                ${columns}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select condition-operator" name="conditions[${conditionCount}][operator]">
                                ${generateOperatorOptions(qryOperator)}
                            </select>
                            <p class="operator-notes">Select an operator to see details</p>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control condition-value" name="conditions[${conditionCount}][value]" placeholder="Value" value="${qryValue ? qryValue : ''}">
                        </div>
                    </div>
                </div>
            `;
            byDefaultConditionOperator();

            $('#conditions').append(newRow);
            conditionCount++;
        }

        function generateOperatorOptions(selectedOperator = '') {
            let options = '';
            for (const [key, operator] of Object.entries(conditionOperators)) {
                const selected = (selectedOperator === operator.value) ? 'selected' : '';
                options += `<option value="${operator.value}" data-notes="${operator.notes}" ${selected}>${operator.key}</option>`;
            }
            return options;
        }

        function byDefaultConditionOperator(){
           // Run on page load for all condition-operator selects
            setTimeout(function(){
                $(".condition-operator").each(function () {
            if (!$(this).val()) {
                $(this).val("="); // Set default if none is selected
            }
            $(this).trigger("change"); // Ensure change event fires
        });
            }, 100);
        }

        // Update on change
        $(document).on("change", ".condition-operator", function () {
            updateOperatorNotes(this);
        });

        function updateOperatorNotes(selectElement) {
            let selectedOption = $(selectElement).find(":selected");
            let notes = selectedOption.attr("data-notes") || "Equal to";
            $(selectElement).closest(".condition-card").find(".operator-notes").text(notes);
        }

        function updateConditionDropdowns() {
            $('.condition-column').each(function() {
                let selected_column = $(this).val();
                let columnOptions = '<option value="">Select Column</option>';

                Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                    let columns = tableInfo.columns;
                    columns.forEach(column => {
                        columnOptions += `<option value="${column.full_name}" 
                                            ${column.full_name == selected_column 
                                                ? 'selected' 
                                                : ''
                                                }>(
                                                    ${
                                                        setting_option_val === 'Label' 
                                                        ?( tablesComments[table]?.table_comment ? tablesComments[table]?.table_comment : table )
                                                        : setting_option_val === 'Key' 
                                                            ? table 
                                                            : setting_option_val === 'Both' 
                                                                ? (tablesComments[table]?.table_comment  ? `${tablesComments[table]?.table_comment} [${table }]` : table )
                                                                : table
                                                    }
                                                ) ${
                                                   setting_option_val == 'Key' 
                                                   ? (column?.name ? column.name : '') 
                                                   : setting_option_val == 'Label' 
                                                   ? (column?.comment ? column.comment : column.name) 
                                                   : setting_option_val == 'Both' 
                                                   ? (column?.comment ? column.comment + ' [' + column.name + ']' : column.name) 
                                                   : (column?.comment ? column.comment : column.name)
                                            }</option>`;
                    });
                });
                $(this).html(columnOptions);
            });
        }

        function updateGroupByDropdowns() {
            $('.groupby-column').each(function () {
                let selected_column = $(this).val();
                let columnOptions = '<option value="">Select Column</option>';

                Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                    let columns = tableInfo.columns;

                    columns.forEach(column => {
                        let columnValue = column.full_name;

                        let tableLabel = setting_option_val === 'Label' 
                                            ?( tablesComments[table]?.table_comment ? tablesComments[table]?.table_comment : table )
                                            : setting_option_val === 'Key' 
                                                ? table 
                                                : setting_option_val === 'Both' 
                                                    ? (tablesComments[table]?.table_comment  ? `${tablesComments[table]?.table_comment} [${table}]` : table )
                                                    : table

                        let columnLabel = setting_option_val == 'Key' 
                                            ? (column?.name ? column.name : '') 
                                            : setting_option_val == 'Label' 
                                                ? (column?.comment ? column.comment : column.name) 
                                                : setting_option_val == 'Both' 
                                                    ? (column?.comment ? column.comment + ' [' + column.name + ']' : column.name) 
                                                    : (column?.comment ? column.comment : column.name)

                        let isChecked = $(`.column-select-checkbox[value="${columnValue}"]`).prop('checked');
                        let isSelected = columnValue === selected_column ? 'selected' : '';

                        // ✅ Only include columns that are checked
                        if (selectedColumns.length > 0) {
                            if (isChecked) {
                                columnOptions += `<option value="${columnValue}" data-option_key="${columnValue}" ${isSelected}>(${tableLabel}) ${columnLabel}</option>`;
                            }
                        }else{
                            columnOptions += `<option value="${columnValue}" data-option_key="${columnValue}" ${isSelected}>(${tableLabel}) ${columnLabel}</option>`;

                        }
                    });
                });

                $(this).html(columnOptions);
            });
        }

        function updateHavingDropdowns() {
            $('.having-column').each(function () {
                let selected_column = $(this).val();
                let columnOptions = '<option value="">Select Column</option>';

                Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                    let columns = tableInfo.columns;

                    columns.forEach(column => {
                        let columnValue = column.full_name;

                        let tableLabel = setting_option_val === 'Label' 
                                            ?( tablesComments[table]?.table_comment ? tablesComments[table]?.table_comment : table )
                                            : setting_option_val === 'Key' 
                                                ? table 
                                                : setting_option_val === 'Both' 
                                                    ? (tablesComments[table]?.table_comment  ? `${tablesComments[table]?.table_comment} [${table}]` : table )
                                                    : table

                        let columnLabel = setting_option_val == 'Key' 
                                            ? (column?.name ? column.name : '') 
                                            : setting_option_val == 'Label' 
                                                ? (column?.comment ? column.comment : column.name) 
                                                : setting_option_val == 'Both' 
                                                    ? (column?.comment ? column.comment + ' [' + column.name + ']' : column.name) 
                                                    : (column?.comment ? column.comment : column.name)

                        let isChecked = $(`.column-select-checkbox[value="${columnValue}"]`).prop('checked');
                        let isSelected = columnValue === selected_column ? 'selected' : '';

                        // ✅ Only include columns that are checked
                        if (selectedColumns.length > 0) {
                            if (isChecked) {
                                columnOptions += `<option value="${columnValue}" data-option_key="${columnValue}" ${isSelected}>(${tableLabel}) ${columnLabel}</option>`;
                            }
                        }else{
                            columnOptions += `<option value="${columnValue}" data-option_key="${columnValue}" ${isSelected}>(${tableLabel}) ${columnLabel}</option>`;

                        }
                    });
                });

                $(this).html(columnOptions);
            });
        }

        function updateOrderByDropdowns() {
            $('.orderby-column').each(function () {
                let selected_column = $(this).val();
                let columnOptions = '<option value="">Select Column</option>';

                Object.entries(availableColumns).forEach(([table, tableInfo]) => {
                    let columns = tableInfo.columns;

                    columns.forEach(column => {
                        let columnValue = column.full_name;

                        let tableLabel = setting_option_val === 'Label' 
                                            ?( tablesComments[table]?.table_comment ? tablesComments[table]?.table_comment : table )
                                            : setting_option_val === 'Key' 
                                                ? table 
                                                : setting_option_val === 'Both' 
                                                    ? (tablesComments[table]?.table_comment  ? `${tablesComments[table]?.table_comment} [${table}]` : table )
                                                    : table

                        let columnLabel = setting_option_val == 'Key' 
                                            ? (column?.name ? column.name : '') 
                                            : setting_option_val == 'Label' 
                                                ? (column?.comment ? column.comment : column.name) 
                                                : setting_option_val == 'Both' 
                                                    ? (column?.comment ? column.comment + ' [' + column.name + ']' : column.name) 
                                                    : (column?.comment ? column.comment : column.name)

                        let isChecked = $(`.column-select-checkbox[value="${columnValue}"]`).prop('checked');
                        let isSelected = columnValue === selected_column ? 'selected' : '';

                        // ✅ Only include columns that are checked
                        if (selectedColumns.length > 0) {
                            if (isChecked) {
                                columnOptions += `<option value="${columnValue}" data-option_key="${columnValue}" ${isSelected}>(${tableLabel}) ${columnLabel}</option>`;
                            }
                        }else{
                            columnOptions += `<option value="${columnValue}" data-option_key="${columnValue}" ${isSelected}>(${tableLabel}) ${columnLabel}</option>`;

                        }
                    });
                });

                $(this).html(columnOptions);
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

        // Form submission
        $('#queryForm').submit(function(e) {
            e.preventDefault();

            $('.resultsTable_mainDiv').html('<div id="resultsTable"></div>');

            var main_table = $('#mainTableSelect').val()
            if ( !main_table || main_table == '' || main_table == undefined || main_table == 'undefined' ) {
                toastr.error('Query details have not been selected.');
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
                toastr.error('Query title has not been entered.');
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
                toastr.error('Query details have not been selected.');
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