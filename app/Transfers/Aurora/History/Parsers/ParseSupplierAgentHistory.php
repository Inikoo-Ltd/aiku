<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseSupplierAgentHistory
{
    protected const array SUPPLIER_FIELD_MAP = [
        'Supplier Name' => 'name',
        'Supplier Company Name' => 'company_name',
        'Supplier Nickname' => 'nickname',
        'Supplier Code' => 'code',
        'Supplier Main Plain Email' => 'email',
        'Supplier Other Email' => 'other_email',
        'Supplier Website' => 'website',
        'Supplier Contact Address' => 'address',
        'Supplier Main Contact Name' => 'contact_name',
        'Supplier Preferred Contact Number Formatted Number' => 'phone',
        'Supplier Preferred Contact Number' => 'phone_raw',
    ];

    protected const array ORG_SUPPLIER_FIELD_MAP = [
        'Supplier Default Currency Code' => 'currency',
        'Supplier Default Incoterm' => 'incoterm',
        'Supplier Default Port of Export' => 'port_of_export',
        'Supplier Default Port of Import' => 'port_of_import',
        'Supplier Products Origin Country Code' => 'origin_country_code',
        'Supplier Account Number' => 'account_number',
        'Supplier Average Delivery Days' => 'delivery_days',
        'Supplier Average Production Days' => 'production_days',
        'Supplier QQ' => 'qq',
        'Supplier On Demand' => 'on_demand',
        'Supplier Sticky Note' => 'notes',
        'Supplier Order Public ID Format' => 'order_reference_format',
        'Supplier Order Last Order ID' => 'last_order_reference',
        'Supplier Default PO Terms and Conditions' => 'po_terms_and_conditions',
    ];

    protected const array AGENT_FIELD_MAP = [
        'Agent Company Name' => 'company_name',
        'Agent Main Contact Name' => 'contact_name',
        'Agent Main Plain Email' => 'email',
        'Agent Code' => 'code',
        'Agent Contact Address' => 'address',
        'Agent Preferred Contact Number Formatted Number' => 'phone',
    ];

    protected const array ORG_AGENT_FIELD_MAP = [
        'Agent Default Currency Code' => 'currency',
        'Agent Default Incoterm' => 'incoterm',
        'Agent Default Port of Export' => 'port_of_export',
        'Agent Default Port of Import' => 'port_of_import',
        'Agent Average Delivery Days' => 'delivery_days',
        'Agent Order Public ID Format' => 'order_reference_format',
        'Agent Order Last Order ID' => 'last_order_reference',
        'Agent Products Origin Country Code' => 'origin_country_code',
    ];

    protected const array ADDRESS_FIELDS = ['address'];

    public static function classify(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;

        if (!in_array($directObject, ['Supplier', 'Agent'], true)) {
            return self::skip();
        }

        $indirectObject = HistoryValueExtractor::normalize($row->{'Indirect Object'} ?? null);

        if ($indirectObject !== null && preg_match('/Acc Parts/i', $indirectObject)) {
            return self::skip();
        }

        if ($indirectObject === 'Category Supplier') {
            return self::skip();
        }

        $action = $row->Action ?? null;

        if ($action === 'created') {
            return self::classifyCreated($row, $directObject);
        }

        if ($action === 'deleted') {
            return [
                'handling' => 'import',
                'event' => 'deleted',
                'field' => null,
                'auditable_type' => $directObject === 'Supplier' ? 'OrgSupplier' : 'OrgAgent',
            ];
        }

        if ($action === 'edited' || $action === '') {
            return self::classifyEdited($directObject, $indirectObject);
        }

        return self::skip();
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => self::extractCreatedValues($row),
            'deleted' => self::extractDeletedValues($row),
            'updated' => self::extractUpdatedValues($row, $field),
            default => self::empty(),
        };
    }

    protected static function classifyCreated(object $row, string $directObject): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if ($directObject === 'Supplier') {
            if ($abstract === 'Supplier created' || preg_match('/^New supplier "(.+)" added$/i', $abstract)) {
                return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'Supplier'];
            }

            return self::skip();
        }

        if (preg_match('/^Agent (.+) created$/i', $abstract)) {
            return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'Agent'];
        }

        return self::skip();
    }

    protected static function classifyEdited(string $directObject, ?string $indirectObject): array
    {
        if ($indirectObject === null) {
            return self::skip();
        }

        $groupMap = $directObject === 'Supplier' ? self::SUPPLIER_FIELD_MAP : self::AGENT_FIELD_MAP;
        $orgMap   = $directObject === 'Supplier' ? self::ORG_SUPPLIER_FIELD_MAP : self::ORG_AGENT_FIELD_MAP;

        if (array_key_exists($indirectObject, $groupMap)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => $groupMap[$indirectObject], 'auditable_type' => $directObject];
        }

        if (array_key_exists($indirectObject, $orgMap)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => $orgMap[$indirectObject], 'auditable_type' => 'Org'.$directObject];
        }

        return self::skip();
    }

    protected static function extractCreatedValues(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;
        $abstract     = trim((string) ($row->{'History Abstract'} ?? ''));

        if ($directObject === 'Supplier' && preg_match('/^New supplier "(.+)" added$/i', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => ['name' => trim($matches[1])], 'data' => []];
        }

        if ($directObject === 'Agent' && preg_match('/^Agent (.+) created$/i', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => ['name' => trim($matches[1])], 'data' => []];
        }

        return self::empty();
    }

    protected static function extractDeletedValues(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;
        $abstract     = trim((string) ($row->{'History Abstract'} ?? ''));

        $pattern = $directObject === 'Supplier'
            ? '/^Supplier record (.+) deleted$/i'
            : '/^Agent record (.+) deleted$/i';

        if (preg_match($pattern, $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [], 'data' => ['name' => trim($matches[1])]];
        }

        return self::empty();
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        if ($field === null) {
            return self::empty();
        }

        $details = (string) ($row->{'History Details'} ?? '');

        $legacy = self::extractLegacyAddress($details);

        if ($legacy !== null) {
            return [
                'old_values' => [$field => $legacy['old']],
                'new_values' => [$field => $legacy['new']],
                'data' => [],
            ];
        }

        $table = HistoryValueExtractor::extractTable($details);
        $old   = $table['old'];
        $new   = $table['new'];

        if ($old === null && $new === null) {
            $sentence = HistoryValueExtractor::extractPlainSentence($details);

            if ($sentence !== null) {
                $old = $sentence['old'];
                $new = $sentence['new'];
            }
        }

        if ($old !== null || $new !== null) {
            return [
                'old_values' => [$field => $old === null ? '' : self::resolveValue($field, $old)],
                'new_values' => [$field => $new === null ? '' : self::resolveValue($field, $new)],
                'data' => [],
            ];
        }

        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));
        $raw      = self::extractAbstractRaw($abstract);

        if ($raw === null) {
            return self::empty();
        }

        return [
            'old_values' => [],
            'new_values' => [$field => self::resolveValue($field, $raw)],
            'data' => [],
        ];
    }

    protected static function extractLegacyAddress(string $details): ?array
    {
        if (!preg_match(
            '/Address changed from\s*(<div class="history_address".*?<\/div>)\s*to\s*(<div class="history_address".*?<\/div>)/is',
            $details,
            $matches
        )) {
            return null;
        }

        return ['old' => trim($matches[1]), 'new' => trim($matches[2])];
    }

    protected static function extractAbstractRaw(string $abstract): ?string
    {
        if (preg_match('/(?:&rArr;|⇒).*?(?:was changed to|set as)\s*(.*)$/u', $abstract, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(?:was changed to|set as)\s*(.*)$/u', $abstract, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected static function resolveValue(string $field, string $value): string|array
    {
        if (in_array($field, self::ADDRESS_FIELDS, true)) {
            return HistoryValueExtractor::parseAdrAddress($value) ?? (HistoryValueExtractor::normalize(strip_tags($value)) ?? '');
        }

        return HistoryValueExtractor::normalize(strip_tags($value)) ?? '';
    }

    protected static function skip(): array
    {
        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    protected static function empty(): array
    {
        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }
}
