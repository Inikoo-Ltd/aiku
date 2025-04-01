<?php

/*
 * author Arya Permana - Kirin
 * created on 02-12-2024-16h-48m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Comms\PostRoom\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Comms\PostRoom;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PostRoomHydrateIntervals implements ShouldBeUnique
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
        $metrics = ['dispatched', 'opened', 'clicked', 'unsubscribed', 'bounced'];
        $timeFrames = [
            'emails_all', 'emails_1y', 'emails_1q', 'emails_1m', 'emails_1w',
            'emails_3d', 'emails_1d', 'emails_ytd', 'emails_qtd', 'emails_mtd',
            'emails_wtd', 'emails_tdy', 'emails_lm', 'emails_lw', 'emails_ld'
        ];
        $timeFramesLastYear = array_map(fn ($frame) => $frame . '_ly', $timeFrames);

        $allKeys = [];
        foreach ($metrics as $metric) {
            foreach (array_merge($timeFrames, $timeFramesLastYear) as $frame) {
                $allKeys[] = "{$metric}_$frame";
            }
        }

        $stats = collect($allKeys)->mapWithKeys(function ($key) use ($postRoom) {
            return [$key => $postRoom->outboxes->sum(fn ($outbox) => $outbox->intervals->$key)];
        })->toArray();


        $postRoom->stats()->update($stats);

    }


}
