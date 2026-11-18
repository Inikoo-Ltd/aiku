<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

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

    public function handle(Supplier $supplier, int $purchaseOrderId, string $choice, ?int $supplierPortalContactId = null): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);

        $deposit = ShowSupplierPortalPurchaseOrder::make()->deposit($purchaseOrder, $supplier);
        if (!$deposit['choosable']) {
            throw ValidationException::withMessages(['deposit' => __('The deposit of this order is already settled.')]);
        }

        if ($choice === self::NOT_NEEDED) {
            return UpdatePurchaseOrder::make()->action($purchaseOrder, ['deposit_amount' => 0]);
        }

        if (self::isAgreementMissing($supplier)) {
            throw ValidationException::withMessages(['deposit' => __('Please accept Supplier Agreement before submitting deposit request.')]);
        }

        $purchaseOrder->update([
            'data' => [
                ...($purchaseOrder->data ?? []),
                'supplier_deposit_request' => [
                    'percentage'                 => self::REQUEST_PERCENTAGE,
                    'amount'                     => $deposit['request_amount'],
                    'requested_at'               => now()->toIso8601String(),
                    'supplier_portal_contact_id' => $supplierPortalContactId,
                ],
            ],
        ]);

        NotifyPurchasingOfSupplierDepositRequest::run($purchaseOrder);

        return $purchaseOrder;
    }

    public static function isAgreementMissing(Supplier $supplier): bool
    {
        $agreement = GetSupplierAgreementStatus::make()->handle($supplier);

        return $agreement !== null && $agreement['status'] !== SupplierAgreementStatusEnum::ACCEPTED->value;
    }

    public static function requestAmount(PurchaseOrder $purchaseOrder): float
    {
        return round((float) $purchaseOrder->cost_items * self::REQUEST_PERCENTAGE / 100, 2);
    }

    public function asController(string $token, int $purchaseOrder, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = Validator::make($request->all(), [
            'choice' => ['required', Rule::in([self::NOT_NEEDED, self::REQUEST])],
        ])->validate();

        $this->handle($link->supplier, $purchaseOrder, $validated['choice'], $link->supplier_portal_contact_id);

        return response()->json(ShowSupplierPortalPurchaseOrder::make()->handle($link->supplier, $purchaseOrder, $token));
    }
}
