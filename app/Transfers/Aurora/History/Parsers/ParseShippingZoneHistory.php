<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseShippingZoneHistory
{
    protected const array AUDITABLE_TYPE_MAP = [
        'Shipping Zone' => 'ShippingZone',
        'Shipping Zone Schema' => 'ShippingZoneSchema',
    ];

    public static function classify(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;

        if (!array_key_exists($directObject, self::AUDITABLE_TYPE_MAP)) {
            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        if (($row->Action ?? null) !== 'created') {
            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        return [
            'handling' => 'import',
            'event' => 'created',
            'field' => null,
            'auditable_type' => self::AUDITABLE_TYPE_MAP[$directObject],
        ];
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        if ($event !== 'created') {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/^(.*?)\s+shipping zone(?: schema)? created$/', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => ['name' => trim($matches[1])], 'data' => []];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }
}
