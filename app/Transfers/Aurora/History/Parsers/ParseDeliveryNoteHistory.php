<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseDeliveryNoteHistory
{
    protected const array STATE_MAP = [
        'approved for dispatch' => 'approved',
        'packed and closed' => 'packed',
        'un dispatched' => 'undispatched',
        'dispatched' => 'dispatched',
        'cancelled' => 'cancelled',
        'opened' => 'opened',
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

        if ($indirectObject === 'Delivery Note Address') {
            return ['handling' => 'import', 'event' => 'updated', 'field' => 'delivery_address'];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyAbstract(string $abstract): array
    {
        if (self::matchState($abstract) === null) {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        return ['handling' => 'import', 'event' => 'state_change', 'field' => null];
    }

    protected static function matchState(string $abstract): ?string
    {
        if (preg_match('/^replacement note$/i', trim($abstract))) {
            return null;
        }

        foreach (self::STATE_MAP as $needle => $token) {
            if (stripos($abstract, $needle) !== false) {
                return $token;
            }
        }

        return null;
    }

    protected static function extractStateChangeValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
        $state = self::matchState($abstract);

        $data = ['source_abstract' => $abstract];

        if (stripos($abstract, 'replacement note') !== false) {
            $data['replacement'] = true;
        }

        if ($state === 'undispatched' && preg_match('/un dispatched\.\s*(.+)$/i', $abstract, $matches)) {
            $reason = trim($matches[1]);
            if ($reason !== '') {
                $data['reason'] = $reason;
            }
        }

        return [
            'old_values' => [],
            'new_values' => $state === null ? [] : ['state' => $state],
            'data' => $data,
        ];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        $details = (string) ($row->{'History Details'} ?? '');
        $table = HistoryValueExtractor::extractTable($details);

        $old = $table['old'];
        $new = $table['new'];

        if ($old === null && $new === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $oldValues = [];
        $newValues = [];

        if ($field !== null) {
            $oldValues[$field] = $old === null ? '' : (HistoryValueExtractor::parseAdrAddress($old) ?? $old);
            $newValues[$field] = $new === null ? '' : (HistoryValueExtractor::parseAdrAddress($new) ?? $new);
        }

        return ['old_values' => $oldValues, 'new_values' => $newValues, 'data' => []];
    }
}
