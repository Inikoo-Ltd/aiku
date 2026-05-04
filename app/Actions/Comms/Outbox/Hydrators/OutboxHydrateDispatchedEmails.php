<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\Hydrators;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDispatchedEmails;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDispatchedEmails;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDispatchedEmails;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OutboxHydrateDispatchedEmails implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $outboxID): string
    {
        return $outboxID ?? 'empty';
    }

    public function handle(?int $outboxID): void
    {
        if (!$outboxID) {
            return;
        }
        $outbox = Outbox::find($outboxID);
        if (!$outbox) {
            return;
        }

        $stats = [
            'number_dispatched_emails' => DB::table('dispatched_emails')->where('outbox_id', $outbox->id)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'dispatched_emails',
                field: 'state',
                enum: DispatchedEmailStateEnum::class,
                models: DispatchedEmail::class,
                where: function ($q) use ($outbox) {
                    $q->where('outbox_id', $outbox->id);
                }
            )
        );

        $outboxStats = $outbox->stats;
        $oldNumberDispatchedEmails = $outboxStats->number_dispatched_emails;
        $outboxStats->update($stats);

        if ($oldNumberDispatchedEmails != $stats['number_dispatched_emails']) {
            GroupHydrateDispatchedEmails::dispatch($outbox->group_id);
            OrganisationHydrateDispatchedEmails::dispatch($outbox->organisation_id);
            ShopHydrateDispatchedEmails::dispatch($outbox->shop_id);
        }
    }

}
