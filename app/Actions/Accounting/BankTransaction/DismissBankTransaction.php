<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\OrgAction;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * From the bank review queue: the line pays no order (a marketplace payout, a refund) and leaves the queue with the
 * reason, or, marked already paid, its order already carries the payment staff recorded by hand. Matching never takes it
 * up again.
 */
class DismissBankTransaction extends OrgAction
{
    use AuthorizesAccountingEdit;

    private ?User $user = null;

    public function handle(BankTransaction $bankTransaction, array $modelData): BankTransaction
    {
        return DB::transaction(function () use ($bankTransaction, $modelData) {
            $bankTransaction = BankTransaction::lockForUpdate()->findOrFail($bankTransaction->id);

            $alreadyPaid = (bool) ($modelData['already_paid'] ?? false);
            $namedOrder  = $alreadyPaid && !$bankTransaction->order_id && isset($modelData['order_id'])
                && in_array($bankTransaction->match_state, [BankTransactionMatchStateEnum::UNMATCHED, BankTransactionMatchStateEnum::REVIEW], true)
                ? Order::where('organisation_id', $bankTransaction->organisation_id)->lockForUpdate()->find($modelData['order_id'])
                : null;
            if ($namedOrder) {
                ($modelData['from_suggestion'] ?? false)
                    ? $this->checkHandPaymentOn($namedOrder, $bankTransaction)
                    : $this->checkPickedOrder($namedOrder, $bankTransaction);
                $bankTransaction->order_id = $namedOrder->id;
                $bankTransaction->setRelation('order', $namedOrder);
            }

            $problem = match (true) {
                $bankTransaction->payment_id !== null => __('This bank payment is already recorded on an order'),
                $bankTransaction->direction !== BankTransactionDirectionEnum::IN || $bankTransaction->status !== BankTransactionStatusEnum::BOOKED => __('Only money received and booked by the bank is reviewed here'),
                $alreadyPaid && !$bankTransaction->order => __('No order is named in this bank payment'),
                $alreadyPaid && $bankTransaction->order->payment_amount <= 0 => __('No payment is recorded on :order yet', ['order' => $bankTransaction->order->reference]),
                default => null,
            };
            if ($problem) {
                throw ValidationException::withMessages(['note' => $problem]);
            }

            $bankTransaction->update([
                'match_state' => $alreadyPaid ? BankTransactionMatchStateEnum::ALREADY_PAID : BankTransactionMatchStateEnum::DISMISSED,
                'match_data'  => array_merge($bankTransaction->match_data, [
                    'dismissed_note' => $modelData['note'],
                    'dismissed_by'   => $this->user?->contact_name ?? $this->user?->username ?? 'staff',
                    'dismissed_at'   => now()->toIso8601String(),
                ]),
                'matched_at'  => now(),
            ]);
            $bankTransaction->recordDecision($alreadyPaid ? 'marked_already_paid' : 'set_aside', array_filter([
                'order' => $bankTransaction->order?->reference,
                'note'  => $modelData['note'],
            ]));

            return $bankTransaction;
        });
    }

    /**
     * Picking the order from a suggestion: decided again under the lock, it must still be the one order suggested as
     * already paid, so a stale page cannot put a second transfer on the same hand payment.
     */
    private function checkHandPaymentOn(Order $order, BankTransaction $bankTransaction): void
    {
        $stillSuggested = collect(MatchBankTransactions::make()->suggestedOrders($bankTransaction))
            ->contains(fn (array $suggestion) => $suggestion['id'] === $order->id && $suggestion['kind'] === 'already_paid');
        if (!$stillSuggested) {
            throw ValidationException::withMessages(['note' => __('No bank payment of this amount is recorded on :order for this payer', ['order' => $order->reference])]);
        }
    }

    /**
     * Picking the order by hand: a transfer of the same amount already paid onto the order, or already marked as paid on
     * it, means this one is a second transfer, not the payment recorded by hand.
     */
    private function checkPickedOrder(Order $order, BankTransaction $bankTransaction): void
    {
        $received = round((float)$bankTransaction->amount, 2);
        $problem  = match (true) {
            $order->currency_id !== $bankTransaction->currency_id => __(':order is in another currency', ['order' => $order->reference]),
            BankTransaction::where('id', '!=', $bankTransaction->id)
                ->where('direction', BankTransactionDirectionEnum::IN)
                ->where(fn ($query) => $query->where('match_state', BankTransactionMatchStateEnum::ALREADY_PAID)->orWhereNotNull('payment_id'))
                ->where(fn ($query) => $query->where('order_id', $order->id)->orWhereRaw("match_data->'order_ids' @> to_jsonb(?::bigint)", [$order->id]))
                ->whereBetween('amount', [$received - 0.01, $received + 0.01])
                ->exists() => __('Another bank payment of this amount is already marked as paid on :order', ['order' => $order->reference]),
            default => null,
        };
        if ($problem) {
            throw ValidationException::withMessages(['note' => $problem]);
        }
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:500'],
            'already_paid' => ['sometimes', 'boolean'],
            'order_id' => ['sometimes', 'nullable', 'integer'],
            'from_suggestion' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, BankTransaction $bankTransaction, ActionRequest $request): BankTransaction
    {
        $this->user = $request->user();
        $this->initialisation($organisation, $request);
        abort_unless($bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
