<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 20 Oct 2022 19:00:13 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Catalogue\DepartmentStats
 *
 * @property int $id
 * @property int $product_category_id
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
 * @property int|null $top_1d_family_id
 * @property int|null $top_1d_product_id
 * @property int|null $top_1w_family_id
 * @property int|null $top_1w_product_id
 * @property int|null $top_1m_family_id
 * @property int|null $top_1m_product_id
 * @property int|null $top_1y_family_id
 * @property int|null $top_1y_product_id
 * @property int|null $top_all_family_id
 * @property int|null $top_all_product_id
 * @property int $number_customers_who_favourited
 * @property int $number_customers_who_un_favourited
 * @property int $number_customers_who_reminded
 * @property int $number_customers_who_un_reminded
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_collections
 * @method static Builder<static>|ProductCategoryStats newModelQuery()
 * @method static Builder<static>|ProductCategoryStats newQuery()
 * @method static Builder<static>|ProductCategoryStats query()
 * @mixin Eloquent
 */
class ProductCategoryStats extends Model
{
    protected $table = 'product_category_stats';

    protected $guarded = [];


}
