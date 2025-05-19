<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 18:34:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Platform\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Models\Dropshipping\Platform;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PlatformHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Platform $platform): string
    {
        return $platform->id;
    }

    public function handle(Platform $platform): void
    {

        $query = DB::table('customer_sales_channels')->where('platform_id', $platform->id)
            ->distinct('customer_id');
        $stats = [
            'number_customers' => $query->count('customer_id')

        ];

        foreach (CustomerStateEnum::cases() as $state) {
            $stats['number_customers_' . $state->value] = $query->where('state', $state->value)->count('customer_id');
        }


        $platform->stats()->update($stats);
    }
}
