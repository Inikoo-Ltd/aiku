<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:48
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\User;
use App\Notifications\MoneyRequestRejectedNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When the client rejects a money request, the agent user who asked for it and the users copied on the client's
 * agent page are told why.
 */
class NotifyMoneyRequestRejected
{
    use AsAction;
    use WithMoneyRequestRecipients;

    private const string REQUESTER = 'requester';
    private const string COPY = 'copy';

    public function handle(DepositRequest $depositRequest, ?User $rejecter): int
    {
        $depositRequest->loadMissing(['agent.organisation', 'organisation']);
        $orgAgent = NotifyMoneyRequestCreated::clientOrgAgent($depositRequest);

        $recipients = $this->recipients([
            self::REQUESTER => array_filter([$depositRequest->created_by]),
            self::COPY      => $orgAgent?->moneyRequestCcIds() ?? [],
        ])->filter(fn (array $recipient) => $recipient['user']->id !== $rejecter?->id);

        foreach ($recipients as ['user' => $user, 'role' => $role]) {
            $route = $role === self::REQUESTER || !$orgAgent
                ? ['name' => 'grp.org.agent.accounting.deposit_requests.index', 'parameters' => ['organisation' => $depositRequest->agent->organisation->slug]]
                : ['name' => 'grp.org.procurement.org_agents.show', 'parameters' => ['organisation' => $depositRequest->organisation->slug, 'orgAgent' => $orgAgent->slug, 'tab' => 'money']];

            $this->inLanguageOf($user, function () use ($depositRequest, $rejecter, $user, $role, $route) {
                $notification = new MoneyRequestRejectedNotification(
                    $depositRequest,
                    $rejecter?->chatName() ?? $depositRequest->organisation->code,
                    $role === self::REQUESTER,
                    $route
                );

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::MONEY_REQUEST_REJECTED,
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
