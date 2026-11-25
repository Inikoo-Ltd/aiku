<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\Procurement\PurchaseOrder\SyncPurchaseOrderDepositPaid;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use App\Actions\SupplyChain\SupplierShareLink\UI\IndexSupplierPortalPurchaseOrders;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierPortalPurchaseOrder;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementStatusEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * At the deposit stage the supplier says from its portal whether it needs a deposit. "No deposit needed" is the
 * buyer's own No deposit needed (deposit_amount 0), which agrees the terms of a submitted order. A deposit request
 * asks for a fixed share of the order: it is kept in purchase_orders.data.supplier_deposit_request, never in
 * deposit_amount (SyncPurchaseOrderDepositPaid rewrites that from the payments), and the buyer, the purchasing
 * leads of the ordering organisation and of the agent are emailed. A supplier asks for a deposit only once it has
 * accepted the current Supplier Agreement; "No deposit needed" never waits for it.
 */
class UpdateSupplierPortalPurchaseOrderDeposit
{
    use AsAction;

    public const string NOT_NEEDED = 'not_needed';

    public const string REQUEST = 'request';

    public const int REQUEST_PERCENTAGE = 30;

    public function handle(Supplier $supplier, int $purchaseOrderId, string $choice, ?int $supplierPortalContactId = null, ?float $percentage = null): PurchaseOrder
    {
        IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);

        $isNewRequest = false;
        $purchaseOrder = DB::transaction(function () use ($supplier, $purchaseOrderId, $choice, $supplierPortalContactId, $percentage, &$isNewRequest) {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrderId)->lockForUpdate()->firstOrFail();

            $deposit = ShowSupplierPortalPurchaseOrder::make()->deposit($purchaseOrder, $supplier);
            if (!$deposit['choosable']) {
                $alreadyAnswered = $choice === self::NOT_NEEDED ? $deposit['not_needed'] : $deposit['request'] !== null;
                if ($alreadyAnswered) {
                    return $purchaseOrder;
                }

                throw ValidationException::withMessages(['deposit' => __('The deposit of this order is already settled.')]);
            }

            if ($choice === self::NOT_NEEDED) {
                $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, ['deposit_amount' => 0]);
                $purchaseOrder->update(['data' => [...($purchaseOrder->data ?? []), 'deposit_not_required_by' => 'supplier']]);

                return $purchaseOrder;
            }

            if (self::isAgreementMissing($supplier)) {
                throw ValidationException::withMessages(['deposit' => __('Please accept Supplier Agreement before submitting deposit request.')]);
            }

            $percentage                       = $percentage ?? self::requestPercentage($purchaseOrder);
            $data                             = $purchaseOrder->data ?? [];
            $data['supplier_deposit_request'] = [
                'percentage'                 => $percentage,
                'amount'                     => self::requestAmount($purchaseOrder, $percentage),
                'requested_at'               => now()->toIso8601String(),
                'supplier_portal_contact_id' => $supplierPortalContactId,
            ];
            $purchaseOrder->update(['data' => $data]);
            $isNewRequest = true;

            return $purchaseOrder;
        });

        if ($isNewRequest) {
            NotifyPurchasingOfSupplierDepositRequest::run($purchaseOrder);
        }

        return $purchaseOrder;
    }

    public static function isAgreementMissing(Supplier $supplier): bool
    {
        $agreement = GetSupplierAgreementStatus::make()->handle($supplier);

        return $agreement !== null && $agreement['status'] !== SupplierAgreementStatusEnum::ACCEPTED->value;
    }

    public static function requestPercentage(PurchaseOrder $purchaseOrder): float
    {
        return SyncPurchaseOrderDepositPaid::defaultDepositPercentage($purchaseOrder) ?? self::REQUEST_PERCENTAGE;
    }

    public static function requestAmount(PurchaseOrder $purchaseOrder, ?float $percentage = null): float
    {
        return round((float) $purchaseOrder->cost_items * ($percentage ?? self::requestPercentage($purchaseOrder)) / 100, 2);
    }

    public function asController(string $token, int $purchaseOrder, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = Validator::make($request->all(), [
            'choice'     => ['required', Rule::in([self::NOT_NEEDED, self::REQUEST])],
            'percentage' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ])->validate();

        $percentage = $validated['choice'] === self::REQUEST && isset($validated['percentage']) ? round((float) $validated['percentage'], 2) : null;

        $this->handle($link->supplier, $purchaseOrder, $validated['choice'], $link->supplier_portal_contact_id, $percentage);

        return response()->json(ShowSupplierPortalPurchaseOrder::make()->handle($link->supplier, $purchaseOrder, $token));
    }
}
