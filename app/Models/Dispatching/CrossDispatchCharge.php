<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\CrossDispatch\CrossDispatchChargeStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Order;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * INI-484. What the company whose warehouse shipped a note of another company's shop charges that company for it, one
 * row per note, in the dispatching company's currency. Written at dispatch with the terms in force then; costed after.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id the dispatching company, which charges
 * @property int $ordering_organisation_id the company of the shop where the order was placed, which pays
 * @property int|null $org_partner_id
 * @property int $delivery_note_id
 * @property int|null $order_id
 * @property int $shop_id
 * @property CrossDispatchChargeStateEnum $state
 * @property Carbon $dispatched_at
 * @property array<string, mixed> $terms
 * @property int $currency_id
 * @property int|null $number_parcels
 * @property string|null $dispatch_fee
 * @property string|null $shipping_amount
 * @property string|null $shipping_cost_source
 * @property string|null $goods_amount
 * @property list<array{delivery_note_item_id: int, org_stock_id: int, quantity: float, unit_cost: float|null, amount: float|null, cost_source: string}> $lines
 * @property Carbon|null $costed_at
 * @property Carbon|null $reversed_at
 */
class CrossDispatchCharge extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'lines' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'state'         => CrossDispatchChargeStateEnum::class,
            'dispatched_at' => 'datetime',
            'terms'         => 'array',
            'lines'         => 'array',
            'costed_at'     => 'datetime',
            'reversed_at'   => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function orderingOrganisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'ordering_organisation_id');
    }

    public function orgPartner(): BelongsTo
    {
        return $this->belongsTo(OrgPartner::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
