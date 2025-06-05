<?php

namespace Webbycrown\QueryBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class ScheduledReport extends Model
{
    protected $fillable = [
        'report_type',
        'frequency',
        'time',
        'email',
        'cc_email',
        'bcc_email',
        'subject',
        'body',
        'format',
        'record_limit',
        'database',
        'active',
    ];
}
