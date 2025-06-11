<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 10:53:37 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Web\WebpageStats
 *
 * @property int $id
 * @property int $webpage_id
 * @property int $number_blocks
 * @property int $number_visible_blocks
 * @property int $number_published_blocks
 * @property int $number_snapshots
 * @property int $number_snapshots_state_unpublished
 * @property int $number_snapshots_state_live
 * @property int $number_snapshots_state_historic
 * @property int $number_deployments
 * @property string|null $last_deployed_at
 * @property int $number_child_webpages
 * @property int $number_child_webpages_state_in_process
 * @property int $number_child_webpages_state_ready
 * @property int $number_child_webpages_state_live
 * @property int $number_child_webpages_state_closed
 * @property int $number_child_webpages_type_storefront
 * @property int $number_child_webpages_type_catalogue
 * @property int $number_child_webpages_type_content
 * @property int $number_child_webpages_type_info
 * @property int $number_child_webpages_type_operations
 * @property int $number_child_webpages_type_blog
 * @property int $number_child_webpages_sub_type_storefront
 * @property int $number_child_webpages_sub_type_catalogue
 * @property int $number_child_webpages_sub_type_products
 * @property int $number_child_webpages_sub_type_product
 * @property int $number_child_webpages_sub_type_family
 * @property int $number_child_webpages_sub_type_department
 * @property int $number_child_webpages_sub_type_collection
 * @property int $number_child_webpages_sub_type_content
 * @property int $number_child_webpages_sub_type_about_us
 * @property int $number_child_webpages_sub_type_contact
 * @property int $number_child_webpages_sub_type_returns
 * @property int $number_child_webpages_sub_type_shipping
 * @property int $number_child_webpages_sub_type_showroom
 * @property int $number_child_webpages_sub_type_terms_and_conditions
 * @property int $number_child_webpages_sub_type_privacy
 * @property int $number_child_webpages_sub_type_cookies_policy
 * @property int $number_child_webpages_sub_type_basket
 * @property int $number_child_webpages_sub_type_checkout
 * @property int $number_child_webpages_sub_type_login
 * @property int $number_child_webpages_sub_type_register
 * @property int $number_child_webpages_sub_type_call_back
 * @property int $number_child_webpages_sub_type_appointment
 * @property int $number_child_webpages_sub_type_pricing
 * @property int $number_child_webpages_sub_type_blog
 * @property int $number_child_webpages_sub_type_article
 * @property int $number_banners
 * @property int $number_banners_state_unpublished
 * @property int $number_banners_state_live
 * @property int $number_banners_state_switch_off
 * @property int $number_redirects
 * @property int $number_redirects_type_301
 * @property int $number_redirects_type_302
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_collections
 * @property int $number_child_webpages_sub_type_sub_department
 * @property int $number_products
 * @property-read \App\Models\Web\Webpage $webpage
 * @method static Builder<static>|WebpageStats newModelQuery()
 * @method static Builder<static>|WebpageStats newQuery()
 * @method static Builder<static>|WebpageStats query()
 * @mixin Eloquent
 */
class WebpageStats extends Model
{
    protected $table = 'webpage_stats';

    protected $guarded = [];

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }
}
