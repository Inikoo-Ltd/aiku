<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 May 2024 15:25:34 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Address\Hydrators;

use App\Models\Helpers\Address;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddressHydrateFixedUsage implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Address $address): string
    {
        return $address->id;
    }

    public function handle(Address $address): void
    {
        if ($address->is_fixed) {
            $fixedUsage = DB::table('model_has_fixed_addresses')->where('group_id', $address->group_id)->where('address_id', $address->id)->count();
            $address->update(['fixed_usage' => $fixedUsage]);
        }
    }


}
