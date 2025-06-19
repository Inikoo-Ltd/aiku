<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\User\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class UserHydrateApiTokens implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(User $user): string
    {
        return $user->id;
    }

    public function handle(User $user): void
    {
        $stats = [
            'number_current_api_tokens' => $user->tokens()
                ->where(function ($query) {
                    $query->where('expires_at', '>', now())
                        ->orWhereNull('expires_at');
                })->count(),

            'number_expired_api_tokens' => $user->tokens()
                ->where('expires_at', '<=', now())
                ->count(),
        ];

        $user->stats()->update($stats);
    }
}
