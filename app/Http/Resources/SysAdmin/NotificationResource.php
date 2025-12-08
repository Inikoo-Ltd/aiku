<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 07 Sept 2022 21:56:20 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property array $data
 */
class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'read_at' => $this->read_at,
            'title' => Arr::get($data, 'title'),
            'body' => Arr::get($data, 'body'),
            'type' => Arr::get($data, 'type'),
            'slug' => Arr::get($data, 'slug'),
            'route' => Arr::get($data, 'route'),
        ];
    }
}
