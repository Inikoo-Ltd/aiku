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
use App\Models\Fulfilment\PalletStoredItem;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PalletReturnPalletExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    use Exportable;

    protected $fulfilmentCustomer;

    public function __construct(FulfilmentCustomer $fulfilmentCustomer)
    {
        $this->fulfilmentCustomer = $fulfilmentCustomer;
    }

    public function query()
    {
        return Pallet::query()
            ->where('state', PalletStateEnum::STORING)
            ->where('fulfilment_customer_id', $this->fulfilmentCustomer->id);
    }

    public function map($row): array
    {
        /** @var PalletStoredItem $row */
        $pallet = $row;

        return [
            $pallet->reference,
        ];
    }

    public function headings(): array
    {
        return [
            'Reference',
        ];
    }
}
