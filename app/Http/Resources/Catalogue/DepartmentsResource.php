<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
 * @property mixed $organisation_code
 * @property mixed $number_current_sub_departments
 * @property mixed $number_current_collections
 * @property mixed $master_product_category_id
 * @property mixed $currency_code
 * @property mixed $is_name_reviewed
 * @property mixed $is_description_title_reviewed
 * @property mixed $is_description_reviewed
 * @property mixed $is_description_extra_reviewed
 * @property mixed $web_images
 */
class DepartmentsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                             => $this->id,
            'slug'                           => $this->slug,
            'shop_slug'                      => $this->shop_slug,
            'shop_code'                      => $this->shop_code,
            'shop_name'                      => $this->shop_name,
            'code'                           => $this->code,
            'name'                           => $this->name,
            'currency_code'                  => $this->currency_code,
            'state'                          => [
                'label' => $this->state->labels()[$this->state->value],
                'icon'  => $this->state->stateIcon()[$this->state->value]['icon'],
                'class' => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'description'                    => $this->description,
            'created_at'                     => $this->created_at,
            'updated_at'                     => $this->updated_at,
            'number_current_families'        => $this->number_current_families,
            'number_current_products'        => $this->number_current_products,
            'number_current_sub_departments' => $this->number_current_sub_departments,
            'number_current_collections'     => $this->number_current_collections,
            'sales'                          => $this->sales ?? 0,
            'sales_ly'                       => $this->sales_ly ?? 0,
            'sales_delta'                    => $this->calculateDelta($this->sales ?? 0, $this->sales_ly ?? 0),
            'invoices'                       => $this->invoices ?? 0,
            'invoices_ly'                    => $this->invoices_ly ?? 0,
            'invoices_delta'                 => $this->calculateDelta($this->invoices ?? 0, $this->invoices_ly ?? 0),
            'current_interval'               => $this->current_interval ?? 'ytd',
            'organisation_name'              => $this->organisation_name,
            'organisation_code'              => $this->organisation_code,
            'organisation_slug'              => $this->organisation_slug,
            'master_product_category_id'     => $this->master_product_category_id,
            'is_name_reviewed'               => $this->is_name_reviewed,
            'is_description_title_reviewed'  => $this->is_description_title_reviewed,
            'is_description_reviewed'        => $this->is_description_reviewed,
            'is_description_extra_reviewed'  => $this->is_description_extra_reviewed,
            'image_thumbnail'                => Arr::get($this->web_images, 'main.thumbnail'),
        ];
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
