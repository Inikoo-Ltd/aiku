<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:36:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */



/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\CRM;

use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateRecargoEquivalenciaFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(): void
    {
        $organisation=Organisation::find(3);
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);

        // Iterate all customers in organisation_id = 3 using chunks to avoid memory issues
        Customer::query()
            ->where('organisation_id', 3)->whereNotNull('source_id')
            ->orderBy('id')
            ->chunkById(1000, function ($customers) {
                foreach ($customers as $customer) {

                    $sourceData          = explode(':', $customer->source_id);
                    $auroraCustomerKey=$sourceData[1];


                    $auroraCustomerData=DB::connection('aurora')->table('Customer Dimension')->where('Customer Key',$auroraCustomerKey)->first();
                    $isRe=$auroraCustomerData->{'Customer Recargo Equivalencia'}=='Yes';
                    UpdateCustomer::make()->action($customer, [
                        'is_re'=>$isRe,
                    ]);

                }
            }, 'id');
    }




    public function getCommandSignature(): string
    {
        return 'maintenance:update_recargo_equivalencia_from_aurora';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
