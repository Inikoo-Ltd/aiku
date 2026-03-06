<?php

namespace App\Audits\Transformers\Traits;

use Illuminate\Support\Arr;

trait ExtractJsonAudit
{
    public static function extractJsonDifferences(array $data, string $columnName) : array
    {
        $oldValues = Arr::get($data, 'old_values.' . $columnName, []);
        $newValues = Arr::get($data, 'new_values.' . $columnName, []);

        if(is_string($oldValues)) $oldValues = json_decode($oldValues, true) ?? [];
        if(is_string($newValues)) $newValues = json_decode($newValues, true) ?? [];

        $oldFlat = Arr::dot($oldValues);
        $newFlat = Arr::dot($newValues);
        
        $changeKeys = array_unique(array_merge(array_keys($oldFlat), array_keys($newFlat)));
        
        foreach($changeKeys as $key){
            $oldVal = $oldFlat[$key] ?? null;
            $newVal = $newFlat[$key] ?? null;

            if(json_encode($oldVal) !== json_encode($newVal))
            {
                $cleanLabel = ucwords(str_replace('.', ' ', $key));

                $formatValue = function($val){
                if (is_bool($val)) return $val ? 'True' : 'False';
                    if (is_array($val)) return json_encode($val);
                    return $val ?? 'Empty';
                };

                $data['old_values'][$cleanLabel] = $formatValue($oldVal);
                $data['new_values'][$cleanLabel] = $formatValue($newVal);
            }
        }

        unset($data['old_values'][$columnName]);
        unset($data['new_values'][$columnName]);

        return $data;
    }
}