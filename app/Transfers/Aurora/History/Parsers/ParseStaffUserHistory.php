<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseStaffUserHistory
{
    protected const array STAFF_FIELD_MAP = [
        'Staff Name' => 'name',
        'Staff Alias' => 'alias',
        'Staff Email' => 'email',
        'Staff Address' => 'address',
        'Staff Birthday' => 'date_of_birth',
        'Staff Next of Kind' => 'emergency_contact',
        'Staff Official ID' => 'official_id',
        'Staff ID' => 'identity_document',
        'Staff Job Title' => 'job_title',
        'Staff Position' => 'position',
        'Staff Type' => 'type',
        'Staff Salary' => 'salary',
        'Staff Working Hours' => 'working_hours',
        'Staff Currently Working' => 'currently_working',
        'Staff Valid From' => 'employment_start_at',
        'Staff Valid To' => 'employment_end_at',
        'Staff User Active' => 'user_active',
        'Staff Supervisor' => 'supervisor',
        'Staff User Handle' => 'user_handle',
        'Staff Clocking PIN' => 'clocking_pin',
        'Staff PIN' => 'pin',
        'Staff User Password' => 'password',
    ];

    protected const array USER_FIELD_MAP = [
        'User Password' => 'password',
        'User Active' => 'active',
        'User Handle' => 'handle',
        'User Password Recovery Email' => 'recovery_email',
        'User PIN' => 'pin',
        'User Alias' => 'alias',
        'User Preferred Locale' => 'locale',
    ];

    protected const array WEBSITE_USER_FIELD_MAP = [
        'Website User Handle' => 'email',
        'Website User Password Hash' => 'password',
    ];

    protected const array SCOPE_INDIRECT_OBJECTS = [
        'User Group', 'Group', 'Store', 'Site', 'Website', 'Warehouse', 'Supplier',
    ];

    protected const array DELETED_ABSTRACT_PATTERNS = [
        '/was deleted$/',
        '/bol odstránený$/',
        '/zostało skasowane$/',
        '/wurde gelöscht$/',
        '/foi excluído$/',
        '/fue eliminado$/',
    ];

    public static function classify(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;

        return match ($directObject) {
            'Staff' => self::classifyStaff($row),
            'User' => self::classifyUser($row),
            'Website User' => self::classifyWebsiteUser($row),
            default => ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null],
        };
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => self::extractCreatedValues($row),
            'access_revoked' => self::extractAccessRevokedValues($row),
            'access_granted' => self::extractAccessGrantedValues($row),
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function classifyStaff(object $row): array
    {
        $action = $row->Action ?? null;

        if ($action === 'created') {
            return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'Employee'];
        }

        if ($action === 'deleted') {
            return ['handling' => 'import', 'event' => 'deleted', 'field' => null, 'auditable_type' => 'Employee'];
        }

        $indirectObject = self::normalizeIndirectObject($row->{'Indirect Object'} ?? null);

        if ($indirectObject !== null && array_key_exists($indirectObject, self::STAFF_FIELD_MAP)) {
            return [
                'handling' => 'import',
                'event' => 'updated',
                'field' => self::STAFF_FIELD_MAP[$indirectObject],
                'auditable_type' => 'Employee',
            ];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    protected static function classifyUser(object $row): array
    {
        $action = $row->Action ?? null;

        if ($action === 'created') {
            return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'User'];
        }

        if ($action === 'deleted') {
            return ['handling' => 'import', 'event' => 'deleted', 'field' => null, 'auditable_type' => 'User'];
        }

        $indirectObject = self::normalizeIndirectObject($row->{'Indirect Object'} ?? null);

        if ($action === 'disassociate' && $indirectObject !== null && in_array($indirectObject, self::SCOPE_INDIRECT_OBJECTS, true)) {
            return ['handling' => 'import', 'event' => 'access_revoked', 'field' => null, 'auditable_type' => 'User'];
        }

        if (($action === null || $action === '') && $indirectObject !== null && in_array($indirectObject, self::SCOPE_INDIRECT_OBJECTS, true)) {
            return ['handling' => 'import', 'event' => 'access_granted', 'field' => null, 'auditable_type' => 'User'];
        }

        if ($indirectObject !== null && array_key_exists($indirectObject, self::USER_FIELD_MAP)) {
            return [
                'handling' => 'import',
                'event' => 'updated',
                'field' => self::USER_FIELD_MAP[$indirectObject],
                'auditable_type' => 'User',
            ];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    protected static function classifyWebsiteUser(object $row): array
    {
        $action = $row->Action ?? null;

        if ($action === 'created') {
            return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'WebUser'];
        }

        $indirectObject = self::normalizeIndirectObject($row->{'Indirect Object'} ?? null);

        if ($indirectObject !== null && array_key_exists($indirectObject, self::WEBSITE_USER_FIELD_MAP)) {
            return [
                'handling' => 'import',
                'event' => 'updated',
                'field' => self::WEBSITE_USER_FIELD_MAP[$indirectObject],
                'auditable_type' => 'WebUser',
            ];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    protected static function extractCreatedValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/^Website user (.+) created$/i', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => ['email' => trim($matches[1])], 'data' => []];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }

    protected static function extractAccessRevokedValues(object $row): array
    {
        $indirectObject = (string) ($row->{'Indirect Object'} ?? '');
        $abstract       = strip_tags((string) ($row->{'History Abstract'} ?? ''));

        $scope = null;
        if (preg_match('/(?:disassociated with|removed from)\s+(.+)$/i', trim($abstract), $matches)) {
            $scope = trim($matches[1]) === '' ? null : trim($matches[1]);
        }

        return [
            'old_values' => [],
            'new_values' => [],
            'data' => ['scope_type' => $indirectObject, 'scope' => $scope],
        ];
    }

    protected static function extractAccessGrantedValues(object $row): array
    {
        $indirectObject = (string) ($row->{'Indirect Object'} ?? '');

        return [
            'old_values' => [],
            'new_values' => [],
            'data' => ['scope_type' => $indirectObject, 'scope' => null],
        ];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        if ($field === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $indirectObject = (string) ($row->{'Indirect Object'} ?? '');
        $details        = (string) ($row->{'History Details'} ?? '');
        $abstract       = trim((string) ($row->{'History Abstract'} ?? ''));

        $table = HistoryValueExtractor::extractTable($details);
        $old   = $table['old'];
        $new   = $table['new'];

        if ($old === null && $new === null) {
            $sentence = HistoryValueExtractor::extractPlainSentence($abstract);

            if ($sentence !== null) {
                $old = $sentence['old'];
                $new = $sentence['new'];
            } elseif (preg_match('/was changed to\s+"?(.*?)"?$/i', $abstract, $matches)
                || preg_match('/⇒\s*was changed to\s+"?(.*?)"?$/i', $abstract, $matches)) {
                $new = trim($matches[1]);
            } elseif (self::isDeletedAbstract($abstract)) {
                $old = '';
                $new = '';
            }
        }

        if ($old === null && $new === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $oldValue = HistoryValueExtractor::redactCredential($indirectObject, $old ?? '');
        $newValue = HistoryValueExtractor::redactCredential($indirectObject, $new ?? '');

        return [
            'old_values' => [$field => $oldValue],
            'new_values' => [$field => $newValue],
            'data' => [],
        ];
    }

    protected static function isDeletedAbstract(string $abstract): bool
    {
        foreach (self::DELETED_ABSTRACT_PATTERNS as $pattern) {
            if (preg_match($pattern, $abstract)) {
                return true;
            }
        }

        return false;
    }

    protected static function normalizeIndirectObject(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
