<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026 07:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderPaymentMethodEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money one of our organisations pays a supplier for a stock delivery, against the supplier's invoice. What was
 * paid before the goods shipped is the deposit of the purchase order and is taken off the invoice with these.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $amount
 * @property int $currency_id
 * @property PurchaseOrderPaymentMethodEnum $method
 * @property string|null $reference
 * @property int|null $media_id
 * @property int|null $created_by
 */
class SupplierPayment extends Model
{
    use InGroup;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'   => 'date',
            'method' => PurchaseOrderPaymentMethodEnum::class,
        ];
    }

    /**
     * A payment dated after today was booked in the bank to go out later (HELP-4878). Aiku cannot see scheduled
     * bank payments, so it is not counted as paid until its date: the bank may still cancel or change it.
     */
    public function isScheduled(): bool
    {
        return $this->date->isAfter(today());
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
