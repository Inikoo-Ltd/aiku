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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent tells the buying organisation that the supplier wants a new price for a line, before confirming the order.
 * Someone who also edits the buying organisation's procurement would only answer their own question, so for them the
 * price applies at once, recorded as an accepted change.
 */
class AskPurchaseOrderTransactionPriceChange extends OrgAction
{
    use WithActionUpdate;

    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?User $user = null, bool $appliesAtOnce = false): PurchaseOrderTransaction
    {
        if ($appliesAtOnce) {
            return $this->applyAtOnce($purchaseOrderTransaction, $modelData, $user);
        }

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

    /**
     * The order is locked as an answer locks it, and the line checked again as it is now.
     */
    private function applyAtOnce(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?User $user): PurchaseOrderTransaction
    {
        return DB::transaction(function () use ($purchaseOrderTransaction, $modelData, $user) {
            PurchaseOrder::whereKey($purchaseOrderTransaction->purchase_order_id)->lockForUpdate()->first();
            $purchaseOrderTransaction = $purchaseOrderTransaction->refresh();

            if (!self::isAskable($purchaseOrderTransaction)) {
                throw ValidationException::withMessages(['unit_cost' => __('A new price can only be set until the goods are shipped')]);
            }

            $previousUnitCost         = $purchaseOrderTransaction->unit_cost ?? $purchaseOrderTransaction->supplierProduct?->cost;
            $unitCost                 = round((float) $modelData['unit_cost'], 6);
            $purchaseOrderTransaction = UpdatePurchaseOrderTransaction::make()->action($purchaseOrderTransaction, [
                'unit_cost'            => $unitCost,
                'update_supplier_cost' => (bool) ($modelData['update_supplier_cost'] ?? false),
            ]);
            $changedAt                = now();

            return $this->update($purchaseOrderTransaction, [
                'price_query_state'              => PurchaseOrderTransactionPriceQueryStateEnum::ACCEPTED,
                'price_query_unit_cost'          => $unitCost,
                'price_query_previous_unit_cost' => $previousUnitCost,
                'price_query_note'               => $modelData['note'] ?? null,
                'price_queried_by_id'            => $user?->id,
                'price_queried_at'               => $changedAt,
                'price_query_answered_at'        => $changedAt,
            ]);
        });
    }

    /**
     * The agent may ask for a new supplier price on an open order until the line is shipped, also after the supplier
     * started making the goods or the agent received them.
     */
    public static function isAskable(PurchaseOrderTransaction $purchaseOrderTransaction): bool
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        return $purchaseOrder !== null
            && $purchaseOrder->isAgentOrder()
            && in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true)
            && in_array($purchaseOrderTransaction->state, [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED], true)
            && !AskPurchaseOrderTransactionQuantityChange::isShipped($purchaseOrderTransaction);
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

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if (!self::isAskable($this->purchaseOrderTransaction)) {
            $validator->errors()->add('unit_cost', __('A new price can only be asked for until the goods are shipped'));
        }

        if (!$this->asAction && $request->boolean('update_supplier_cost') && !$request->user()?->authTo('supply-chain.edit')) {
            $validator->errors()->add('update_supplier_cost', __('You do not have permission to change supplier prices'));
        }
    }

    public function rules(): array
    {
        return [
            'unit_cost' => ['required', 'numeric', 'gt:0'],
            'note'      => ['sometimes', 'nullable', 'string', 'max:1000'],
            'update_supplier_cost' => ['sometimes', 'boolean'],
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

        $appliesAtOnce = AskPurchaseOrderTransactionQuantityChange::isBuyerEditing($purchaseOrder, $request->user());
        $pendingAsker  = $purchaseOrderTransaction->price_query_state === PurchaseOrderTransactionPriceQueryStateEnum::PENDING ? $purchaseOrderTransaction->priceQueriedBy : null;

        $purchaseOrderTransaction = $this->handle($purchaseOrderTransaction, $this->validatedData, $request->user(), $appliesAtOnce);

        if ($appliesAtOnce) {
            AnswerPurchaseOrderTransactionPriceChange::notifyAgentUser($purchaseOrder, $purchaseOrderTransaction, $pendingAsker, $request->user());

            return back();
        }

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), fn () => __('the supplier asks a new price for :code: :new, was :old', [
            'code' => $purchaseOrderTransaction->supplierProduct?->code ?? $purchaseOrderTransaction->orgStock?->code,
            'new'  => trimDecimalZeros($purchaseOrderTransaction->price_query_unit_cost).' '.$purchaseOrder->currency?->code,
            'old'  => trimDecimalZeros($purchaseOrderTransaction->price_query_previous_unit_cost).' '.$purchaseOrder->currency?->code,
        ]));

        return back();
    }
}
