<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 00:07:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Address\Hydrators;

use App\Models\Helpers\Address;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddressHydrateUsage implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Address $address): string
    {
        return $address->id;
    }

    public function handle(Address $address): void
    {
        if (!$address->is_fixed) {
            $usage = DB::table('model_has_addresses')->where('group_id', $address->group_id)->where('address_id', $address->id)->count();
            $address->update(['usage' => $usage]);
        }
    }


}
