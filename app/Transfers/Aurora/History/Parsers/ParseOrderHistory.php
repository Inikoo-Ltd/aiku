<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseOrderHistory
{
    protected const array FIELD_MAP = [
        'Order Delivery Address' => 'delivery_address',
        'Order Invoice Address' => 'invoice_address',
        'Order Sticky Note' => 'notes',
        'Order Delivery Sticky Note' => 'delivery_notes',
        'Order Customer Message' => 'customer_message',
        'Order Tax Number' => 'tax_number',
        'Order Tax Number Valid' => 'tax_number_validation',
        'Order Registration Number' => 'registration_number',
        'Order Customer Purchase Order ID' => 'customer_purchase_order_id',
        'Order Customer Name' => 'customer_name',
        'Order Email' => 'email',
        'Order For Collection' => 'for_collection',
        'Order Submitted by Customer Date' => 'submitted_by_customer_at',
    ];

    protected const array ADDRESS_FIELDS = ['delivery_address', 'invoice_address'];

    protected const array EXPLICIT_SKIP_FIELDS = [
        'Order Packed Done Date',
        'Order Number Items',
        'Order Number Items with Deals',
        'Order Deal Amount Off',
        'Order Site Key',
        'Order Tax Number Validation Date',
    ];

    protected const array STATE_MAP = [
        'order submitted' => 'submitted',
        'order dispatched' => 'dispatched',
        'order invoiced' => 'invoiced',
        'order cancelled' => 'cancelled',
        'order packed and closed' => 'packed',
        'send to warehouse' => 'sent_to_warehouse',
    ];

    public static function classify(object $row): array
    {
        $action = $row->Action ?? null;

        return match ($action) {
            'created' => ['handling' => 'import', 'event' => 'created', 'field' => null],
            'edited' => self::classifyEdited($row),
            default => ['handling' => 'skip', 'event' => null, 'field' => null],
        };
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => ['old_values' => [], 'new_values' => [], 'data' => []],
            'state_change' => self::extractStateChangeValues($row),
            'item_added', 'item_removed' => self::extractItemValues($row),
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

        if (str_contains($indirectObject, 'Payment')) {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        if (in_array($indirectObject, self::EXPLICIT_SKIP_FIELDS, true)) {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        if (array_key_exists($indirectObject, self::FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyAbstract(string $abstract): array
    {
        $stateToken = self::matchState($abstract);
        if ($stateToken !== null) {
            return ['handling' => 'import', 'event' => 'state_change', 'field' => null];
        }

        $itemChange = self::matchItemChange($abstract);
        if ($itemChange !== null) {
            return ['handling' => 'import', 'event' => $itemChange['action'] === 'added' ? 'item_added' : 'item_removed', 'field' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function matchState(string $abstract): ?string
    {
        foreach (self::STATE_MAP as $needle => $token) {
            if (stripos($abstract, $needle) !== false) {
                return $token;
            }
        }

        return null;
    }

    protected static function matchItemChange(string $abstract): ?array
    {
        if (preg_match('/(\d+)?\s*<span[^>]*onclick="change_view\(\'products\/\d*\/(\d+)\'\)"[^>]*>([^<]+)<\/span>\s*(added|removed)/i', $abstract, $matches)) {
            return [
                'quantity' => $matches[1] !== '' ? $matches[1] : null,
                'product_source_id' => $matches[2],
                'product_code' => trim($matches[3]),
                'action' => strtolower($matches[4]),
            ];
        }

        return null;
    }

    protected static function extractStateChangeValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
        $state = self::matchState($abstract);

        $data = ['source_abstract' => $abstract];
        if (stripos($abstract, 'updated by customer') !== false) {
            $data['by_customer'] = true;
        }

        return [
            'old_values' => [],
            'new_values' => $state === null ? [] : ['state' => $state],
            'data' => $data,
        ];
    }

    protected static function extractItemValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
        $match = self::matchItemChange($abstract);

        $data = [];
        if ($match !== null) {
            $data['product_source_id'] = $match['product_source_id'];
            $data['product_code'] = $match['product_code'];
            $data['quantity'] = $match['quantity'];
        }

        if (stripos($abstract, 'updated by customer') !== false) {
            $data['by_customer'] = true;
        }

        return ['old_values' => [], 'new_values' => [], 'data' => $data];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        $details = (string) ($row->{'History Details'} ?? '');
        $table = HistoryValueExtractor::extractTable($details);

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
            if (preg_match("/Order(?:'s| &rArr;) (.+?) was changed to (.+)$/i", trim($abstract), $matches)) {
                $new = trim($matches[2]);
            }
        }

        if ($old === null && $new === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $oldValues = [];
        $newValues = [];

        if ($field !== null) {
            if (in_array($field, self::ADDRESS_FIELDS, true)) {
                $oldValues[$field] = $old === null ? '' : (HistoryValueExtractor::parseAdrAddress($old) ?? $old);
                $newValues[$field] = $new === null ? '' : (HistoryValueExtractor::parseAdrAddress($new) ?? $new);
            } else {
                $oldValues[$field] = $old ?? '';
                $newValues[$field] = $new ?? '';
            }
        }

        return ['old_values' => $oldValues, 'new_values' => $newValues, 'data' => []];
    }
}
