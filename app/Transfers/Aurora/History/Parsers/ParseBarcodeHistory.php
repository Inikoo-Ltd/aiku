<?php

namespace App\Transfers\Aurora\History\Parsers;

class ParseBarcodeHistory
{
    public static function classify(object $row): array
    {
        $action = $row->Action ?? null;

        $event = match ($action) {
            'associated' => 'associated',
            'disassociate' => 'disassociated',
            default => null,
        };

        if ($event === null) {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        $abstract = (string) ($row->{'History Abstract'} ?? '');

        if (self::extractPartLink($abstract) === null) {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        return ['handling' => 'import', 'event' => $event, 'field' => null];
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        $abstract = (string) ($row->{'History Abstract'} ?? '');
        $part     = self::extractPartLink($abstract);

        if ($part === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $data = ['part_source_id' => $part['id'], 'part_code' => $part['code']];

        if (preg_match('/change_view\(\'upload\/(\d+)/', $abstract, $matches)) {
            $data['upload_source_id'] = $matches[1];
        }

        return match ($event) {
            'associated' => ['old_values' => [], 'new_values' => ['part' => $part['code']], 'data' => $data],
            'disassociated' => ['old_values' => ['part' => $part['code']], 'new_values' => [], 'data' => $data],
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function extractPartLink(string $abstract): ?array
    {
        if (!preg_match('/change_view\(\'part\/(\d+)\'\)"[^>]*>([^<]*)<\/span>/', $abstract, $matches)) {
            return null;
        }

        return ['id' => $matches[1], 'code' => trim($matches[2])];
    }
}
