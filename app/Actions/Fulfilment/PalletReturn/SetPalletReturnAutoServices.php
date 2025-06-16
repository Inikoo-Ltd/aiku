<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Mar 2025 18:23:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\WithSetAutoServices;
use App\Actions\OrgAction;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\DB;

class SetPalletReturnAutoServices extends OrgAction
{
    use WithSetAutoServices;

    /**
     * @throws \Throwable
     */
    public function handle(PalletReturn $palletReturn, $debug = false): PalletReturn
    {
        $palletTypes = DB::table('pallets')
            ->leftJoin('pallet_return_items', 'pallets.id', '=', 'pallet_return_items.pallet_id')
            ->select('pallets.type', DB::raw('count(*) as count'))
            ->where('pallet_return_items.pallet_return_id', $palletReturn->id)
            ->where('pallet_return_items.type', 'Pallet')
            ->groupBy('pallets.type')->pluck('count', 'type')->toArray();


        $autoServices = $palletReturn->fulfilment->shop->services()
            ->where('auto_assign_trigger', 'PalletReturn')
            ->where('auto_assign_subject', 'Pallet')
            ->where('is_auto_assign', true)->get();

        return $this->processAutoServices($palletReturn, $autoServices, $palletTypes, $debug);
    }


}
