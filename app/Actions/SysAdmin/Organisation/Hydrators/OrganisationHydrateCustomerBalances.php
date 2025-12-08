<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateCustomerBalances implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [];

        $stats['number_customers_with_balances'] = DB::table('customers')
            ->where('organisation_id', $organisation->id)
            ->where('balance', '!=', 0)
            ->count();

        $stats['number_customers_with_positive_balances'] = DB::table('customers')
            ->where('organisation_id', $organisation->id)
            ->where('balance', '>', 0)
            ->count();

        $stats['number_customers_with_negative_balances'] = DB::table('customers')
            ->where('organisation_id', $organisation->id)
            ->where('balance', '<', 0)
            ->count();

        $organisation->accountingStats()->update($stats);
    }
}
