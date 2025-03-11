<?php

use Illuminate\Support\Facades\Route;
use Programmer9WC\QueryBuilder\Http\Controllers\QueryBuilderController;

/**
 * Group routes under the 'web' middleware for session management, CSRF protection, etc.
 * The 'auth' middleware ensures that only authenticated users can access these routes.
 */
Route::middleware(['web', 'auth'])->group(function () {

    /**
     * Query Builder Routes
     * 
     * These routes provide a user interface for building and managing database queries.
     * They are prefixed with 'queries' to logically group related functionalities.
     */
    Route::controller(QueryBuilderController::class)->prefix('queries')->group(function () {

        /**
         * Display the list of saved queries.
         */
        Route::get('/', 'index')->name('queries.index');

        /**
         * Show the form to create a new query.
         */
        Route::get('/add', 'add')->name('queries.add');

        /**
         * Show the form to edit an existing query.
         * 
         * @param int $id Query ID to be edited.
         */
        Route::get('/edit/{id}', 'edit')->name('queries.edit');

        /**
         * View the details of a specific query.
         * 
         * @param int $id Query ID to be viewed.
         */
        Route::get('/view/{id}', 'view')->name('queries.view');

    });

    /**
     * Query Builder API Routes
     * 
     * These routes handle API calls related to querying database information dynamically.
     * They are prefixed with 'api/queries' to clearly differentiate them from UI routes.
     */
    Route::controller(QueryBuilderController::class)->prefix('api/queries')->group(function () {

        /**
         * Fetch the columns of a given database table.
         * 
         * @param string $table Table name.
         */
        Route::get('/columns/{table}', 'getColumnsByTable')->name('api.queries.columns');

        /**
         * Retrieve the relational data of a specified table.
         * 
         * @param string $table Table name.
         */
        Route::get('/relations/{table}', 'getRelationsByTable')->name('api.queries.relations');

        /**
         * Perform a search operation based on query details.
         */
        Route::get('/search', 'getDataByQueryDetails')->name('api.queries.search');

        /**
         * Save query details for future use.
         */
        Route::post('/save', 'saveQueryDetails')->name('api.queries.save');

        /**
         * Delete a specific saved query.
         */
        Route::post('/delete', 'delete')->name('api.queries.delete');

    });

});
