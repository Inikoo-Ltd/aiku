<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 10:42:34 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\Hydrators;

use App\Actions\Comms\OrgPostRoom\Hydrators\OrgPostRoomHydrateRuns;
use App\Actions\Comms\PostRoom\Hydrators\PostRoomHydrateRuns;
use App\Models\Comms\Outbox;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OutboxHydrateEmailBulkRuns
{
    use AsAction;

    private Outbox $outbox;

    public function __construct(Outbox $outbox)
    {
        $this->outbox = $outbox;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->outbox->id))->dontRelease()];
    }


    public function handle(Outbox $outbox): void
    {
        if ($outbox->model_type != 'EmailBulkRun') {
            return;
        }
        $count = DB::table('email_bulk_runs')
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
