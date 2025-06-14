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
 *
 *
 * @property int $id
 * @property int $trade_unit_id
 * @property int $number_products Number of products in this trade unit
 * @property int $number_current_products Number of products in this trade unit that are active or discontinuing
 * @property int $number_products_state_in_process
 * @property int $number_products_state_active
 * @property int $number_products_state_discontinuing
 * @property int $number_products_state_discontinued
 * @property int $number_customer_exclusive_products Number of products in this trade unit
 * @property int $number_customer_exclusive_current_products Number of products in this trade unit that are active or discontinuing
 * @property int $number_customer_exclusive_products_state_in_process
 * @property int $number_customer_exclusive_products_state_active
 * @property int $number_customer_exclusive_products_state_discontinuing
 * @property int $number_customer_exclusive_products_state_discontinued
 * @property int $number_org_stocks
 * @property int $number_current_org_stocks Number of org stocks in this trade unit that are active or discontinuing
 * @property int $number_org_stocks_state_active
 * @property int $number_org_stocks_state_discontinuing
 * @property int $number_org_stocks_state_discontinued
 * @property int $number_org_stocks_state_suspended
 * @property int $number_org_stocks_state_abnormality
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Goods\TradeUnit $tradeUnit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitStats query()
 * @mixin \Eloquent
 */
class TradeUnitStats extends Model
{
    protected $table = 'trade_unit_stats';

    protected $guarded = [];

    public function tradeUnit(): BelongsTo
    {
        return $this->belongsTo(TradeUnit::class);
    }
}
