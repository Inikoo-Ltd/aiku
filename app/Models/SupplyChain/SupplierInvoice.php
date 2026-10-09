<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The invoice a supplier issued for a stock delivery that did not come through an agent, as received, or estimated
 * from the delivery when the paper invoice was never entered (every delivery fetched from Aurora).
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $supplier_id
 * @property int $stock_delivery_id
 * @property StockDeliveryInvoiceSourceEnum $source
 * @property string|null $reference
 * @property \Illuminate\Support\Carbon $date
 * @property int $currency_id
 * @property int $number_lines
 * @property string $goods_amount
 * @property string $charges_amount
 * @property string $total_amount
 * @property array<int, array{stock_delivery_item_id: int, org_stock_id: int|null, code: string|null, name: string|null, quantity: float, unit_price: float, amount: float}> $lines
 * @property array<int, array{description: string, type: string, amount: float}> $charges
 * @property array<string, mixed> $data
 */
class SupplierInvoice extends Model
{
    use InGroup;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'    => 'date',
            'source'  => StockDeliveryInvoiceSourceEnum::class,
            'lines'   => 'array',
            'charges' => 'array',
            'data'    => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
}
