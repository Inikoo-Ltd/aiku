<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Sept 2024 11:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomerClients;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClients;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCustomerClients;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCustomerClients;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerClient;

class DeleteCustomerClient extends OrgAction
{
    use WithActionUpdate;


    public function handle(CustomerClient $customerClient, array $deletedData): CustomerClient
    {
        $customerClient = $this->update($customerClient, $deletedData, ['data']);
        $customerClient->delete();

        CustomerHydrateClients::dispatch($customerClient->customer);
        ShopHydrateCustomerClients::dispatch($customerClient->shop);
        OrganisationHydrateCustomerClients::dispatch($customerClient->organisation);
        GroupHydrateCustomerClients::dispatch($customerClient->group);

        return $customerClient;
    }

    public function action(CustomerClient $customerClient, array $modelData): CustomerClient
    {
        $this->initialisationFromShop($customerClient->shop, $modelData);

        return $this->handle($customerClient, $this->validatedData);
    }

}
