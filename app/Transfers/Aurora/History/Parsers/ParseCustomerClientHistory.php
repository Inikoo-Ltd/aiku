<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseCustomerClientHistory
{
    protected const array FIELD_MAP = [
        'Customer Client Company Name' => 'company_name',
        'Customer Client Contact Address' => 'address',
        'Customer Client Main Plain Email' => 'email',
        'Customer Client Main Contact Name' => 'contact_name',
        'Customer Client Code' => 'code',
    ];

    protected const array ADDRESS_FIELDS = ['address'];

    public static function classify(object $row): array
    {
        $action = $row->Action ?? null;

        return match ($action) {
            'created' => ['handling' => 'import', 'event' => 'created', 'field' => null],
            'deleted' => ['handling' => 'import', 'event' => 'deleted', 'field' => null],
            'edited' => self::classifyEdited($row),
            default => ['handling' => 'skip', 'event' => null, 'field' => null],
        };
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => self::extractCreatedValues($row),
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function classifyEdited(object $row): array
    {
        $indirectObject = HistoryValueExtractor::normalize((string) ($row->{'Indirect Object'} ?? ''));

        if ($indirectObject === null) {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        if (array_key_exists($indirectObject, self::FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function extractCreatedValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match("/Customer's client created \((.+)\)/", $abstract, $matches)) {
            $name = trim($matches[1]);

            return ['old_values' => [], 'new_values' => $name === '' ? [] : ['name' => $name], 'data' => []];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        if ($field === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $details = (string) ($row->{'History Details'} ?? '');
        $table   = HistoryValueExtractor::extractTable($details);

        $old = $table['old'];
        $new = $table['new'];

        if ($old === null && $new === null) {
            $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

            if (preg_match("/Customer Client's (.+) set as (.+)/", $abstract, $matches)
                || preg_match("/Customer Client's (.+) was changed to (.+)/", $abstract, $matches)) {
                $new = trim($matches[2]);
            } elseif (preg_match('/Customer Client (?:&rArr;|⇒) (.+) was changed to (.+)/', $abstract, $matches)) {
                $new = trim($matches[2]);
            }

            if ($new === null) {
                return ['old_values' => [], 'new_values' => [], 'data' => []];
            }
        }

        if (in_array($field, self::ADDRESS_FIELDS, true)) {
            $oldValue = $old === null ? '' : (HistoryValueExtractor::parseAdrAddress($old) ?? $old);
            $newValue = $new === null ? '' : (HistoryValueExtractor::parseAdrAddress($new) ?? $new);

            return ['old_values' => [$field => $oldValue], 'new_values' => [$field => $newValue], 'data' => []];
        }

        return ['old_values' => [$field => $old ?? ''], 'new_values' => [$field => $new ?? ''], 'data' => []];
    }
}
