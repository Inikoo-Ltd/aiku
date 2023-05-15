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
 * @property mixed $location
 * @property mixed $adoption
 * @property bool $is_owner
 */
class MarketplaceAgentResource extends JsonResource
{
    private function getAdoption($value, $isOwner): string
    {
        if($isOwner) {
            return 'owner';
        }

        if ($value === null) {
            return 'available';
        }

        return $value ? 'adopted' : 'removed';
    }

    public function toArray($request): array
    {
        return [
            'slug'                     => $this->slug,
            'code'                     => $this->code,
            'name'                     => $this->name,
            'location'                 => $this->location,
            'number_suppliers'         => $this->number_suppliers,
            'number_supplier_products' => $this->number_supplier_products,
            'adoption'                 => $this->getAdoption($this->adoption, $this->is_owner)

        ];
    }
}
