<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Comms\PostRoom\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Comms\PostRoom;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PostRoomHydrateRuns implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(PostRoom $postRoom): string
    {
        return $postRoom->id;
    }

    public function handle(PostRoom $postRoom): void
    {
        $count = DB::table('outbox_intervals')->leftjoin('outboxes', 'outbox_intervals.outbox_id', '=', 'outboxes.id')
            ->where('post_room_id', $postRoom->id)->sum('runs_all');

        $postRoom->intervals()->update(
            [
                'runs_all' => $count,
            ]
        );
    }

}
