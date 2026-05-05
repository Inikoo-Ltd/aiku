<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM\Prospect;

use App\Models\CRM\Prospect;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ProspectSearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Prospect $prospect */
        $prospect = $this;

        return [
            'name'         => $prospect->name,
            'contact_name' => $prospect->contact_name,
            'company_name' => $prospect->company_name,
            'email'        => $prospect->email,
            'phone'        => $prospect->phone,


        ];
    }
}
