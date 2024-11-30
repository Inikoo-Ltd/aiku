<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Apr 2023 11:32:21 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\OrganisationCatalogueStats
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $number_shops
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
 * @property int $number_departments_state_discontinuing
 * @property int $number_departments_state_discontinued
 * @property int $number_collection_categories
 * @property int $number_collections
 * @property int $number_sub_departments
 * @property int $number_current_sub_departments state: active+discontinuing
 * @property int $number_sub_departments_state_in_process
 * @property int $number_sub_departments_state_active
 * @property int $number_sub_departments_state_discontinuing
 * @property int $number_sub_departments_state_discontinued
 * @property int $number_families
 * @property int $number_current_families state: active+discontinuing
 * @property int $number_families_state_in_process
 * @property int $number_families_state_active
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
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static Builder<static>|OrganisationCatalogueStats newModelQuery()
 * @method static Builder<static>|OrganisationCatalogueStats newQuery()
 * @method static Builder<static>|OrganisationCatalogueStats query()
 * @mixin Eloquent
 */
class OrganisationCatalogueStats extends Model
{
    protected $table = 'organisation_catalogue_stats';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
