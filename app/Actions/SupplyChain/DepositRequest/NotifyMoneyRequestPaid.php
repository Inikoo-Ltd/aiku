<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\User;
use App\Notifications\MoneyRequestPaidNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When the client sends money against a request, the agent user who asked for it and the users copied on the client's
 * agent page are pointed to the agent's ledger, where the transfer and its bank statement are.
 */
class NotifyMoneyRequestPaid
{
    use AsAction;
    use WithMoneyRequestRecipients;

    private const string REQUESTER = 'requester';
    private const string COPY = 'copy';

    public function handle(DepositRequest $depositRequest, AgentPayment $transfer, ?User $payer): int
    {
        $depositRequest->loadMissing(['agent.organisation', 'organisation', 'currency']);
        $orgAgent = NotifyMoneyRequestCreated::clientOrgAgent($depositRequest);

        $recipients = $this->recipients([
            self::REQUESTER => array_filter([$depositRequest->created_by]),
            self::COPY      => $orgAgent?->moneyRequestCcIds() ?? [],
        ])->filter(fn (array $recipient) => $recipient['user']->id !== $payer?->id);

        $route = ['name' => 'grp.org.agent.accounting.ledger.index', 'parameters' => ['organisation' => $depositRequest->agent->organisation->slug]];

        foreach ($recipients as ['user' => $user, 'role' => $role]) {
            $this->inLanguageOf($user, function () use ($depositRequest, $transfer, $payer, $user, $role, $route) {
                $notification = new MoneyRequestPaidNotification(
                    $depositRequest,
                    $transfer,
                    $payer?->chatName() ?? $depositRequest->organisation->code,
                    NotifyMoneyRequestCreated::money($depositRequest, (float) $transfer->amount),
                    $role === self::REQUESTER,
                    $route
                );

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::MONEY_REQUEST_PAID,
                    $notification,
                    $notification->title(),
                    $notification->body(),
                    route($route['name'], $route['parameters'])
                );
            });
        }

        return $recipients->count();
    }
}
