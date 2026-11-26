<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use App\Notifications\PurchaseOrderPriceChangeAnsweredNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The buying organisation answers the agent's question about a new supplier price: accept it, keep the old price, or set its own.
 */
class AnswerPurchaseOrderTransactionPriceChange extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData): PurchaseOrderTransaction
    {
        $answer = PurchaseOrderTransactionPriceQueryStateEnum::from($modelData['answer']);

        return DB::transaction(function () use ($purchaseOrderTransaction, $modelData, $answer) {
            PurchaseOrder::whereKey($purchaseOrderTransaction->purchase_order_id)->lockForUpdate()->first();
            $this->purchaseOrderTransaction = $purchaseOrderTransaction->refresh();
            $validator                      = ValidatorFacade::make([], []);
            $this->addAnswerErrors($validator);
            if ($validator->errors()->isNotEmpty()) {
                throw new ValidationException($validator);
            }

            $unitCost = match ($answer) {
                PurchaseOrderTransactionPriceQueryStateEnum::ACCEPTED => $purchaseOrderTransaction->price_query_unit_cost,
                PurchaseOrderTransactionPriceQueryStateEnum::ADJUSTED => $modelData['unit_cost'],
                default                                               => null,
            };

            if ($unitCost !== null) {
                $purchaseOrderTransaction = UpdatePurchaseOrderTransaction::make()->action($purchaseOrderTransaction, [
                    'unit_cost'            => $unitCost,
                    'update_supplier_cost' => (bool) ($modelData['update_supplier_cost'] ?? false),
                ]);
            }

            $purchaseOrderTransaction->update([
                'price_query_state'       => $answer,
                'price_query_answered_at' => now(),
            ]);

            return $purchaseOrderTransaction;
        });
    }

    /**
     * The same product's other lines, in this organisation's other open orders, where the supplier also asks a new price
     * that is still waiting for an answer: the buyer can accept them together.
     *
     * @return Collection<int, PurchaseOrderTransaction>
     */
    public static function pendingSiblings(PurchaseOrderTransaction $purchaseOrderTransaction): Collection
    {
        if ($purchaseOrderTransaction->supplier_product_id === null && $purchaseOrderTransaction->org_stock_id === null) {
            return collect();
        }

        return PurchaseOrderTransaction::query()
            ->with('purchaseOrder')
            ->where('organisation_id', $purchaseOrderTransaction->organisation_id)
            ->whereKeyNot($purchaseOrderTransaction->id)
            ->when(
                $purchaseOrderTransaction->supplier_product_id !== null,
                fn ($query) => $query->where('supplier_product_id', $purchaseOrderTransaction->supplier_product_id),
                fn ($query) => $query->where('org_stock_id', $purchaseOrderTransaction->org_stock_id)
            )
            ->where('price_query_state', PurchaseOrderTransactionPriceQueryStateEnum::PENDING)
            ->whereIn('state', [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED])
            ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('state', PurchaseOrderStateEnum::open()))
            ->orderBy('id')
            ->get()
            ->filter(fn (PurchaseOrderTransaction $sibling) => AskPurchaseOrderTransactionPriceChange::isAskable($sibling))
            ->values();
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $this->addAnswerErrors($validator);

        $siblingIds = $this->get('also_accept_transaction_ids', []);
        if (!empty($siblingIds)) {
            if ($this->get('answer') !== PurchaseOrderTransactionPriceQueryStateEnum::ACCEPTED->value) {
                $validator->errors()->add('also_accept_transaction_ids', __('Only an accepted price can be accepted on other orders too'));
            } elseif (array_diff($siblingIds, self::pendingSiblings($this->purchaseOrderTransaction)->pluck('id')->all())) {
                $validator->errors()->add('also_accept_transaction_ids', __('The new price on another order is no longer waiting for an answer, reload the page'));
            }
        }

        if (!$this->asAction && $request->boolean('update_supplier_cost') && !$request->user()?->authTo('supply-chain.edit')) {
            $validator->errors()->add('update_supplier_cost', __('You do not have permission to change supplier prices'));
        }
    }

    /**
     * Checked when the answer is sent and again, with the order locked, as it is saved: two buyers answering at once
     * can not both apply it.
     */
    private function addAnswerErrors(Validator $validator): void
    {
        if ($this->purchaseOrderTransaction->price_query_state !== PurchaseOrderTransactionPriceQueryStateEnum::PENDING
            || !in_array($this->purchaseOrderTransaction->state, [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED], true)
            || !in_array($this->purchaseOrderTransaction->purchaseOrder->state, PurchaseOrderStateEnum::open(), true)) {
            $validator->errors()->add('answer', __('There is no new price waiting for an answer on this line'));
        } elseif ($this->get('answer') !== PurchaseOrderTransactionPriceQueryStateEnum::REJECTED->value
            && !AskPurchaseOrderTransactionPriceChange::isAskable($this->purchaseOrderTransaction)) {
            $validator->errors()->add('answer', __('The goods are shipped, the price can no longer be changed, only kept'));
        }
    }

    public function rules(): array
    {
        return [
            'answer'               => ['required', Rule::in([
                PurchaseOrderTransactionPriceQueryStateEnum::ACCEPTED->value,
                PurchaseOrderTransactionPriceQueryStateEnum::REJECTED->value,
                PurchaseOrderTransactionPriceQueryStateEnum::ADJUSTED->value,
            ])],
            'unit_cost'            => ['required_if:answer,adjusted', 'nullable', 'numeric', 'gt:0'],
            'update_supplier_cost' => ['sometimes', 'boolean'],
            'also_accept_transaction_ids'   => ['sometimes', 'array'],
            'also_accept_transaction_ids.*' => ['integer'],
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

        $siblings = self::pendingSiblings($purchaseOrderTransaction)->whereIn('id', $this->validatedData['also_accept_transaction_ids'] ?? []);

        $purchaseOrderTransaction = DB::transaction(function () use ($purchaseOrderTransaction, $siblings) {
            $answered = $this->handle($purchaseOrderTransaction, Arr::except($this->validatedData, 'also_accept_transaction_ids'));

            foreach ($siblings as $sibling) {
                AnswerPurchaseOrderTransactionPriceChange::make()->action($sibling, [
                    'answer' => PurchaseOrderTransactionPriceQueryStateEnum::ACCEPTED->value,
                ]);
            }

            return $answered;
        });

        $this->notifyAgent($purchaseOrder, $purchaseOrderTransaction, $request->user());
        foreach ($siblings as $sibling) {
            $this->notifyAgent($sibling->purchaseOrder, $sibling->refresh(), $request->user());
        }

        return back();
    }

    private function notifyAgent(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, User $user): void
    {
        self::notifyAgentUser($purchaseOrder, $purchaseOrderTransaction, $purchaseOrderTransaction->priceQueriedBy, $user);
    }

    public static function notifyAgentUser(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ?User $agentUser, User $user): void
    {
        $agentOrganisation = $purchaseOrder->agent?->organisation;

        if (!$agentUser || !$agentUser->status || !$agentOrganisation || $agentUser->id === $user->id
            || !$agentUser->authTo("procurement.$agentOrganisation->id.view")) {
            return;
        }

        $notification = new PurchaseOrderPriceChangeAnsweredNotification($purchaseOrder, $purchaseOrderTransaction, $agentOrganisation->slug);
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
