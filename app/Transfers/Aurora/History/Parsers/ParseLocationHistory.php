<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseLocationHistory
{
    protected const array LOCATION_FIELD_MAP = [
        'Location Code' => 'code',
        'Location Max Weight' => 'max_weight',
        'Location Max Volume' => 'max_volume',
        'Location Sticky Note' => 'notes',
        'Location Fulfilment' => 'is_fulfilment',
        'Location Warehouse Flag Key' => 'flag',
    ];

    protected const array WAREHOUSE_AREA_FIELD_MAP = [
        'Warehouse Area Code' => 'code',
        'Warehouse Area Name' => 'name',
        'Warehouse Area Place' => 'place',
    ];

    protected const array MEASURE_FIELDS = ['max_weight', 'max_volume'];

    public static function classify(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;
        $action       = $row->Action ?? null;

        if ($action === 'created') {
            return ['handling' => 'import', 'event' => 'created', 'field' => null];
        }

        if ($action !== 'edited') {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        $indirectObject = $row->{'Indirect Object'} ?? null;

        if ($indirectObject === null || $indirectObject === '') {
            return self::classifyAssociation($row, $directObject);
        }

        if ($directObject === 'Location' && array_key_exists($indirectObject, self::LOCATION_FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::LOCATION_FIELD_MAP[$indirectObject]];
        }

        if ($directObject === 'Warehouse Area' && array_key_exists($indirectObject, self::WAREHOUSE_AREA_FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::WAREHOUSE_AREA_FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        $directObject = $row->{'Direct Object'} ?? null;

        if ($event === 'created') {
            return $directObject === 'Warehouse Area'
                ? self::extractWarehouseAreaCreated($row)
                : self::extractLocationCreated($row);
        }

        if ($event === 'associated') {
            return $directObject === 'Warehouse Area'
                ? self::extractWarehouseAreaAssociated($row)
                : self::extractLocationAssociated($row);
        }

        if ($event === 'updated' && $field === 'warehouse_area') {
            return self::extractLocationMoved($row);
        }

        if ($event === 'updated') {
            return self::extractUpdatedValues($row, $field, $directObject);
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }

    protected static function classifyAssociation(object $row, ?string $directObject): array
    {
        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if ($directObject === 'Warehouse Area') {
            if (preg_match('/associated/i', $abstract)) {
                return ['handling' => 'import', 'event' => 'associated', 'field' => null];
            }

            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        if (preg_match('/Location moved from to warehouse area/i', $abstract)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => 'warehouse_area'];
        }

        if (preg_match('/Location associated to warehouse area/i', $abstract)) {
            return ['handling' => 'import', 'event' => 'associated', 'field' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function extractLocationCreated(object $row): array
    {
        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if ($abstract === 'Location Created') {
            $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Details'} ?? ''));
        }

        $patterns = [
            '/^(.+?) location (?:record )?created$/',
            '/Location (.+) created?$/',
            '/^New location (.+?)(?: added| dodano)?$/',
            '/^Poloha(.+) bola vytvorená$/u',
        ];

        $data = self::extractUploadData($row);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $abstract, $matches)) {
                return ['old_values' => [], 'new_values' => ['code' => trim($matches[1])], 'data' => $data];
            }
        }

        return ['old_values' => [], 'new_values' => [], 'data' => $data];
    }

    protected static function extractWarehouseAreaCreated(object $row): array
    {
        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/Warehouse area <span class="italic">([^<]*)<\/span> \(<span title="Code" class="strong">([^<]*)<\/span>\) created/', $abstract, $matches)) {
            return [
                'old_values' => [],
                'new_values' => ['name' => trim($matches[1]), 'code' => trim($matches[2])],
                'data' => [],
            ];
        }

        if (preg_match('/Warehouse area <span class="italic">([a-zA-Z0-9_\s]+)/', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => ['code' => trim($matches[1])], 'data' => []];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }

    protected static function extractLocationAssociated(object $row): array
    {
        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if (!preg_match('/Location associated to warehouse area <span[^>]*onclick="change_view\(\'warehouse\/\d+\/areas\/(\d+)\'\)"[^>]*>([^<]*)<\/span>/', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $data = ['warehouse_area_source_id' => $matches[1], 'warehouse_area' => trim($matches[2])];

        if (preg_match('/change_view\(\'upload\/(\d+)/', $abstract, $uploadMatch)) {
            $data['upload_source_id'] = $uploadMatch[1];
        }

        return [
            'old_values' => [],
            'new_values' => ['warehouse_area' => trim($matches[2])],
            'data' => $data,
        ];
    }

    protected static function extractLocationMoved(object $row): array
    {
        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if (!preg_match(
            '/Location moved from to warehouse area .*?areas\/(\d+)\'\)"[^>]*>([^<]*)<\/span> to .*?areas\/(\d+)\'\)"[^>]*>([^<]*)<\/span>/',
            $abstract,
            $matches
        )) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        return [
            'old_values' => ['warehouse_area' => trim($matches[2])],
            'new_values' => ['warehouse_area' => trim($matches[4])],
            'data' => [
                'old_warehouse_area_source_id' => $matches[1],
                'new_warehouse_area_source_id' => $matches[3],
            ],
        ];
    }

    protected static function extractWarehouseAreaAssociated(object $row): array
    {
        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if (!preg_match('/Location <span[^>]*onclick="change_view\(\'locations\/\d+\/areas\/(\d+)\'\)"[^>]*>([^<]*)<\/span> associated/', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        return [
            'old_values' => [],
            'new_values' => ['location' => trim($matches[2])],
            'data' => ['location_source_id' => $matches[1]],
        ];
    }

    protected static function extractUpdatedValues(object $row, ?string $field, ?string $directObject): array
    {
        if ($field === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $details = (string) ($row->{'History Details'} ?? '');
        $table   = HistoryValueExtractor::extractTable($details);

        $old = $table['old'];
        $new = $table['new'];

        if ($old === null && $new === null) {
            $fallback = self::extractFallbackFromAbstract($row, $field);
            if ($fallback !== null) {
                [$old, $new] = $fallback;
            }
        }

        if ($field === 'flag') {
            return self::extractFlagValues($old, $new);
        }

        if (in_array($field, self::MEASURE_FIELDS, true)) {
            $old = $old === null ? null : self::normalizeMeasure($old);
            $new = $new === null ? null : self::normalizeMeasure($new);
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

    protected static function extractFallbackFromAbstract(object $row, string $field): ?array
    {
        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match("/Location's ([^{}]+?) was changed to (.+)$/u", $abstract, $matches)) {
            return [null, trim($matches[2])];
        }

        if (preg_match("/Location's ([^{}]+?) (.+) was deleted$/u", $abstract, $matches)) {
            return [trim($matches[2]), ''];
        }

        if (preg_match('/Location ⇒\s*([^{}]+?) was changed to (.+)$/u', $abstract, $matches)) {
            return [null, trim($matches[2])];
        }

        return null;
    }

    protected static function extractFlagValues(?string $old, ?string $new): array
    {
        $oldParsed = self::parseFlag($old);
        $newParsed = self::parseFlag($new);

        if ($oldParsed === null && $newParsed === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $oldValues = [];
        $newValues = [];
        $data      = [];

        if ($oldParsed !== null) {
            $oldValues['flag'] = $oldParsed['label'];
        }

        if ($newParsed !== null) {
            $newValues['flag']  = $newParsed['label'];
            $data['flag_color'] = $newParsed['color'];
        }

        return ['old_values' => $oldValues, 'new_values' => $newValues, 'data' => $data];
    }

    protected static function parseFlag(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (!preg_match('/<i[^>]*class="[^"]*fa-flag\s+([a-zA-Z0-9_-]+)[^"]*"[^>]*>\s*<\/i>\s*(.*)$/is', $value, $matches)) {
            return ['label' => trim(strip_tags($value)), 'color' => null];
        }

        return ['label' => trim(strip_tags($matches[2])), 'color' => $matches[1]];
    }

    protected static function normalizeMeasure(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = str_ireplace(['cubic meters', 'cubic meter', 'Kg'], '', $value);
        $value = str_replace(',', '', $value);

        return trim($value);
    }

    protected static function extractUploadData(object $row): array
    {
        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/change_view\(\'upload\/(\d+)/', $abstract, $matches)) {
            return ['upload_source_id' => $matches[1]];
        }

        return [];
    }
}
