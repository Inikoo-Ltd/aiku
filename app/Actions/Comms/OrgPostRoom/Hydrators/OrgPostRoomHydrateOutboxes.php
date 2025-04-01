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
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgPostRoomHydrateOutboxes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OrgPostRoom $orgPostRoom): string
    {
        return $orgPostRoom->id;
    }

    public function handle(OrgPostRoom $orgPostRoom): void
    {
        $stats = [
            'number_outboxes' => $orgPostRoom->outboxes()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'outboxes',
                field: 'type',
                enum: OutboxCodeEnum::class,
                models: Outbox::class,
                where: function ($q) use ($orgPostRoom) {
                    $q->where('org_post_room_id', $orgPostRoom->id);
                }
            )
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'outboxes',
                field: 'state',
                enum: OutboxStateEnum::class,
                models: Outbox::class,
                where: function ($q) use ($orgPostRoom) {
                    $q->where('org_post_room_id', $orgPostRoom->id);
                }
            )
        );

        $orgPostRoom->stats()->update($stats);
    }

}
