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
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
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

        $count = DB::table('outbox_time_series_records')
            ->join('outbox_time_series', 'outbox_time_series_records.outbox_time_series_id', '=', 'outbox_time_series.id')
            ->join('outboxes', 'outbox_time_series.outbox_id', '=', 'outboxes.id')
            ->where('outbox_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('outboxes.org_post_room_id', $orgPostRoom->id)
            ->sum('outbox_time_series_records.runs');

        $orgPostRoom->intervals()->update(
            [
                'runs_all' => $count,
            ]
        );
    }

}
