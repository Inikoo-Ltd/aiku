<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 00:50:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\PostRoom;

use App\Actions\Comms\PostRoom\Hydrators\PostRoomHydrateOrgPostRooms;
use App\Actions\Comms\PostRoom\Hydrators\PostRoomHydrateOutboxes;
use App\Actions\HydrateModel;
use App\Models\Comms\PostRoom;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class HydratePostRooms extends HydrateModel
{
    public string $commandSignature = 'hydrate:post_rooms';

    public function handle(PostRoom $postRoom): void
    {
        PostRoomHydrateOrgPostRooms::run($postRoom);
        PostRoomHydrateOutboxes::run($postRoom);
    }

    public function asCommand(Command $command): int
    {

        $command->info('Hydrating Post Rooms');
        $count = PostRoom::count();
        $bar   = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();
        PostRoom::chunk(1000, function (Collection $models) use ($bar) {
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
