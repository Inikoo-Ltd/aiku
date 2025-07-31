<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Apr 2024 17:00:31 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\GroupCatalogueStats
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_shops
 * @property int $number_current_shops state=open+closing_down
 * @property int $number_shops_state_in_process
 * @property int $number_shops_state_open
 * @property int $number_shops_state_closing_down
 * @property int $number_shops_state_closed
 * @property int $number_shops_type_b2b
 * @property int $number_shops_type_b2c
 * @property int $number_shops_type_fulfilment
 * @property int $number_shops_type_dropshipping
 * @property int $number_departments
 * @property int $number_current_departments
 * @property int $number_departments_state_in_process
 * @property int $number_departments_state_active
 * @property int $number_departments_state_inactive
 * @property int $number_departments_state_discontinuing
 * @property int $number_departments_state_discontinued
 * @property int $number_collections
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
 * @property int $number_assets
 * @property int $number_current_assets state: active+discontinuing
 * @property int $number_historic_assets
 * @property int $number_assets_state_in_process
 * @property int $number_assets_state_active
 * @property int $number_assets_state_discontinuing
 * @property int $number_assets_state_discontinued
 * @property int $number_assets_type_product
 * @property int $number_assets_type_service
 * @property int $number_assets_type_subscription
 * @property int $number_assets_type_rental
 * @property int $number_assets_type_charge
 * @property int $number_assets_type_shipping_zone
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
 * @property int $number_product_variants
 * @property int|null $top_1d_department_id
 * @property int|null $top_1d_family_id
 * @property int|null $top_1d_product_id
 * @property int|null $top_1w_department_id
 * @property int|null $top_1w_family_id
 * @property int|null $top_1w_product_id
 * @property int|null $top_1m_department_id
 * @property int|null $top_1m_family_id
 * @property int|null $top_1m_product_id
 * @property int|null $top_1y_department_id
 * @property int|null $top_1y_family_id
 * @property int|null $top_1y_product_id
 * @property int|null $top_all_department_id
 * @property int|null $top_all_family_id
 * @property int|null $top_all_product_id
 * @property int $number_charges
 * @property int $number_charges_state_in_process
 * @property int $number_charges_state_active
 * @property int $number_charges_state_discontinued
 * @property int $number_shipping_zone_schemas
 * @property int $number_shipping_zone_schemas_state_in_process
 * @property int $number_shipping_zone_schemas_state_live
 * @property int $number_shipping_zone_schemas_state_decommissioned
 * @property int $number_shipping_zones
 * @property int $number_adjustments
 * @property int $number_adjustments_type_error_net
 * @property int $number_adjustments_type_error_tax
 * @property int $number_adjustments_type_credit
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_current_product_variants state: active+discontinuing
 * @property int $number_product_variants_state_in_process
 * @property int $number_product_variants_state_active
 * @property int $number_product_variants_state_discontinuing
 * @property int $number_product_variants_state_discontinued
 * @property int $number_product_variants_status_in_process
 * @property int $number_product_variants_status_for_sale
 * @property int $number_product_variants_status_not_for_sale
 * @property int $number_product_variants_status_out_of_stock
 * @property int $number_product_variants_status_discontinued
 * @property int $number_product_variants_trade_config_auto
 * @property int $number_product_variants_trade_config_force_offline
 * @property int $number_product_variants_trade_config_force_out_of_stock
 * @property int $number_product_variants_trade_config_force_for_sale
 * @property int $number_products_with_variants
 * @property int $number_current_products_with_variants state: active+discontinuing
 * @property int $number_products_with_variants_state_in_process
 * @property int $number_products_with_variants_state_active
 * @property int $number_products_with_variants_state_discontinuing
 * @property int $number_products_with_variants_state_discontinued
 * @property int $number_products_with_variants_status_in_process
 * @property int $number_products_with_variants_status_for_sale
 * @property int $number_products_with_variants_status_not_for_sale
 * @property int $number_products_with_variants_status_out_of_stock
 * @property int $number_products_with_variants_status_discontinued
 * @property int $number_products_with_variants_trade_config_auto
 * @property int $number_products_with_variants_trade_config_force_offline
 * @property int $number_products_with_variants_trade_config_force_out_of_stock
 * @property int $number_products_with_variants_trade_config_force_for_sale
 * @property int $number_current_shops_type_b2b
 * @property int $number_current_shops_type_b2c
 * @property int $number_current_shops_type_fulfilment
 * @property int $number_current_shops_type_dropshipping
 * @property int $number_families_no_department
 * @property int $number_products_no_family
 * @property int $number_current_collections state=active+discontinuing
 * @property int $number_collections_state_in_process
 * @property int $number_collections_state_active
 * @property int $number_collections_state_inactive
 * @property int $number_collections_state_discontinuing
 * @property int $number_collections_state_discontinued
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCatalogueStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCatalogueStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCatalogueStats query()
 * @mixin \Eloquent
 */
class GroupCatalogueStats extends Model
{
    protected $table = 'group_catalogue_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
