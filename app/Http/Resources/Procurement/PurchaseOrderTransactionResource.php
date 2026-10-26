<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\GetOrgStockBuyingSignals;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionQuantityQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PurchaseOrderTransaction $transaction */
        $transaction = $this->resource;

        $supplierProduct = $transaction->supplierProduct;
        $orgStock        = $transaction->orgStock;
        $tradeUnit       = $orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

        return [
            'id'                   => $transaction->id,
            'slug'                 => $supplierProduct?->slug,
            'org_supplier_product_slug' => $transaction->orgSupplierProduct?->slug,
            'code'                 => $supplierProduct?->code ?? $orgStock?->code,
            'name'                 => $supplierProduct?->name ?? $orgStock?->name,
            'supplier_name'        => $supplierProduct?->supplier?->name,
            'supplier_slug'        => $transaction->orgSupplierProduct?->orgSupplier?->slug,
            'org_stock_id'         => $transaction->org_stock_id,
            'org_stock_code'       => $orgStock?->code,
            'image_thumbnail'      => $tradeUnit?->imageSources(160, 160),
            'image_preview'        => $tradeUnit?->imageSources(480, 480),
            'image_full'           => $tradeUnit?->image?->fullResolutionUrls(),
            'stock_in_locations'   => $orgStock?->quantity_in_locations === null ? null : trimDecimalZeros($orgStock->quantity_in_locations),
            'quarterly_usage'      => $transaction->quarterly_usage ?? [],
            'discontinue_suggestions' => $transaction->discontinue_suggestions,
            'stock_cover'          => $transaction->buying_signals ?? GetOrgStockBuyingSignals::run($orgStock, $supplierProduct),
            'stock_deliveries'     => $transaction->stock_deliveries,
            'other_open_purchase_orders' => $transaction->other_open_purchase_orders ?? [],
            'partner_stock'        => $transaction->partner_stock?->stock === null ? null : (float) $transaction->partner_stock->stock,
            'partner_units_per_carton' => $transaction->partner_stock?->units_per_carton,
            'hub_name'             => $transaction->partner_stock?->hub_name,

            'unit_cost'            => $transaction->unit_cost ?? $supplierProduct?->cost,
            'supplier_unit_cost'   => $supplierProduct?->cost,
            'can_update_supplier_cost' => $supplierProduct !== null && $request->user()?->authTo('supply-chain.edit'),
            'units_per_pack'       => $orgStock?->packed_in ?: $supplierProduct?->units_per_pack,
            'units_per_carton'     => $supplierProduct ? $supplierProduct->units_per_carton : $orgStock?->packed_in,
            'supplier_unit'        => $supplierProduct?->supplier_unit?->value,
            'units_per_supplier_unit' => $supplierProduct?->supplier_unit ? $supplierProduct->unitsPerSupplierUnit() : null,
            'quantity_ordered'     => $transaction->quantity_ordered,
            'has_packing_instructions' => $supplierProduct !== null,
            'minimum_carton_order' => $supplierProduct?->minimum_carton_order ? (int) $supplierProduct->minimum_carton_order : null,

            'net_amount'           => $transaction->net_amount,
            'net_currency'         => $transaction->purchaseOrder?->currency?->code ?? $supplierProduct?->currency?->code,
            'org_net_amount'       => $transaction->org_net_amount,
            'org_currency'         => $transaction->organisation?->currency?->code,
            'org_exchange'         => $transaction->org_exchange,

            'weight'               => $transaction->weight === null ? null : (float) $transaction->weight,
            'volume'               => $transaction->volume === null ? null : (float) $transaction->volume,

            'state'                => $transaction->state->value,
            'state_label'          => $transaction->state->labels()[$transaction->state->value],
            'state_icon'           => $transaction->state->stateIcon()[$transaction->state->value],

            'delivery_state'       => $transaction->delivery_state->value,
            'delivery_state_label' => PurchaseOrderTransactionDeliveryStateEnum::labels()[$transaction->delivery_state->value],
            'delivery_state_icon'  => PurchaseOrderTransactionDeliveryStateEnum::stateIcon()[$transaction->delivery_state->value],

            'updateRoute'          => [
                'name'       => 'grp.models.purchase-order.transaction.update',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'patch',
            ],
            'deleteRoute'          => [
                'name'       => 'grp.models.purchase-order.transaction.delete',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'delete',
            ],
            'price_query'          => $transaction->price_query_state ? [
                'state'              => $transaction->price_query_state->value,
                'label'              => $transaction->price_query_state->label(),
                'unit_cost'          => $transaction->price_query_unit_cost,
                'previous_unit_cost' => $transaction->price_query_previous_unit_cost,
                'note'               => $transaction->price_query_note,
                'asked_by'           => $transaction->priceQueriedBy?->contact_name ?: $transaction->priceQueriedBy?->username,
                'asked_at'           => $transaction->price_queried_at,
                'answered_at'        => $transaction->price_query_answered_at,
            ] : null,
            'askPriceRoute'        => ($canAgentAsk = $this->canAgentAsk($transaction, $request)) ? [
                'name'       => 'grp.models.purchase-order.transaction.price_change.ask',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'post',
            ] : null,
            'answerPriceRoute'     => $this->canOrganisationAnswer($transaction, $request, $transaction->price_query_state === PurchaseOrderTransactionPriceQueryStateEnum::PENDING) ? [
                'name'       => 'grp.models.purchase-order.transaction.price_change.answer',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'patch',
            ] : null,
            'quantity_query'       => $transaction->quantity_query_state ? [
                'state'             => $transaction->quantity_query_state->value,
                'label'             => $transaction->quantity_query_state->label(),
                'quantity'          => $transaction->quantity_query_quantity,
                'previous_quantity' => $transaction->quantity_query_previous_quantity,
                'note'              => $transaction->quantity_query_note,
                'asked_by'          => $transaction->quantityQueriedBy?->contact_name ?: $transaction->quantityQueriedBy?->username,
                'asked_at'          => $transaction->quantity_queried_at,
                'answered_at'       => $transaction->quantity_query_answered_at,
            ] : null,
            'askQuantityRoute'     => $canAgentAsk ? [
                'name'       => 'grp.models.purchase-order.transaction.quantity_change.ask',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'post',
            ] : null,
            'answerQuantityRoute'  => $this->canOrganisationAnswer($transaction, $request, $transaction->quantity_query_state === PurchaseOrderTransactionQuantityQueryStateEnum::PENDING) ? [
                'name'       => 'grp.models.purchase-order.transaction.quantity_change.answer',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'patch',
            ] : null,
            'agent_warehouse'      => $this->agentWarehouse($transaction, $request),
            'cancelRoute'          => $this->isCancellable($transaction) ? [
                'name'       => 'grp.models.purchase-order.transaction.cancel',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'patch',
            ] : null,
        ];
    }

    private function canAgentAsk(PurchaseOrderTransaction $transaction, $request): bool
    {
        $purchaseOrder = $transaction->purchaseOrder;

        if (!$purchaseOrder?->isAgentOrder()
            || $purchaseOrder->state !== PurchaseOrderStateEnum::SUBMITTED
            || $transaction->state !== PurchaseOrderTransactionStateEnum::SUBMITTED) {
            return false;
        }

        $agentOrganisationId = $purchaseOrder->agent?->organisation_id;

        return $agentOrganisationId !== null
            && $this->viewingOrganisationId($request) === $agentOrganisationId
            && $request->user()?->authTo("procurement.$agentOrganisationId.edit");
    }

    private function canOrganisationAnswer(PurchaseOrderTransaction $transaction, $request, bool $isQuestionPending): bool
    {
        return $isQuestionPending
            && $transaction->state === PurchaseOrderTransactionStateEnum::SUBMITTED
            && $transaction->purchaseOrder?->state === PurchaseOrderStateEnum::SUBMITTED
            && $this->viewingOrganisationId($request) === $transaction->organisation_id
            && $request->user()?->authTo("procurement.$transaction->organisation_id.edit");
    }

    /**
     * What the agent holds of an agent order's line, with packing it for the agent viewing its own order and adding it
     * to a container for whoever may fill the containers.
     */
    private function agentWarehouse(PurchaseOrderTransaction $transaction, $request): ?array
    {
        if ($transaction->getAttribute('quantity_awaiting_delivery') === null) {
            return null;
        }

        $purchaseOrder       = $transaction->purchaseOrder;
        $agentOrganisationId = $purchaseOrder?->agent?->organisation_id;
        $user                = $request->user();

        $isAgentEditing = $agentOrganisationId !== null
            && $this->viewingOrganisationId($request) === $agentOrganisationId
            && $user?->authTo("procurement.$agentOrganisationId.edit");

        $packable = (float) $transaction->getAttribute('quantity_packable');
        $packed   = (float) $transaction->getAttribute('quantity_packed');
        $awaiting = max(0, (float) $transaction->getAttribute('quantity_awaiting_delivery'));
        $onContainers = max(0, (float) $transaction->quantity_ordered - $awaiting);

        $canEdit = (bool) $user?->authTo("procurement.$transaction->organisation_id.edit");

        $readyToShip = $purchaseOrder && StoreStockDeliveryFromPurchaseOrder::shipsFromAgentWarehouse($purchaseOrder)
            ? max(0, min($awaiting, (float) $transaction->getAttribute('quantity_received_at_agent_warehouse') - $onContainers))
            : $awaiting;

        $canAddToContainer = $purchaseOrder?->state === PurchaseOrderStateEnum::CONFIRMED
            && $transaction->state === PurchaseOrderTransactionStateEnum::CONFIRMED
            && $readyToShip > StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE
            && ($isAgentEditing || $canEdit);

        return [
            'quantity_packable'                => $packable,
            'quantity_to_check'                => (float) $transaction->getAttribute('quantity_to_check'),
            'quantity_packed'                  => $packed,
            'quantity_awaiting_delivery'       => $awaiting,
            'quantity_ready_to_ship'           => $readyToShip,
            'quantity_packed_not_on_container' => max(0, round($packed - $onContainers, 3)),
            'pack_route'                       => $isAgentEditing && $packable > 0 ? [
                'name'       => 'grp.models.purchase-order.transaction.pack',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'patch',
            ] : null,
            'containers_route'                 => $canAddToContainer ? [
                'name'       => 'grp.json.purchase_order.open_agent_containers',
                'parameters' => ['purchaseOrder' => $transaction->purchase_order_id],
            ] : null,
            'new_container_route'              => $canAddToContainer ? [
                'name'       => 'grp.models.purchase-order.new_container',
                'parameters' => ['purchaseOrder' => $transaction->purchase_order_id],
                'method'     => 'post',
            ] : null,
            'organisation_code'                => $transaction->organisation?->code,
            'is_qc_approved'                   => $purchaseOrder?->qc_passed_at !== null,
            'containers'                       => $this->containersOfLine($transaction, $request, $isAgentEditing || $canEdit),
        ];
    }

    /**
     * The containers holding units of the line, linked from the agent's or the buyer's side, and removable while in process.
     */
    private function containersOfLine(PurchaseOrderTransaction $transaction, $request, bool $canRemove): array
    {
        $viewingOrganisation = $request->route('organisation');
        $isAgentViewing      = $viewingOrganisation instanceof Organisation && $viewingOrganisation->id !== $transaction->organisation_id;

        return collect(json_decode($transaction->getAttribute('agent_containers') ?? '[]', true))
            ->map(fn (array $container) => [
                'id'            => $container['id'],
                'reference'     => $container['reference'],
                'unit_quantity' => (float) $container['unit_quantity'],
                'show_route'    => $viewingOrganisation instanceof Organisation ? [
                    'name'       => $isAgentViewing ? 'grp.org.agent.stock_deliveries.show' : 'grp.org.procurement.stock_deliveries.show',
                    'parameters' => [$viewingOrganisation->slug, $container['slug']],
                ] : null,
                'remove_route'  => $canRemove && $container['state'] === StockDeliveryStateEnum::IN_PROCESS->value ? [
                    'name'       => 'grp.models.stock-delivery.purchase_order_items.remove',
                    'parameters' => ['stockDelivery' => $container['id'], 'purchaseOrderTransaction' => $transaction->id],
                    'method'     => 'delete',
                ] : null,
            ])
            ->values()
            ->all();
    }

    private function viewingOrganisationId($request): ?int
    {
        $organisation = $request->route('organisation');

        return $organisation instanceof Organisation ? $organisation->id : null;
    }

    private function isCancellable(PurchaseOrderTransaction $transaction): bool
    {
        if ($transaction->purchaseOrder?->areLinesLockedByProduction()) {
            return false;
        }

        return match ($transaction->state) {
            PurchaseOrderTransactionStateEnum::SUBMITTED => true,
            PurchaseOrderTransactionStateEnum::CONFIRMED => !$transaction->is_on_delivery,
            default => false,
        };
    }
}
