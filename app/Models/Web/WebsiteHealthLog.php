<?php

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

class WebsiteHealthLog extends Model
{
    protected $fillable = [
        'url',
        'is_up',
        'status_code',
        'error_message',
        'last_deployment_date',
    ];

    protected $casts = [
        'is_up' => 'boolean',
        'status_code' => 'integer',
    ];
}
