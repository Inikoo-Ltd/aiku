<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-14h-58m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
 * @property mixed $show_in_website
 * @property mixed $sub_departments
 * @property mixed $collections
 * @property mixed $web_images
 */
class MasterDepartmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'code'             => $this->code,
            'name'             => $this->name,
            'image_thumbnail'  => Arr::get($this->web_images, 'main.thumbnail'),
            'description'      => $this->description,
            'master_shop_slug' => $this->master_shop_slug,
            'master_shop_code' => $this->master_shop_code,
            'master_shop_name' => $this->master_shop_name,
            'used_in'          => $this->used_in,
            'families'         => $this->families,
            'products'         => $this->products,
            'sub_departments'  => $this->sub_departments,
            'collections'      => $this->collections,
            'show_in_website'  => $this->show_in_website,
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
