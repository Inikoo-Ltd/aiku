<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\Dispatching\PartnerStaging\ReleasePartnerStagingTask;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleaseUncoveredPrePicks
{
    use AsAction;

    /**
     * When the shelf turns out to hold less than was promised to buyers (an audit finds it short, or
     * it was sold elsewhere), the part nobody can walk to a bay stops being promised. With no free
     * stock behind it the line lands in To produce on its own. Newest pre-picks give way first.
     *
     * @throws \Throwable
     */
    public function handle(OrgStock $orgStock): float
    {
        $promisedNotStaged = (float) DB::scalar('select '.PartnerShoppingListItem::promisedNotStagedSql((string) $orgStock->organisation_id, (string) $orgStock->stock_id));
        $uncovered         = round($promisedNotStaged - (float) $orgStock->quantity_available, 3);

        if ($uncovered <= 0) {
            return 0.0;
        }

        $buyerOrgPartners = PartnerShoppingListItem::query()
            ->where('partner_organisation_id', $orgStock->organisation_id)
            ->where('stock_id', $orgStock->stock_id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNotNull('pre_picked_at')
            ->groupBy('organisation_id')
            ->orderByRaw('max(pre_picked_at) desc')
            ->pluck('organisation_id');

        $released = 0.0;
        foreach ($buyerOrgPartners as $buyerOrganisationId) {
            $orgPartner = OrgPartner::where('organisation_id', $orgStock->organisation_id)->where('partner_id', $buyerOrganisationId)->first();
            if (!$orgPartner) {
                continue;
            }

            $released += ReleasePartnerStagingTask::make()->releaseUnstaged($orgPartner, $orgStock, $uncovered - $released);
            if ($released >= $uncovered) {
                break;
            }
        }

        return round($released, 3);
    }
}
