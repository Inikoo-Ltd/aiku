<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent tells the buying organisation that the supplier wants a new price for a line, before confirming the order.
 */
class AskPurchaseOrderTransactionPriceChange extends OrgAction
{
    use WithActionUpdate;

    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?User $user = null): PurchaseOrderTransaction
    {
        return $this->update($purchaseOrderTransaction, [
            'price_query_state'              => PurchaseOrderTransactionPriceQueryStateEnum::PENDING,
            'price_query_unit_cost'          => round((float) $modelData['unit_cost'], 6),
            'price_query_previous_unit_cost' => $purchaseOrderTransaction->unit_cost ?? $purchaseOrderTransaction->supplierProduct?->cost,
            'price_query_note'               => $modelData['note'] ?? null,
            'price_queried_by_id'            => $user?->id,
            'price_queried_at'               => now(),
            'price_query_answered_at'        => null,
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $purchaseOrder       = $this->purchaseOrderTransaction->purchaseOrder;
        $agentOrganisationId = $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId !== null && $request->user()->authTo("procurement.$agentOrganisationId.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->purchaseOrderTransaction->purchaseOrder->state !== PurchaseOrderStateEnum::SUBMITTED
            || $this->purchaseOrderTransaction->state !== PurchaseOrderTransactionStateEnum::SUBMITTED) {
            $validator->errors()->add('unit_cost', __('A new price can only be asked for while the order waits for the agent to confirm it'));
        }
    }

    public function rules(): array
    {
        return [
            'unit_cost' => ['required', 'numeric', 'gt:0'],
            'note'      => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?User $user = null): PurchaseOrderTransaction
    {
        $this->asAction                 = true;
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $modelData);

        return $this->handle($purchaseOrderTransaction, $this->validatedData, $user);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $purchaseOrderTransaction = $this->handle($purchaseOrderTransaction, $this->validatedData, $request->user());

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), fn () => __('the supplier asks a new price for :code: :new, was :old', [
            'code' => $purchaseOrderTransaction->supplierProduct?->code ?? $purchaseOrderTransaction->orgStock?->code,
            'new'  => trimDecimalZeros($purchaseOrderTransaction->price_query_unit_cost).' '.$purchaseOrder->currency?->code,
            'old'  => trimDecimalZeros($purchaseOrderTransaction->price_query_previous_unit_cost).' '.$purchaseOrder->currency?->code,
        ]));

        return back();
    }
}
