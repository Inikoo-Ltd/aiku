<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseCategoryHistory
{
    protected const array CATEGORY_FIELD_MAP = [
        'Category Code'                              => 'code',
        'Category Label'                              => 'name',
        'Part Category Status Including Parts'        => 'state',
        'Part Category Status'                         => 'state',
        'Category Sticky Note'                          => 'notes',
        'Product Category Description'                  => 'description',
    ];

    protected const array FAMILY_FIELD_MAP = [
        'Product Family Description'          => 'description',
        'Product Family Name'                 => 'name',
        'Product Family Code'                 => 'code',
        'Product Family Special Characteristic' => 'special_characteristic',
        'Family Department'                    => 'department',
        'Product Family Sticky Note'            => 'notes',
    ];

    protected const array DEPARTMENT_FIELD_MAP = [
        'Product Department Description' => 'description',
        'Product Department Name'        => 'name',
        'Product Department Code'        => 'code',
    ];

    public static function classify(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;

        return match ($directObject) {
            'Category'   => self::classifyCategory($row),
            'Family'     => self::classifyFamily($row),
            'Department' => self::classifyDepartment($row),
            default      => ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null],
        };
    }

    public static function extractValues(object $row, string $event, ?string $field, ?string $auditableType = null): array
    {
        if ($event === 'created') {
            return match ($auditableType) {
                'ProductCategory', null => self::extractCategoryOrFamilyOrDepartmentCreatedValues($row),
                default => ['old_values' => [], 'new_values' => [], 'data' => []],
            };
        }

        if ($event !== 'updated') {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        return self::extractUpdatedValues($row, $field);
    }

    protected static function classifyCategory(object $row): array
    {
        $scope   = $row->aikuCategoryScope ?? null;
        $subject = $row->aikuCategorySubject ?? null;

        if ($scope === null) {
            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        $auditableType = match (true) {
            $scope === 'Product' && $subject === 'Category' => 'ProductCategory',
            $scope === 'Part' && $subject === 'Part'         => 'StockFamily',
            default                                          => null,
        };

        if ($auditableType === null) {
            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        $action   = $row->Action ?? null;
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if ($action === 'created') {
            return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => $auditableType];
        }

        if ($action === 'edited' || $action === null || $action === '') {
            $indirectObject = $row->{'Indirect Object'} ?? null;

            if (array_key_exists((string) $indirectObject, self::CATEGORY_FIELD_MAP)) {
                return [
                    'handling'       => 'import',
                    'event'          => 'updated',
                    'field'          => self::CATEGORY_FIELD_MAP[$indirectObject],
                    'auditable_type' => $auditableType,
                ];
            }

            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    protected static function classifyFamily(object $row): array
    {
        $action = $row->Action ?? null;

        if ($action === 'created') {
            return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'ProductCategory'];
        }

        if ($action === 'edited' || $action === null || $action === '') {
            $indirectObject = $row->{'Indirect Object'} ?? null;

            if (array_key_exists((string) $indirectObject, self::FAMILY_FIELD_MAP)) {
                return [
                    'handling'       => 'import',
                    'event'          => 'updated',
                    'field'          => self::FAMILY_FIELD_MAP[$indirectObject],
                    'auditable_type' => 'ProductCategory',
                ];
            }
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    protected static function classifyDepartment(object $row): array
    {
        $action = $row->Action ?? null;

        if ($action === 'created') {
            return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'ProductCategory'];
        }

        if ($action === 'edited' || $action === null || $action === '') {
            $indirectObject = $row->{'Indirect Object'} ?? null;

            if (array_key_exists((string) $indirectObject, self::DEPARTMENT_FIELD_MAP)) {
                return [
                    'handling'       => 'import',
                    'event'          => 'updated',
                    'field'          => self::DEPARTMENT_FIELD_MAP[$indirectObject],
                    'auditable_type' => 'ProductCategory',
                ];
            }
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    protected static function extractCategoryOrFamilyOrDepartmentCreatedValues(object $row): array
    {
        $details = trim((string) ($row->{'History Details'} ?? ''));

        if (preg_match('/Family (.+?) \(([^)]+)\) Created/i', $details, $matches)) {
            return ['old_values' => [], 'new_values' => ['name' => trim($matches[1]), 'code' => trim($matches[2])], 'data' => []];
        }

        if (preg_match('/Department (.+?) \(([^)]+)\) Created/i', $details, $matches)) {
            return ['old_values' => [], 'new_values' => ['name' => trim($matches[1]), 'code' => trim($matches[2])], 'data' => []];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        if ($field === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $details = (string) ($row->{'History Details'} ?? '');
        $table   = HistoryValueExtractor::extractTable($details);

        if ($table['old'] !== null || $table['new'] !== null) {
            return [
                'old_values' => [$field => $table['old'] ?? ''],
                'new_values' => [$field => $table['new'] ?? ''],
                'data'       => [],
            ];
        }

        $plain = HistoryValueExtractor::extractPlainSentence($details);

        if ($plain !== null) {
            return [
                'old_values' => [$field => $plain['old'] ?? ''],
                'new_values' => [$field => $plain['new'] ?? ''],
                'data'       => [],
            ];
        }

        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        $sentence = self::matchAbstractEras($abstract);

        if ($sentence === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        if ($sentence['old'] === null) {
            return ['old_values' => [], 'new_values' => [$field => $sentence['new']], 'data' => []];
        }

        return ['old_values' => [$field => $sentence['old']], 'new_values' => [$field => $sentence['new']], 'data' => []];
    }

    protected static function matchAbstractEras(string $abstract): ?array
    {
        if (preg_match('/Category .+? changed \((.*?)(?:→|&rarr;)(.*?)\)/is', $abstract, $matches)) {
            return ['old' => HistoryValueExtractor::normalize($matches[1]), 'new' => HistoryValueExtractor::normalize($matches[2])];
        }

        if (preg_match('/Category\'s .+? was changed to (.+)$/i', $abstract, $matches)) {
            return ['old' => null, 'new' => HistoryValueExtractor::normalize($matches[1])];
        }

        if (preg_match('/Category\s*(?:&rArr;|⇒)\s{1,2}.+? was changed to (.+)$/iu', $abstract, $matches)) {
            return ['old' => null, 'new' => HistoryValueExtractor::normalize($matches[1])];
        }

        return null;
    }
}
