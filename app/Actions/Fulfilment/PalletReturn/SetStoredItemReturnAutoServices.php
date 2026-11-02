<?php

/*
 * author Arya Permana - Kirin
 * created on 15-05-2025-13h-09m
 * GitHub: https://github.com/KirinZero0
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
        $autoServices = $palletReturn->fulfilment->shop->services()
            ->where('auto_assign_trigger', 'PalletReturn')
            ->where('auto_assign_subject', 'StoredItem')
            ->where('is_auto_assign', true)->get();

        $quantities = $this->getQuantities($palletReturn, $isPicking ? 'quantity_picked' : 'quantity_ordered');

        if (!$autoServices->contains('auto_assign_subject_type', 'carton')) {
            $quantities['piece'] = $quantities['total'];
        }

        return $this->processAutoServices($palletReturn, $autoServices, $quantities, $debug);
    }

    /**
     * Full cartons are counted per stored item, so a carton split across two pallets still counts as one
     *
     * @return array{piece: int, carton: int, total: int}
     */
    public function getQuantities(PalletReturn $palletReturn, string $quantityColumn): array
    {
        $rows = DB::table('pallet_return_items')
            ->join('stored_items', 'stored_items.id', '=', 'pallet_return_items.stored_item_id')
            ->where('pallet_return_items.pallet_return_id', $palletReturn->id)
            ->where('pallet_return_items.type', 'StoredItem')
            ->groupBy('stored_items.id', 'stored_items.units_per_carton')
            ->selectRaw("stored_items.units_per_carton, sum(pallet_return_items.$quantityColumn) as quantity")
            ->get();

        $quantities = ['piece' => 0, 'carton' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $quantity = (int) $row->quantity;
            $cartons  = $row->units_per_carton ? intdiv($quantity, $row->units_per_carton) : 0;

            $quantities['carton'] += $cartons;
            $quantities['piece']  += $quantity - $cartons * (int) $row->units_per_carton;
            $quantities['total']  += $quantity;
        }

        return $quantities;
    }
}
