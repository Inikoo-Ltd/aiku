<?php

namespace App\Audits\Transformers;

use Illuminate\Support\Arr;
use App\Audits\Transformers\Traits\ExtractJsonAudit;

class AuditShopTransformer extends ExtractJsonAudit
{
    use ExtractJsonAudit;

    public static function transform(array $data) : array
    {
       if (Arr::has($data, 'new_values.settings')) {
            $data = self::extractJsonDifferences($data, 'settings', 'Settings');
        }
        return $data;
    }
}