<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property string $number
 * @property string $slug
 * @property string $date
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property mixed $parent_name
 * @property mixed $reference
 * @property mixed $parent_type
 * @property mixed $state
 * @property mixed $org_currency_code
 * @property mixed $parent_slug
 * @property mixed $org_total_cost
 * @property mixed $supplier_purchase_orders
 * @property mixed $organisation_name
 * @property mixed $organisation_code
 * @property mixed $organisation_slug
 * @property mixed $quantity_ordered
 * @property mixed $org_net_amount
 * @property int|null $number_pending_line_answers
 * @property string|null $shipments
 */
class PurchaseOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'reference'         => $this->reference,
            'agent_order_reference' => $this->agent_order_reference,
            'state'             => $this->state,
            'state_label'       => $this->state->labelFor((bool) $this->agent_id),
            'state_icon'        => $this->state->iconFor((bool) $this->agent_id),
            'parent_type'       => $this->parent_type,
            'parent_name'       => $this->parent_name,
            'parent_slug'       => $this->parent->slug,
            'agent_name'        => $this->agent_id && $this->relationLoaded('agent') ? $this->agent?->name : null,
            'agent_slug'        => $this->agent_id && $this->relationLoaded('agent') ? $this->agent?->slug : null,
            'org_agent_slug'    => $this->agent_id && $this->parent_type === 'OrgSupplier' && $this->parent && $this->parent->relationLoaded('orgAgent') ? $this->parent->orgAgent?->slug : null,
            'supplier_slug'     => $this->parent_type === 'OrgSupplier' && $this->parent && $this->parent->relationLoaded('supplier') ? $this->parent->supplier?->slug : null,
            'slug'              => $this->slug,
            'number_current_purchase_order_transactions'   => $this->number_current_purchase_order_transactions,
            'quantity_ordered'  => $this->quantity_ordered,
            'date'              => $this->date,
            'submitted_at'      => $this->submitted_at ?? $this->date,
            'org_currency_code'     => $this->org_currency_code,
            'org_net_amount'    => $this->org_net_amount,
            'org_total_cost'    => $this->org_total_cost,
            'supplier_purchase_orders' => $this->supplier_purchase_orders,
            'organisation_name' => $this->organisation_name,
            'organisation_code' => $this->organisation_code,
            'organisation_slug' => $this->organisation_slug,
            'number_pending_line_answers' => (int) $this->number_pending_line_answers,
            'shipments'         => $this->shipmentLinks($request),
            'is_without_deposit' => $this->isWithoutDeposit(),
        ];
    }

    /**
     * A sent order that owes a deposit and has no payment recorded yet.
     */
    private function isWithoutDeposit(): bool
    {
        return in_array($this->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true)
            && $this->deposit_paid_at === null
            && !GetPurchaseOrderStages::make()->depositNotRequired($this->resource);
    }

    /**
     * @return array<int, array{reference: string, state_icon: ?array, cbm: ?float, show_route: ?array}>
     */
    private function shipmentLinks($request): array
    {
        $viewingOrganisation = $request->route('organisation');
        $isAgentViewing      = $viewingOrganisation instanceof Organisation && $viewingOrganisation->id !== $this->organisation_id;
        $stateIcons          = StockDeliveryStateEnum::stateIcon();

        return collect(json_decode($this->getAttribute('shipments') ?? '[]', true))
            ->map(fn (array $shipment) => [
                'reference'  => $shipment['reference'],
                'state_icon' => isset($stateIcons[$shipment['state']]) ? Arr::only($stateIcons[$shipment['state']], ['tooltip', 'icon', 'class']) : null,
                'cbm'        => $shipment['cbm'] === null ? null : round((float) $shipment['cbm'], 3),
                'show_route' => $viewingOrganisation instanceof Organisation ? [
                    'name'       => $isAgentViewing ? 'grp.org.agent.stock_deliveries.show' : 'grp.org.procurement.stock_deliveries.show',
                    'parameters' => [$viewingOrganisation->slug, $shipment['slug']],
                ] : null,
            ])
            ->all();
    }

}
