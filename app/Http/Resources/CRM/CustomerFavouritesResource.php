<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Mar 2026 11:41:35 Central Indonesia Time, Bali Airport, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $slug
 */
class CustomerFavouritesResource extends JsonResource
{
    public function toArray($request): array
    {
        return array(
            'id'   => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug

        );
    }
}
