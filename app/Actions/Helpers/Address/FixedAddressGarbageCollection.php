<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Nov 2024 20:03:31 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Address;

use App\Models\Helpers\Address;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FixedAddressGarbageCollection
{
    use AsAction;

    public function handle(int $addressID): ?bool
    {

        $address=Address::find($addressID);
        if(!$address){
            DB::table('model_has_fixed_addresses')->where('address_id', $addressID)->delete();
            DB::table('addresses')->where('id', $addressID)->delete();
            return null;
        }

        $inUse = DB::table('model_has_fixed_addresses')
            ->where('address_id', $address->id)->exists();

        if (!$inUse) {
            $address->forceDelete();
            return true;
        }

        return false;
    }
}
