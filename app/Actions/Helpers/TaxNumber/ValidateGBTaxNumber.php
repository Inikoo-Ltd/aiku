<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Oct 2025 22:15:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberValidationTypeEnum;
use App\Models\Helpers\TaxNumber;
use App\Actions\Helpers\TaxNumber\Concerns\AsTaxNumberCommand;
use App\Actions\Helpers\TaxNumber\Traits\WithValidateTaxNumberCustomAudit;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateGBTaxNumber
{
    use AsAction;
    use AsTaxNumberCommand;
    use WithValidateTaxNumberCustomAudit;

    public function handle(TaxNumber $taxNumber, TaxNumber|null $oldTaxNumberData = null): TaxNumber
    {
        if ($taxNumber->type == TaxNumberTypeEnum::GB_VAT) {
            $number = $this->cleanTaxNumber($taxNumber->number);
            if(!$oldTaxNumberData) $oldTaxNumberData = $taxNumber->replicate();

            if (!$number || strlen($number) != 9) {

                $validationData = [
                    'valid'              => false,
                    'status'             => TaxNumberStatusEnum::INVALID,
                    'validation_type'    => TaxNumberValidationTypeEnum::BASIC,
                    'checked_at'         => now(),
                    'invalid_checked_at' => now()

                ];

                $taxNumber->update($validationData);
                $taxNumber->refresh();
                $this->deployTaxValidationCustomAudit($oldTaxNumberData, $taxNumber, TaxNumberValidationTypeEnum::BASIC);

                return $taxNumber;
            }

            if (config('hmrc_api.enabled')) {
                ValidateOnlineGBTaxNumber::dispatch($taxNumber);
            } else {
                $validationData = [
                    'valid'           => true,
                    'status'          => TaxNumberStatusEnum::VALID,
                    'validation_type' => TaxNumberValidationTypeEnum::BASIC,
                    'checked_at'      => now(),

                ];
                
                $taxNumber->update($validationData);
                $taxNumber->refresh();
                $this->deployTaxValidationCustomAudit($oldTaxNumberData, $taxNumber, TaxNumberValidationTypeEnum::BASIC);

                return $taxNumber;
            }
        }

        return $taxNumber;
    }


    public function cleanTaxNumber($taxNumber): string
    {
        $taxNumber = $taxNumber ?? '';
        $taxNumber = preg_replace('/\s+/', '', $taxNumber);

        return preg_replace('/^(gb)/i', '', $taxNumber, 1);
    }


    public function getCommandSignature(): string
    {
        return 'validate:tax_number_gb {id}';
    }


}
