<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:15:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $master_shop_id
 * @property int $number_shops
 * @property int $number_current_shops state=open+closing_down
 * @property int $number_shops_state_in_process
 * @property int $number_shops_state_open
 * @property int $number_shops_state_closing_down
 * @property int $number_shops_state_closed
 * @property int $number_master_product_categories
 * @property int $number_current_master_product_categories status=true
 * @property int $number_master_product_categories_type_department
 * @property int $number_current_master_product_categories_type_department
 * @property int $number_master_product_categories_type_sub_department
 * @property int $number_current_master_product_categories_type_sub_department
 * @property int $number_master_product_categories_type_family
 * @property int $number_current_master_product_categories_type_family
 * @property int $number_master_assets
 * @property int $number_current_master_assets status=true
 * @property int $number_master_assets_type_product
 * @property int $number_current_master_assets_type_product
 * @property int $number_master_assets_type_service
 * @property int $number_current_master_assets_type_service
 * @property int $number_master_assets_type_subscription
 * @property int $number_current_master_assets_type_subscription
 * @property int $number_master_assets_type_rental
 * @property int $number_current_master_assets_type_rental
 * @property int $number_master_assets_type_charge
 * @property int $number_current_master_assets_type_charge
 * @property int $number_master_assets_type_shipping_zone
 * @property int $number_current_master_assets_type_shipping_zone
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Masters\MasterShop $masterShop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopStats query()
 * @mixin \Eloquent
 */
class MasterShopStats extends Model
{
    protected $table = 'master_shop_stats';

    protected $guarded = [];

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }
}
