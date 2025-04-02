<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Comms\OrgPostRoom\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Comms\OrgPostRoom;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgPostRoomHydrateRuns implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OrgPostRoom $orgPostRoom): string
    {
        return $orgPostRoom->id;
    }

    public function handle(OrgPostRoom $orgPostRoom): void
    {

        $count = DB::table('outbox_intervals')->leftjoin('outboxes', 'outbox_intervals.outbox_id', '=', 'outboxes.id')
            ->where('org_post_room_id', $orgPostRoom->id)->sum('runs_all');

        $orgPostRoom->intervals()->update(
            [
                'runs_all' => $count,
            ]
        );
    }

}
