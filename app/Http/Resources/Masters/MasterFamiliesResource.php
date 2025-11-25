<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-15h-10m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property mixed $master_shop_slug
 * @property mixed $master_shop_code
 * @property mixed $master_shop_name
 * @property mixed $master_department_slug
 * @property mixed $master_department_name
 * @property mixed $master_department_code
 * @property mixed $used_in
 * @property mixed $products
 * @property mixed $show_in_website
 */
class MasterFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'slug'                   => $this->slug,
            'code'                   => $this->code,
            'name'                   => $this->name,
            'master_shop_slug'       => $this->master_shop_slug,
            'master_shop_code'       => $this->master_shop_code,
            'master_shop_name'       => $this->master_shop_name,
            'master_department_slug' => $this->master_department_slug,
            'master_department_code' => $this->master_department_code,
            'master_department_name' => $this->master_department_name,
            'used_in'                => $this->used_in,
            'products'               => $this->products,
            'show_in_website'        => $this->show_in_website,
            'status_icon'            => $this->status ? [
                'tooltip' => __('Active'),
                'icon'    => 'fas fa-check-circle',
                'class'   => 'text-green-400'
            ] : [
                'tooltip' => __('Closed'),
                'icon'    => 'fas fa-times-circle',
                'class'   => 'text-red-400'
            ],
            'current_master_assets' => $this->current_master_assets,
        ];
    }
}
