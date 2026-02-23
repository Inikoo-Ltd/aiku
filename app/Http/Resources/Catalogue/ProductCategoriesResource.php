<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property mixed $state
 * @property mixed $type
 * @property string $description
 */
class ProductCategoriesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'slug'        => $this->slug,
            'code'        => $this->code,
            'name'        => $this->name,
            'state'       => $this->state,
            'type'        => $this->type,
            'description' => $this->description,
        ];
    }
}
