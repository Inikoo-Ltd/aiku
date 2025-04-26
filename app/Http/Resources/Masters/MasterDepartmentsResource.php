<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-14h-58m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use App\Models\Masters\MasterProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $master_shop_slug
 * @property mixed $master_shop_code
 * @property mixed $master_shop_name
 * @property int $families
 * @property int $products
 * @property int $used_in
 */
class MasterDepartmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var MasterProductCategory $masterDepartment */
        $masterDepartment = $this;

        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'code'             => $this->code,
            'name'             => $this->name,
            'image'            => $masterDepartment->imageSources(720, 480),
            'description'      => $this->description,
            'master_shop_slug' => $this->master_shop_slug,
            'master_shop_code' => $this->master_shop_code,
            'master_shop_name' => $this->master_shop_name,
            'used_in'          => $this->used_in,
            'families'         => $this->families,
            'products'         => $this->products,
        ];
    }
}
