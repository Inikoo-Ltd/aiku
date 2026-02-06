<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
