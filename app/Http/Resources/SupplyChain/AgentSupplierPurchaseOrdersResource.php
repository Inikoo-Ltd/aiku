<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderDeliveryStateEnum;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $reference
 * @property AgentSupplierPurchaseOrderStateEnum $state
 * @property AgentSupplierPurchaseOrderDeliveryStateEnum $delivery_state
 * @property mixed $date
 * @property numeric $cost_total
 * @property numeric|null $deposit_amount
 * @property mixed $deposit_paid_at
 * @property mixed $estimated_received_at
 * @property string $currency_code
 * @property string $supplier_code
 * @property string $supplier_slug
 * @property string $purchase_order_reference
 */
class AgentSupplierPurchaseOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        $isOverdue = $this->estimated_received_at
            && now()->greaterThan($this->estimated_received_at)
            && !in_array($this->delivery_state->value, ['received', 'checked', 'placed', 'cancelled'], true);

        return [
            'slug'                     => $this->slug,
            'reference'                => $this->reference,
            'state'                    => $this->state,
            'state_icon'               => $this->state->stateIcon()[$this->state->value],
            'delivery_state'           => $this->delivery_state,
            'delivery_state_icon'      => $this->delivery_state->stateIcon()[$this->delivery_state->value],
            'date'                     => $this->date,
            'cost_total'               => $this->cost_total,
            'deposit_amount'           => $this->deposit_amount,
            'deposit_paid_at'          => $this->deposit_paid_at,
            'estimated_received_at'    => $this->estimated_received_at,
            'is_overdue'               => $isOverdue,
            'currency_code'            => $this->currency_code,
            'supplier_code'            => $this->supplier_code,
            'supplier_slug'            => $this->supplier_slug,
            'purchase_order_reference' => $this->purchase_order_reference,
        ];
    }
}
