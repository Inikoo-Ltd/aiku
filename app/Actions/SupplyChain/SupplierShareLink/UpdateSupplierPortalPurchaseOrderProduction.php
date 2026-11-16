<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\SupplyChain\SupplierShareLink\UI\IndexSupplierPortalPurchaseOrders;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierPortalPurchaseOrder;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The supplier records from its portal when production started and when it expects to finish, on an agreed order
 * until the goods are handed over. Production started is the Making It Happen stage of the order, as when the buyer
 * or the agent marks it; the expected finish is the order's estimated production date.
 */
class UpdateSupplierPortalPurchaseOrderProduction
{
    use AsAction;

    /**
     * @param  array{production_started_at: string|null, estimated_production_date: string|null}  $modelData
     */
    public function handle(Supplier $supplier, int $purchaseOrderId, array $modelData): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);

        $production = ShowSupplierPortalPurchaseOrder::make()->production($purchaseOrder);
        if (!$production['editable']) {
            throw ValidationException::withMessages(['production' => $production['blocked'] ?? __('This order is closed.')]);
        }

        $startedAt = $modelData['production_started_at'];
        $finishAt  = $modelData['estimated_production_date'];
        if ($startedAt !== null && $startedAt > Carbon::parse(MarkPurchaseOrderStage::today($purchaseOrder->organisation))->addDay()->toDateString()) {
            throw ValidationException::withMessages(['production_started_at' => __('Production cannot start in the future.')]);
        }
        if ($startedAt !== null && $finishAt !== null && $finishAt < $startedAt) {
            throw ValidationException::withMessages(['estimated_production_date' => __('The expected finish cannot be before production started.')]);
        }

        if ($startedAt !== $purchaseOrder->production_started_at?->toDateString()) {
            $reasons       = MarkPurchaseOrderStage::make()->reasonsNeeded($purchaseOrder, 'production_started_at', $startedAt);
            $purchaseOrder = MarkPurchaseOrderStage::make()->action($purchaseOrder, [
                'field'  => 'production_started_at',
                'date'   => $startedAt,
                'reason' => $reasons ? __('Recorded by the supplier from its portal') : null,
            ]);
        }

        if ($finishAt !== Arr::get($purchaseOrder->data ?? [], 'estimated_production_date')) {
            $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, ['estimated_production_date' => $finishAt]);
        }

        return $purchaseOrder;
    }

    public function asController(string $token, int $purchaseOrder, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = Validator::make($request->all(), [
            'production_started_at'     => ['present', 'nullable', 'date_format:Y-m-d'],
            'estimated_production_date' => ['present', 'nullable', 'date_format:Y-m-d'],
        ])->validate();

        $this->handle($link->supplier, $purchaseOrder, $validated);

        return response()->json(ShowSupplierPortalPurchaseOrder::make()->handle($link->supplier, $purchaseOrder, $token));
    }
}
