<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Sept 2025 14:02:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Dispatching\Picking\WithAuroraApi;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
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

        if(!$customer->shop->is_aiku){
            return;
        }

        $apiUrl = $this->getApiUrl($customer->organisation);


        $shopSourceId = explode(':', $customer->shop->source_id);


        $customerAuroraId = null;


        $customerSourceId = null;
        if ($customer->source_id) {
            $customerSourceId = $customer->source_id;
        }
        if (!$customerSourceId) {
            $customerSourceId = $customer->post_source_id;
        }


        if ($customerSourceId) {
            $customerSourceId = explode(':', $customerSourceId);
            $customerAuroraId = $customerSourceId[1];
        }


        $data = [
            'action'                   => 'create_customer',
            'contact_name'             => $customer->contact_name,
            'company_name'             => $customer->company_name,
            'phone'                    => $customer->phone,
            'email'                    => $customer->email,
            'identity_document_number' => $customer->identity_document_number,
            'tax_number'               => $customer->taxNumber?->number,
            'send_newsletter'          => $customer->comms->is_subscribed_to_newsletter,
            'send_marketing'           => $customer->comms->is_subscribed_to_marketing,
            'address_line_1'           => $customer->address->address_line_1,
            'address_line_2'           => $customer->address->address_line_2,
            'sorting_code'             => $customer->address->sorting_code,
            'postal_code'              => $customer->address->postal_code,
            'dependent_locality'       => $customer->address->dependent_locality,
            'locality'                 => $customer->address->locality,
            'administrative_area'      => $customer->address->administrative_area,
            'country_code'             => $customer->address->country_code,
            'store_key'                => $shopSourceId[1],
            'aiku_id'                  => $customer->id,
            'picker_name'              => 'customer',
            'created_at'               => $customer->created_at?->format('Y-m-d H:i:s'),
            'customer_key'             => $customerAuroraId

        ];


        $response = Http::withHeaders([
            'secret' => $this->getApiToken($customer->organisation),
        ])->withQueryParameters($data)->get($apiUrl);


        if (Arr::get($response, 'customer_key')) {
            $customer->update(['post_source_id' => $customer->organisation->id.':'.$response['customer_key']]);
        }


        print_r($response->json());
    }


    public function getCommandSignature(): string
    {
        return 'customer:aurora_save {customerID? : The ID of the customer to save in Aurora (optional, processes all customers if not provided)}';
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
            $command->info("$count customers processed successfully");
        }

        return 0;
    }


}
