<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsitesEdgeDatacentersResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        [$countryCode, $location] = config('cloudflare_colos.'.$this->colo) ?? [null, null];

        return [
            'colo'             => $this->colo,
            'country_code'     => $countryCode,
            'location'         => $location,
            'city'             => $location === null ? null : strtok($location, ','),
            'websites'         => (int) $this->websites,
            'requests'         => (int) $this->requests,
            'requests_share'   => $this->requests_share === null ? null : (float) $this->requests_share,
            'requests_hit'     => (int) $this->requests_hit,
            'hit_share'        => $this->hit_share === null ? null : (float) $this->hit_share,
            'requests_reached' => (int) $this->requests_reached,
            'reached_share'    => $this->reached_share === null ? null : (float) $this->reached_share,
            'requests_none'    => (int) $this->requests_none,
        ];
    }
}
