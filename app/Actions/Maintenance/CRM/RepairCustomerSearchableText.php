<?php

/*
 * author Louis Perez
 * created on 30-01-2026-14h-51m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\CRM;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairCustomerSearchableText
{
    use WithActionUpdate;


    protected function handle(Customer $customer): void
    {
        $customer->syncSearchableText();
        $customer->save();
    }

    public string $commandSignature = 'customers:repair_searchable_text';

    public function asCommand(Command $command): void
    {
        $customers = Customer::query();
        $total = (clone $customers)->count();

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->start();

        $customers->chunk(200, function ($customers) use ($bar) {
            foreach ($customers as $customer) {
                $this->handle($customer);
                $bar->advance();
            }
        });

        $bar->finish();

    }

}
