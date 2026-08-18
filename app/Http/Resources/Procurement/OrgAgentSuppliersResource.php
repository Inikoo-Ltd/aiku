<?php

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

class OrgAgentSuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'org_supplier_slug'                     => $this->org_supplier_slug,
            'org_agent_slug'                        => $this->org_agent_slug,
            'code'                                  => $this->code,
            'name'                                  => $this->name,
            'location'                              => json_decode($this->location),
            'agent_code'                            => $this->agent_code,
            'agent_name'                            => $this->agent_name,
            'number_org_supplier_products'          => $this->number_org_supplier_products,
            'number_agent_supplier_purchase_orders' => $this->number_agent_supplier_purchase_orders,
            'number_supplier_deliveries'            => $this->number_supplier_deliveries,
        ];
    }
}
