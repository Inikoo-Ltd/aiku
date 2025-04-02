<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Dec 2024 00:10:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydratePortfolios implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;



    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_portfolios'                      => $group->portfolios()->count(),
            'number_current_portfolios'              => $group->portfolios()->where('status', true)->count(),
            'number_portfolios_platform_shopify'     => $group->portfolios()->where('type', PortfolioTypeEnum::SHOPIFY->value)->count(),
            'number_portfolios_platform_woocommerce' => $group->portfolios()->where('type', PortfolioTypeEnum::WOOCOMMERCE->value)->count(),
        ];

        $group->dropshippingStats->update($stats);
    }
}
