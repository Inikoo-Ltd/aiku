<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\CRM\Prospect\Mailshots\Filters\FilterProspectsNeverContacted;
use App\Actions\CRM\Prospect\Mailshots\Filters\FilterProspectsLastContacted3WeeksAgo;
use App\Actions\CRM\Prospect\Mailshots\Filters\FilterProspectsSentEmail3Times;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsObject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GetProspectMailshotRecipientsQueryBuilder
{
    use AsObject;

    /**
     * @throws \Exception
     */
    public function handle(Mailshot $mailshot): ?Builder
    {
        if (!empty($mailshot->recipients_recipe)) {
            return $this->getRecipientsFromCustomQuery($mailshot);
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    private function getRecipientsFromCustomQuery(Mailshot $mailshot): Builder
    {
        $query = DB::table('prospects');

        if ($mailshot->shop_id) {
            $query->where('prospects.shop_id', $mailshot->shop_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query->whereNotNull('prospects.email');
        $query->whereNull('prospects.deleted_at');
        $query->where('prospects.can_contact_by_email', true);
        $query->where('prospects.dont_contact_me', false);
        $query->where('prospects.is_valid_email', true);

        $filters = $mailshot->recipients_recipe;

        // Filter Never Contacted
        (new FilterProspectsNeverContacted())->apply($query, $filters);

        // Filter Last Contacted 3 Weeks Ago
        (new FilterProspectsLastContacted3WeeksAgo())->apply($query, $filters);

        // Filter Sent Email 3 Times
        (new FilterProspectsSentEmail3Times())->apply($query, $filters);

        return $query;
    }
}
