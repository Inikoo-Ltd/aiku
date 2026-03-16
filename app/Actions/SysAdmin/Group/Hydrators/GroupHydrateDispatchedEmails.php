<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateDispatchedEmails implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(?int $groupID): string
    {
        return $groupID ?? 'empty';
    }

    public function handle(?int $groupID): void
    {
        if (!$groupID) {
            return;
        }
        $group = Group::find($groupID);
        if (!$group) {
            return;
        }
        $stats = [
            'number_dispatched_emails' => DB::table('outboxes')->where('group_id', $group->id)
                ->leftJoin('outbox_stats', 'outboxes.id', '=', 'outbox_stats.outbox_id')
                ->sum('number_dispatched_emails'),
        ];

        $group->commsStats()->update($stats);
    }


}
