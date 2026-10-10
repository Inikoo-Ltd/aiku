<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Models\Dispatching\BatchCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SKOs of one batch checked in on a stock delivery line.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_item_id
 * @property int $batch_code_id
 * @property string $quantity
 * @property-read BatchCode $batchCode
 * @property-read StockDeliveryItem $stockDeliveryItem
 */
class StockDeliveryItemBatch extends Model
{
    protected $guarded = [];

    public function batchCode(): BelongsTo
    {
        return $this->belongsTo(BatchCode::class);
    }

    public function stockDeliveryItem(): BelongsTo
    {
        return $this->belongsTo(StockDeliveryItem::class);
    }
}
