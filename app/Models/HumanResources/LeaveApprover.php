<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name User name diambil dari users table
 * @property int $user_id
 * @property int $sequence_number Level approval: 1, 2, 3
 * @property string|null $description
 * @property bool $is_active
 * @property int $organisation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Organisation $organisation
 * @property-read User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprover active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprover byOrganisation(\App\Models\SysAdmin\Organisation $organisation)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprover bySequence(int $sequence)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprover newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprover newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprover query()
 * @mixin \Eloquent
 */
class LeaveApprover extends Model
{
    use InOrganisation;

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
