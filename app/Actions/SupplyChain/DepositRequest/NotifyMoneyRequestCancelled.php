<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-09 11:34
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\User;
use App\Notifications\MoneyRequestCancelledNotification;
use App\Notifications\MoneyRequestCreatedNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When the agent withdraws a money request, the client's payers and copied users stop waiting on it, and the agent
 * user who asked for it gets a copy when someone else withdrew it.
 */
class NotifyMoneyRequestCancelled
{
    use AsAction;
    use WithMoneyRequestRecipients;

    public function handle(DepositRequest $depositRequest, ?User $canceller): int
    {
        $depositRequest->loadMissing(['agent.organisation', 'organisation', 'currency']);
        $orgAgent = NotifyMoneyRequestCreated::clientOrgAgent($depositRequest);

        $recipients = $this->recipients([
            MoneyRequestCreatedNotification::FOR_PAYER     => $orgAgent?->moneyRequestPayerIds() ?? [],
            MoneyRequestCreatedNotification::FOR_COPY      => $orgAgent?->moneyRequestCcIds() ?? [],
            MoneyRequestCreatedNotification::FOR_REQUESTER => array_filter([$depositRequest->created_by]),
        ])->filter(fn (array $recipient) => $recipient['user']->id !== $canceller?->id);

        $amount = NotifyMoneyRequestCreated::money($depositRequest, (float) $depositRequest->items()->sum('amount'));

        foreach ($recipients as ['user' => $user, 'role' => $role]) {
            $route = $role === MoneyRequestCreatedNotification::FOR_REQUESTER || !$orgAgent
                ? ['name' => 'grp.org.agent.accounting.deposit_requests.index', 'parameters' => ['organisation' => $depositRequest->agent->organisation->slug]]
                : ['name' => 'grp.org.procurement.org_agents.show', 'parameters' => ['organisation' => $depositRequest->organisation->slug, 'orgAgent' => $orgAgent->slug, 'tab' => 'money']];

            $this->inLanguageOf($user, function () use ($depositRequest, $canceller, $user, $role, $route, $amount) {
                $notification = new MoneyRequestCancelledNotification(
                    $depositRequest,
                    $canceller?->chatName() ?? $depositRequest->agent->code,
                    $amount,
                    $role,
                    $route
                );

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::MONEY_REQUEST_CANCELLED,
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
