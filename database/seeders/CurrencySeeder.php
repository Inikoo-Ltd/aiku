<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 26 Aug 2021 05:39:38 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace Database\Seeders;

use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Intl\Currency\CurrencyRepository;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run()
    {
        $currencyRepository = new CurrencyRepository;

        foreach ($currencyRepository->getAll() as $currency) {
            $symbol = match ($currency->getCurrencyCode()) {
                'AFN' => '؋',
                'ALL' => 'L',
                'DZD' => 'دج',
                'AOA' => 'Kz',
                'ARS' => '$',
                'AMD' => '֏',
                'AWG' => 'ƒ',
                'AZN' => '₼',
                'BSD' => 'B$',
                'BHD' => 'د.ب',
                'BDT' => '৳',
                'BBD' => 'Bds$',
                'BZD' => '$',
                'BMD' => '$',
                'BTN' => 'Nu.',
                'VED' => 'Bs',
                'BOB' => 'Bs',
                'BAM' => 'KM',
                'BWP' => 'P',
                'BND' => 'B$',
                'BGN' => 'Лв',
                'BIF' => 'FBu',
                'KHR' => '៛',
                'CVE' => 'Esc',
                'KYD' => '$',
                'XPF' => '₣',
                'CLP' => '$',
                'COP' => '$',
                'KMF' => 'CF',
                'CDF' => 'FC',
                'CRC' => '₡',
                'CUC' => 'CUC$',
                'CUP' => '₱',
                'CZK' => 'Kč',
                'DKK' => 'Kr',
                'DJF' => 'Fdj',
                'DOP' => 'RD$',
                default => $currency->getSymbol()
            };
            Currency::UpdateOrCreate(
                ['code' => $currency->getCurrencyCode()],
                [
                    'name' => $currency->getName(),
                    'symbol' => $symbol,
                    'fraction_digits' => $currency->getFractionDigits(),

                ]
            );
        }

        $countryRepository = new CountryRepository;
        $countryList = $countryRepository->getList('en-GB');
        foreach ($countryList as $countryCode => $countryName) {
            if ($country = Country::where('code', $countryCode)->first()) {
                $_country = $countryRepository->get($countryCode);

                if ($currency = Currency::where('code', $_country->getCurrencyCode())->first()) {
                    $country->currency_id = $currency->id;
                    $country->save();
                } else {
                    echo "Currency not found : {$_country->getCurrencyCode()} for country $countryCode\n";
                }
            }
        }
    }
}
