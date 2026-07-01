<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ip_geolocation_id
 * @property bool $was_blocked
 * @property \Illuminate\Support\Carbon $last_request_at
 * @property int $number_requests
 */
class RestrictedCountryRegionLog extends Model
{
    protected $table = 'restricted_country_region_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'was_blocked'     => 'boolean',
            'last_request_at' => 'datetime',
        ];
    }

    public function ipGeolocation(): BelongsTo
    {
        return $this->belongsTo(IpGeolocation::class);
    }
}
