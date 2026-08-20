<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseSupplierPartHistory
{
    protected const array FIELD_MAP = [
        'Supplier Part Unit Cost' => 'cost',
        'Supplier Part On Demand' => 'on_demand',
        'Supplier Part Fresh' => 'fresh',
        'Supplier Part Packages Per Carton' => 'packages_per_carton',
        'Supplier Part Description' => 'description',
        'Supplier Part Unit Extra Cost Percentage' => 'extra_cost_percentage',
        'Supplier Part Reference' => 'supplier_reference',
        'Supplier Part Carton Barcode' => 'carton_barcode',
        'Supplier Part Currency Code' => 'currency',
        'Supplier Part Status' => 'status',
        'Supplier Part Sticky Note' => 'notes',
        'Supplier Part Minimum Carton Order' => 'minimum_carton_order',
        'Supplier Part Unit Expense' => 'expense',
        'Supplier Part Carton CBM' => 'carton_cbm',
        'Supplier Part Average Delivery Days' => 'delivery_days',
    ];

    protected const array COST_FIELDS = ['cost', 'expense'];

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
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function classifyEdited(object $row): array
    {
        $indirectObject = $row->{'Indirect Object'} ?? null;

        if ($indirectObject === null || $indirectObject === '') {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        if (array_key_exists($indirectObject, self::FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
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
            $new = self::extractFromAbstract($row);
        }

        $data = [];

        if ($field !== null && in_array($field, self::COST_FIELDS, true)) {
            [$old, $oldData] = self::postProcessCost($field, $old);
            [$new, $newData] = self::postProcessCost($field, $new);
            $data = array_merge($data, $oldData, $newData);
        }

        if ($old === null && $new === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $oldValues = [];
        $newValues = [];

        if ($field !== null) {
            $oldValues[$field] = $old ?? '';
            $newValues[$field] = $new ?? '';
        }

        return ['old_values' => $oldValues, 'new_values' => $newValues, 'data' => $data];
    }

    protected static function extractFromAbstract(object $row): ?string
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
        $abstract = preg_replace('/\s+/', ' ', $abstract);

        if (preg_match("/^Supplier Part's .+ was changed to (.+)$/", $abstract, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match("/^Supplier Part's .+ set as (.+)$/", $abstract, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^Supplier Part (?:&rArr;|⇒) .+ was changed to (.+)$/u', $abstract, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected static function postProcessCost(string $field, ?string $value): array
    {
        if ($value === null) {
            return [null, []];
        }

        $data  = [];
        $value = preg_replace('/<span[^>]*class="[^"]*discreet[^"]*"[^>]*>.*?<\/span>/is', '', $value);

        if (preg_match('/\(([^)]*per carton)\)/i', $value, $matches)) {
            $perCarton = preg_replace('/\s*per carton$/i', '', trim($matches[1]));
            $data[$field.'_per_carton'] = self::extractNumeric($perCarton);
        }

        return [self::extractNumeric($value), $data];
    }

    protected static function extractNumeric(string $value): ?string
    {
        $value = str_replace(',', '', $value);

        if (preg_match('/([\d]+(?:\.\d+)?)/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
