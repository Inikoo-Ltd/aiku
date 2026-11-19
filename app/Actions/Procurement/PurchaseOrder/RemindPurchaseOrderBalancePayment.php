<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-19 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * When the organisation pays the balance to the supplier itself, the agent reminds its buyer and accounts to pay it and
 * record the payment with its bank statement, by email and in the Aiku inbox. One reminder an hour at most, kept in
 * purchase_orders.data->balance_reminder.
 */
class RemindPurchaseOrderBalancePayment extends OrgAction
{
    public const int MINUTES_BETWEEN_REMINDERS = 60;

    private PurchaseOrder $purchaseOrder;

    /**
     * @return array{last_at: ?string, last_by: ?int, count: int}
     */
    public static function reminder(PurchaseOrder $purchaseOrder): array
    {
        $reminder = Arr::get($purchaseOrder->data, 'balance_reminder', []);

        return [
            'last_at' => Arr::get($reminder, 'last_at'),
            'last_by' => Arr::get($reminder, 'last_by'),
            'count'   => (int) Arr::get($reminder, 'count', 0),
        ];
    }

    public static function nextReminderAt(PurchaseOrder $purchaseOrder): ?Carbon
    {
        $lastAt = self::reminder($purchaseOrder)['last_at'];
        if ($lastAt === null) {
            return null;
        }

        $nextReminderAt = Carbon::parse($lastAt)->addMinutes(self::MINUTES_BETWEEN_REMINDERS);

        return $nextReminderAt->isFuture() ? $nextReminderAt : null;
    }

    public static function canRemind(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->isAgentOrder()
            && ConfirmPurchaseOrderPayment::acceptsPayments($purchaseOrder)
            && SyncPurchaseOrderDepositPaid::leftToPay($purchaseOrder) > 0;
    }

    /**
     * @throws ValidationException
     */
    public function handle(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        if (!self::canRemind($purchaseOrder)) {
            throw ValidationException::withMessages(['reminder' => __('Nothing is left to pay on this order.')]);
        }

        $previousReminder = Arr::get($purchaseOrder->data, 'balance_reminder');
        $count            = self::reminder($purchaseOrder)['count'];

        $claimed = PurchaseOrder::whereKey($purchaseOrder->id)
            ->where(fn ($query) => $query
                ->whereNull('data->balance_reminder->last_at')
                ->orWhere('data->balance_reminder->last_at', '<=', now()->subMinutes(self::MINUTES_BETWEEN_REMINDERS)->toIso8601ZuluString()))
            ->toBase()
            ->update([
                'data->balance_reminder' => [
                    'last_at' => now()->toIso8601ZuluString(),
                    'last_by' => $user->id,
                    'count'   => $count + 1,
                ],
            ]);

        if (!$claimed) {
            throw ValidationException::withMessages(['reminder' => __('A reminder was sent less than an hour ago. Try again in :minutes minutes.', [
                'minutes' => max(1, (int) ceil(now()->diffInMinutes(self::nextReminderAt($purchaseOrder->refresh()) ?? now()))),
            ])]);
        }

        $purchaseOrder->refresh();

        if (NotifyPurchaseOrderBalanceDue::run($purchaseOrder, $user, true) === 0) {
            PurchaseOrder::whereKey($purchaseOrder->id)->toBase()->update(['data->balance_reminder' => $previousReminder]);

            throw ValidationException::withMessages(['reminder' => __('Nobody in :organisation can be reminded: the order has no active buyer and no one edits its accounts.', [
                'organisation' => $purchaseOrder->organisation->name,
            ])]);
        }

        return $purchaseOrder;
    }

    public function authorize(ActionRequest $request): bool
    {
        $agentOrganisationId = $this->purchaseOrder->isAgentOrder() ? $this->purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId !== null && $request->user()->authTo("procurement.$agentOrganisationId.edit");
    }

    /**
     * @throws ValidationException
     */
    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->agent?->organisation ?? $purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
