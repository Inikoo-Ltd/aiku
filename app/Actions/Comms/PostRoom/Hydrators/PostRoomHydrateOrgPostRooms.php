<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Nov 2024 00:22:22 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\PostRoom\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Comms\PostRoom;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PostRoomHydrateOrgPostRooms implements ShouldBeUnique
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
        $stats = [
            'number_org_post_rooms' => $postRoom->orgPostRooms()->count(),
        ];

        $postRoom->stats()->update($stats);
    }

}
