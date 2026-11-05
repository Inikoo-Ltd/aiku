<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * Storefront HTML requests from visitors (requestSource eyeball) that reached a website's
 * Cloudflare zone. requests_hit counts copies the edge served (hit, stale, updating,
 * revalidated), requests_none what Cloudflare answered itself without a cache lookup
 * (redirect rules, security blocks). origin_response_ms_total is the summed origin time of the
 * requests that went to our servers (miss, expired, dynamic, bypass, other).
 *
 * @property int $id
 * @property int $website_edge_time_series_id
 * @property string $frequency
 * @property string $period
 * @property int $requests
 * @property int $requests_hit
 * @property int $requests_miss
 * @property int $requests_expired
 * @property int $requests_dynamic
 * @property int $requests_bypass
 * @property int $requests_bypass_redirect
 * @property int $requests_none
 * @property int $requests_other_cache_status
 * @property int $origin_response_ms_total
 * @property int $requests_2xx
 * @property int $requests_3xx
 * @property int $requests_4xx
 * @property int $requests_5xx
 * @property int $requests_google_bot
 * @property int $requests_bing_bot
 * @property int $requests_apple_bot
 * @property int $requests_other_bots
 * @property int $requests_unknown_agent
 * @property int $requests_browsers
 * @property int $requests_far_countries
 * @property int $requests_far_countries_hit
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WebsiteEdgeTimeSeriesRecord extends Model
{
    protected $table = 'website_edge_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
