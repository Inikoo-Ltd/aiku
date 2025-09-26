<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Sept 2025 14:02:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Dispatching\Picking\WithAuroraApi;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dispatching\Picking;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveCustomerInAurora implements ShouldBeUnique
{
    use AsAction;
    use WithAuroraApi;

    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id;
    }



    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Customer $customer): void
    {

        if($customer->source_id){
            return;
        }

        $apiUrl = $this->getApiUrl($customer->organisation);




        Http::withHeaders([
            'secret' => $this->getApiToken($customer->organisation),
        ])->withQueryParameters(
            [

            ]
        )->get($apiUrl);
    }


    public function getCommandSignature(): string
    {
        return 'customer:aurora_save {customerID? : The ID of the customer to save in Aurora (optional, processes all pickings if not provided)}';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): int
    {
        $customerID = $command->argument('customerID');

        if ($customerID) {
            // Process a single customer
            $command->info("Processing customer ID: $customerID");
            $customer = Customer::findOrFail($customerID);
            $this->handle($customer);
            $command->info("Customer ID: $customerID processed successfully");
        } else {
            // Process all pickings
            $command->info('Processing all customers');

            $chunkSize = 100;
            $count     = 0;

            $totalCustomers = Customer::whereNull('source_id')->count();

            if ($totalCustomers === 0) {
                $command->info('No customers to process');

                return 0;
            }

            // Create a progress bar
            $bar = $command->getOutput()->createProgressBar($totalCustomers);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $bar->start();

            // Process pickings in chunks to avoid memory issues
            Customer::whereNull('source_id')
                ->chunk($chunkSize, function ($customers) use (&$count, $bar, $command) {
                    foreach ($customers as $customer) {
                        try {
                            $this->handle($customer);
                            $count++;
                        } catch (\Exception $e) {
                            $command->error("Error processing customer: $customer->slug - {$e->getMessage()}");
                        }
                        $bar->advance();
                    }
                });

            $bar->finish();
            $command->newLine();
            $command->info("$count pickings processed successfully");
        }

        return 0;
    }


}
