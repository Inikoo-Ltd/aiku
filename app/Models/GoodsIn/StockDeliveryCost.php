<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property StockDeliveryCostTypeEnum $type
 * @property string|null $label
 * @property numeric|null $amount
 * @property bool $is_na
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property-read StockDelivery $stockDelivery
 */
class StockDeliveryCost extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected $casts = [
        'type'        => StockDeliveryCostTypeEnum::class,
        'received_at' => 'datetime',
        'is_na'       => 'boolean',
    ];

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }
}
