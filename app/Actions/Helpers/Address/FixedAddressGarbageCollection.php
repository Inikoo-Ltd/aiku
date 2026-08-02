<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Nov 2024 20:03:31 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Address;

use App\Models\Helpers\Address;
use Exception;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FixedAddressGarbageCollection
{
    use AsAction;

    public function handle(int $addressID): ?bool
    {
        $address = Address::find($addressID);
        if (!$address) {
            DB::table('model_has_fixed_addresses')->where('address_id', $addressID)->delete();
            DB::table('addresses')->where('id', $addressID)->delete();

            return null;
        }

        /**
         * Both pivots have to be clear. An address can be pinned to an order and, at the same
         * time, held by the customer it belongs to; deleting it from under the customer is what
         * the foreign key was catching.
         */
        $inUse = DB::table('model_has_fixed_addresses')->where('address_id', $address->id)->exists()
            || DB::table('model_has_addresses')->where('address_id', $address->id)->exists();

        if (!$inUse) {
            try {
                $address->forceDelete();

                return true;
            } catch (Exception) {
                return false;
            }
        }

        return false;
    }
}
