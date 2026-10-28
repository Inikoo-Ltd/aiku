<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Models\SupplyChain\SupplierPortalContact;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierPortalContact
 */
class SupplierPortalContactsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'email'             => $this->email,
            'route'             => ['name' => 'grp.supply-chain.suppliers.share.emails.show', 'parameters' => [$this->supplier_slug, $this->id]],
            'name'              => $this->name,
            'revoked_at'        => $this->revoked_at,
            'last_link_sent_at' => $this->last_link_sent_at,
            'number_links_sent' => $this->number_links_sent,
            'number_visits'     => (int) $this->number_visits,
            'last_visit_at'     => $this->last_visit_at,
            'last_visit_ip'     => $this->last_visit_ip,
        ];
    }
}
