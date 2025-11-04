<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;

class RepairCustomerCountryCodeMismatch
{
    use WithActionUpdate;

    protected function handle(Customer $customer, Command $command): void
    {
        $countryCode0 = $customer->address->country_code;
        $countryCode1 = $customer->address->country->code;
        if ($countryCode0 !== $countryCode1) {
            $command->line("customer: $customer->slug country_code: $countryCode0 ||>>>><<<<|| country_id->code: $countryCode1");
        }
    }

    public string $commandSignature = 'repair:country_code_mismatch';

    public function asCommand(Command $command): void
    {
        Customer::orderBy('created_at')->chunk(1000, function ($customers) use ($command) {
            foreach ($customers as $customer) {
                $this->handle($customer, $command);
            }
        });
    }
}
