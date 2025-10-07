<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Product\DeleteProduct;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomers;
use App\Actions\CRM\WebUser\DeleteWebUser;
use App\Actions\Dropshipping\CustomerClient\DeleteCustomerClient;
use App\Actions\Goods\Stock\DeleteStock;
use App\Actions\Ordering\Order\DeleteOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationArgument;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Console\Command;

class DeleteCustomer
{
    use WithActionUpdate;
    use WithOrganisationArgument;

    public string $commandSignature = 'delete:customer {slug}';

    protected array $deletedDependants;

    public function __construct()
    {
        $this->deletedDependants = [
            'clients'          => 0,
            'webUsers'         => 0,
            'products'         => 0,
            'fulfilmentOrders' => 0,
            'orders'           => 0,
        ];
    }

    public function handle(Customer $customer, array $deletedData = [], bool $skipHydrate = false): Customer
    {
        $this->deletedDependants = [
            'clients'          => $customer->clients()->count(),
            'webUsers'         => $customer->webUsers()->count(),
            'products'         => $customer->products()->count(),
            'orders'           => $customer->orders()->count(),
        ];

        $dependantDeletedData = [
            'data' => [
                'deleted' => ['cause' => 'deleted_customer']
            ]
        ];
        foreach ($customer->clients as $client) {
            DeleteCustomerClient::make()->action(
                customerClient: $client,
                modelData: $dependantDeletedData
            );
        }

        foreach ($customer->webUsers as $webUser) {
            DeleteWebUser::run(
                webUser: $webUser,
                skipHydrate: true,
                deletedData: $dependantDeletedData
            );
        }

        foreach ($customer->orders as $order) {
            DeleteOrder::run(
                order: $order,
                deletedData: $dependantDeletedData
            );
        }

        foreach ($customer->products as $product) {
            DeleteProduct::run(
                product: $product,
                skipHydrate: true,
                deletedData: $dependantDeletedData
            );
        }




        $customer->delete();
        $customer = $this->update($customer, $deletedData, ['data']);

        if (!$skipHydrate) {
            ShopHydrateCustomers::dispatch($customer->shop);
        }

        return $customer;
    }


    public function asCommand(Command $command): int
    {
        try {
            $customer = Customer::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception) {
            $command->error('Customer not found');

            return 1;
        }

        $customer = $this->handle($customer);

        $command->info('Customer '.$customer->name.' deleted');


        return 0;
    }
}
