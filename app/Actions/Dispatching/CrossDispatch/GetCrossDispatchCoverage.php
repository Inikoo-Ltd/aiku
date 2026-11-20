<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch;

use App\Models\Ordering\Order;
use App\Models\Procurement\OrgPartner;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCrossDispatchCoverage
{
    use AsObject;

    /**
     * The companies both sides have switched on for this order's organisation that could ship the order whole today, keyed by organisation id, with the SKO mapping of each.
     *
     * @return array<int, array<int, array<int, array{home_org_stock_id: int, org_stock_id: int, quantity: float}>>>
     */
    public function handle(Order $order): array
    {
        // ponytail: each order is tested alone against quantity_available, so two orders can claim the same last SKO; allocate when the router (INI-486) needs it
        $directions = OrgPartner::where('organisation_id', $order->organisation_id)
            ->with('partner')
            ->orderBy('id')
            ->get()
            ->filter(fn (OrgPartner $orgPartner) => $orgPartner->isCrossDispatchEnabled());

        if ($directions->isEmpty()) {
            return [];
        }

        $fillToOrderProductIds = ResolveCrossDispatch::make()->fillToOrderProductIds($order);

        return $directions
            ->mapWithKeys(fn (OrgPartner $orgPartner) => [$orgPartner->partner_id => ResolveCrossDispatch::run($order, $orgPartner->partner, $fillToOrderProductIds)])
            ->reject(fn (array $result) => $result['refusals'] !== [])
            ->map(fn (array $result) => $result['mapping'])
            ->all();
    }
}
