<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 22:08:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $contact_name
 * @property string $alias
 * @property mixed $id
 *
 */
class PickersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'contact_name' => $this->contact_name,
            'alias'        => $this->alias,
            'user_id'      => $this->user_id ?? null
        ];
    }
}
