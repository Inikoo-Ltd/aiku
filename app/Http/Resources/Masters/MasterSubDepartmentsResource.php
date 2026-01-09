<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-15h-19m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

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
 * @property int $number_families
 * @property mixed $number_products
 * @property mixed $id
 * @property mixed $description_title
 * @property mixed $description_extra
 * @property mixed $web_images
 * @property mixed $status
 * @property mixed $currency_code
 * @property mixed $sales
 * @property mixed $sales_ly
 * @property mixed $invoices
 * @property mixed $invoices_ly
 */
class MasterSubDepartmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'code'              => $this->code,
            'name'              => $this->name,
            'description'       => $this->description,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'number_families'   => $this->number_families,
            'number_products'   => $this->number_products,
            'description_title' => $this->description_title,
            'description_extra' => $this->description_extra,
            'image_thumbnail'   => Arr::get($this->web_images, 'main.thumbnail'),
            'status_icon'       => $this->status
                ? [
                    'tooltip' => __('Active'),
                    'icon'    => 'fas fa-check-circle',
                    'class'   => 'text-green-400'
                ]
                : [
                    'tooltip' => __('Closed'),
                    'icon'    => 'fas fa-times-circle',
                    'class'   => 'text-red-400'
                ],
            'currency_code'    => $this->currency_code,
            'sales'            => $this->sales ?? 0,
            'sales_ly'         => $this->sales_ly ?? 0,
            'sales_delta'      => $this->calculateDelta($this->sales ?? 0, $this->sales_ly ?? 0),
            'invoices'         => $this->invoices ?? 0,
            'invoices_ly'      => $this->invoices_ly ?? 0,
            'invoices_delta'   => $this->calculateDelta($this->invoices ?? 0, $this->invoices_ly ?? 0),
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
