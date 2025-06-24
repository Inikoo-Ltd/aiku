<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:41:32 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\Catalogue;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Catalogue\ShopStats
 *
 * @property int $id
 * @property int $shop_id
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
 * @property int $number_uploads
 * @property int $number_upload_records
 * @property int $number_queries
 * @property int $number_static_queries is_static=true
 * @property int $number_dynamic_queries is_static=false
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
 * @property int $number_families_no_department
 * @property int $number_products_no_family
 * @property int $number_current_collections state=active+discontinuing
 * @property int $number_collections_state_in_process
 * @property int $number_collections_state_active
 * @property int $number_collections_state_inactive
 * @property int $number_collections_state_discontinuing
 * @property int $number_collections_state_discontinued
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Catalogue\ProductCategory|null $top1dDepartment
 * @property-read \App\Models\Catalogue\ProductCategory|null $top1dFamily
 * @property-read \App\Models\Catalogue\Product|null $top1dProduct
 * @property-read \App\Models\Catalogue\ProductCategory|null $top1mDepartment
 * @property-read \App\Models\Catalogue\ProductCategory|null $top1mFamily
 * @property-read \App\Models\Catalogue\Product|null $top1mProduct
 * @property-read \App\Models\Catalogue\ProductCategory|null $top1wDepartment
 * @property-read \App\Models\Catalogue\ProductCategory|null $top1wFamily
 * @property-read \App\Models\Catalogue\Product|null $top1wProduct
 * @property-read \App\Models\Catalogue\ProductCategory|null $top1yDepartment
 * @property-read \App\Models\Catalogue\ProductCategory|null $top1yFamily
 * @property-read \App\Models\Catalogue\Product|null $top1yProduct
 * @property-read \App\Models\Catalogue\ProductCategory|null $topAllDepartment
 * @property-read \App\Models\Catalogue\ProductCategory|null $topAllFamily
 * @property-read \App\Models\Catalogue\Product|null $topAllProduct
 * @method static Builder<static>|ShopStats newModelQuery()
 * @method static Builder<static>|ShopStats newQuery()
 * @method static Builder<static>|ShopStats query()
 * @mixin Eloquent
 */
class ShopStats extends Model
{
    protected $table = 'shop_stats';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function top1dProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'top_1d_product_id');
    }
    public function top1dFamily(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_1d_family_id');
    }
    public function top1dDepartment(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_1d_department_id');
    }

    public function top1wProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'top_1w_product_id');
    }
    public function top1wFamily(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_1w_family_id');
    }
    public function top1wDepartment(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_1w_department_id');
    }

    public function top1mProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'top_1m_product_id');
    }
    public function top1mFamily(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_1m_family_id');
    }
    public function top1mDepartment(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_1m_department_id');
    }

    public function top1yProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'top_1y_product_id');
    }
    public function top1yFamily(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_1y_family_id');
    }
    public function top1yDepartment(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_1y_department_id');
    }

    public function topAllProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'top_all_product_id');
    }
    public function topAllFamily(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_all_family_id');
    }
    public function topAllDepartment(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'top_all_department_id');
    }

}
