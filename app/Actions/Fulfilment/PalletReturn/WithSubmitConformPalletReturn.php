<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Apr 2025 19:28:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateFulfilmentOrders;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Exceptions\Fulfilment\PalletReturnStockShortException;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\PalletStoredItem;
use Illuminate\Support\Collection;

trait WithSubmitConformPalletReturn
{
    public function processChangeState(PalletReturnStateEnum $state, PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $modelData['state'] = $state;

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            foreach ($palletReturn->pallets as $pallet) {
                UpdatePallet::run(
                    pallet: $pallet,
                    modelData: [
                        'state'  => $state == PalletReturnStateEnum::SUBMITTED ? PalletStateEnum::REQUEST_RETURN_SUBMITTED : PalletStateEnum::REQUEST_RETURN_CONFIRMED,
                        'status' => PalletStatusEnum::RETURNING
                    ],
                    hydrateParents: false
                );

                $palletReturn->pallets()->syncWithoutDetaching([
                    $pallet->id => [
                        'state' => $state == PalletReturnStateEnum::SUBMITTED ? PalletReturnItemStateEnum::SUBMITTED : PalletReturnItemStateEnum::CONFIRMED
                    ]
                ]);
            }
        }

        $palletReturn = $this->update($palletReturn, $modelData);

        GroupHydratePalletReturns::dispatch($palletReturn->group);
        OrganisationHydratePalletReturns::dispatch($palletReturn->organisation);
        WarehouseHydratePalletReturns::dispatch($palletReturn->warehouse);
        FulfilmentCustomerHydratePalletReturns::dispatch($palletReturn->fulfilmentCustomer);
        FulfilmentHydratePalletReturns::dispatch($palletReturn->fulfilment);

        if ($palletReturn->customerSalesChannel) {
            CustomerSalesChannelsHydrateFulfilmentOrders::dispatch($palletReturn->customerSalesChannel);
        }

        return $palletReturn;
    }

    public function ensureStockIsAvailable(PalletReturn $palletReturn): void
    {
        $palletReturnItems = $palletReturn->items()
            ->whereNotNull('pallet_stored_item_id')
            ->with('storedItem.portfolio')
            ->get()
            ->filter(fn (PalletReturnItem $palletReturnItem) => $palletReturnItem->getQuantityLeftToPick() > 0);

        if ($palletReturnItems->isEmpty()) {
            return;
        }

        $palletStoredItems = PalletStoredItem::query()
            ->whereIn('id', $palletReturnItems->pluck('pallet_stored_item_id')->unique())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->load('reservingPalletReturnItems.palletReturn')
            ->keyBy('id');

        $availableQuantity = fn (PalletReturnItem $palletReturnItem): float => (float) ($palletStoredItems->get($palletReturnItem->pallet_stored_item_id)?->getStockReservations($palletReturn->id)['available_quantity'] ?? 0);

        $shortLines = $palletReturnItems
            ->groupBy('stored_item_id')
            ->map(function (Collection $storedItemLines) use ($availableQuantity): ?array {
                $storedItem        = $storedItemLines->first()->storedItem;
                $quantityOrdered   = (float) $storedItemLines->sum(fn (PalletReturnItem $palletReturnItem) => $palletReturnItem->getQuantityLeftToPick());
                $quantityAvailable = (float) $storedItemLines->sum($availableQuantity);
                $isShort           = $storedItemLines->contains(fn (PalletReturnItem $palletReturnItem) => $palletReturnItem->getQuantityLeftToPick() > $availableQuantity($palletReturnItem));

                $reason = match (true) {
                    $storedItem->state === StoredItemStateEnum::DISCONTINUED => PalletReturnStockShortException::REASON_DISCONTINUED,
                    $isShort && $quantityAvailable <= 0 => PalletReturnStockShortException::REASON_OUT_OF_STOCK,
                    $isShort => PalletReturnStockShortException::REASON_SHORT,
                    default => null,
                };

                if (!$reason) {
                    return null;
                }

                return [
                    'stored_item_id'     => $storedItem->id,
                    'portfolio_id'       => $storedItem->portfolio?->id,
                    'reference'          => $storedItem->reference,
                    'quantity_ordered'   => $quantityOrdered,
                    'quantity_available' => $quantityAvailable,
                    'reason'             => $reason,
                ];
            })
            ->filter()
            ->sortBy('reference')
            ->values()
            ->all();

        if ($shortLines) {
            throw PalletReturnStockShortException::forLines($shortLines);
        }
    }
}
