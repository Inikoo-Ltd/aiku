<?php

/*
 * author Louis Perez
 * created on 22-06-2026-09h-07m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Helpers\TaxNumber;

use App\Actions\Helpers\TaxNumber\Concerns\HasTaxNumberType;
use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxNumber;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneTaxNumberFromCustomer
{
    use AsAction;
    use HasTaxNumberType;

    public function handle(Invoice $target, TaxNumber $originalTaxNumber, $checkViaThirdParty = true): TaxNumber
    {
        if ($checkViaThirdParty && in_array($originalTaxNumber->status, [
            TaxNumberStatusEnum::UNKNOWN,
            TaxNumberStatusEnum::INVALID,
            TaxNumberStatusEnum::NA,
        ])) {
            if ($originalTaxNumber->type == TaxNumberTypeEnum::EU_VAT) {
                $originalTaxNumber = ValidateEuropeanTaxNumber::run($originalTaxNumber);
            } elseif ($originalTaxNumber->type == TaxNumberTypeEnum::GB_VAT) {
                $originalTaxNumber = ValidateGBTaxNumber::run($originalTaxNumber);
            }
        }

        $targetTaxNumber = $target->taxNumber()->create($originalTaxNumber->only([
            'country_code',
            'number',
            'type',
            'country_id',
            'status',
            'valid',
            'data',
            'historic',
            'usage',
            'checksum',
            'checked_at',
            'validation_type'
        ]));

        return $targetTaxNumber;
    }

}
