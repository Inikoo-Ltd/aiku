<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

class OrgPartnersResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var OrgPartner $orgPartner */
        $orgPartner = $this;

        return [
            'id'                       => $orgPartner->id,
            'slug'                     => $orgPartner->slug,
            'code'                     => $orgPartner->code,
            'name'                     => $orgPartner->name,
            'email'                    => $orgPartner->email

        ];
    }
}
