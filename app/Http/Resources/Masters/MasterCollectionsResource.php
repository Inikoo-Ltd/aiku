<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\Catalogue\Collection\CollectionStateEnum;

/**
 * @property mixed $id
 * @property mixed $group_id
 * @property mixed $master_shop_id
 * @property string $slug
 * @property string $code
 * @property string $description
 * @property bool $status
 * @property mixed $data
 * @property mixed $name
 * @property mixed $products_status
 */
class MasterCollectionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                             => $this->id,
            'slug'                           => $this->slug,
            'code'                           => $this->code,
            'name'                           => $this->name,
            'description'                    => $this->description,
            'state'                          => $this->state,
            'products_status'                => $this->products_status,
            'master_shop_slug'               => $this->master_shop_slug,
            'master_shop_code'               => $this->master_shop_code,
            'master_shop_name'               => $this->master_shop_name,
            'used_in'                        => $this->used_in,
            'number_current_master_families' => $this->number_current_master_families,
            'number_current_master_products' => $this->number_current_master_products,
            'state_icon'                     => $this->state ? CollectionStateEnum::from($this->state->value)->stateIcon()[$this->state->value] : null,
            'parents_data'                   => $this->parseCollectionParentsData($this->parents_data),
        ];
    }


    private function parseCollectionParentsData(string|null $parentsData): array
    {
        $parents = [];
        if ($parentsData == '|||' || $parentsData === null) {
            return $parents;
        }


        list($slugsData, $typesData, $codesData, $namesData) = explode('|', $parentsData);
        $slugs = explode(',', $slugsData);
        $types = explode(',', $typesData);
        $codes = explode(',', $codesData);
        $names = explode(',', $namesData);

        foreach ($slugs as $key => $slug) {
            $parents[] = [
                'slug' => $slug,
                'type' => $types[$key] ?? null,
                'code' => $codes[$key] ?? null,
                'name' => $names[$key] ?? null,
            ];
        }

        return $parents;
    }
}
