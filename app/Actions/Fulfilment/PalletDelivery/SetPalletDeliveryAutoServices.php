<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Mar 2025 18:23:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\WithSetAutoServices;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Support\Facades\DB;

/**
 * Class SetPalletDeliveryAutoServices
 *
 * This action automatically assigns services to a pallet delivery based on the types of pallets it contains.
 * It counts the number of pallets by type and creates, updates or deletes fulfilment transactions accordingly,
 * using services configured as auto-assignable for pallets.
 *
 * @package App\Actions\Fulfilment\PalletDelivery
 */
class SetPalletDeliveryAutoServices extends OrgAction
{
    use WithSetAutoServices;

    /**
     * Automatically assign services to a pallet delivery based on pallet types.
     *
     * This method:
     * - Counts pallets by type in the delivery
     * - Fetches auto-assignable services for pallets
     * - Creates transactions for new service assignments
     * - Updates quantities for existing transactions
     * - Deletes transactions with zero quantity
     *
     * @param  PalletDelivery  $palletDelivery  The pallet delivery to assign services to
     *
     * @return PalletDelivery The updated pallet delivery instance
     * @throws \Throwable
     */
    public function handle(PalletDelivery $palletDelivery, $debug = false): PalletDelivery
    {
        $palletTypes = DB::table('pallets')
            ->select('type', DB::raw('count(*) as count'))
            ->where('pallet_delivery_id', $palletDelivery->id)
            ->whereNull('pallets.deleted_at')
            ->where('pallets.status', '!=', PalletStatusEnum::NOT_RECEIVED->value)
            ->groupBy('type')->pluck('count', 'type')->toArray();

        $autoServices = $palletDelivery->fulfilment->shop->services()
            ->where('auto_assign_trigger', 'PalletDelivery')
            ->where('auto_assign_subject', 'Pallet')
            ->whereNull('deleted_at')
            ->where('is_auto_assign', true)->get();



        return $this->processAutoServices($palletDelivery, $autoServices, $palletTypes, $debug);
    }


}
