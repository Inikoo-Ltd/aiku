<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $deposit_request_id
 * @property int $aspo_deposit_id
 * @property int $organisation_id
 * @property numeric $amount
 * @property numeric $exchange
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property-read DepositRequest $depositRequest
 * @property-read AspoDeposit $aspoDeposit
 * @property-read Organisation $organisation
 */
class DepositRequestItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'   => 'decimal:2',
        'exchange' => 'decimal:6',
        'paid_at'  => 'datetime',
    ];

    public function depositRequest(): BelongsTo
    {
        return $this->belongsTo(DepositRequest::class);
    }

    public function aspoDeposit(): BelongsTo
    {
        return $this->belongsTo(AspoDeposit::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
