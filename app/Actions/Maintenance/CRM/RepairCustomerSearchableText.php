<?php

/*
 * author Louis Perez
 * created on 30-01-2026-14h-51m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\CRM;

use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

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

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('aiku_eta');
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
