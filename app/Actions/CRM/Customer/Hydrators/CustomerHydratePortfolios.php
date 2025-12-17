<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Dec 2024 00:21:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydratePortfolios implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $customerId): string
    {
        return $customerId ?? 'empty';
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $stats = [
            'number_portfolios'         => DB::table('portfolios')->where('customer_id', $customer->id)->count(),
            'number_current_portfolios' => DB::table('portfolios')->where('customer_id', $customer->id)->where('status', true)->count(),
        ];

        $customer->stats->update($stats);
    }
}
