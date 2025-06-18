<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 May 2024 13:47:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Address\Hydrators;

use App\Models\Helpers\Address;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddressHydrateMultiplicity implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Address $address): string
    {
        return $address->id;
    }

    public function handle(Address $address): void
    {
        $multiplicity = DB::table('addresses')->where('group_id', $address->group_id)->where('checksum', $address->checksum)->count();
        $address->update(['multiplicity' => $multiplicity]);
    }


}
