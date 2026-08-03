<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 15 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $supplier_name
 * @property string $supplier_slug
 * @property string $org_supplier_slug
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string $currency_code
 * @property string $org_currency_code
 * @property int $org_stock_id
 * @property int $org_supplier_product_id
 * @property numeric $unit_cost
 * @property numeric $delivered_unit_cost
 * @property int $units_per_carton
 * @property int $units_per_pack
 * @property int $packages_per_carton
 * @property bool $is_preferred
 * @property int $local_priority
 */
class OrgStockSupplierProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                    => $this->slug,
            'org_stock_id'            => $this->org_stock_id,
            'org_supplier_product_id' => $this->org_supplier_product_id,
            'supplier_name'       => $this->supplier_name,
            'supplier_slug'       => $this->supplier_slug,
            'org_supplier_slug'   => $this->org_supplier_slug,
            'code'                => $this->code,
            'description'         => $this->description ?? $this->name,
            'currency_code'       => $this->currency_code,
            'org_currency_code'   => $this->org_currency_code,
            'unit_cost'           => $this->unit_cost,
            'delivered_unit_cost' => $this->delivered_unit_cost,
            'units_per_carton'    => $this->units_per_carton,
            'units_per_pack'      => $this->units_per_pack,
            'packages_per_carton' => $this->packages_per_carton,
            'is_preferred'        => (bool) $this->is_preferred,
        ];
    }
}
