<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Masters;

use App\Models\Catalogue\Collection;
use App\Traits\ParsesCollectionParentsData;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
        $activeWebpage = [];
        if(isset($this->has_active_webpage)){
            $activeWebpage = [
                'childrens'          => $this->childrenCollections()->select([
                    'collections.shop_id',
                    'collections.code',
                    'collections.name',
                    'collections.state',
                ])
                ->with('shop:id,code,slug')
                ->with('webpage:id,model_id,state')
                ->get() ?? []
            ];
        }

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

            'currency_code'  => $this->currency_code,
            'sales'          => $this->sales ?? 0,
            'sales_ly'       => $this->sales_ly ?? 0,
            'sales_delta'    => $this->calculateDelta($this->sales ?? 0, $this->sales_ly ?? 0),
            'invoices'       => $this->invoices ?? 0,
            'invoices_ly'    => $this->invoices_ly ?? 0,
            'invoices_delta' => $this->calculateDelta($this->invoices ?? 0, $this->invoices_ly ?? 0),

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
            'image_thumbnail'    => Arr::get($this->web_images, 'main.thumbnail'),
            ...$activeWebpage
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

    private function calculateDelta($current, $previous): ?array
    {
        if (!$previous || $previous == 0) {
            return null;
        }

        $delta = (($current - $previous) / $previous) * 100;

        return [
            'value'       => $delta,
            'formatted'   => number_format($delta, 1).'%',
            'is_positive' => $delta > 0,
            'is_negative' => $delta < 0,
        ];
    }
}
