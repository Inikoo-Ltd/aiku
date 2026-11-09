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
use App\Notifications\MoneyRequestApprovedNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When the client approves a money request, the agent user who asked for it and the users copied on the client's
 * agent page are told the money is on its way to being paid.
 */
class NotifyMoneyRequestApproved
{
    use AsAction;
    use WithMoneyRequestRecipients;

    private const string REQUESTER = 'requester';
    private const string COPY = 'copy';

    public function handle(DepositRequest $depositRequest, ?User $approver): int
    {
        $depositRequest->loadMissing(['agent.organisation', 'organisation', 'currency']);
        $orgAgent = NotifyMoneyRequestCreated::clientOrgAgent($depositRequest);

        $recipients = $this->recipients([
            self::REQUESTER => array_filter([$depositRequest->created_by]),
            self::COPY      => $orgAgent?->moneyRequestCcIds() ?? [],
        ])->filter(fn (array $recipient) => $recipient['user']->id !== $approver?->id);

        $amount = NotifyMoneyRequestCreated::money($depositRequest, (float) $depositRequest->items()->sum('amount'));

        foreach ($recipients as ['user' => $user, 'role' => $role]) {
            $route = $role === self::REQUESTER || !$orgAgent
                ? ['name' => 'grp.org.agent.accounting.deposit_requests.index', 'parameters' => ['organisation' => $depositRequest->agent->organisation->slug]]
                : ['name' => 'grp.org.procurement.org_agents.show', 'parameters' => ['organisation' => $depositRequest->organisation->slug, 'orgAgent' => $orgAgent->slug, 'tab' => 'money']];

            $this->inLanguageOf($user, function () use ($depositRequest, $approver, $user, $role, $route, $amount) {
                $notification = new MoneyRequestApprovedNotification(
                    $depositRequest,
                    $approver?->chatName() ?? $depositRequest->organisation->code,
                    $amount,
                    $role === self::REQUESTER,
                    $route
                );

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::MONEY_REQUEST_APPROVED,
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
