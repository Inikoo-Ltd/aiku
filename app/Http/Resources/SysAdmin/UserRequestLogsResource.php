<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 07 Sept 2022 21:56:20 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use JsonSerializable;

class UserRequestLogsResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'username' => $this['username'],
            'ip_address' => $this['ip_address'],
            'route_name' => $this['route_name'],
            'route_parameter' => $this['arguments'],
            'module' => Str::title($this['module']),
            'datetime' => $this['datetime'],
            'location' => $this['location'],
            'user_agent' => [
                $this['device_type'],
                $this['platform'],
                $this['browser'],
            ],
            'url' => $this['url'],
        ];
    }
}
