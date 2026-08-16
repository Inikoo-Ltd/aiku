<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseSupplierDeliveryHistory
{
    protected const array FIELD_MAP = [
        'Supplier Delivery Invoice Public ID' => 'invoice_reference',
        'Supplier Delivery Invoice Date' => 'invoice_date',
        'Supplier Delivery Estimated Receiving Date' => 'estimated_receiving_date',
        'Supplier Delivery Dispatched Date' => 'dispatched_date',
        'Supplier Delivery Public ID' => 'reference',
    ];

    protected const array STATE_MAP = [
        'set as placed' => 'placed',
        'set as dispatched' => 'dispatched',
        'set as in process' => 'in_process',
        'set as received' => 'received',
        'set as checked' => 'checked',
        'cancelled' => 'cancelled',
        'setting costs' => 'setting_costs',
        'costing done' => 'costing_done',
        'redoing costing' => 'redoing_costing',
    ];

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
            'created' => ['old_values' => [], 'new_values' => [], 'data' => []],
            'state_change' => self::extractStateChangeValues($row),
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function classifyEdited(object $row): array
    {
        $indirectObject = $row->{'Indirect Object'} ?? null;

        if ($indirectObject === null || $indirectObject === '') {
            return self::classifyAbstract(trim((string) ($row->{'History Abstract'} ?? '')));
        }

        if (array_key_exists($indirectObject, self::FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyAbstract(string $abstract): array
    {
        if (self::matchState($abstract) !== null) {
            return ['handling' => 'import', 'event' => 'state_change', 'field' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function matchState(string $abstract): ?string
    {
        $normalized = strtolower($abstract);

        foreach (self::STATE_MAP as $needle => $token) {
            if (str_contains($normalized, $needle)) {
                return $token;
            }
        }

        return null;
    }

    protected static function extractStateChangeValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
        $state    = self::matchState($abstract);

        return [
            'old_values' => [],
            'new_values' => $state === null ? [] : ['state' => $state],
            'data' => ['source_abstract' => $abstract],
        ];
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

        if ($old === null && $new === null && $field === 'invoice_reference') {
            $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
            if (preg_match("/Supplier Delivery's Invoice number set as (.+)$/i", $abstract, $matches)) {
                $new = trim($matches[1]);
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
