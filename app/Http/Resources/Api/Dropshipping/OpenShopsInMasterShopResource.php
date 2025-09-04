<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Sept 2025 10:10:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api\Dropshipping;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property mixed $type
 * @property mixed $currency_code
 * @property mixed $organisation_id
 */
class OpenShopsInMasterShopResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'code'            => $this->code,
            'name'            => $this->name,
            'type'            => $this->type->labels()[$this->type->value],
            'currency'        => $this->currency_code,
            'organisation_id' => $this->organisation_id,
            'product' => [
                'stock' => 0,
                'cost_price' => 0,
                'margin' => 0,
                'org_cost' => 0,
                'grp_cost' => 0,
                'has_org_stocks' => false,
                'price'           => 0,
            ]
        ];
    }
}
