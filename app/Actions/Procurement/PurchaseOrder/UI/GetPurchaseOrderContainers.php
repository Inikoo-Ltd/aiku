<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-05 08:37
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The containers holding goods of an agent order, and the lines that can still go in one, so the whole order can be
 * put in a container from its stages.
 */
class GetPurchaseOrderContainers
{
    use AsObject;

    /**
     * @return array{containers: array<int, array<string, mixed>>, items_to_add: array<int, array<string, mixed>>}|null
     */
    public function handle(PurchaseOrder $purchaseOrder, Organisation $viewingOrganisation, Request $request): ?array
    {
        if (!$purchaseOrder->isAgentOrder()) {
            return null;
        }

        return [
            'containers'   => $this->containers($purchaseOrder, $viewingOrganisation),
            'items_to_add' => $this->itemsToAdd($purchaseOrder, $request),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function containers(PurchaseOrder $purchaseOrder, Organisation $viewingOrganisation): array
    {
        $isAgentViewing = $viewingOrganisation->id !== $purchaseOrder->organisation_id;
        $stateLabels    = StockDeliveryStateEnum::labels();
        $stateIcons     = StockDeliveryStateEnum::stateIcon();

        return StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction(DB::query())
            ->join('purchase_order_transactions', 'purchase_order_transactions.purchase_order_id', 'purchase_order_stock_delivery.purchase_order_id')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->where('purchase_order_stock_delivery.purchase_order_id', $purchaseOrder->id)
            ->whereNull('stock_deliveries.deleted_at')
            ->whereNotIn('stock_deliveries.state', [StockDeliveryStateEnum::CANCELLED->value, StockDeliveryStateEnum::NOT_RECEIVED->value])
            ->groupBy('stock_deliveries.id', 'stock_deliveries.reference', 'stock_deliveries.slug', 'stock_deliveries.state')
            ->orderBy('stock_deliveries.id')
            ->select('stock_deliveries.id', 'stock_deliveries.reference', 'stock_deliveries.slug', 'stock_deliveries.state')
            ->selectRaw('count(distinct purchase_order_transactions.id) as number_lines')
            ->selectRaw('coalesce(sum(stock_delivery_items.unit_quantity), 0) as unit_quantity')
            ->get()
            ->map(fn (object $container) => [
                'id'            => $container->id,
                'reference'     => $container->reference,
                'state'         => $container->state,
                'state_label'   => $stateLabels[$container->state] ?? $container->state,
                'state_icon'    => isset($stateIcons[$container->state]) ? Arr::only($stateIcons[$container->state], ['icon', 'class']) : null,
                'number_lines'  => (int) $container->number_lines,
                'unit_quantity' => (float) $container->unit_quantity,
                'show_route'    => [
                    'name'       => $isAgentViewing ? 'grp.org.agent.stock_deliveries.show' : 'grp.org.procurement.stock_deliveries.show',
                    'parameters' => [$viewingOrganisation->slug, $container->slug],
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsToAdd(PurchaseOrder $purchaseOrder, Request $request): array
    {
        $query = $purchaseOrder->purchaseOrderTransactions()->getQuery()
            ->select('purchase_order_transactions.*')
            ->where('purchase_order_transactions.state', PurchaseOrderTransactionStateEnum::CONFIRMED)
            ->with(['supplierProduct:id,code,name', 'orgStock:id,code,name', 'orgStock.tradeUnits.image', 'organisation:id,code'])
            ->orderBy('purchase_order_transactions.id');

        IndexPurchaseOrderTransactions::selectAgentWarehouseQuantities($query);

        return $query->get()
            ->map(function (PurchaseOrderTransaction $transaction) use ($purchaseOrder, $request) {
                $transaction->setRelation('purchaseOrder', $purchaseOrder);
                $agentWarehouse = PurchaseOrderTransactionResource::make($transaction)->agentWarehouse($transaction, $request);
                $tradeUnit      = $transaction->orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

                return [
                    'id'              => $transaction->id,
                    'code'            => $transaction->supplierProduct?->code ?? $transaction->orgStock?->code,
                    'name'            => $transaction->supplierProduct?->name ?? $transaction->orgStock?->name,
                    'image_thumbnail' => $tradeUnit?->imageSources(80, 80),
                    'agent_warehouse' => $agentWarehouse,
                ];
            })
            ->filter(fn (array $item) => $item['agent_warehouse']['containers_route'] ?? null)
            ->values()
            ->all();
    }
}
