<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:40:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $trade_unit_family_id
 * @property int $number_trade_units
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Goods\TradeUnitFamily $tradeUnitFamily
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamilyStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamilyStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamilyStats query()
 * @mixin \Eloquent
 */
class TradeUnitFamilyStats extends Model
{
    protected $table = 'trade_unit_family_stats';

    protected $guarded = [];

    public function tradeUnitFamily(): BelongsTo
    {
        return $this->belongsTo(TradeUnitFamily::class);
    }
}
