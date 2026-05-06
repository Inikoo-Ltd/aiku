<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $leave_id
 * @property int $approver_id
 * @property int $sequence_number
 * @property string $status
 * @property string|null $comments
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read User|null $approver
 * @property-read \App\Models\HumanResources\Leave|null $leave
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprovalRecord approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprovalRecord byLeave(\App\Models\HumanResources\Leave $leave)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprovalRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprovalRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprovalRecord pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprovalRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApprovalRecord rejected()
 * @mixin \Eloquent
 */
class LeaveApprovalRecord extends Model
{
    protected $casts = [
        'decided_at' => 'datetime',
    ];

    protected $guarded = [];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(Leave::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByLeave($query, Leave $leave)
    {
        return $query->where('leave_id', $leave->id);
    }
}
