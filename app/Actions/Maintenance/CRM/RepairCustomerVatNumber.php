<?php

/*
 * author Louis Perez
 * created on 23-02-2026-17h-14m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\CRM;

use App\Actions\Helpers\TaxNumber\UpdateTaxNumber;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\TaxNumber;
use Illuminate\Console\Command;

class RepairCustomerVatNumber
{
    use WithActionUpdate;


    protected function handle(TaxNumber $taxNumber): void
    {
        UpdateTaxNumber::run($taxNumber, ['number' => $taxNumber->number, 'country_id' => $taxNumber->country_id]);
    }

    public string $commandSignature = 'customers:repair_vat';

    public function asCommand(Command $command): void
    {
        $query = TaxNumber::where('country_id', 39)->get();
        $total = $query->count();

        if ($total === 0) {
            $command->info('No invoice transactions to repair.');

            return;
        }

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        foreach ($query as $taxNumber) {
            $this->handle($taxNumber);
            $bar?->advance();
        }

        
        $bar->finish();
        $command->newLine();
        $command->info('VAT repair completed');
    }

}
