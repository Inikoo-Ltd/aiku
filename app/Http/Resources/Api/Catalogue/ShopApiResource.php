<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 13 Oct 2022 15:56:55 Central European Summer Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api\Catalogue;

use App\Http\Resources\Helpers\CurrencyResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $warehouse_area_slug
 * @property mixed $type
 * @property mixed $state
 * @property mixed $organisation_id
 * @property mixed $currency
 */
class ShopApiResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'code'            => $this->code,
            'name'            => $this->name,
            'type'            => $this->type,
            'state'           => $this->state,
            'organisation_id' => $this->organisation_id,
            'currency'        => CurrencyResource::make($this->currency),
        ];
    }
}
