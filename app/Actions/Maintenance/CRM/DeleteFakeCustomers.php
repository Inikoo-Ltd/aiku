<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Sept 2025 09:07:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\CRM;

use App\Actions\CRM\Customer\ForceDeleteCustomer;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;

class DeleteFakeCustomers
{
    use WithActionUpdate;


    protected function handle(Customer $customer, Command $command): void
    {
        $command->info("Deleting $customer->name ");

        ForceDeleteCustomer::run($customer);

    }

    public string $commandSignature = 'customers:delete_fake';

    public function asCommand(Command $command): void
    {

        $value1 = 'https';

        Customer::withTrashed()
            ->whereRaw("identity_document_number COLLATE \"C\" ILIKE ?", "%".$value1.'%')
            ->whereRaw("name COLLATE \"C\" ILIKE ?", "%".$value1.'%')
            ->orderBy('id')
            ->chunkById(500, function ($customers) use ($command) {
                foreach ($customers as $customer) {
                    $this->handle($customer, $command);
                }
            }, 'id');

        $value1 = 'REBGERBG';
        $value2 = '+38012334554676';

        Customer::withTrashed()
            ->where('identity_document_number', $value1)
            ->where('phone', $value2)
            ->orderBy('id')
            ->chunkById(500, function ($customers) use ($command) {
                foreach ($customers as $customer) {
                    $this->handle($customer, $command);
                }
            }, 'id');



    }

}
