<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 23:32:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomerClients;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClients;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCustomerClients;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCustomerClients;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;

class ForceDeleteCustomerClient extends OrgAction
{
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(CustomerClient $customerClient): CustomerClient
    {
        $customerClient = DB::transaction(function () use ($customerClient) {
            Order::where('customer_client_id', $customerClient->id)
                ->update(['customer_client_id' => null]);

            if ($customerClient->stats) {
                $customerClient->stats->forceDelete();
            }

            $customerClient->forceDelete();

            return $customerClient;
        });

        CustomerHydrateClients::dispatch($customerClient->customer);
        ShopHydrateCustomerClients::dispatch($customerClient->shop);
        OrganisationHydrateCustomerClients::dispatch($customerClient->organisation);
        GroupHydrateCustomerClients::dispatch($customerClient->group);

        return $customerClient;
    }

    /**
     * @throws \Throwable
     */
    public function action(CustomerClient $customerClient): CustomerClient
    {
        $this->initialisationFromShop($customerClient->shop, []);

        return $this->handle($customerClient);
    }

}
