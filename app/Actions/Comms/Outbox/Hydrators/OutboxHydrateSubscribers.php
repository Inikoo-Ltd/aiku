<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\Outbox\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Comms\Outbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OutboxHydrateSubscribers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Outbox $outbox): string
    {
        return $outbox->id;
    }


    public function handle(Outbox $outbox): void
    {
        $stats = [
            'number_subscribed_user' => DB::table('outbox_has_subscribers')->whereNotNull('user_id')->where('outbox_id', $outbox->id)->count(),
            'number_subscribed_external_emails' => DB::table('outbox_has_subscribers')->whereNotNull('external_email')->where('outbox_id', $outbox->id)->count(),
        ];

        $outbox->stats()->update($stats);
    }

}
