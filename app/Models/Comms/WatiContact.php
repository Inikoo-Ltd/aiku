<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Models\Comms;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatiContact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'teams'           => 'array',
            'segments'        => 'array',
            'custom_params'   => 'array',
            'opted_in'        => 'boolean',
            'allow_broadcast' => 'boolean',
            'allow_sms'       => 'boolean',
            'wati_created_at' => 'datetime',
            'wati_updated_at' => 'datetime',
            'synced_at'       => 'datetime',
        ];
    }

    public function isLinked(): bool
    {
        return $this->customer_id !== null;
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
