<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 11:03:10 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;

class ScheduledTaskLog extends Model
{
    protected $fillable = [
        'task_name',
        'task_type',
        'scheduled_at',
        'started_at',
        'finished_at',
        'status',
        'error_message',
        'duration',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
        'scheduled_at' => 'string',
    ];
}
