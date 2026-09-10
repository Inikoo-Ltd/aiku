<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $task_id
 * @property string $type
 * @property string $status
 * @property int|null $model_id
 * @property string|null $model_type
 * @property string $name
 * @property string|null $description
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Model|\Eloquent|null $model
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\SysAdmin\Task $task
 * @mixin \Eloquent
 */
class SubTask extends Model
{
    use InOrganisation;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'         => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
