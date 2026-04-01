<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveApprover extends Model
{
    use InOrganisation;
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByOrganisation($query, Organisation $organisation)
    {
        return $query->where('organisation_id', $organisation->id);
    }

    public function scopeBySequence($query, int $sequence)
    {
        return $query->where('sequence_number', $sequence);
    }
}
