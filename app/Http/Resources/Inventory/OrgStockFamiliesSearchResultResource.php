<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 12:43:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\OrgStockFamily;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class OrgStockFamiliesSearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var OrgStockFamily $orgStockFamily */
        $orgStockFamily = $this;

        return [
            'name'  => $orgStockFamily->name,
            'slug'  => $orgStockFamily->slug,
            'code'  => $orgStockFamily->code,
            'state' => $orgStockFamily->state,
            'id'    => $orgStockFamily->id,
        ];
    }
}
