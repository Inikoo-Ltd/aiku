<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseInvoiceHistory
{
    protected const array FIELD_MAP = [
        'Invoice Message' => 'message',
        'Invoice Customer Name' => 'customer_name',
        'Invoice Registration Number' => 'registration_number',
        'Invoice Tax Number' => 'tax_number',
        'Invoice Tax Liability Date' => 'tax_liability_date',
        'Invoice Date' => 'date',
        'Invoice EORI' => 'eori',
        'Invoice File As' => 'file_as',
        'Invoice Public ID' => 'reference',
    ];

    public static function classify(object $row): array
    {
        $action = $row->Action ?? null;

        return match ($action) {
            'created' => self::classifyCreated($row),
            'deleted' => ['handling' => 'import', 'event' => 'deleted', 'field' => null],
            'associated' => ['handling' => 'skip', 'event' => null, 'field' => null],
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

    protected static function classifyCreated(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/^(Invoice|Refund)\s+(\S+)\s+created$/i', $abstract)) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function extractCreatedValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (!preg_match('/^(Invoice|Refund)\s+(\S+)\s+created$/i', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $data = [];
        if (strtolower($matches[1]) === 'refund') {
            $data['is_refund'] = true;
        }

        return ['old_values' => [], 'new_values' => ['reference' => $matches[2]], 'data' => $data];
    }

    protected static function classifyEdited(object $row): array
    {
        $indirectObject = $row->{'Indirect Object'} ?? null;

        if ($indirectObject === 'Invoice Source Key') {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        if (array_key_exists($indirectObject, self::FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
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
            $plain = HistoryValueExtractor::extractPlainSentence($details);
            if ($plain !== null) {
                $old = $plain['old'];
                $new = $plain['new'];
            }
        }

        if ($old === null && $new === null) {
            $abstract = (string) ($row->{'History Abstract'} ?? '');
            if (preg_match("/Invoice(?:'s| &rArr;) (.+?) was changed to (.+)$/i", trim($abstract), $matches)) {
                $new = trim($matches[2]);
            }
        }

        if ($old === null && $new === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        return [
            'old_values' => [$field => $old ?? ''],
            'new_values' => [$field => $new ?? ''],
            'data' => [],
        ];
    }
}
