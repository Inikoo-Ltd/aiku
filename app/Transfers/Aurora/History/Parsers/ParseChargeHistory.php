<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseChargeHistory
{
    protected const array FIELD_MAP = [
        'Charge Name' => 'name',
        'Charge Description' => 'description',
        'Charge Public Description' => 'public_description',
        'Charge Active' => 'active',
        'Charge Metadata' => 'metadata',
    ];

    public static function classify(object $row): array
    {
        $indirectObject = HistoryValueExtractor::normalize((string) ($row->{'Indirect Object'} ?? ''));

        if (($row->Action ?? null) === 'edited' && $indirectObject !== null && array_key_exists($indirectObject, self::FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        $table = HistoryValueExtractor::extractTable((string) ($row->{'History Details'} ?? ''));

        if ($field === null || ($table['old'] === null && $table['new'] === null)) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        return [
            'old_values' => [$field => $table['old'] ?? ''],
            'new_values' => [$field => $table['new'] ?? ''],
            'data' => [],
        ];
    }
}
