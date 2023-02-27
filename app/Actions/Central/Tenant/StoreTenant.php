<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 14:51:12 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Central\Tenant;


use App\Actions\Accounting\PaymentServiceProvider\StorePaymentServiceProvider;
use App\Models\Central\Tenant;
use Lorisleiva\Actions\Concerns\AsAction;
use Str;

class StoreTenant
{
    use AsAction;

    public function handle(array $modelData): Tenant
    {
        $modelData['numeric_id'] = $this->getAutoincrementID();
        $tenant                  = Tenant::create($modelData);

        $tenant->stats()->create();
        $tenant->procurementStats()->create();
        $tenant->inventoryStats()->create();
        $tenant->productionStats()->create();
        $tenant->marketingStats()->create();
        $tenant->salesStats()->create();
        $tenant->fulfilmentStats()->create();
        $tenant->accountingStats()->create();
        $tenant->refresh();

        $tenant->run(function () {
            CreateTenantStorageLink::run();
        });

        $tenant->run(function () {
            StorePaymentServiceProvider::run(
                modelData: [
                               'type'  => 'account',
                               'data' => [
                                   'service-code'=>'accounts'
                               ],
                               'code'  => 'accounts'
                           ]
            );
        });




        return $tenant;
    }

    private function getAutoincrementID(): int
    {
        return Tenant::count() + 1;
    }

}
