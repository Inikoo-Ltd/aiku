<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 May 2023 17:20:09 Malaysia Time, Airport, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property string $created_at
 * @property string $updated_at
 * @property numeric $number_suppliers
 * @property numeric $number_supplier_products
 */
class MarketplaceAgentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'code'                    => $this->code,
            'name'                    => $this->name,
            'slug'                    => $this->slug,
            'number_suppliers'        => $this->number_suppliers,
            'number_supplier_products'=> $this->number_supplier_products,

        ];
    }
}
