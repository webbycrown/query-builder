<?php

namespace Webbycrown\QueryBuilder\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

/**
 * QueryReports Model
 * 
 * Represents the "reports" table in the database and defines its structure, 
 * fillable attributes, hidden attributes, and type casting.
 */
class QueryReports extends Model
{
    use HasFactory;


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reports'; // Enables model factory support for database seeding and testing.
    
    /**
     * The attributes that are mass assignable.
     * 
     * These fields can be filled using Eloquent's create() or update() methods.
     *
     * @var array<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'query_details',
    ];

    /**
     * The attributes that should be hidden when the model is converted to an array or JSON.
     * 
     * These fields won't be included in API responses or serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    
    /**
     * The attributes that should be cast to native types.
     * 
     * This ensures 'query_details' is automatically converted to an array when accessed.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'query_details' => 'array',
    ];
}
