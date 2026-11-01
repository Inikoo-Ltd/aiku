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

class WebsitesEdgeTrafficResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $share = fn (?string $value) => $value === null ? null : (float) $value;

        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'domain'              => $this->domain,
            'organisation_name'   => $this->organisation_name,
            ...collect(FetchWebsiteEdgeTimeSeries::METRICS)->mapWithKeys(fn (string $metric) => [$metric => (int) $this->{$metric}])->all(),
            'requests_reached'    => (int) $this->requests_reached,
            'requests_varnish'    => $this->requests_varnish === null ? null : (int) $this->requests_varnish,
            'requests_octane'     => $this->requests_octane === null ? null : (int) $this->requests_octane,
            'edge_share'          => $share($this->edge_share),
            'share_4xx'           => $share($this->share_4xx),
            'unknown_agent_share' => $share($this->unknown_agent_share),
            'far_share'           => $share($this->far_share),
            'avg_origin_ms'       => $this->avg_origin_ms === null ? null : (int) $this->avg_origin_ms,
        ];
    }
}
