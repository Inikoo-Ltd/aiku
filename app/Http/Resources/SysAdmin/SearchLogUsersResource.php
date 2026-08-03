<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 06:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class SearchLogUsersResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'username'         => $this->username,
            'searches'         => (int)$this->searches,
            'clicks'           => (int)$this->clicks,
            'click_through'    => $this->searches ? round($this->clicks / $this->searches * 100, 1) : 0,
            'zero_results'     => (int)$this->zero_results,
            'last_searched_at' => $this->last_searched_at,
        ];
    }
}
