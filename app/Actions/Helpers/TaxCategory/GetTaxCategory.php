<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Jul 2024 14:16:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxCategory;

use App\Actions\Helpers\Country\UI\IsEuropeanUnion;
use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxCategory;
use App\Models\Helpers\TaxNumber;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTaxCategory
{
    use AsAction;

    public function handle(Country $country, ?TaxNumber $taxNumber, Address $billingAddress, ?Address $deliveryAddress): TaxCategory
    {
        return match ($country->code) {
            'GB'    => $this->gbTaxCategory($billingAddress, $deliveryAddress),
            default => IsEuropeanUnion::run($country->code)
                ?
                $this->euTaxCategory($country, $billingAddress, $deliveryAddress, $taxNumber)
                :
                TaxCategory::find(1)
        };
    }


    protected function gbTaxCategory(Address $billingAddress, ?Address $deliveryAddress): TaxCategory
    {
        $gbCountryId      = Country::where('code', 'GB')->first()->id;
        $taxableCountries = ['GB', 'IM'];

        if (in_array($billingAddress->country_code, $taxableCountries) || in_array($deliveryAddress->country_code, $taxableCountries)) {
            return TaxCategory::where('type', TaxCategoryTypeEnum::STANDARD)->where('country_id', $gbCountryId)->where('status', true)->first();
        }

        return $this->getOutsideTaxCategory();
    }


    protected function euTaxCategory(Country $country, Address $billingAddress, ?Address $deliveryAddress, ?TaxNumber $taxNumber, bool $isRe = false): TaxCategory
    {

        if ($billingAddress->country_code == $country->code || $deliveryAddress->country->code == $country->code) {
            if ($country->code == 'ES') {
                $esCountryId = Country::where('code', 'ES')->first()->id;
                if ($deliveryAddress->country_code == 'ES' && preg_match('/^(35|38|51|52)/', $deliveryAddress->postal_code)) {
                    return $this->getOutsideTaxCategory();
                }

                if ($isRe) {
                    return TaxCategory::where('type', TaxCategoryTypeEnum::SPECIAL)
                        ->where('data->is_re', true)
                        ->where('country_id', $esCountryId)
                        ->where('status', true)->first();
                } else {
                    return TaxCategory::where('type', TaxCategoryTypeEnum::STANDARD)->where('country_id', $country->id)->where('status', true)->first();
                }
            } else {
                return TaxCategory::where('type', TaxCategoryTypeEnum::STANDARD)->where('country_id', $country->id)->where('status', true)->first();
            }
        }


        if (IsEuropeanUnion::run($billingAddress->country->code)  &&
            IsEuropeanUnion::run($deliveryAddress->country->code) &&
            $taxNumber                                           &&
            $taxNumber->valid
        ) {
            return $this->getEuValidTaxNumber();
        }

        if ($deliveryAddress->country_code == 'MC') {
            //FR=124
            return TaxCategory::where('type', TaxCategoryTypeEnum::STANDARD)->where('country_id', 124)->where('status', true)->first();
        }

        if ($deliveryAddress->country_code == 'PT') {
            //PT=69
            if (preg_match('/^(90|91|92|93|94)/', $deliveryAddress->postal_code)) {
                return TaxCategory::where('type', TaxCategoryTypeEnum::SPECIAL)
                    ->where('label', 'PT-SR-RAM')
                    ->where('country_id', 69)->where('status', true)->first();
            }
            if (str_starts_with($deliveryAddress->postal_code, '9')) {
                return TaxCategory::where('type', TaxCategoryTypeEnum::SPECIAL)
                    ->where('label', 'PT-SR-RAA')
                    ->where('country_id', 69)->where('status', true)->first();
            }
        }

        if (IsEuropeanUnion::run($deliveryAddress->country->code)) {
            return TaxCategory::where('type', TaxCategoryTypeEnum::STANDARD)
                ->where('country_id', $deliveryAddress->country_id)
                ->where('status', true)->first();
        }

        return $this->getOutsideTaxCategory();
    }

    protected function getOutsideTaxCategory(): TaxCategory
    {
        /** @var TaxCategory $taxCategory */
        $taxCategory = TaxCategory::find(1);

        return $taxCategory;
    }

    protected function getEuValidTaxNumber(): TaxCategory
    {
        /** @var TaxCategory $taxCategory */
        $taxCategory = TaxCategory::find(2);

        return $taxCategory;
    }

}
