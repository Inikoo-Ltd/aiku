<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property int|null $stock_delivery_item_id an extra that belongs to this one line (a SKO's packaging) instead of the whole delivery
 * @property StockDeliveryCostTypeEnum $type
 * @property string|null $label
 * @property numeric|null $amount
 * @property bool $is_na
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property int|null $currency_id
 * @property numeric|null $exchange
 * @property bool $from_service_invoices
 * @property StockDeliveryServiceInvoiceTypeEnum|null $category
 * @property bool $is_waiting_for_invoice
 * @property bool $is_estimate
 * @property \Illuminate\Support\Carbon|null $invoice_expected_at
 * @property int|null $stock_delivery_service_invoice_id
 * @property array<int, string>|null $applies_to_tariff_codes the tariff codes an anti-dumping row was picked for, as shown to the user
 * @property array<int, int>|null $applies_to_item_ids the delivery lines those tariff codes had when picked: the row goes to these lines only
 * @property-read Currency|null $currency
 * @property-read StockDelivery $stockDelivery
 */
class StockDeliveryCost extends Model implements HasMedia, Auditable
{
    use InOrganisation;
    use HasAttachments;
    use HasHistory;
    use SoftDeletes;

    protected array $auditInclude = [
        'type',
        'category',
        'label',
        'amount',
        'currency_id',
        'exchange',
        'is_na',
        'received_at',
        'is_waiting_for_invoice',
        'invoice_expected_at',
        'is_estimate',
        'applies_to_tariff_codes',
    ];

    public function generateTags(): array
    {
        return ['procurement'];
    }

    protected $guarded = [];

    protected $casts = [
        'type'        => StockDeliveryCostTypeEnum::class,
        'received_at' => 'datetime',
        'is_na'       => 'boolean',
        'from_service_invoices' => 'boolean',
        'is_waiting_for_invoice' => 'boolean',
        'is_estimate'            => 'boolean',
        'category'               => StockDeliveryServiceInvoiceTypeEnum::class,
        'invoice_expected_at'    => 'date',
        'applies_to_tariff_codes' => 'array',
        'applies_to_item_ids'     => 'array',
    ];

    public function serviceInvoice(): BelongsTo
    {
        return $this->belongsTo(StockDeliveryServiceInvoice::class, 'stock_delivery_service_invoice_id');
    }

    public function stockDeliveryItem(): BelongsTo
    {
        return $this->belongsTo(StockDeliveryItem::class);
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function waitingLabel(): ?string
    {
        if (!$this->is_waiting_for_invoice) {
            return null;
        }

        if (!$this->invoice_expected_at) {
            return __('Waiting for the invoice');
        }

        $days = (int) today()->diffInDays($this->invoice_expected_at, false);

        return match (true) {
            $days < 0   => trans_choice('Invoice :count day late|Invoice :count days late', -$days, ['count' => -$days]),
            $days === 0 => __('Invoice expected today'),
            default     => trans_choice('Invoice expected in :count day|Invoice expected in :count days', $days, ['count' => $days]),
        };
    }

    public function lineCostField(): ?string
    {
        if ($this->type === StockDeliveryCostTypeEnum::EXTRA) {
            return ($this->category ?? StockDeliveryServiceInvoiceTypeEnum::OTHER)->lineCostField();
        }

        return $this->type->itemCostField();
    }

    public function appliesByTariffCode(): bool
    {
        return $this->type === StockDeliveryCostTypeEnum::EXTRA && (bool) $this->category?->appliesByTariffCode();
    }

    public function amountInDeliveryCurrency(): float
    {
        $orgExchange = (float) $this->stockDelivery->org_exchange;
        if ($this->currency_id && $orgExchange > 0 && $this->currency_id == $this->stockDelivery->organisation->currency_id) {
            return (float) $this->amount / $orgExchange;
        }

        return (float) $this->amount * (float) ($this->exchange ?? 1);
    }
}
