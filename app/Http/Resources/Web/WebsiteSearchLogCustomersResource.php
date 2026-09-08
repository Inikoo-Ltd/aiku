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

class WebsiteSearchLogCustomersResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'customer_name'    => $this->customer_name,
            'customer_slug'    => $this->customer_slug,
            'searches'         => (int)$this->searches,
            'clicks'           => (int)$this->clicks,
            'click_through'    => $this->searches ? round($this->clicks / $this->searches * 100, 1) : 0,
            'zero_results'     => (int)$this->zero_results,
            'last_searched_at' => $this->last_searched_at,
        ];
    }
}
