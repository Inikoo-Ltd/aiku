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
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\TaxNumber;
use Illuminate\Console\Command;

class RepairGreekCustomerVatNumber
{
    use WithActionUpdate;


    protected function handle(TaxNumber $taxNumber): void
    {

        $number = preg_replace('/^(GR|EL)/i', '', $taxNumber->number);

        $number = 'EL'.$number;
        UpdateTaxNumber::run($taxNumber, ['number' => $number, 'country_id' => $taxNumber->country_id]);
    }

    public string $commandSignature = 'repair:greek_tax_number';

    public function asCommand(Command $command): void
    {
        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();
        $query     = TaxNumber::where('country_id', 39)->where('owner_type', 'Customer')->get();
        foreach ($query as $taxNumber) {
            $customer = Customer::find($taxNumber->owner_id);
            if (in_array($customer->shop_id, $aikuShops)) {
                $command->info('Fixing '.$taxNumber->number." $taxNumber->id");
                $this->handle($taxNumber);
            }
        }
    }

}
