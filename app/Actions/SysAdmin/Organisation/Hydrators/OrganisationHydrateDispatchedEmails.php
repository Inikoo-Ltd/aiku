<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Mar 2025 20:33:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateDispatchedEmails implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'ses-analytics';

    public function getJobUniqueId(?int $organisationId): string
    {
        return $organisationId ?? 'empty';
    }

    public function handle(?int $organisationId): void
    {
        if (!$organisationId) {
            return;
        }
        $organisation = Organisation::on('aiku_no_sticky')->find($organisationId);
        if (!$organisation) {
            return;
        }

        $stats = [
            'number_dispatched_emails' => DB::connection('aiku_no_sticky')->table('outboxes')->where('organisation_id', $organisation->id)
                ->leftJoin('outbox_stats', 'outboxes.id', '=', 'outbox_stats.outbox_id')
                ->sum('number_dispatched_emails'),
        ];

        $organisation->commsStats()->update($stats);
    }


}
