<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\CRM\TrafficSource;

class TrafficSourceResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TrafficSource $poll */
        $trafficSource = $this;
        return [
            'id'                       => $trafficSource->id,
            'slug'                     => $trafficSource->slug,
            'name'                     => $trafficSource->name,
        ];
    }
}
