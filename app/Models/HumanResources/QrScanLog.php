<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $organisation_id
 * @property int|null $workplace_id
 * @property int|null $clocking_machine_id
 * @property int|null $employee_id
 * @property string|null $qr_token
 * @property \Illuminate\Support\Carbon $scanned_at
 * @property string|null $lat
 * @property string|null $lng
 * @property string|null $status
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\ClockingMachine|null $clockingMachine
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrScanLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrScanLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QrScanLog query()
 * @mixin \Eloquent
 */
class QrScanLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scanned_at' => 'datetime',
        'is_valid' => 'boolean',
        'coordinates' => 'array',
    ];

    public function clockingMachine(): BelongsTo
    {
        return $this->belongsTo(ClockingMachine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
