<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseCustomerHistory
{
    protected const array FIELD_MAP = [
        'Customer Send Newsletter' => 'subscribed_to_newsletter',
        'Customer Send Email Marketing' => 'subscribed_to_email_marketing',
        'Customer Send Basket Emails' => 'subscribed_to_basket_emails',
        'Customer Send Postal Marketing' => 'subscribed_to_postal_marketing',
        'Customer Tax Number' => 'tax_number',
        'Customer Tax Number Valid' => 'tax_number_validation',
        'Customer Type by Activity' => 'customer_type',
        'Customer Invoice Address' => 'invoice_address',
        'Customer Delivery Address' => 'delivery_address',
        'Customer Registration Number' => 'registration_number',
        'Customer Company Name' => 'company_name',
        'Customer Preferred Contact Number Formatted Number' => 'phone',
    ];

    protected const array ADDRESS_FIELDS = ['invoice_address', 'delivery_address'];

    protected const array CREATED_PATTERNS = [
        '/^Customer (.+) registered$/',
        '/^(.+) customer record created$/',
        '/^Kunde (.+) erstellt$/',
        '/^Nouveau Client (.+) soi ajoute$/',
        '/^Nouveau Client (.+) ajoute$/',
        '/^Byl vytvořen zákaznický záznam\s?(.+)$/',
        '/^Bol vytvorený zákaznícky záznam\s?(.+)$/',
        '/^Utworzono klienta\s?(.*)$/',
        '/^New customer (.+) added$/',
        '/^New customer (.+) dodano$/',
        '/^New customer (.+)$/',
    ];

    public static function classify(object $row): array
    {
        $action = $row->Action ?? null;

        return match ($action) {
            'created' => self::classifyCreated($row),
            'merged' => self::classifyMerged($row),
            'edited' => self::classifyEdited($row),
            default => ['handling' => 'skip', 'event' => null, 'field' => null],
        };
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => self::extractCreatedValues($row),
            'merged' => self::extractMergedValues($row),
            'unsubscribed' => [
                'old_values' => [],
                'new_values' => [
                    'subscribed_to_newsletter' => 'No',
                    'subscribed_to_email_marketing' => 'No',
                ],
                'data' => ['reason' => 'bounce'],
            ],
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function classifyCreated(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (in_array($abstract, ['Customer Created', 'Compte Client Créé'], true)) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

        foreach (self::CREATED_PATTERNS as $pattern) {
            if (preg_match($pattern, $abstract)) {
                return ['handling' => 'import', 'event' => 'created', 'field' => null];
            }
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyMerged(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/^Customer (\S+) merged$/', $abstract)) {
            return ['handling' => 'import', 'event' => 'merged', 'field' => null];
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

        if (preg_match("/Customer's company name .+ was deleted/", $abstract)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => 'company_name'];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyActivityStream(string $abstract): array
    {
        $patterns = [
            '/^Se anuló la suscripción/',
            '/^Unsubscribed/',
            '/unsubscribed because/',
            '/^Odhlásen/',
            '/^Odhláš/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $abstract)) {
                return ['handling' => 'import', 'event' => 'unsubscribed', 'field' => null];
            }
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function extractCreatedValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (in_array($abstract, ['Customer Created', 'Compte Client Créé'], true)) {
            $details = trim((string) ($row->{'History Details'} ?? ''));
            foreach (self::CREATED_PATTERNS as $pattern) {
                if ($details !== '' && preg_match($pattern, $details, $matches)) {
                    $name = trim($matches[1]);

                    return ['old_values' => [], 'new_values' => $name === '' ? [] : ['name' => $name], 'data' => []];
                }
            }

            return ['old_values' => [], 'new_values' => [], 'data' => ['name_unavailable' => true]];
        }

        foreach (self::CREATED_PATTERNS as $pattern) {
            if (preg_match($pattern, $abstract, $matches)) {
                $name = trim($matches[1]);

                return ['old_values' => [], 'new_values' => $name === '' ? [] : ['name' => $name], 'data' => []];
            }
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }

    protected static function extractMergedValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/^Customer (\S+) merged$/', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [], 'data' => ['merged_ref' => $matches[1]]];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if ($field === 'company_name' && preg_match("/Customer's company name (.+) was deleted/", $abstract, $matches)) {
            return [
                'old_values' => ['company_name' => trim($matches[1])],
                'new_values' => ['company_name' => ''],
                'data' => [],
            ];
        }

        $details = (string) ($row->{'History Details'} ?? '');
        $table   = HistoryValueExtractor::extractTable($details);

        $oldValues = [];
        $newValues = [];

        if ($field !== null) {
            $old = $table['old'];
            $new = $table['new'];

            if ($old === null && $new === null) {
                return ['old_values' => [], 'new_values' => [], 'data' => []];
            }

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
