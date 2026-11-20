<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-20 09:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\SupplyChain\SupplierShareLink\UI\IndexSupplierPortalPurchaseOrders;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierPortalPurchaseOrder;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The supplier writes its note on an order from its portal: the agent reads it on an order through an agent, the
 * buyer on a direct one.
 */
class UpdateSupplierPortalPurchaseOrderNotes
{
    use AsAction;

    public function handle(Supplier $supplier, int $purchaseOrderId, ?string $supplierNotes): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);

        return UpdatePurchaseOrder::make()->action($purchaseOrder, ['supplier_notes' => $supplierNotes]);
    }

    public function asController(string $token, int $purchaseOrder, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = Validator::make($request->all(), [
            'supplier_notes' => ['present', 'nullable', 'string', 'max:5000'],
        ])->validate();

        $this->handle($link->supplier, $purchaseOrder, $validated['supplier_notes']);

        return response()->json(ShowSupplierPortalPurchaseOrder::make()->handle($link->supplier, $purchaseOrder, $token));
    }
}
