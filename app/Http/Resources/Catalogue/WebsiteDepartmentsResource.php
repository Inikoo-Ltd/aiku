<?php

/*
 * author Arya Permana - Kirin
 * created on 27-05-2025-13h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;

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
 * @property int $number_current_families
 * @property int $number_current_products
 * @property mixed $sales_all
 * @property mixed $organisation_name
 * @property mixed $invoices_all
 * @property mixed $organisation_slug
 * @property mixed $id
 */
class WebsiteDepartmentsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'slug'                    => $this->slug,
            'shop_slug'               => $this->shop_slug,
            'shop_code'               => $this->shop_code,
            'shop_name'               => $this->shop_name,
            'code'                    => $this->code,
            'name'                    => $this->name,
            'state'            => [
                'label' => $this->state->labels()[$this->state->value],
                'icon'  => $this->state->stateIcon()[$this->state->value]['icon'],
                'class' => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'description'             => $this->description,
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
            'number_current_families' => $this->number_current_families,
            'number_current_products' => $this->number_current_products,
            'sales'                   => $this->sales_all,
            'images'                  => ImageResource::collection($this->images),
            'image_thumbnail'         => $this->imageSources(720, 480),
            'invoices'                => $this->invoices_all,
            'organisation_name'       => $this->organisation_name,
            'organisation_slug'       => $this->organisation_slug,
            'sub_departments_route'   => [
                'name' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.index',
                'parameters' => [
                    'organisation' => $this->organisation->slug,
                    'shop'         => $this->shop->slug,
                    'department'   => $this->slug
                ]
            ]
        ];
    }
}
