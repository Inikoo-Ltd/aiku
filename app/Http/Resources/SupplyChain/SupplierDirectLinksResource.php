<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierShareLink
 */
class SupplierDirectLinksResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'created_at'     => $this->created_at,
            'created_by'     => $this->created_by,
            'person_name'    => $this->person_name,
            'live'           => $this->isLive(),
            'expires_at'     => $this->expires_at,
            'revoked_at'     => $this->revoked_at,
            'email'          => $this->email,
            'number_views'   => $this->number_views,
            'last_viewed_at' => $this->last_viewed_at,
            'last_visit_ip'  => $this->last_visit_ip,
            'revoke_route'   => ['name' => 'grp.models.supplier_share_link.revoke', 'parameters' => ['supplierShareLink' => $this->id]],
        ];
    }
}
