<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $name
 * @property mixed $master_shop_slug
 * @property mixed $master_shop_code
 * @property mixed $master_shop_name
 * @property mixed $master_department_slug
 * @property mixed $master_department_code
 * @property mixed $master_department_name
 * @property mixed $master_family_slug
 * @property mixed $master_family_code
 * @property mixed $master_family_name
 * @property mixed $show_in_website
 * @property mixed $used_in
 * @property mixed $id
 * @property mixed $unit
 * @property mixed $currency_code
 * @property mixed $rrp
 * @property mixed $price
 * @property mixed $status
 */
class MasterProductsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $extraField = [];
        // Add this check so that this won't disturb usage in other place. Needed this for the checks done during variant creation.
        if (isset($this->is_variant_leader)) {
            $allChildHasWeb = true;
            foreach ($this->products as $product) {
                if (!$product->shop || $product->shop->state == ShopStateEnum::CLOSED) {
                    continue;
                }
                $hasValidProduct = true;
                if (!$product->webpage()->exists()) {
                    $allChildHasWeb = false;
                    break;
                }
            }
             data_set($extraField, 'allChildHasWebpage', $hasValidProduct && $allChildHasWeb);
        }

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
            'master_family_slug'     => $this->master_family_slug,
            'master_family_code'     => $this->master_family_code,
            'master_family_name'     => $this->master_family_name,
            'master_sub_department_slug' => $this->master_sub_department_slug,
            'master_sub_department_code' => $this->master_sub_department_code,
            'master_sub_department_name' => $this->master_sub_department_name,
            'show_in_website'        => $this->show_in_website,
            'used_in'                => $this->used_in,
            'unit'                   => $this->unit,
            'units'                  => $this->units,
            'price'                  => $this->price,
            'rrp'                    => $this->rrp,
            'status'                 => $this->status,
            'currency_code'          => $this->currency_code,
            'image_thumbnail'        => $this->web_images,
            'status_icon'            => $this->status ? [
                'tooltip' => __('Active'),
                'icon'    => 'fas fa-check-circle',
                'class'   => 'text-green-400'
            ] : [
                'tooltip' => __('Closed'),
                'icon'    => 'fas fa-times-circle',
                'class'   => 'text-red-400'
            ],
            'variant_slug'           => $this->variant_slug,
            'is_variant_leader'      => $this->is_variant_leader,
            'variant_code'           => $this->variant_code,
            ...$extraField
        ];
    }
}
