<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Oct 2025 13:20:52 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Prospect\UpdateProspect;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use Illuminate\Console\Command;

class MatchCustomerProspects extends OrgAction
{
    use WithActionUpdate;

    public function handle(Customer $customer): Customer
    {
        $prospect = Prospect::where('email', $customer->email)->where('shop_id', $customer->shop_id)->first();
        if ($prospect) {
            $fistInvoice    = null;
            $numberInvoices = $customer->invoices()->count();

            if ($numberInvoices > 0) {
                /** @var Invoice $fistInvoice */
                $fistInvoice = $customer->invoices()->oldest()->first();
            }


            UpdateProspect::make()->action(
                $prospect,
                [
                    'customer_id'   => $customer->id,
                    'state'         => ProspectStateEnum::SUCCESS,
                    'success_state' => $numberInvoices > 0 ? ProspectSuccessStatusEnum::INVOICED : ProspectSuccessStatusEnum::REGISTERED,
                    'fail_status'   => ProspectFailStatusEnum::NA,
                    'registered_at' => $customer->created_at,
                    'invoiced_at'   => $fistInvoice?->created_at,
                ]
            );
        }


        return $customer;
    }


    public function getCommandSignature(): string
    {
        return 'customer:match_prospects {customerID? : The ID of the customer to save in Aurora (optional, processes all customers if not provided)}';
    }


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
            $shops = Shop::where('is_aiku', true)->pluck('id')->toArray();

            // Process all pickings
            $command->info('Processing all customers');

            $chunkSize = 100;
            $count     = 0;

            $totalCustomers = Customer::whereIn('shop_id', $shops)->count();

            if ($totalCustomers === 0) {
                $command->info('No customers to process');

                return 0;
            }

            // Create a progress bar
            $bar = $command->getOutput()->createProgressBar($totalCustomers);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $bar->start();

            // Process pickings in chunks to avoid memory issues
            Customer::whereIn('shop_id', $shops)
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
