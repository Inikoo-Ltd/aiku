<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Web\Website\Cloudflare\FetchWebsiteEdgeTimeSeries;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteVisitorCountriesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'country_code'       => $this->country_code,
            'is_far'             => in_array($this->country_code, FetchWebsiteEdgeTimeSeries::FAR_COUNTRIES, true),
            'website_slug'       => $this->website_slug,
            'domain'             => $this->domain,
            'sessions'           => (int) $this->sessions,
            'logged_in_sessions' => (int) $this->logged_in_sessions,
            'logged_in_share'    => $this->logged_in_share === null ? null : (float) $this->logged_in_share,
            'page_views'         => (int) $this->page_views,
            'visitors_url'       => $this->fulfilment_slug
                ? route('grp.org.fulfilments.show.web.analytics.visitors.index', [$this->organisation_slug, $this->fulfilment_slug, $this->website_slug, 'filter' => array_filter(['country_code' => $this->country_code])])
                : route('grp.org.shops.show.web.analytics.visitors.index', [$this->organisation_slug, $this->shop_slug, $this->website_slug, 'filter' => array_filter(['country_code' => $this->country_code])]),
        ];
    }
}
