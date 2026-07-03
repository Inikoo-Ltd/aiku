<?php

namespace App\Models\Workspace;

use App\Models\HumanResources\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\Workspace\TaskStatusEnum;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use SoftDeletes;
    use InGroup;

    protected $table = 'workspace_tasks';

    protected $guarded = [];

    protected $casts = [
        'status' => TaskStatusEnum::class,
    ];

    public function assigner() : BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigner_id');
    }

    public function assignee() : BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }
}
