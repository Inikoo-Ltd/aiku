<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Support\Collection;

trait WithMoneyRequestRecipients
{
    /**
     * Active users to tell, each once with the first role they appear under.
     *
     * @param  array<string, array<int, int|null>>  $userIdsByRole
     * @param  Collection<int, User>|null  $activeUsersById  the active users already loaded, keyed by id
     *
     * @return Collection<int, array{user: User, role: string}>
     */
    protected function recipients(array $userIdsByRole, ?Collection $activeUsersById = null): Collection
    {
        $users = $activeUsersById ?? self::activeUsersById(collect($userIdsByRole)->flatten()->all());

        $recipients = collect();
        foreach ($userIdsByRole as $role => $userIds) {
            foreach ($userIds as $userId) {
                $user = $users->get($userId);
                if ($user && !$recipients->has($user->id)) {
                    $recipients->put($user->id, ['user' => $user, 'role' => $role]);
                }
            }
        }

        return $recipients->values();
    }

    /**
     * @param  array<int, int|null>  $userIds
     *
     * @return Collection<int, User>
     */
    protected static function activeUsersById(array $userIds): Collection
    {
        return User::whereIn('id', collect($userIds)->filter()->unique()->values()->all())
            ->where('status', true)
            ->get()
            ->keyBy('id');
    }

    protected function inLanguageOf(User $user, Closure $callback): void
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($user->language?->code ?: 'en');

        try {
            $callback();
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
