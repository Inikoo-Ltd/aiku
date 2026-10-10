<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;

/**
 * A bill a third party (forwarder, customs broker, haulier, port) issued to the receiving organisation for one or
 * more stock deliveries, paid locally and never part of the agent or supplier invoice.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property StockDeliveryServiceInvoiceTypeEnum $type
 * @property string $issuer
 * @property string|null $reference
 * @property \Illuminate\Support\Carbon $date
 * @property int $currency_id
 * @property numeric $exchange to the organisation currency
 * @property numeric $total_amount
 * @property numeric $org_total_amount
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $notes
 * @property-read Currency $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StockDelivery> $stockDeliveries
 */
class StockDeliveryServiceInvoice extends Model implements HasMedia
{
    use InOrganisation;
    use HasAttachments;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'    => StockDeliveryServiceInvoiceTypeEnum::class,
            'date'    => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function stockDeliveries(): BelongsToMany
    {
        return $this->belongsToMany(StockDelivery::class, 'stock_delivery_service_invoice_allocations')
            ->withPivot('amount')
            ->withTimestamps()
            ->orderBy('stock_delivery_service_invoice_allocations.id');
    }
}
