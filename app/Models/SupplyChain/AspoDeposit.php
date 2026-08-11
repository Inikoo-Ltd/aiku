<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\AspoDeposit\AspoDepositStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $agent_id
 * @property int $agent_supplier_purchase_order_id
 * @property string|null $reference
 * @property numeric $amount
 * @property int $currency_id
 * @property AspoDepositStateEnum $state
 * @property \Illuminate\Support\Carbon|null $paid_to_supplier_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $notes
 * @property-read Agent $agent
 * @property-read AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder
 * @property-read Currency $currency
 */
class AspoDeposit extends Model implements Auditable
{
    use InGroup;
    use HasHistory;

    protected $guarded = [];

    protected $casts = [
        'state'                => AspoDepositStateEnum::class,
        'amount'                => 'decimal:2',
        'paid_to_supplier_at'   => 'datetime',
        'refunded_at'           => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function agentSupplierPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(AgentSupplierPurchaseOrder::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function depositRequestItems(): HasMany
    {
        return $this->hasMany(DepositRequestItem::class);
    }

    public function stockDeliveryApplications(): HasMany
    {
        return $this->hasMany(\App\Models\GoodsIn\StockDeliveryDepositApplication::class);
    }

    public function getAppliedAmountAttribute(): float
    {
        return (float) $this->stockDeliveryApplications()->sum('amount');
    }

    public function getUnappliedAmountAttribute(): float
    {
        return (float) $this->amount - $this->applied_amount;
    }
}
