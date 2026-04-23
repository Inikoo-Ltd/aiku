<?php

namespace App\Audits\Transformer;

use Illuminate\Support\Arr;

class JsonTransformer{
    public static function execute(array $auditData, string $jsonColumn, array $keyToTrack) : array {

        if(Arr::has($auditData, 'new_values.' . $jsonColumn)) {
            $oldJson = Arr::get($auditData, 'old_values.' . $jsonColumn, []);
            $newJson = Arr::get($auditData, 'new_values.' . $jsonColumn, []);

            foreach($keyToTrack as $key) {
                $oldValue = Arr::get($oldJson, $key);
                $newValue = Arr::get($newJson, $key);

                if($oldValue !== $newValue) {
                    $auditKey = $jsonColumn . '-' . str_replace('.', '-', $key);
                    $auditData['old_values'][$auditKey] = $oldValue;
                    $auditData['new_values'][$auditKey] = $newValue;
                }
            }
            unset($auditData['new_values'][$jsonColumn]);
            unset($auditData['old_values'][$jsonColumn]);
        }

        return $auditData;
    }
}