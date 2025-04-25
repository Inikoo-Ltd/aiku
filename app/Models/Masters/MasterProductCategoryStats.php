<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:15:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Catalogue\MasterProductCategoryStats
 *
 * @property int $id
 * @property int $master_product_category_id
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
 * @property int $number_departments
 * @property int $number_current_departments
 * @property int $number_departments_state_in_process
 * @property int $number_departments_state_active
 * @property int $number_departments_state_inactive
 * @property int $number_departments_state_discontinuing
 * @property int $number_departments_state_discontinued
 * @property int $number_sub_departments
 * @property int $number_current_sub_departments state: active+discontinuing
 * @property int $number_sub_departments_state_in_process
 * @property int $number_sub_departments_state_active
 * @property int $number_sub_departments_state_inactive
 * @property int $number_sub_departments_state_discontinuing
 * @property int $number_sub_departments_state_discontinued
 * @property int $number_families
 * @property int $number_current_families state: active+discontinuing
 * @property int $number_families_state_in_process
 * @property int $number_families_state_active
 * @property int $number_families_state_inactive
 * @property int $number_families_state_discontinuing
 * @property int $number_families_state_discontinued
 * @property int $number_orphan_families
 * @property int $number_products
 * @property int $number_current_products state: active+discontinuing
 * @property int $number_products_state_in_process
 * @property int $number_products_state_active
 * @property int $number_products_state_discontinuing
 * @property int $number_products_state_discontinued
 * @property int $number_products_status_in_process
 * @property int $number_products_status_for_sale
 * @property int $number_products_status_not_for_sale
 * @property int $number_products_status_out_of_stock
 * @property int $number_products_status_discontinued
 * @property int $number_products_trade_config_auto
 * @property int $number_products_trade_config_force_offline
 * @property int $number_products_trade_config_force_out_of_stock
 * @property int $number_products_trade_config_force_for_sale
 * @property int $number_rentals
 * @property int $number_rentals_state_in_process
 * @property int $number_rentals_state_active
 * @property int $number_rentals_state_discontinued
 * @property int $number_services
 * @property int $number_services_state_in_process
 * @property int $number_services_state_active
 * @property int $number_services_state_discontinued
 * @property int $number_subscriptions
 * @property int $number_subscriptions_state_in_process
 * @property int $number_subscriptions_state_active
 * @property int $number_subscriptions_state_discontinued
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder<static>|MasterProductCategoryStats newModelQuery()
 * @method static Builder<static>|MasterProductCategoryStats newQuery()
 * @method static Builder<static>|MasterProductCategoryStats query()
 * @mixin Eloquent
 */
class MasterProductCategoryStats extends Model
{
    protected $table = 'master_product_category_stats';

    protected $guarded = [];


}
