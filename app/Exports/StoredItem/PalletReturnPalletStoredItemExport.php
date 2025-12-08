<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 12:40:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\StoredItem;

use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletStoredItem;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PalletReturnPalletStoredItemExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    use Exportable;

    protected $fulfilmentCustomer;

    public function __construct(FulfilmentCustomer $fulfilmentCustomer)
    {
        $this->fulfilmentCustomer = $fulfilmentCustomer;
    }

    public function query()
    {
        return PalletStoredItem::query()
            ->whereHas('storedItem', function ($query) {
                $query->where('fulfilment_customer_id', $this->fulfilmentCustomer->id);
            });
    }

    public function map($row): array
    {
        /** @var PalletStoredItem $row */
        $palletStoredItem = $row;

        return [
            $palletStoredItem->storedItem->reference,
            $palletStoredItem->quantity,
        ];
    }

    public function headings(): array
    {
        return [
            'Reference',
            'Quantity',
        ];
    }
}
