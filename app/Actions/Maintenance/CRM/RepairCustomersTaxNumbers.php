<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Oct 2025 11:35:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\CRM;

use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairCustomersTaxNumbers
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Customer $customer): void
    {

        if ($customer->taxNumber) {
            $taxNumber = $customer->taxNumber->number;

            if (strtolower($taxNumber) == 'na'  || strtolower($taxNumber) == 'n/a' || strtolower($taxNumber) == '-' || strtolower($taxNumber) == 'no') {
                $taxNumber = '';
            }


            UpdateCustomer::run(
                $customer,
                [
                    'tax_number' => [
                        'number' => $taxNumber
                    ]
                ]
            );

            print "{$customer->slug} {$taxNumber}\n";

        }

    }


    public function getCommandSignature(): string
    {
        return 'maintenance:repair_customers_tax_numbers {shop_id}';
    }

    public function asCommand(Command $command): int
    {



        $shopId = $command->argument('shop_id');
        $shop   = Shop::find($shopId);

        if (!$shop) {
            $command->error("Shop not found: {$shopId}");

            return 1;
        }

        try {
            Customer::query()
                ->where('customers.shop_id', $shop->id)
                ->orderBy('id')
                ->chunkById(1000, function ($customers) {
                    foreach ($customers as $customer) {
                        $this->handle($customer);
                    }
                }, 'id');

            $command->info("Completed chunked processing of customers for shop {$shop->id}.");
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
