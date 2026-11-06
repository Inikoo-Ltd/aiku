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
     *
     * @return Collection<int, array{user: User, role: string}>
     */
    protected function recipients(array $userIdsByRole): Collection
    {
        $users = User::whereIn('id', collect($userIdsByRole)->flatten()->filter()->unique()->all())
            ->where('status', true)
            ->get()
            ->keyBy('id');

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
