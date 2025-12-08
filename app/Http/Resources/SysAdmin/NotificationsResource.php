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
 * @property string $id
 * @property array $data
 * @property string $href
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $read_at
 */
class NotificationsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => Arr::get($this->data, 'title'),
            'body' => Arr::get($this->data, 'body'),
            'route' => Arr::get($this->data, 'route'),
            'created_at' => $this->created_at,
            'read' => (bool) $this->read_at,
        ];
    }
}
