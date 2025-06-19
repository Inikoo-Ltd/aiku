<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateApiTokens implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_current_api_tokens' => $group->tokens()->where('expires_at', '>', now())->orWhereNull('expires_at')->count(),
            'number_expired_api_tokens' => $group->tokens()->where('expires_at', '<=', now())->count(),
        ];

        $group->stats->update($stats);
    }
}
