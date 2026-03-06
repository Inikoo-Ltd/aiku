<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestrictedException extends Model
{
    protected $guarded = [];

    protected $casts = [
        'from_date' => 'date',
        'to_date'   => 'date',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function restrictedPeriod(): BelongsTo
    {
        return $this->belongsTo(RestrictedPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
