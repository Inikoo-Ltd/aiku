<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $agent_id
 * @property string|null $reference
 * @property int $currency_id
 * @property DepositRequestStateEnum $state
 * @property \Illuminate\Support\Carbon $requested_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property-read Agent $agent
 * @property-read Currency $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DepositRequestItem> $items
 */
class DepositRequest extends Model
{
    use InGroup;

    protected $guarded = [];

    protected $casts = [
        'state'        => DepositRequestStateEnum::class,
        'requested_at' => 'datetime',
        'settled_at'   => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DepositRequestItem::class);
    }
}
