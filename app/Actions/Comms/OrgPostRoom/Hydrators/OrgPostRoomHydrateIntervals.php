<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Comms\OrgPostRoom\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Comms\OrgPostRoom;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgPostRoomHydrateIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithIntervalsAggregators;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(OrgPostRoom $orgPostRoom): string
    {
        return $orgPostRoom->id;
    }


    public function handle(OrgPostRoom $orgPostRoom): void
    {
        // direct from sources
        //  $stats = [];
        //        $queryBase = DB::table('dispatched_emails')->leftJoin('outboxes', 'dispatched_emails.outbox_id', '=', 'outboxes.id')
        //            ->where('outboxes.org_post_room_id', $orgPostRoom->id)
        //            ->whereNotNull('dispatched_emails.sent_at')
        //            ->selectRaw('count(*) as  sum_aggregate ');
        //        $stats = $this->getIntervalsData($stats, $queryBase, 'dispatched_emails_','dispatched_emails.sent_at');


        $metrics    = [
            'dispatched_emails',
            'opened_emails',
            'clicked_emails',
            'bounced_emails',
            'unsubscribed',
        ];
        $timeFrames = [
            'all',
            '1y',
            '1q',
            '1m',
            '1w',
            '3d',
  //          '1d',we don't have this in the database, add it if needed
            'ytd',
            'qtd',
            'mtd',
            'wtd',
            'tdy',
            'lm',
            'lw',
            'ld'
        ];

        $timeFramesLastYear = array_filter(array_map(fn ($frame) => $frame !== 'all' ? $frame.'_ly' : null, $timeFrames));


        $allKeys = [];
        foreach ($metrics as $metric) {
            foreach (array_merge($timeFrames, $timeFramesLastYear) as $frame) {
                $allKeys[] = "{$metric}_$frame";
            }
        }

        $allKeys = array_filter($allKeys);

        $stats = collect($allKeys)->mapWithKeys(function ($key) use ($orgPostRoom) {
            return [$key => $orgPostRoom->outboxes->sum(fn ($outbox) => $outbox->intervals->$key)];
        })->toArray();


        $orgPostRoom->intervals()->update($stats);
    }


}
