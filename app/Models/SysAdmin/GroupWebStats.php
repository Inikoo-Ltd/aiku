<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 15:10:51 Central European Summer Time, Plane Malaga - Abu Dhabi
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_websites
 * @property int $number_websites_under_maintenance
 * @property int $number_websites_type_info
 * @property int $number_websites_type_b2b
 * @property int $number_websites_type_b2c
 * @property int $number_websites_type_dropshipping
 * @property int $number_websites_type_fulfilment
 * @property int $number_websites_type_digital_agency
 * @property int $number_websites_state_in_process
 * @property int $number_websites_state_live
 * @property int $number_websites_state_closed
 * @property int $number_webpages
 * @property int $number_webpages_state_in_process
 * @property int $number_webpages_state_ready
 * @property int $number_webpages_state_live
 * @property int $number_webpages_state_closed
 * @property int $number_webpages_type_storefront
 * @property int $number_webpages_type_catalogue
 * @property int $number_webpages_type_content
 * @property int $number_webpages_type_info
 * @property int $number_webpages_type_operations
 * @property int $number_webpages_type_blog
 * @property int $number_webpages_sub_type_storefront
 * @property int $number_webpages_sub_type_catalogue
 * @property int $number_webpages_sub_type_products
 * @property int $number_webpages_sub_type_product
 * @property int $number_webpages_sub_type_family
 * @property int $number_webpages_sub_type_department
 * @property int $number_webpages_sub_type_collection
 * @property int $number_webpages_sub_type_content
 * @property int $number_webpages_sub_type_about_us
 * @property int $number_webpages_sub_type_contact
 * @property int $number_webpages_sub_type_returns
 * @property int $number_webpages_sub_type_shipping
 * @property int $number_webpages_sub_type_showroom
 * @property int $number_webpages_sub_type_terms_and_conditions
 * @property int $number_webpages_sub_type_privacy
 * @property int $number_webpages_sub_type_cookies_policy
 * @property int $number_webpages_sub_type_basket
 * @property int $number_webpages_sub_type_checkout
 * @property int $number_webpages_sub_type_login
 * @property int $number_webpages_sub_type_register
 * @property int $number_webpages_sub_type_call_back
 * @property int $number_webpages_sub_type_appointment
 * @property int $number_webpages_sub_type_pricing
 * @property int $number_webpages_sub_type_blog
 * @property int $number_webpages_sub_type_article
 * @property int $number_banners
 * @property int $number_banners_state_unpublished
 * @property int $number_banners_state_live
 * @property int $number_banners_state_switch_off
 * @property int $number_redirects
 * @property int $number_redirects_type_301
 * @property int $number_redirects_type_302
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_web_user_requests
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static Builder<static>|GroupWebStats newModelQuery()
 * @method static Builder<static>|GroupWebStats newQuery()
 * @method static Builder<static>|GroupWebStats query()
 * @mixin Eloquent
 */
class GroupWebStats extends Model
{
    protected $table = 'group_web_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
