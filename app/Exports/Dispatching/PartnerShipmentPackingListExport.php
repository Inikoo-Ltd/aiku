<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Dispatching;

use App\Actions\Dispatching\PartnerShipment\UI\ShowPartnerShipment;
use App\Models\Dispatching\PartnerShipment;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * The consolidated delivery note of a truck to a partner (HELP-4482): every delivery note on it
 * with its invoice, parcels, items and weight.
 */
class PartnerShipmentPackingListExport implements FromArray, ShouldAutoSize
{
    public function __construct(public PartnerShipment $partnerShipment)
    {
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $truck = $this->partnerShipment;
        $notes = collect(ShowPartnerShipment::noteRows($truck));

        $rows = [
            ['CONSOLIDATED DELIVERY NOTE', '', '', $truck->reference, $truck->date->format('d/m/Y')],
            [],
            ['Shipper', $truck->organisation->name],
            ['Consignee', $truck->orgPartner->partner->name],
            ['Number of pallets', $truck->number_pallets],
            [],
            ['Delivery note', 'Invoice', 'Shop', 'Parcels', 'Items', 'Weight (kg)'],
        ];
        foreach ($notes as $note) {
            $rows[] = [$note['reference'], $note['is_invoiced'] ? $note['invoice'] : '', $note['shop_code'], $note['number_parcels'], $note['number_items'], $note['weight']];
        }
        $rows[] = ['TOTAL', $notes->count().' delivery notes', '', $notes->sum('number_parcels'), $notes->sum('number_items'), round($notes->sum('weight'), 1)];

        return $rows;
    }
}
