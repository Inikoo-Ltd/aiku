<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:40:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeUnitFamilyStats extends Model
{
    protected $table = 'trade_unit_family_stats';

    protected $guarded = [];

    public function tradeUnitFamily(): BelongsTo
    {
        return $this->belongsTo(TradeUnitFamily::class);
    }
}
