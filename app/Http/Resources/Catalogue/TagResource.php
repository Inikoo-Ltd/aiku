<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Catalogue;

use App\Helpers\WebsiteLanguage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $web_image
 */
class TagResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                        => $this->id,
            'slug'                      => $this->slug,
            'name'                      => WebsiteLanguage::tagName($this->resource),
            'image'                     => $this->web_image,
            'label'                     => $this->label
        ];
    }
}
