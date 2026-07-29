<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 12:50:56 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $slug
 */
class OrgSupplierProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
