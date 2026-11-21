<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\User;
use App\Notifications\MoneyRequestPaymentUndoneNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When the client undoes a payment that was set as paid from its bank, the agent user who asked for the money and the
 * users copied on the client's agent page are told that the request is open to pay again.
 */
class NotifyMoneyRequestPaymentUndone
{
    use AsAction;
    use WithMoneyRequestRecipients;

    private const string REQUESTER = 'requester';
    private const string COPY = 'copy';

    public function handle(DepositRequest $depositRequest, float $amount, ?User $undoer): int
    {
        $depositRequest->loadMissing(['agent.organisation', 'organisation', 'currency']);
        $orgAgent = NotifyMoneyRequestCreated::clientOrgAgent($depositRequest);

        $recipients = $this->recipients([
            self::REQUESTER => array_filter([$depositRequest->created_by]),
            self::COPY      => $orgAgent?->moneyRequestCcIds() ?? [],
        ])->filter(fn (array $recipient) => $recipient['user']->id !== $undoer?->id);

        foreach ($recipients as ['user' => $user, 'role' => $role]) {
            $route = $role === self::REQUESTER || !$orgAgent
                ? ['name' => 'grp.org.agent.accounting.deposit_requests.index', 'parameters' => ['organisation' => $depositRequest->agent->organisation->slug]]
                : ['name' => 'grp.org.procurement.org_agents.show', 'parameters' => ['organisation' => $depositRequest->organisation->slug, 'orgAgent' => $orgAgent->slug, 'tab' => 'money']];

            $this->inLanguageOf($user, function () use ($depositRequest, $amount, $undoer, $user, $role, $route) {
                $notification = new MoneyRequestPaymentUndoneNotification(
                    $depositRequest,
                    $undoer?->chatName() ?? $depositRequest->organisation->code,
                    NotifyMoneyRequestCreated::money($depositRequest, $amount),
                    $role === self::REQUESTER,
                    $route
                );

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::MONEY_REQUEST_PAYMENT_UNDONE,
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
