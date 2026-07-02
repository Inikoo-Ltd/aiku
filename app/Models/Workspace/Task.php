<?php

namespace App\Models\Workspace;

use App\Models\HumanResources\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $table = 'workspace_tasks';

    protected $guarded = [];

    public function assigner()
    {
        return $this->belongsTo(Employee::class, 'assigner_id');
    }

    public function assignee()
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }
}
