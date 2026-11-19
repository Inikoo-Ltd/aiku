<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use App\Notifications\PurchaseOrderBalanceDueNotification;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Approving the QC releases the balance: the buyer and the organisation's accounts are told it can be paid. The agent
 * sends the same notice again as a reminder when the organisation pays the balance itself and has not recorded it,
 * then only to whom its money requests go: the client's payers and the order's buyer, with the copied users in Cc.
 */
class NotifyPurchaseOrderBalanceDue
{
    use AsAction;

    public const string REMINDER_TO = 'to';

    public const string REMINDER_CC = 'cc';

    public function handle(PurchaseOrder $purchaseOrder, ?User $sender, bool $isReminder = false): int
    {
        $left = SyncPurchaseOrderDepositPaid::leftToPay($purchaseOrder);
        if ($left <= 0) {
            return 0;
        }

        $recipients = $isReminder
            ? self::reminderRecipients($purchaseOrder, $sender)->pluck('user')
            : self::recipients($purchaseOrder, $sender);

        $balance = $purchaseOrder->currency?->code.' '.number_format($left, 2);
        foreach ($recipients as $recipient) {
            $notification = new PurchaseOrderBalanceDueNotification($purchaseOrder, $balance, $isReminder ? ($sender?->contact_name ?: $sender?->username ?: __('The agent')) : null);
            NotifyUserOnChosenChannels::run(
                $recipient,
                UserNotificationEnum::PURCHASE_ORDER_BALANCE_DUE,
                $notification,
                $notification->title(),
                $notification->body(),
                route($notification->route()['name'], $notification->route()['parameters'])
            );
        }

        return $recipients->count();
    }

    /**
     * The order's buyer and everyone active who edits the organisation's accounts, without the sender.
     *
     * @return Collection<int, User>
     */
    public static function recipients(PurchaseOrder $purchaseOrder, ?User $sender): Collection
    {
        return User::permission(["accounting.$purchaseOrder->organisation_id.edit"])->where('status', true)->get()
            ->push($purchaseOrder->buyer)
            ->filter(fn (?User $user) => $user && $user->status && $user->id !== $sender?->id)
            ->unique('id')
            ->values();
    }

    /**
     * The client's money request payers and the order's buyer (To), then its copied users (Cc), each once, without the sender.
     *
     * @return Collection<int, array{user: User, role: string}>
     */
    public static function reminderRecipients(PurchaseOrder $purchaseOrder, ?User $sender): Collection
    {
        $orgAgent = OrgAgent::where('agent_id', $purchaseOrder->agent_id)
            ->where('organisation_id', $purchaseOrder->organisation_id)
            ->first();

        $userIdsByRole = [
            self::REMINDER_TO => array_merge($orgAgent?->moneyRequestPayerIds() ?? [], array_filter([$purchaseOrder->buyer_id])),
            self::REMINDER_CC => $orgAgent?->moneyRequestCcIds() ?? [],
        ];

        $users = User::whereIn('id', collect($userIdsByRole)->flatten()->unique()->values()->all())
            ->where('status', true)
            ->get()
            ->keyBy('id');

        $recipients = collect();
        foreach ($userIdsByRole as $role => $userIds) {
            foreach ($userIds as $userId) {
                $user = $users->get($userId);
                if ($user && $user->id !== $sender?->id && !$recipients->has($user->id)) {
                    $recipients->put($user->id, ['user' => $user, 'role' => $role]);
                }
            }
        }

        return $recipients->values();
    }

    /**
     * What the agent sees before sending: who gets the reminder in To and Cc, and whether by email or only in aiku.
     *
     * @return array{to: list<array{name: string, email: string|null, by_email: bool, is_buyer: bool}>, cc: list<array{name: string, email: string|null, by_email: bool, is_buyer: bool}>}
     */
    public static function reminderEmailRecipients(PurchaseOrder $purchaseOrder, ?User $sender): array
    {
        $recipients = self::reminderRecipients($purchaseOrder, $sender)->map(fn (array $recipient) => [
            'role'     => $recipient['role'],
            'name'     => $recipient['user']->contact_name ?: $recipient['user']->username,
            'email'    => $recipient['user']->email,
            'by_email' => (bool) $recipient['user']->email && in_array('email', UserNotificationEnum::PURCHASE_ORDER_BALANCE_DUE->channelsFor($recipient['user']), true),
            'is_buyer' => $recipient['user']->id === $purchaseOrder->buyer_id,
        ]);

        $withoutRole = fn (Collection $group) => $group->map(fn (array $recipient) => collect($recipient)->except('role')->all())->values()->all();

        return [
            'to' => $withoutRole($recipients->where('role', self::REMINDER_TO)),
            'cc' => $withoutRole($recipients->where('role', self::REMINDER_CC)),
        ];
    }
}
