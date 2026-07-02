<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Comms\OrgPostRoom;

use App\Actions\Comms\OrgPostRoom\Hydrators\OrgPostRoomHydrateRuns;
use App\Actions\Comms\OrgPostRoom\Hydrators\OrgPostRoomHydrateIntervals;
use App\Actions\Comms\OrgPostRoom\Hydrators\OrgPostRoomHydrateOutboxes;
use App\Actions\HydrateModel;
use App\Models\Comms\OrgPostRoom;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class HydrateOrgPostRooms extends HydrateModel
{
    public string $commandSignature = 'hydrate:org_post_rooms';

    public function handle(OrgPostRoom $orgPostRoom): void
    {
        OrgPostRoomHydrateOutboxes::run($orgPostRoom);
        OrgPostRoomHydrateRuns::run($orgPostRoom);
    }

    public function asCommand(Command $command): int
    {
        $command->info('Hydrating Org Post Rooms');
        $count = OrgPostRoom::count();
        $bar   = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();
        OrgPostRoom::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });
        $bar->finish();
        $command->info("");

        return 0;
    }



}
