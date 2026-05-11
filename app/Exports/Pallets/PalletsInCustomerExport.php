<?php

namespace App\Exports\Pallets;

use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerPalletsTabsEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PalletsInCustomerExport implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping
{
    use Exportable;

    public function __construct(
        protected FulfilmentCustomer $fulfilmentCustomer,
        protected string $tab = FulfilmentCustomerPalletsTabsEnum::ALL->value
    ) {
    }

    public function query(): Builder
    {
        $query = Pallet::query()
            ->where('fulfilment_customer_id', $this->fulfilmentCustomer->id)
            ->leftJoin('locations', 'pallets.location_id', '=', 'locations.id')
            ->with(['storedItems:id,name,reference'])
            ->select('pallets.*', 'locations.code as location_code');

        if ($this->tab === FulfilmentCustomerPalletsTabsEnum::STORING->value) {
            $query->whereIn('pallets.status', [
                PalletStatusEnum::STORING->value,
                PalletStatusEnum::RETURNING->value,
            ]);
        } elseif ($this->tab === FulfilmentCustomerPalletsTabsEnum::INCOMING->value) {
            $query->whereIn('pallets.status', [PalletStatusEnum::IN_PROCESS->value]);
        } elseif ($this->tab === FulfilmentCustomerPalletsTabsEnum::INCIDENT->value) {
            $query->whereIn('pallets.status', [PalletStatusEnum::INCIDENT->value]);
        } elseif ($this->tab === FulfilmentCustomerPalletsTabsEnum::RETURNED->value) {
            $query->whereIn('pallets.status', [PalletStatusEnum::RETURNED->value]);
        }

        return $query->orderBy('pallets.reference');
    }

    public function map($row): array
    {
        /** @var Pallet $pallet */
        $pallet = $row;

        $storedItems = collect($pallet->storedItems ?? [])->map(function (StoredItem $storedItem): string {
            return sprintf('%s (%d)', (string) $storedItem->name, (int) ($storedItem->pivot->quantity ?? 0));
        });

        return [
            is_object($pallet->type) ? $pallet->type->value : (string) $pallet->type,
            (string) $pallet->reference,
            (string) $pallet->customer_reference,
            (string) ($pallet->location_code ?? ''),
            $storedItems->implode('; '),
            (string) collect($pallet->storedItems ?? [])->sum(fn ($storedItem): int => (int) ($storedItem->pivot->quantity ?? 0)),
        ];
    }

    public function headings(): array
    {
        return [
            __('Type'),
            __('Reference'),
            __("Customer Reference"),
            __('Location Code'),
            __("Customer's SKUs"),
            __("Customer's SKUs Quantity"),
        ];
    }
}
