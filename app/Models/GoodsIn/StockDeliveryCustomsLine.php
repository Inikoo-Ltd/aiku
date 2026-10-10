<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One tariff line of the import declaration of a delivery, copied from what the customs agent filed.
 * Amounts are in the organisation currency. The duty of the line is shared by the delivery items
 * assigned to it, so a duty-free line never takes duty from a 9.6% one.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property string $tariff_code
 * @property string|null $description
 * @property numeric $duty_rate percentage
 * @property numeric $customs_value
 * @property numeric $duty_amount
 * @property numeric|null $import_vat
 * @property-read StockDelivery $stockDelivery
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StockDeliveryItem> $items
 */
class StockDeliveryCustomsLine extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'duty_rate'     => 'decimal:4',
            'customs_value' => 'decimal:2',
            'duty_amount'   => 'decimal:2',
            'import_vat'    => 'decimal:2',
        ];
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockDeliveryItem::class);
    }

    public static function normaliseTariffCode(?string $code): string
    {
        return (string) preg_replace('/\D/', '', (string) $code);
    }
}
