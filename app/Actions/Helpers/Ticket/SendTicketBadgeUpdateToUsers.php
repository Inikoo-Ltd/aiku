<?php

/*
 * Author Louis Perez
 * Created on 15-09-2026-09h-34m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Events\BroadcastTicketBadgeUpdate;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SendTicketBadgeUpdateToUsers
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    /**
     * @param array<int, int> $userIds
     * @param array{title: string, body: string, route: string}|null $notification
     */
    public function handle(array $userIds, ?array $notification = null): void
    {
        $users = User::where('status', true)->whereIn('id', array_values(array_unique($userIds)))->get();

        foreach ($users as $user) {
            BroadcastTicketBadgeUpdate::dispatch(
                $user->id,
                GetTicketBadgeData::run($user),
                $notification
            );
        }
    }
}
