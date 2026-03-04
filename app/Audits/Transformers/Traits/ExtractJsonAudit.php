<?php

namespace App\Audits\Transformers\Traits;

use Illuminate\Support\Arr;

class ExtractJsonAudit
{
    public static function extractJsonDifferences(array $data, string $columnName, string $labelPrefix) : array
    {
        $oldValues = Arr::get($data, 'old_values.' . $columnName, []);
        $newValues = Arr::get($data, 'new_values.' . $columnName, []);

        if(is_string($oldValues)) $oldValues = json_decode($oldValues, true) ?? [];
        if(is_string($newValues)) $newValues = json_decode($newValues, true) ?? [];
        
        $changeKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        
        foreach($changeKeys as $key){
            $oldVal = $oldValues[$key] ?? null;
            $newVal = $newValues[$key] ?? null;

            if(json_encode($oldVal) !== json_encode($newVal))
            {
                $cleanLabel = $labelPrefix . ' - ' . ucwords(str_replace('_', ' ', $key));

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