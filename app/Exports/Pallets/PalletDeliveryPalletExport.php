<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 12:40:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Pallets;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletStoredItem;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PalletDeliveryPalletExport implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping
{
    use Exportable;

    protected $palletDelivery;

    public function __construct(PalletDelivery $palletDelivery)
    {
        $this->palletDelivery = $palletDelivery;
    }

    public function query()
    {
        return Pallet::query()
            ->where('pallet_delivery_id', $this->palletDelivery->id);
    }
    public function map($row): array
    {
        /** @var Pallet $row */
        $pallet = $row;

        $storedItems = $pallet->palletStoredItems->map(function ($palletStoredItem) {
            return [
                'code' => $palletStoredItem->storedItem->reference,
                'quantity' => $palletStoredItem->quantity,
            ];
        })->values()->all() ?? [];

        return [
            $pallet->reference,
            $pallet->customer_reference,
            $pallet->rental->code,
            $pallet->location->code,
            $storedItems
        ];
    }

    public function headings(): array
    {
        return [
            'Reference',
            'Customer Reference',
            'Rental',
            'Location',
            'Stored Items'
        ];
    }
}
