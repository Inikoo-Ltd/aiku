<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\OrgPartner;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgPartnerResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var OrgPartner $orgPartner */
        $orgPartner = $this;

        return [
            'id'    => $orgPartner->id,
            'code'  => $orgPartner->partner->code,
            'type'  => 'Partner',
            'name'  => $orgPartner->partner->name,
            'email' => $orgPartner->partner->email,
        ];
    }
}
