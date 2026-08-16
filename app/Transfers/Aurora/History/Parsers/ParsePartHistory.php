<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParsePartHistory
{
    protected const array TRADE_UNIT_FIELD_MAP = [
        'Part Reference' => 'code',
        'Part Recommended Product Unit Name' => 'name',
        'Part Package Description' => 'package_description',
        'Part Unit Label' => 'unit_label',
        'Part Unit Dimensions' => 'unit_dimensions',
        'Part Package Dimensions' => 'package_dimensions',
        'Part Unit Weight' => 'unit_weight',
        'Part Package Weight' => 'package_weight',
        'Part Materials' => 'materials',
        'Part Origin Country Code' => 'origin_country_code',
        'Part Tariff Code' => 'tariff_code',
        'Part Duty Rate' => 'duty_rate',
        'Part CPNP Number' => 'cpnp_number',
        'Part UFI' => 'ufi',
        'Part UN Number' => 'un_number',
        'Part UN Class' => 'un_class',
        'Part Packing Group' => 'packing_group',
        'Part Proper Shipping Name' => 'proper_shipping_name',
        'Part GPSR Languages' => 'gpsr_languages',
        'Part GPSR Manufacturer' => 'gpsr_manufacturer',
        'Part GPSR EU Responsable' => 'gpsr_eu_responsible',
        'Part GPSR Warnings' => 'gpsr_warnings',
        'Part GPSR Manual' => 'gpsr_manual',
        'Part SKO Barcode' => 'sko_barcode',
        'Part Carton Barcode' => 'carton_barcode',
        'Part Barcode Number' => 'barcode',
        'Part Sticky Note' => 'notes',
        'Part Unit Price' => 'price',
        'Part Unit RRP' => 'rrp',
    ];

    protected const array ORG_STOCK_FIELD_MAP = [
        'Part Status' => 'state',
        'Part On Demand' => 'on_demand',
        'Part SKOs per Carton' => 'skos_per_carton',
        'Part Units Per Package' => 'units_per_package',
        'Part Recommended Packages Per Selling Outer' => 'packages_per_outer',
        'Part Delivery Days' => 'delivery_days',
    ];

    protected const array PRICE_FIELDS = ['price', 'rrp'];

    public static function classify(object $row): array
    {
        $action = $row->{'Action'} ?? null;

        return match ($action) {
            'created' => self::classifyCreated($row),
            'deleted' => ['handling' => 'import', 'event' => 'deleted', 'field' => null, 'auditable_type' => 'TradeUnit'],
            'edited' => self::classifyEdited($row),
            'associated' => self::classifyAssociated($row),
            'disassociate' => self::classifyDisassociate($row),
            default => self::skip(),
        };
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match (true) {
            $event === 'created' => self::extractCreatedValues($row),
            $event === 'deleted' => ['old_values' => [], 'new_values' => [], 'data' => self::uploadSourceData($row)],
            $event === 'updated' && $field === 'main_supplier' => self::extractMainSupplierValues($row),
            $event === 'updated' => self::extractUpdatedValues($row, $field),
            $event === 'associated' && $field === 'category' => self::extractCategoryValues($row, true),
            $event === 'disassociated' && $field === 'category' => self::extractCategoryValues($row, false),
            $event === 'associated' && $field === 'barcode' => self::extractBarcodeValues($row, true),
            $event === 'disassociated' && $field === 'barcode' => self::extractBarcodeValues($row, false),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function skip(): array
    {
        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    protected static function abstract(object $row): string
    {
        return HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));
    }

    protected static function classifyCreated(object $row): array
    {
        return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'TradeUnit'];
    }

    protected static function classifyEdited(object $row): array
    {
        $indirectObject = self::normalizedIndirectObject($row);

        if ($indirectObject !== null) {
            if (array_key_exists($indirectObject, self::TRADE_UNIT_FIELD_MAP)) {
                return ['handling' => 'import', 'event' => 'updated', 'field' => self::TRADE_UNIT_FIELD_MAP[$indirectObject], 'auditable_type' => 'TradeUnit'];
            }

            if (array_key_exists($indirectObject, self::ORG_STOCK_FIELD_MAP)) {
                return ['handling' => 'import', 'event' => 'updated', 'field' => self::ORG_STOCK_FIELD_MAP[$indirectObject], 'auditable_type' => 'OrgStock'];
            }

            return self::skip();
        }

        if (self::matchMainSupplier(self::abstract($row)) !== null) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => 'main_supplier', 'auditable_type' => 'SupplierProduct'];
        }

        return self::skip();
    }

    protected static function classifyAssociated(object $row): array
    {
        $indirectObject = self::normalizedIndirectObject($row);
        $abstract       = self::abstract($row);

        if ($indirectObject === 'Category Part') {
            if (preg_match('/\bdisassociated\b/i', $abstract)) {
                return ['handling' => 'import', 'event' => 'disassociated', 'field' => 'category', 'auditable_type' => 'TradeUnit'];
            }

            if (preg_match('/\bassociated\b/i', $abstract)) {
                return ['handling' => 'import', 'event' => 'associated', 'field' => 'category', 'auditable_type' => 'TradeUnit'];
            }

            return self::skip();
        }

        if ($indirectObject === null || $indirectObject === '') {
            if (self::matchMainSupplier($abstract) !== null) {
                return ['handling' => 'import', 'event' => 'updated', 'field' => 'main_supplier', 'auditable_type' => 'SupplierProduct'];
            }

            if (self::extractBarcodeDigits($abstract) !== null) {
                if (preg_match('/\bdisassociated\b/i', $abstract)) {
                    return ['handling' => 'import', 'event' => 'disassociated', 'field' => 'barcode', 'auditable_type' => 'TradeUnit'];
                }

                if (preg_match('/\bassociated\b/i', $abstract)) {
                    return ['handling' => 'import', 'event' => 'associated', 'field' => 'barcode', 'auditable_type' => 'TradeUnit'];
                }
            }
        }

        return self::skip();
    }

    protected static function classifyDisassociate(object $row): array
    {
        $indirectObject = self::normalizedIndirectObject($row);
        $abstract       = self::abstract($row);

        if ($indirectObject === 'Category Part' && preg_match('/\bdisassociated\b/i', $abstract)) {
            return ['handling' => 'import', 'event' => 'disassociated', 'field' => 'category', 'auditable_type' => 'TradeUnit'];
        }

        if (($indirectObject === null || $indirectObject === '') && self::extractBarcodeDigits($abstract) !== null && preg_match('/\bdisassociated\b/i', $abstract)) {
            return ['handling' => 'import', 'event' => 'disassociated', 'field' => 'barcode', 'auditable_type' => 'TradeUnit'];
        }

        return self::skip();
    }

    protected static function normalizedIndirectObject(object $row): ?string
    {
        return HistoryValueExtractor::normalize((string) ($row->{'Indirect Object'} ?? ''));
    }

    protected static function matchMainSupplier(string $abstract): ?array
    {
        if (preg_match('/Part main supplier set to\s+(\S+)\s*\(([^)]*)\)/i', $abstract, $matches)) {
            return ['code' => trim($matches[1]), 'reference' => trim($matches[2])];
        }

        return null;
    }

    protected static function extractBarcodeDigits(string $abstract): ?string
    {
        $stripped = strip_tags($abstract);

        if (preg_match('/\b(\d{8,})\b/', $stripped, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected static function uploadSourceData(object $row): array
    {
        $abstract = self::abstract($row);

        if (preg_match('/change_view\(\'upload\/(\d+)/', $abstract, $matches)) {
            return ['upload_source_id' => $matches[1]];
        }

        return [];
    }

    protected static function extractCreatedValues(object $row): array
    {
        return ['old_values' => [], 'new_values' => [], 'data' => self::uploadSourceData($row)];
    }

    protected static function extractMainSupplierValues(object $row): array
    {
        $match = self::matchMainSupplier(self::abstract($row));

        if ($match === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        return [
            'old_values' => [],
            'new_values' => ['main_supplier' => $match['code']],
            'data' => ['supplier_reference' => $match['reference']],
        ];
    }

    protected static function extractCategoryValues(object $row, bool $associated): array
    {
        $abstract = self::abstract($row);
        $data     = [];

        if (preg_match('/part_category\.php\?id=(\d+)"[^>]*>([^<]*)</i', $abstract, $matches)) {
            $data['category_source_id'] = $matches[1];
            $data['category_code']      = trim($matches[2]);
        }

        $code = $data['category_code'] ?? null;

        return [
            'old_values' => $associated ? [] : ['category' => $code],
            'new_values' => $associated ? ['category' => $code] : [],
            'data' => $data,
        ];
    }

    protected static function extractBarcodeValues(object $row, bool $associated): array
    {
        $barcode = self::extractBarcodeDigits(self::abstract($row));

        return [
            'old_values' => $associated ? [] : ['barcode' => $barcode],
            'new_values' => $associated ? ['barcode' => $barcode] : [],
            'data' => [],
        ];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
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
            $new = self::extractFromAbstract($row);
        }

        $data = [];

        if ($field !== null && in_array($field, self::PRICE_FIELDS, true)) {
            [$old, $oldData] = self::postProcessPrice($field, $old);
            [$new, $newData] = self::postProcessPrice($field, $new);
            $data           = array_merge($data, $oldData, $newData);
        } elseif ($field === 'state') {
            $old = self::postProcessState($old);
            $new = self::postProcessState($new);
        }

        $oldValues = [];
        $newValues = [];

        if ($field !== null) {
            $oldValues[$field] = $old;
            $newValues[$field] = $new;

            if ($old === null && $new === null) {
                return ['old_values' => [], 'new_values' => [], 'data' => $data];
            }

            if ($old === null && $new !== null) {
                $oldValues[$field] = '';
            }
            if ($new === null && $old !== null) {
                $newValues[$field] = '';
            }
        }

        return ['old_values' => $oldValues, 'new_values' => $newValues, 'data' => $data];
    }

    protected static function extractFromAbstract(object $row): ?string
    {
        $abstract = trim(self::abstract($row));
        $abstract = preg_replace('/\s+/', ' ', $abstract);

        if (preg_match("/^Part's (.+) was changed to (.+)$/", $abstract, $matches)) {
            return trim($matches[2]);
        }

        if (preg_match("/^Part's (.+) set as (.+)$/", $abstract, $matches)) {
            return trim($matches[2]);
        }

        if (preg_match('/^Part (?:&rArr;|⇒)\s*(.+) was changed to (.+)$/u', $abstract, $matches)) {
            return trim($matches[2]);
        }

        return null;
    }

    protected static function postProcessPrice(string $field, ?string $value): array
    {
        if ($value === null) {
            return [null, []];
        }

        $data = [];

        if (preg_match('/margin\s+(-?[\d.]+)%/', $value, $matches)) {
            $data[$field.'_margin'] = $matches[1];
            if (str_starts_with($matches[1], '-')) {
                $data['negative_margin'] = true;
            }
        }

        if (preg_match('/class="[^"]*error[^"]*"/', $value)) {
            $data['negative_margin'] = true;
        }

        $numeric = str_replace(',', '', $value);
        if (preg_match('/([\d]+(?:\.\d+)?)/', $numeric, $matches)) {
            $value = $matches[1];
        }

        return [$value, $data];
    }

    protected static function postProcessState(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return match ($value) {
            'In Use' => 'active',
            'Not In Use' => 'discontinued',
            default => $value,
        };
    }
}
