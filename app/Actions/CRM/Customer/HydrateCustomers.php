<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClients;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateDeliveryNotes;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateOrders;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTopUps;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateWebUsers;
use App\Actions\Fulfilment\FulfilmentCustomer\HydrateFulfilmentCustomer;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\CRM\Customer;

class HydrateCustomers
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:customers {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model = Customer::class;
    }

    public function handle(Customer $customer): void
    {
        CustomerHydrateInvoices::run($customer);
        CustomerHydrateWebUsers::run($customer);
        CustomerHydrateClients::run($customer);
        CustomerHydrateOrders::run($customer);
        CustomerHydrateInvoices::run($customer);
        CustomerHydrateDeliveryNotes::run($customer);
        CustomerHydrateTopUps::run($customer);
        CustomerHydrateCreditTransactions::run($customer);
        CustomerHydrateBasket::run($customer);

        if ($customer->fulfilmentCustomer) {
            HydrateFulfilmentCustomer::run($customer->fulfilmentCustomer);
        }
    }


}
