<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionQuantityQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use App\Notifications\PurchaseOrderQuantityChangeAnsweredNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The buying organisation answers the agent's request for a new quantity on a line: accept it or keep the old quantity.
 */
class AnswerPurchaseOrderTransactionQuantityChange extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData): PurchaseOrderTransaction
    {
        $answer = PurchaseOrderTransactionQuantityQueryStateEnum::from($modelData['answer']);

        return DB::transaction(function () use ($purchaseOrderTransaction, $answer) {
            if ($answer === PurchaseOrderTransactionQuantityQueryStateEnum::ACCEPTED) {
                $purchaseOrderTransaction = UpdatePurchaseOrderTransaction::make()->action($purchaseOrderTransaction, [
                    'quantity_ordered' => $purchaseOrderTransaction->quantity_query_quantity,
                ]);
            }

            $purchaseOrderTransaction->update([
                'quantity_query_state'       => $answer,
                'quantity_query_answered_at' => now(),
            ]);

            return $purchaseOrderTransaction;
        });
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->purchaseOrderTransaction->quantity_query_state !== PurchaseOrderTransactionQuantityQueryStateEnum::PENDING
            || !in_array($this->purchaseOrderTransaction->state, [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED])
            || !in_array($this->purchaseOrderTransaction->purchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])) {
            $validator->errors()->add('answer', __('There is no new quantity waiting for an answer on this line'));

            return;
        }

        if ((float) $this->get('quantity_ordered') !== (float) $this->purchaseOrderTransaction->quantity_query_quantity) {
            $validator->errors()->add('answer', __('The agent changed the quantity asked, reload the page to see it'));

            return;
        }

        if ($this->get('answer') === PurchaseOrderTransactionQuantityQueryStateEnum::ACCEPTED->value) {
            AskPurchaseOrderTransactionQuantityChange::addQuantityErrors($validator, $this->purchaseOrderTransaction, (float) $this->purchaseOrderTransaction->quantity_query_quantity, 'answer');
        }
    }

    public function rules(): array
    {
        return [
            'answer' => ['required', Rule::in([
                PurchaseOrderTransactionQuantityQueryStateEnum::ACCEPTED->value,
                PurchaseOrderTransactionQuantityQueryStateEnum::REJECTED->value,
            ])],
            'quantity_ordered' => ['required', 'numeric'],
        ];
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData): PurchaseOrderTransaction
    {
        $this->asAction                 = true;
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $modelData);

        return $this->handle($purchaseOrderTransaction, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $purchaseOrderTransaction = $this->handle($purchaseOrderTransaction, $this->validatedData);
        $this->notifyAgent($purchaseOrder, $purchaseOrderTransaction, $request->user());

        return back();
    }

    private function notifyAgent(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, User $user): void
    {
        $agentUser         = $purchaseOrderTransaction->quantityQueriedBy;
        $agentOrganisation = $purchaseOrder->agent?->organisation;

        if (!$agentUser || !$agentUser->status || !$agentOrganisation || $agentUser->id === $user->id) {
            return;
        }

        $notification = new PurchaseOrderQuantityChangeAnsweredNotification($purchaseOrder, $purchaseOrderTransaction, $agentOrganisation->slug);
        NotifyUserOnChosenChannels::run(
            $agentUser,
            UserNotificationEnum::PURCHASE_ORDER_PRICE_ANSWERED,
            $notification,
            $notification->title(),
            $notification->body(),
            route('grp.org.agent.purchase_orders.show', [$agentOrganisation->slug, $purchaseOrder->slug])
        );
    }
}
