<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class WebsiteSearchLogsResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'            => $this->id,
            'query'         => $this->query,
            'scope'         => $this->scope,
            'customer_name' => $this->customer_name,
            'customer_slug' => $this->customer_slug,
            'device'        => $this->device,
            'browser'       => $this->browser,
            'results_count' => $this->results_count,
            'clicked_at'    => $this->clicked_at,
            'clicked_url'   => $this->clicked_url,
            'created_at'    => $this->created_at,
        ];
    }
}
