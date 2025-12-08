<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 May 2023 19:34:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property string $created_at
 * @property string $updated_at
 * @property string $description
 * @property string $agent_slug
 * @property string $supplier_slug
 */
class MarketplaceSupplierProductResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'agent_slug' => $this->agent_slug,
            'supplier_slug' => $this->supplier_slug,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

        ];
    }
}
