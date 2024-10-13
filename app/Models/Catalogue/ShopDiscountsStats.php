<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 21:30:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $shop_id
 * @property int $number_customers
 * @property int $number_orders
 * @property string $amount
 * @property string $org_amount
 * @property string $group_amount
 * @property int $number_offer_campaigns
 * @property int $number_current_offer_campaigns
 * @property int $number_offer_campaigns_state_in_process
 * @property int $number_offer_campaigns_state_active
 * @property int $number_offer_campaigns_state_finished
 * @property int $number_offers
 * @property int $number_current_offers
 * @property int $number_offers_state_in_process
 * @property int $number_offers_state_active
 * @property int $number_offers_state_finished
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder|ShopDiscountsStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ShopDiscountsStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ShopDiscountsStats query()
 * @mixin \Eloquent
 */
class ShopDiscountsStats extends Model
{
    protected $table = 'shop_discounts_stats';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
