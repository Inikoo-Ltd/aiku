<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Masters;

use App\Traits\ParsesCollectionParentsData;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property mixed $products_status
 * @property string|null $master_shop_slug
 * @property string|null $master_shop_code
 * @property string|null $master_shop_name
 * @property array|string|null $used_in
 * @property int|null $number_current_master_families
 * @property int|null $number_current_master_products
 * @property string|null $parents_data
 * @property mixed $number_current_master_collections
 * @property mixed $status
 * @property mixed $has_active_webpage
 * @property mixed $web_images
 * 
 */
class MasterCollectionsResource extends JsonResource
{
    use ParsesCollectionParentsData;

    public function toArray($request): array
    {
        return [
            'id'   => $this->id,
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,

            'products_status' => $this->products_status,

            'master_shop_slug' => $this->master_shop_slug,
            'master_shop_code' => $this->master_shop_code,
            'master_shop_name' => $this->master_shop_name,

            'used_in' => $this->used_in,

            'number_current_master_families'    => $this->number_current_master_families,
            'number_current_master_products'    => $this->number_current_master_products,
            'number_current_master_collections' => $this->number_current_master_collections,

            'departments_data' => $this->decodeJson($this->departments_data),
            'sub_departments_data' => $this->decodeJson($this->sub_departments_data),

            'status_icon' => $this->status ? [
                'tooltip' => __('Active'),
                'icon'    => 'fas fa-check-circle',
                'class'   => 'text-green-400',
            ] : [
                'tooltip' => __('Closed'),
                'icon'    => 'fas fa-times-circle',
                'class'   => 'text-red-400',
            ],

            'parents_data' => $this->parseCollectionParentsData($this->parents_data),

            'delete_route' => [
                'method' => 'delete',
                'name'   => 'grp.models.master_collection.delete',
                'parameters' => [
                    'masterCollection' => $this->id,
                ],
            ],

            'has_active_webpage' => $this->has_active_webpage,
            'image_thumbnail'    => Arr::get($this->web_images, 'main.thumbnail'),
        ];
    }

    private function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return [];
    }
}