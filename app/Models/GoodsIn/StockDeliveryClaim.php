<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\StockDeliveryClaimStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * What we ask a supplier back for a line that arrived short. It is only tracked: the delivery's costing
 * is not changed by it, the credit note is recorded here when it arrives.
 * Quantity is in units, amount in the delivery currency.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property int $stock_delivery_item_id
 * @property int|null $org_stock_id
 * @property StockDeliveryClaimStateEnum $state
 * @property numeric $quantity
 * @property numeric $amount
 * @property int $currency_id
 * @property string|null $credit_note_reference
 * @property numeric|null $credit_note_amount
 * @property \Illuminate\Support\Carbon|null $credit_note_date
 * @property string|null $notes
 * @property int|null $created_by_id
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property-read StockDelivery $stockDelivery
 * @property-read StockDeliveryItem $stockDeliveryItem
 * @property-read Currency $currency
 */
class StockDeliveryClaim extends Model implements Auditable
{
    use InOrganisation;
    use HasHistory;

    protected $guarded = [];

    protected array $auditInclude = [
        'state',
        'quantity',
        'amount',
        'credit_note_reference',
        'credit_note_amount',
        'credit_note_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'state'              => StockDeliveryClaimStateEnum::class,
            'quantity'           => 'decimal:4',
            'amount'             => 'decimal:2',
            'credit_note_amount' => 'decimal:2',
            'credit_note_date'   => 'date',
            'sent_at'            => 'datetime',
            'closed_at'          => 'datetime',
        ];
    }

    public function generateTags(): array
    {
        return ['procurement'];
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function stockDeliveryItem(): BelongsTo
    {
        return $this->belongsTo(StockDeliveryItem::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
