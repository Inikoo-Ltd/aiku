<?php

/*
 * author Louis Perez
 * created on 26-02-2026-09h-45m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Helpers\TaxNumber\Traits;

use App\Models\Helpers\TaxNumber;
use App\Enums\Helpers\TaxNumber\TaxNumberValidationTypeEnum;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

trait WithValidateTaxNumberCustomAudit
{
    public function deployTaxValidationCustomAudit(TaxNumber $oldTaxNumber, TaxNumber $newTaxNumber, TaxNumberValidationTypeEnum $validationType, $thirdPartyStatus = null)
    {
        // Binded to customer instead
        $customer = $newTaxNumber->owner;
        $customer->auditEvent = 'tax_number_validation';
        $customer->isCustomEvent = true;

        $customer->auditCustomOld = [
            'tax_number'           => $oldTaxNumber->number == $newTaxNumber->number ? null : $oldTaxNumber->number, // To trick always show tax number on VAT Validation History (If old & new is the same, won't be displayed)
        ];

        $customer->auditCustomNew = array_filter([
            'country_code'          => $newTaxNumber->country_code,
            'tax_number'            => $newTaxNumber->number,
            'status'                => ucfirst($newTaxNumber->status->value),
            'third_party_status'    => $thirdPartyStatus,
            'validation_type'       => ucfirst($validationType->value),
            'validated_at'          => now()->format('d/m/Y H:i A T'),
        ]);

        Event::dispatch(new AuditCustom($customer));
    }
}
