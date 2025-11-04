<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $scope
 */
class TagsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'slug'  => $this->slug,
            'name'  => $this->name,
            'scope' => $this->scope->pretty(),
        ];
    }
}
