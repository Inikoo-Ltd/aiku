<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseDealHistory
{
    protected const array AUDITABLE_TYPE_MAP = [
        'Deal' => 'Offer',
        'Deal Component' => 'OfferComponent',
    ];

    protected const array DEAL_FIELD_MAP = [
        'Deal Name Label' => 'name',
        'Deal Term Label' => 'terms_label',
        'Deal Allowance Label' => 'allowance_label',
        'Deal Status' => 'status',
        'Deal Begin Date' => 'begin_date',
        'Deal End Date' => 'end_date',
        'Deal Expiration Date' => 'expiration_date',
    ];

    protected const array DEAL_COMPONENT_FIELD_MAP = [
        'Deal Component Expiration Date' => 'expiration_date',
    ];

    public static function classify(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;

        if (!array_key_exists($directObject, self::AUDITABLE_TYPE_MAP)) {
            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        $auditableType = self::AUDITABLE_TYPE_MAP[$directObject];
        $action        = $row->Action ?? null;

        if ($action === 'created') {
            return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => $auditableType];
        }

        if ($action === 'edited') {
            $indirectObject = $row->{'Indirect Object'} ?? null;
            $fieldMap       = $directObject === 'Deal' ? self::DEAL_FIELD_MAP : self::DEAL_COMPONENT_FIELD_MAP;

            if (array_key_exists($indirectObject, $fieldMap)) {
                return [
                    'handling' => 'import',
                    'event' => 'updated',
                    'field' => $fieldMap[$indirectObject],
                    'auditable_type' => $auditableType,
                ];
            }

            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => self::extractCreatedValues($row),
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function extractCreatedValues(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;
        $abstract     = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if ($directObject === 'Deal Component') {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        if (!preg_match('/^Offer\s+(.*?)\s*\(/', $abstract, $nameMatch)) {
            if (preg_match('/^Offer\s+(.*?)\s+created/', $abstract, $nameMatch)) {
                return ['old_values' => [], 'new_values' => ['name' => trim($nameMatch[1])], 'data' => []];
            }

            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $newValues = ['name' => trim($nameMatch[1])];
        $data      = [];

        if (preg_match('/<span[^>]*class="term"[^>]*>(.*?)<\/span>/is', $abstract, $termMatch)) {
            $data['term'] = trim(strip_tags($termMatch[1]));
        }

        if (preg_match('/<span[^>]*class="allowance"[^>]*>(.*?)<\/span>/is', $abstract, $allowanceMatch)) {
            $data['allowance'] = trim(strip_tags($allowanceMatch[1]));
        }

        if (preg_match('/voucher:\s*(.*?)\s*(?:<|\(|$)/i', $abstract, $voucherMatch)) {
            $data['voucher'] = trim($voucherMatch[1]);
        }

        return ['old_values' => [], 'new_values' => $newValues, 'data' => $data];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        if ($field === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        if ($field === 'expiration_date' && ($row->{'Direct Object'} ?? null) === 'Deal Component') {
            $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

            if (preg_match('/set as\s*(.*)$/u', $abstract, $matches)) {
                return ['old_values' => [], 'new_values' => [$field => trim($matches[1])], 'data' => []];
            }

            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $details = (string) ($row->{'History Details'} ?? '');
        $table   = HistoryValueExtractor::extractTable($details);

        $old = $table['old'];
        $new = $table['new'];

        if ($old === null && $new === null) {
            $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));
            $new      = self::extractAbstractNewValue($abstract);

            if ($new === null) {
                return ['old_values' => [], 'new_values' => [], 'data' => []];
            }

            return ['old_values' => [], 'new_values' => [$field => $new], 'data' => []];
        }

        return [
            'old_values' => [$field => $old ?? ''],
            'new_values' => [$field => $new ?? ''],
            'data' => [],
        ];
    }

    protected static function extractAbstractNewValue(string $abstract): ?string
    {
        if (preg_match('/public name was changed to\s*(.*)$/u', $abstract, $matches)) {
            return HistoryValueExtractor::normalize(strip_tags($matches[1]));
        }

        if (preg_match('/public terms info was changed to\s*(.*)$/u', $abstract, $matches)) {
            return HistoryValueExtractor::normalize(strip_tags($matches[1]));
        }

        if (preg_match('/(?:&rArr;|⇒)\s*.*?was changed to\s*(.*)$/u', $abstract, $matches)) {
            return HistoryValueExtractor::normalize(strip_tags($matches[1]));
        }

        if (preg_match('/was changed to\s*(.*)$/u', $abstract, $matches)) {
            return HistoryValueExtractor::normalize(strip_tags($matches[1]));
        }

        if (preg_match('/bolo zmenen.\s*na(.*)$/u', $abstract, $matches)) {
            return HistoryValueExtractor::normalize(strip_tags($matches[1]));
        }

        return null;
    }
}
