<?php
/*
 * author Arya Permana - Kirin
 * created on 15-05-2025-13h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\WithSetAutoServices;
use App\Actions\OrgAction;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;

class SetStoredItemReturnAutoServices extends OrgAction
{
    use WithSetAutoServices;

    /**
     * @throws \Throwable
     */
    public function handle(PalletReturn $palletReturn, $isPicking = false, $debug = false): PalletReturn
    {
        $totalStoredItems = $isPicking
            ? $this->getPickedQuantity($palletReturn)
            : $this->getOrderedQuantity($palletReturn);

        $autoServices = $palletReturn->fulfilment->shop->services()
            ->where('auto_assign_trigger', 'PalletReturn')
            ->where('auto_assign_subject', 'StoredItem')
            ->where('is_auto_assign', true)->get();

        return $this->processStoredItemAutoServices($palletReturn, $autoServices, $totalStoredItems, $debug);
    }

    public function getOrderedQuantity($palletReturn): int
    {
        return (int) DB::table('pallet_return_items')
            ->where('pallet_return_items.pallet_return_id', $palletReturn->id)
            ->where('pallet_return_items.type', 'StoredItem')
            ->sum('quantity_ordered');
    }

    public function getPickedQuantity($palletReturn): int
    {
        return (int) DB::table('pallet_return_items')
            ->where('pallet_return_items.pallet_return_id', $palletReturn->id)
            ->where('pallet_return_items.type', 'StoredItem')
            ->sum('quantity_picked');
    }


}
