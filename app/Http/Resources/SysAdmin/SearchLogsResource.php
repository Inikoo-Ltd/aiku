<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class SearchLogsResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'                => $this->id,
            'query'             => $this->query,
            'scope'             => $this->scope,
            'username'          => $this->username,
            'organisation_code' => $this->organisation_code,
            'shop_code'         => $this->shop_code,
            'results_count'     => $this->results_count,
            'clicked_at'        => $this->clicked_at,
            'clicked_url'       => $this->clicked_url,
            'created_at'        => $this->created_at,
        ];
    }
}
