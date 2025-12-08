<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 11:03:10 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $task_name
 * @property string $task_type
 * @property string $scheduled_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property float|null $duration
 * @property string $status
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskLog query()
 *
 * @mixin \Eloquent
 */
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
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'scheduled_at' => 'string',
    ];
}
