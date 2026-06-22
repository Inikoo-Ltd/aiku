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
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
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
        $count = DB::table('outbox_time_series_records')
            ->join('outbox_time_series', 'outbox_time_series_records.outbox_time_series_id', '=', 'outbox_time_series.id')
            ->join('outboxes', 'outbox_time_series.outbox_id', '=', 'outboxes.id')
            ->where('outbox_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('outboxes.post_room_id', $postRoom->id)
            ->sum('outbox_time_series_records.runs');

        $postRoom->intervals()->update(
            [
                'runs_all' => $count,
            ]
        );
    }

}
