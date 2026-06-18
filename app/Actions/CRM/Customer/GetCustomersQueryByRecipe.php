<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Comms\Mailshot\Filters\FilterByDepartment;
use App\Actions\Comms\Mailshot\Filters\FilterByFamily;
use App\Actions\Comms\Mailshot\Filters\FilterByFamilyNeverOrdered;
use App\Actions\Comms\Mailshot\Filters\FilterByInterest;
use App\Actions\Comms\Mailshot\Filters\FilterByLocation;
use App\Actions\Comms\Mailshot\Filters\FilterByOrderValue;
use App\Actions\Comms\Mailshot\Filters\FilterByShowroomOrders;
use App\Actions\Comms\Mailshot\Filters\FilterBySubdepartment;
use App\Actions\Comms\Mailshot\Filters\FilterGoldRewardStatus;
use App\Actions\Comms\Mailshot\Filters\FilterOrdersCollection;
use App\Actions\Comms\Mailshot\Filters\FilterOrdersInBasket;
use App\Actions\Comms\Mailshot\Filters\FilterRegisteredNeverOrdered;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomersQueryByRecipe
{
    use AsObject;

    /**
     * Build the marketing-consent gated customers query for a given filter recipe.
     *
     * @throws \Exception
     */
    public function handle(?int $shopId, array $filters): Builder
    {
        $query = DB::table('customers');

        $query->whereExists(function (Builder $query) {
            $query->select(DB::raw(1))
                ->from('customer_comms')
                ->whereColumn('customer_comms.customer_id', 'customers.id')
                ->where('customer_comms.is_subscribed_to_marketing', true);
        });

        if ($shopId) {
            $query->where('customers.shop_id', $shopId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query->whereNotNull('customers.email');
        $query->whereNull('customers.deleted_at');

        (new FilterRegisteredNeverOrdered())->apply($query, $filters);
        (new FilterByFamilyNeverOrdered())->apply($query, $filters);
        (new FilterGoldRewardStatus())->apply($query, $filters);
        (new FilterOrdersInBasket())->apply($query, $filters);
        (new FilterByOrderValue())->apply($query, $filters);
        (new FilterBySubdepartment())->apply($query, $filters);
        (new FilterByDepartment())->apply($query, $filters);
        (new FilterByShowroomOrders())->apply($query, $filters);
        (new FilterByInterest())->apply($query, $filters);
        (new FilterOrdersCollection())->apply($query, $filters);
        (new FilterByFamily())->apply($query, $filters);
        (new FilterByLocation())->apply($query, $filters);

        return $query;
    }
}
