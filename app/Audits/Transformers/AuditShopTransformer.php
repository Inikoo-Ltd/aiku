<?php

namespace App\Audits\Transformers;

use Illuminate\Support\Arr;
use App\Audits\Transformers\Traits\ExtractJsonAudit;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;

class AuditShopTransformer
{
    use ExtractJsonAudit;

    public static function transform(array $data) : array
    {
        if (Arr::has($data, 'new_values.settings')) {
            $data = self::extractJsonDifferences($data, 'settings');
        }

        if (Arr::has($data, 'new_values.data')) {
            $data = self::extractJsonDifferences($data, 'data');
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