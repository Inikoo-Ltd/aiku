<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\Hydrators;

use App\Actions\Comms\OrgPostRoom\Hydrators\OrgPostRoomHydrateRuns;
use App\Actions\Comms\PostRoom\Hydrators\PostRoomHydrateRuns;
use App\Models\Comms\Outbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OutboxHydrateMailshots implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Outbox $outbox): string
    {
        return $outbox->id;
    }


    public function handle(Outbox $outbox): void
    {

        if ($outbox->model_type != 'Mailshot') {
            return;
        }

        $count = DB::table('mailshots')
            ->where('outbox_id', $outbox->id)->count();


        $outbox->intervals()->update(
            [
                'runs_all' => $count,
            ]
        );
        OrgPostRoomHydrateRuns::run($outbox->orgPostRoom);
        PostRoomHydrateRuns::run($outbox->postRoom);

    }


}
