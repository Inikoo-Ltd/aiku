<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:59:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateSales implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;


    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory): void
    {
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $foreignKey = 'department_id';
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $foreignKey = 'family_id';
        } else {
            return;
        }

        $stats = [];

        $queryBase = InvoiceTransaction::where('in_process', false)->where($foreignKey, $productCategory->id)->selectRaw('sum(net_amount) as  sum_aggregate  ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_'
        );

        $queryBase = InvoiceTransaction::where('in_process', false)->where($foreignKey, $productCategory->id)->selectRaw('sum(grp_net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_grp_currency_'
        );

        $queryBase = InvoiceTransaction::where('in_process', false)->where($foreignKey, $productCategory->id)->selectRaw('sum(org_net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_org_currency_'
        );


        $productCategory->salesIntervals()->update($stats);
    }


}
