<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 18:17:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\DevOps;

use Illuminate\Database\Eloquent\Model;

class CiRun extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'jobs'         => '{}',
        'deploy_tasks' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'jobs'         => 'array',
            'deploy_tasks' => 'array',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
