<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Mar 2023 02:05:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Actions\Traits\WithOrganisationArgument;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Helpers\TaxNumber;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateTaxNumber
{
    use AsAction;
    use WithOrganisationArgument;

    public string $commandSignature = 'validate:tax-number {tax_number_id}';

    protected array $deletedDependants;

    public function __construct()
    {
        $this->deletedDependants = [
            'clients'          => 0,
            'webUsers'         => 0,
            'products'         => 0,
            'fulfilmentOrders' => 0,
            'orders'           => 0,
        ];
    }

    public function handle(TaxNumber $taxNumber): TaxNumber
    {
        if ($taxNumber->type == TaxNumberTypeEnum::EU_VAT) {
            $taxNumber = ValidateEuropeanTaxNumber::run($taxNumber);
        }
        
        return $taxNumber;
    }


    public function asCommand(Command $command): int
    {
        try {
            $taxNumber = TaxNumber::findOrFail($command->argument('tax_number_id'));
        } catch (Exception) {
            $command->error('Tax Number not found');

            return 1;
        }
        $this->handle($taxNumber);

        return 0;
    }

    public function asController(TaxNumber $taxNumber)
    {
        return $this->handle($taxNumber);
    }
}
