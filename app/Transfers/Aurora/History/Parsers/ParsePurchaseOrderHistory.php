<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParsePurchaseOrderHistory
{
    protected const array FIELD_MAP = [
        'Purchase Order Public ID' => 'reference',
        'Purchase Order Estimated Receiving Date' => 'estimated_receiving_date',
        'Purchase Order Estimated Production Date' => 'estimated_production_date',
        'Purchase Order Estimated Start Production Date' => 'estimated_start_production_date',
    ];

    protected const array STATE_MAP = [
        'purchase order submitted' => 'submitted',
        'purchase order confirmed by supplier' => 'confirmed',
        'purchase order confirmed' => 'confirmed',
        'purchase order cancelled' => 'cancelled',
        'cancel confirmation' => 'cancel_confirmation',
        'send back to planing' => 'back_to_planning',
        'send back to process' => 'back_to_process',
        'sent back to queue' => 'back_to_queue',
        'job order start manufacturing' => 'manufacturing',
        'job order manufactured' => 'manufactured',
        'job order quality control done' => 'quality_control_done',
        'job order set back as manufacturing' => 'back_to_manufacturing',
        'quality control for job order cancelled' => 'quality_control_cancelled',
    ];

    public static function classify(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;
        $action       = $row->Action ?? null;

        if ($directObject === 'Agent Supplier Purchase Order') {
            return self::classifyAgentSupplierPurchaseOrder($row, $action);
        }

        return match ($action) {
            'created' => self::classifyCreated($row),
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

    protected static function classifyAgentSupplierPurchaseOrder(object $row, ?string $action): array
    {
        if ($action !== 'created') {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (strcasecmp($abstract, 'Agent supplier purchase order created') === 0) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyCreated(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (self::matchState($abstract) !== null) {
            return ['handling' => 'import', 'event' => 'state_change', 'field' => null];
        }

        return ['handling' => 'import', 'event' => 'created', 'field' => null];
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

        if (preg_match('/^verejn.? Id$/ui', (string) $indirectObject)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => 'reference'];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyAbstract(string $abstract): array
    {
        if (stripos($abstract, 'purchase order created') !== false) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

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

        if ($old === null && $new === null) {
            $abstract = (string) ($row->{'History Abstract'} ?? '');
            $abstract = trim($abstract);

            if (preg_match("/Purchase Order's (.+?) was changed to (.+)$/i", $abstract, $matches)) {
                $new = trim($matches[2]);
            } elseif (preg_match('/Purchase Order.*?set as (.+)$/i', $abstract, $matches)) {
                $new = trim($matches[1]);
            } elseif (preg_match('/Purchase Order\s*(?:&rArr;|⇒)\s*(.+?)\s*(?:&rArr;|⇒)\s*(.+)$/iu', $abstract, $matches)) {
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
