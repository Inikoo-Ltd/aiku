<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch;

use App\Enums\Dispatching\CrossDispatch\CrossDispatchChargeStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\CrossDispatchCharge;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Procurement\OrgPartner;
use Lorisleiva\Actions\Concerns\AsObject;

class StoreCrossDispatchCharge
{
    use AsObject;

    /**
     * INI-484 C1/C2. Every dispatch of a note worked in another company's warehouse, repairs and replacements included,
     * leaves one charge for it with the pair's terms of that moment. A repair run on a note that has its charge
     * changes nothing; any other dispatch of a note already charged (undone from the order page or the note, or moved
     * back by unpacking it) brings its charge back with what ships now and the terms of the new dispatch. Written whether the direction is switched on or not, since notes raised while it was
     * on still ship after it is switched off. Call it inside the dispatch transaction; the costing runs after commit.
     */
    public function handle(DeliveryNote $deliveryNote, bool $repair = false): ?CrossDispatchCharge
    {
        if ($deliveryNote->state !== DeliveryNoteStateEnum::DISPATCHED || !$deliveryNote->isCrossDispatch()) {
            return null;
        }

        $charge = CrossDispatchCharge::where('delivery_note_id', $deliveryNote->id)->lockForUpdate()->first();
        if ($repair && $charge?->state === CrossDispatchChargeStateEnum::OPEN) {
            return $charge;
        }

        $orderingOrganisationId = $deliveryNote->shop->organisation_id;
        $orgPartner             = OrgPartner::where('organisation_id', $orderingOrganisationId)->where('partner_id', $deliveryNote->organisation_id)->first();
        $values                 = [
            'group_id'                 => $deliveryNote->group_id,
            'organisation_id'          => $deliveryNote->organisation_id,
            'ordering_organisation_id' => $orderingOrganisationId,
            'org_partner_id'           => $orgPartner?->id,
            'order_id'                 => $deliveryNote->orders()->value('orders.id'),
            'shop_id'                  => $deliveryNote->shop_id,
            'state'                    => CrossDispatchChargeStateEnum::OPEN,
            'dispatched_at'            => $deliveryNote->dispatched_at ?? now(),
            'terms'                    => $orgPartner?->crossDispatchSettings() ?? OrgPartner::CROSS_DISPATCH_DEFAULTS,
            'currency_id'              => $deliveryNote->organisation->currency_id,
            'number_parcels'           => null,
            'dispatch_fee'             => null,
            'shipping_amount'          => null,
            'shipping_cost_source'     => null,
            'goods_amount'             => null,
            'lines'                    => [],
            'costed_at'                => null,
            'reversed_at'              => null,
        ];

        if ($charge) {
            $charge->update($values);
        } else {
            $charge = CrossDispatchCharge::createOrFirst(['delivery_note_id' => $deliveryNote->id], $values);
        }

        CostCrossDispatchCharge::dispatch($charge)->afterCommit();

        return $charge;
    }
}
