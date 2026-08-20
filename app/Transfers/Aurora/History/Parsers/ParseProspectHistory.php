<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseProspectHistory
{
    protected const array FIELD_MAP = [
        'Prospect Main Plain Email' => 'email',
        'Prospect Main Contact Name' => 'contact_name',
        'Prospect Company Name' => 'company_name',
        'Prospect Preferred Contact Number' => 'phone_raw',
        'Prospect Preferred Contact Number Formatted Number' => 'phone',
        'Prospect Contact Address' => 'address',
        'Prospect Website' => 'contact_website',
        'Prospect Sticky Note' => 'note',
    ];

    public static function classify(object $row): array
    {
        $action = $row->Action ?? null;

        return match ($action) {
            'created' => self::classifyCreated($row),
            'edited' => self::classifyEdited($row),
            default => ['handling' => 'skip', 'event' => null, 'field' => null],
        };
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => self::extractCreatedValues($row),
            'registered' => self::extractRegisteredValues($row),
            'opted_out' => self::extractOptedOutValues($row),
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function classifyCreated(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/prospect record created/', $abstract)) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

        if (preg_match('/^Bol vytvoren.*z[aá]znam o perspekt[ií]ve/u', $abstract)) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

        if (preg_match('/^Byl vytvo/u', $abstract)) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

        if (preg_match('/kil.t.srekord l.trehozva/u', $abstract)) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyEdited(object $row): array
    {
        $indirectObject = $row->{'Indirect Object'} ?? null;
        $abstract       = trim((string) ($row->{'History Abstract'} ?? ''));

        if ($indirectObject === null || $indirectObject === '') {
            return self::classifyActivityStream($abstract);
        }

        if (array_key_exists($indirectObject, self::FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyActivityStream(string $abstract): array
    {
        if (preg_match('/registered as a customer/', $abstract)) {
            return ['handling' => 'import', 'event' => 'registered', 'field' => null];
        }

        if (preg_match('/Recipient opt out again/', $abstract)) {
            return ['handling' => 'import', 'event' => 'opted_out', 'field' => null];
        }

        if (preg_match('/Recipient opt out/', $abstract)) {
            return ['handling' => 'import', 'event' => 'opted_out', 'field' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function extractCreatedValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
        $data     = [];

        if (preg_match('/change_view\(\'upload\/(\d+)/', $abstract, $matches)) {
            $data['upload_source_id'] = (int) $matches[1];
        }

        $abstract = preg_replace('/<span.*$/s', '', $abstract);
        $abstract = trim($abstract);

        if (preg_match('/^(.*?)\s*prospect record created/', $abstract, $matches)) {
            $name = trim($matches[1]);

            return ['old_values' => [], 'new_values' => $name === '' ? [] : ['name' => $name], 'data' => $data];
        }

        if (preg_match('/^Bol vytvoren.*z[aá]znam o perspekt[ií]ve(.*)$/u', $abstract, $matches)) {
            $company = trim($matches[1]);
            $newValues = [];
            if ($company !== '') {
                $newValues['company_name'] = $company;
            }

            return ['old_values' => [], 'new_values' => $newValues, 'data' => $data];
        }

        if (preg_match('/^Byl vytvo.*z[aá]znam o perspekt[ií]ve(.*)$/u', $abstract, $matches)) {
            $company = trim($matches[1]);
            $newValues = [];
            if ($company !== '') {
                $newValues['company_name'] = $company;
            }

            return ['old_values' => [], 'new_values' => $newValues, 'data' => $data];
        }

        if (preg_match('/(.+) kil.t.srekord l.trehozva$/u', $abstract, $matches)) {
            $name = trim($matches[1]);

            return ['old_values' => [], 'new_values' => $name === '' ? [] : ['name' => $name], 'data' => $data];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => $data];
    }

    protected static function extractRegisteredValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
        $data     = [];

        if (preg_match('/change_view\(\'customers\/\d*\/(\d+)/', $abstract, $matches)) {
            $data['customer_source_id'] = (int) $matches[1];
        }

        if (preg_match('/\(([^()]+)\)\s*$/', $abstract, $matches)) {
            $data['customer_reference'] = trim($matches[1]);
        }

        return ['old_values' => [], 'new_values' => [], 'data' => $data];
    }

    protected static function extractOptedOutValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        $data = [];
        if (preg_match('/Recipient opt out again/', $abstract)) {
            $data['repeat'] = true;
        }

        return ['old_values' => [], 'new_values' => [], 'data' => $data];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        if ($field === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $details = (string) ($row->{'History Details'} ?? '');
        $table   = HistoryValueExtractor::extractTable($details);

        if ($table['old'] !== null || $table['new'] !== null) {
            $old = $table['old'];
            $new = $table['new'] ?? '';

            if ($field === 'address') {
                $old = $old === null ? null : (HistoryValueExtractor::parseAdrAddress($old) ?? $old);
                $new = HistoryValueExtractor::parseAdrAddress($new) ?? $new;
            }

            return [
                'old_values' => $old === null ? [] : [$field => $old],
                'new_values' => [$field => $new],
                'data' => [],
            ];
        }

        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/was deleted$/', $abstract) || preg_match('/byl smazán$/u', $abstract)) {
            return ['old_values' => [$field => ''], 'new_values' => [$field => ''], 'data' => []];
        }

        if (preg_match('/was changed to (.+)$/', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [$field => trim($matches[1])], 'data' => []];
        }

        if (preg_match('/bolo zmenené na(.+)$/u', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [$field => trim($matches[1])], 'data' => []];
        }

        if (preg_match('/set as (.+)$/', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [$field => trim($matches[1])], 'data' => []];
        }

        if (preg_match('/nastavit (.+)$/u', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [$field => trim($matches[1])], 'data' => []];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }
}
