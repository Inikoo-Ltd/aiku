<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $department_slug
 * @property string $code
 * @property string $name
 * @property mixed $state
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 */
class MasterProductCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'               => $this->slug,
            'shop_slug'          => $this->shop_slug,
            'shop_code'          => $this->shop_code,
            'shop_name'          => $this->shop_name,
            'code'               => $this->code,
            'name'               => $this->name,
            'description'       => $this->description,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'current_families'  => $this->stats->number_families ?? 0,
            'current_products'  => $this->stats->number_products ?? 0,
            'image'        => $this ->imageSources(720, 480),
            'type'              => $this->type,
            'show_in_website'  => $this->show_in_website,
            'follow_master'     => $this->follow_master
        ];
    }
}
