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
            'email' => $this['email'],
            'slug' => $this['slug'],
            'contact_name' => $this['contact_name'],
            'image_id' => $this['image_id'],
            'status' => $this['status'],
            'group_name' => $this['group_name'],
            'section_name' => $this['section_name'],
            'date' => $this['date'],
            'route_name' => $this['route_name'],
            'route_params' => $this['route_params'],
            'os' => $this['os'],
            'device' => $this['device'],
            'browser' => $this['browser'],
            'ip_address' => $this['ip_address'],
            'location' => $this['location'],
        ];
    }
}
