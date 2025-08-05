<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-13h-36m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use App\Http\Resources\HasSelfCall;
use App\Models\Masters\MasterShop;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $name
 */
class MasterShopResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {

        /** @var MasterShop $masterShop */
        $masterShop = $this;

        return [
            'slug'               => $this->slug,
            'code'               => $this->code,
            'name'               => $this->name,
            'statsBox' => [
                     [
                         'label' => __('Master Departments'),
                         'route' => [
                             'name'       => 'grp.masters.master_departments.index',
                             'parameters' => []
                         ],
                         'icon'  => 'fal fa-folder-tree',
                         // "color" => "#a3e635",
                         'value' => $masterShop->stats->number_current_master_product_categories_type_department,

                         'metaRight'  => [
                             'tooltip' => __('Master Sub Departments'),
                             'icon'    => [
                                 'icon'  => 'fal fa-folder-tree',
                                 'class' => ''
                             ],
                             'count'   => $masterShop->stats->number_current_master_product_categories_type_sub_department,
                         ],
                     ],
                     [
                         'label' => __('Master Families'),
                         'route' => [
                             'name'       => 'grp.masters.master_families.index',
                             'parameters' => []
                         ],
                         'icon'  => 'fal fa-folder',
                         // "color" => "#e879f9",
                         'value' => $masterShop->stats->number_current_master_product_categories_type_family,
                     ],
                     [
                         'label' => __('Master Products'),
                         'route' => [
                             'name'       => 'grp.masters.master_products.index',
                             'parameters' => []
                         ],
                         'icon'  => 'fal fa-cube',
                         // "color" => "#38bdf8",
                         'value' => $masterShop->stats->number_current_master_assets_type_product,
                     ],
                     [
                         'label' => __('Master Collections'),
                         'route' => [
                             'name'       => 'grp.masters.master_collections.index',
                             'parameters' => []
                         ],
                         'icon'  => 'fal fa-album-collection',
                         // "color" => "#4f46e5",
                         'value' => $masterShop->stats->number_current_master_collections,
                     ],
                ]
        ];
    }
}
