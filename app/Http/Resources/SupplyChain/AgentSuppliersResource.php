<?php

namespace App\Http\Resources\SupplyChain;

use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property array|null $location
 * @property string $agent_slug
 * @property string $agent_code
 * @property string $agent_name
 * @property int|null $number_supplier_products
 * @property int|null $number_purchase_orders
 * @property int|null $number_stock_deliveries
 * @property string|null $agreement_status
 * @property int|null $agreement_accepted_version
 */
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
            'agreement'                => GetSupplierAgreementStatus::make()->badge($this->agreement_status ?? null, $this->agreement_accepted_version ?? null),
        ];
    }
}
