<?php

namespace App\Audits\Transformers;

use Illuminate\Support\Arr;
use App\Audits\Transformers\Traits\ExtractJsonAudit;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;

class AuditShopTransformer
{
    use ExtractJsonAudit;

    public static function transform(array $data) : array
    {
        if (Arr::has($data, 'new_values.settings')) {

            $oldSettings = Arr::get($data, 'old_values.settings', []);
            $newSettings = Arr::get($data, 'new_values.settings', []);

            if (Arr::has($newSettings, 'ebay.warehouse_country') || Arr::has($oldSettings, 'ebay.warehouse_country')) {
                $oldEbayCountry = Arr::get($oldSettings, 'ebay.warehouse_country', null);
                $newEbayCountry = Arr::get($newSettings, 'ebay.warehouse_country', null);

                $data['old_values']['ebay_warehouse_country_name'] = $oldEbayCountry ? Country::find($oldEbayCountry)?->name : 'Empty';
                $data['new_values']['ebay_warehouse_country_name'] = $newEbayCountry ? Country::find($newEbayCountry)?->name : 'Empty';

                unset($data['old_values']['ebay.warehouse_country'], $data['new_values']['ebay.warehouse_country']);
            }

            $data = self::extractJsonDifferences($data, 'settings');
        }

        if (Arr::has($data, 'new_values.data')) {
            $data = self::extractJsonDifferences($data, 'data');
        }

        if (Arr::has($data, 'new_values.forbidden_dispatch_countries')) {
            $oldValues = Arr::get($data, 'old_values.forbidden_dispatch_countries', null);
            $newValues = Arr::get($data, 'new_values.forbidden_dispatch_countries', null);

            if(is_string($oldValues)) $oldValues = json_decode($oldValues, true) ?? [];
            if(is_string($newValues)) $newValues = json_decode($newValues, true) ?? [];
            
            $formatCountries = function($ids){
                if(empty($ids)) return 'Empty';
                
                $countries = Country::whereIn('id', $ids)->pluck('name')->toArray();
                
                if(count($countries) > 100){
                    return implode(', ', array_slice($countries, 0, 5)) . '...';
                }

                return implode(', ', $countries);
            };

            $data['old_values']['forbidden_dispatch_countries'] = $formatCountries($oldValues);
            $data['new_values']['forbidden_dispatch_countries'] = $formatCountries($newValues);

            $changeKeys = array_unique(array_merge(array_keys(Arr::get($data, 'old_values', [])), array_keys(Arr::get($data, 'new_values', []))));

             foreach($changeKeys as $key){
                if(str_contains($key, 'forbidden_dispatch_countries'))
                {
                    $cleanLabel = ucwords(str_replace('_', ' ', $key));
                    $data['old_values'][$cleanLabel] = Arr::get($data, 'old_values.' . $key, 'Empty');
                    $data['new_values'][$cleanLabel] = Arr::get($data, 'new_values.' . $key, 'Empty');
                    
                    unset($data['old_values'][$key]);
                    unset($data['new_values'][$key]);
                }
            }
        }

        if (Arr::has($data, 'new_values.country_id')) {
            $oldCountryId = Arr::get($data, 'old_values.country_id', null);
            $newCountryId = Arr::get($data, 'new_values.country_id', null);

            $data['old_values']['country_name'] = $oldCountryId ? Country::find($oldCountryId)->name : 'Empty';
            $data['new_values']['country_name'] = $newCountryId ? Country::find($newCountryId)->name : 'Empty';

            unset($data['old_values']['country_id']);
            unset($data['new_values']['country_id']);
        }

        if(Arr::has($data, 'new_values.invoice_footer')){
            $oldInvoiceFooter = Arr::get($data, 'old_values.invoice_footer', null);
            $newInvoiceFooter = Arr::get($data, 'new_values.invoice_footer', null);

            $data['old_values']['invoice_footer'] = $oldInvoiceFooter ? strip_tags($oldInvoiceFooter) : 'Empty';
            $data['new_values']['invoice_footer'] = $newInvoiceFooter ? strip_tags($newInvoiceFooter) : 'Empty';
        }

        if(Arr::has($data, 'new_values.currency_id')){
            $oldCurrencyId = Arr::get($data, 'old_values.currency_id', null);
            $newCurrencyId = Arr::get($data, 'new_values.currency_id', null);

            $data['old_values']['currency_name'] = $oldCurrencyId ? Currency::find($oldCurrencyId)->name : 'Empty';
            $data['new_values']['currency_name'] = $newCurrencyId ? Currency::find($newCurrencyId)->name : 'Empty';

            unset($data['old_values']['currency_id']);
            unset($data['new_values']['currency_id']);
        }

        if(Arr::has($data, 'new_values.language_id')){
            $oldLanguageId = Arr::get($data, 'old_values.language_id', null);
            $newLanguageId = Arr::get($data, 'new_values.language_id', null);

            $data['old_values']['language_name'] = $oldLanguageId ? Language::find($oldLanguageId)->name : 'Empty';
            $data['new_values']['language_name'] = $newLanguageId ? Language::find($newLanguageId)->name : 'Empty';

            unset($data['old_values']['language_id']);
            unset($data['new_values']['language_id']);
        }

        // $relations = [
        //     'country_id' => Country::class,
        //     'currency_id' => Currency::class,
        // ];

        // foreach($relations as $key => $column){
        //     if(Arr::has($data, 'new_values.' . $column)){
        //         $oldId = Arr::get($data, 'old_values.' . $column, null);
        //         $newId = Arr::get($data, 'new_values.' . $column, null);

        //         $clearLabal = ucwords(str_replace('_id', ' ', $column));

        //         $data['old_values'][$clearLabal] = $oldId ? $column::find($oldId)->name : 'Empty';
        //         $data['new_values'][$clearLabal] = $newId ? $column::find($newId)->name : 'Empty';

        //         unset($data['old_values'][$column]);
        //         unset($data['new_values'][$column]);
        //     }
        // }

        return $data;
    }
}