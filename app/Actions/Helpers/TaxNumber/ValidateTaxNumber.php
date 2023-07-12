<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Mar 2023 02:05:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Actions\Traits\WithTenantArgument;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Helpers\TaxNumber;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateTaxNumber
{
    use AsAction;
    use WithTenantArgument;

    public string $commandSignature = 'validate:tax-number {tenant} {tax_number_id}';

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

    public function handle(TaxNumber $taxNumber): void
    {
        if ($taxNumber->type==TaxNumberTypeEnum::EU_VAT) {
            ValidateEuropeanTaxNumber::run($taxNumber);
        }
    }


    public function asCommand(Command $command): int
    {
        $this->getTenant($command)->execute(
            function () use ($command) {
                $taxNumber=TaxNumber::findOrFail($command->argument('tax_number_id'));
                $this->handle($taxNumber);
            }
        );



        return 0;
    }
}
