<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Dec 2024 20:27:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_master_shops
 * @property int $number_current_master_shops status=true
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
 * @property int $number_trade_units
 * @property int $number_trade_units_with_barcode
 * @property int $number_trade_units_with_net_weight
 * @property int $number_trade_units_with_gross_weight
 * @property int $number_trade_units_with_marketing_weight
 * @property int $number_trade_units_with_dimensions
 * @property int $number_trade_units_with_images
 * @property int $number_stock_families
 * @property int $number_current_stock_families active + discontinuing
 * @property int $number_stock_families_state_in_process
 * @property int $number_stock_families_state_active
 * @property int $number_stock_families_state_discontinuing
 * @property int $number_stock_families_state_discontinued
 * @property int $number_stocks
 * @property int $number_current_stocks active + discontinuing
 * @property int $number_stocks_state_in_process
 * @property int $number_stocks_state_active
 * @property int $number_stocks_state_discontinuing
 * @property int $number_stocks_state_discontinued
 * @property int $number_stocks_state_suspended
 * @property int $number_ingredients
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_trade_units_status_in_process
 * @property int $number_trade_units_status_active
 * @property int $number_trade_units_status_discontinued
 * @property int $number_trade_units_status_anomaly
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupGoodsStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupGoodsStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupGoodsStats query()
 * @mixin \Eloquent
 */
class GroupGoodsStats extends Model
{
    protected $table = 'group_goods_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
