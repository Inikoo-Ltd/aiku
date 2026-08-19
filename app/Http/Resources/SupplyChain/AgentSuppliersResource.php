<?php

namespace App\Http\Resources\SupplyChain;

use Illuminate\Http\Resources\Json\JsonResource;

class AgentSuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'slug'                     => $this->slug,
            'code'                     => $this->code,
            'name'                     => $this->name,
            'location'                 => $this->location,
            'agent_slug'               => $this->agent_slug,
            'agent_code'               => $this->agent_code,
            'agent_name'               => $this->agent_name,
            'number_supplier_products' => $this->number_supplier_products,
            'number_purchase_orders'   => $this->number_purchase_orders,
            'number_stock_deliveries'  => $this->number_stock_deliveries,
        ];
    }
}
