<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'team_id',
        'activity_name',
        'log_content',
        'receipt_number',
        'status',
    ];
}
