<?php

namespace App\Models\Workspace;

use App\Models\HumanResources\Employee;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $table = 'workspace_notes';

    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
