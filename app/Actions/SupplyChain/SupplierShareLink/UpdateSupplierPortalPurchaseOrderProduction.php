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

    public const string STARTED_BY_SUPPLIER_KEY = 'supplier_production_started_on';

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
        $agreedOn = ($purchaseOrder->confirmed_at ?? $purchaseOrder->submitted_at)?->copy()->setTimezone($purchaseOrder->organisation->timezone?->name ?? config('app.timezone'))->toDateString();
        if ($startedAt !== null && $agreedOn !== null && $startedAt < $agreedOn) {
            throw ValidationException::withMessages(['production_started_at' => __('Production cannot start before the order was agreed.')]);
        }
        if ($startedAt !== null && $finishAt !== null && $finishAt < $startedAt) {
            throw ValidationException::withMessages(['estimated_production_date' => __('The expected finish cannot be before production started.')]);
        }

        $recordedOn = $purchaseOrder->production_started_at?->toDateString();
        if ($startedAt !== $recordedOn) {
            if ($recordedOn !== null && Arr::get($purchaseOrder->data ?? [], self::STARTED_BY_SUPPLIER_KEY) !== $recordedOn) {
                throw ValidationException::withMessages(['production_started_at' => __('This date was recorded by our team: ask us to change it.')]);
            }

            $reasons       = MarkPurchaseOrderStage::make()->reasonsNeeded($purchaseOrder, 'production_started_at', $startedAt);
            $purchaseOrder = MarkPurchaseOrderStage::make()->gated()->action($purchaseOrder, [
                'field'  => 'production_started_at',
                'date'   => $startedAt,
                'reason' => $reasons ? __('Recorded by the supplier from its portal') : null,
            ]);

            $data = $purchaseOrder->data ?? [];
            if ($startedAt === null) {
                unset($data[self::STARTED_BY_SUPPLIER_KEY]);
            } else {
                $data[self::STARTED_BY_SUPPLIER_KEY] = $startedAt;
            }
            $purchaseOrder->update(['data' => $data]);
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
