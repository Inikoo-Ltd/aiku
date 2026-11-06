<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 15:02
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\User;
use App\Notifications\MoneyRequestUnapprovedNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When the client takes back its approval of a money request, the agent user who asked for it is told.
 */
class NotifyMoneyRequestUnapproved
{
    use AsAction;
    use WithMoneyRequestRecipients;

    public function handle(DepositRequest $depositRequest, ?User $unapprover): int
    {
        $depositRequest->loadMissing(['agent.organisation', 'organisation']);

        $recipients = $this->recipients(['requester' => array_filter([$depositRequest->created_by])])
            ->filter(fn (array $recipient) => $recipient['user']->id !== $unapprover?->id);

        $route = ['name' => 'grp.org.agent.accounting.deposit_requests.index', 'parameters' => ['organisation' => $depositRequest->agent->organisation->slug]];

        foreach ($recipients as ['user' => $user]) {
            $this->inLanguageOf($user, function () use ($depositRequest, $unapprover, $user, $route) {
                $notification = new MoneyRequestUnapprovedNotification(
                    $depositRequest,
                    $unapprover?->chatName() ?? $depositRequest->organisation->code,
                    $route
                );

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::MONEY_REQUEST_UNAPPROVED,
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
