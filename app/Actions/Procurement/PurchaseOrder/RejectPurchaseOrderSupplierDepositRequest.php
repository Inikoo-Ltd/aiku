<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 16:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierShareLink\NotifySupplierOfDepositAnswer;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The buyer, or the agent's staff, rejects the deposit a supplier asked for from its portal, with the reason the
 * supplier reads in its email and on the order in its portal. The request is moved to
 * data.supplier_deposit_request_rejected, so the supplier can answer the deposit stage again.
 */
class RejectPurchaseOrderSupplierDepositRequest extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction
            || $request->user()->authTo("procurement.{$this->organisation->id}.edit")
            || MarkPurchaseOrderStage::isAgentStaff($request->user(), $this->purchaseOrder);
    }

    public function handle(PurchaseOrder $purchaseOrder, string $reason, ?int $userId): PurchaseOrder
    {
        $request = null;
        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $reason, $userId, &$request) {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            $data          = $purchaseOrder->data ?? [];
            $request       = Arr::get($data, 'supplier_deposit_request');

            if (!$request || $purchaseOrder->isDepositPaid()) {
                throw ValidationException::withMessages(['reason' => __('There is no deposit request waiting for an answer on this order.')]);
            }

            unset($data['supplier_deposit_request']);
            $data['supplier_deposit_request_rejected'] = [
                ...$request,
                'reason'              => $reason,
                'rejected_at'         => now()->toIso8601String(),
                'rejected_by_user_id' => $userId,
            ];
            $purchaseOrder->update(['data' => $data]);

            return $purchaseOrder;
        });

        NotifySupplierOfDepositAnswer::make()->rejected($purchaseOrder, $request, $reason);

        return $purchaseOrder;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'reason.required' => __('Tell the supplier why, it reads this in its email and on its page.'),
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, trim($this->validatedData['reason']), $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
