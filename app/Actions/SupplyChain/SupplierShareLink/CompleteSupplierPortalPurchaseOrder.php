<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-23 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage;
use App\Actions\SupplyChain\SupplierShareLink\UI\IndexSupplierPortalPurchaseOrders;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierPortalPurchaseOrder;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A supplier selling direct (no agent) marks from its portal that its order is complete: it dispatched the goods to
 * AW Group. That is the order's Clean Hand Over, recorded today. It needs the order agreed and at least one shipping
 * document uploaded from the portal first.
 */
class CompleteSupplierPortalPurchaseOrder
{
    use AsAction;

    public const string COMPLETED_BY_SUPPLIER_KEY = 'supplier_completed_on';

    public function handle(Supplier $supplier, int $purchaseOrderId): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);

        $completion = ShowSupplierPortalPurchaseOrder::make()->completion($purchaseOrder);
        if (!$completion['available'] || !$completion['completable']) {
            throw ValidationException::withMessages(['completion' => $completion['blocked'] ?? __('This order cannot be completed from here.')]);
        }

        $today         = MarkPurchaseOrderStage::today($purchaseOrder->organisation);
        $reasons       = MarkPurchaseOrderStage::make()->reasonsNeeded($purchaseOrder, 'handed_over_at', $today);
        $purchaseOrder = MarkPurchaseOrderStage::make()->action($purchaseOrder, [
            'field'  => 'handed_over_at',
            'date'   => $today,
            'reason' => $reasons ? __('Order complete: recorded by the supplier from its portal') : null,
        ]);

        $purchaseOrder->update(['data' => [...($purchaseOrder->data ?? []), self::COMPLETED_BY_SUPPLIER_KEY => $today]]);

        rescue(fn () => NotifyPurchasingOfSupplierShipment::run($purchaseOrder));

        return $purchaseOrder;
    }

    public function asController(string $token, int $purchaseOrder): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $this->handle($link->supplier, $purchaseOrder);

        return response()->json(ShowSupplierPortalPurchaseOrder::make()->handle($link->supplier, $purchaseOrder, $token));
    }
}
