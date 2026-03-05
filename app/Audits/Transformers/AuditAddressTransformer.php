<?php

namespace App\Audits\Transformers;

use Illuminate\Support\Arr;
use App\Models\Helpers\Country;

class AuditAddressTransformer
{
    public static function transform(array $data) : array
    {
        if(Arr::has($data, 'new_values.country_id')){
            $oldCountryId = Arr::get($data, 'old_values.country_id', null);
            $newCountryId = Arr::get($data, 'new_values.country_id', null);

            $data['old_values']['country_name'] = $oldCountryId ? Country::find($oldCountryId)->name : 'Empty';
            $data['new_values']['country_name'] = $newCountryId ? Country::find($newCountryId)->name : 'Empty';

            unset($data['old_values']['country_id']);
            unset($data['new_values']['country_id']);
        }

        return $data;
    }
}