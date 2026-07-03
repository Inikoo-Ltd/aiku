<?php

namespace App\Models\Workspace;

use App\Models\HumanResources\Employee;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use InGroup;

    protected $table = 'workspace_notes';

    protected $guarded = [];

    public function employee() : BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
