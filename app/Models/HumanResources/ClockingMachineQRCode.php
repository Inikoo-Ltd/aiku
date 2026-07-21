<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $clocking_machine_id
 * @property string|null $label
 * @property string $hash
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\ClockingMachine|null $clockingMachine
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineQRCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineQRCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineQRCode query()
 * @mixin \Eloquent
 */
class ClockingMachineQRCode extends Model
{
    protected $table = 'clocking_machine_qr_codes';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function clockingMachine(): BelongsTo
    {
        return $this->belongsTo(ClockingMachine::class);
    }
}
