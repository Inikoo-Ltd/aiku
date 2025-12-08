<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2024 14:42:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Models\Comms\ModelHasDispatchedEmail;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraOrderDispatchedEmails extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:order_dispatched_emails {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): null
    {
        $orderDispatchedEmailData = $organisationSource->fetchOrderDispatchedEmail($organisationSourceId);
        if (! $orderDispatchedEmailData) {
            // print "error no dispatched email data $organisationSourceId\n";

            return null;
        }

        if (! $orderDispatchedEmailData['dispatchedEmail']) {
            // print "error no dispatched email (*) $organisationSourceId\n";

            return null;
        }

        if (ModelHasDispatchedEmail::where('source_id', $orderDispatchedEmailData['modelHasDispatchedEmail']['source_id'])->first()) {
            $orderDispatchedEmailData['order']->dispatchedEmails()->updateExistingPivot(
                $orderDispatchedEmailData['dispatchedEmail']->id,
                [
                    'last_fetched_at' => $orderDispatchedEmailData['modelHasDispatchedEmail']['last_fetched_at'],
                ]
            );
        } else {
            $orderDispatchedEmailData['order']->dispatchedEmails()->attach(
                [
                    $orderDispatchedEmailData['dispatchedEmail']->id => [
                        'source_id' => $orderDispatchedEmailData['modelHasDispatchedEmail']['source_id'],
                        'fetched_at' => $orderDispatchedEmailData['modelHasDispatchedEmail']['fetched_at'],
                        'outbox_id' => $orderDispatchedEmailData['outbox']->id,
                    ],
                ]
            );
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Order Sent Email Bridge')
            ->select('Order Sent Email Bridge Key as source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->orderBy('Order Sent Email Bridge Key');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Order Sent Email Bridge');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
