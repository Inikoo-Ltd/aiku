<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseProductHistory
{
    protected const array FIELD_MAP = [
        'Product Price' => 'price',
        'Product RRP' => 'rrp',
        'Product Name' => 'name',
        'Product Code' => 'code',
        'Product Status' => 'state',
        'Product Unit Dimensions' => 'unit_dimensions',
        'Product Unit Weight' => 'unit_weight',
        'Product Materials' => 'materials',
        'Product CPNP Number' => 'cpnp_number',
        'Product Tariff Code' => 'tariff_code',
        'Product Duty Rate' => 'duty_rate',
        'Product Origin Country Code' => 'origin_country_code',
        'Product Barcode Number' => 'barcode',
        'Product Units Per Case' => 'units_per_case',
        'Product Unit Label' => 'unit_label',
        'Product UN Number' => 'un_number',
        'Product UN Class' => 'un_class',
        'Product Packing Group' => 'packing_group',
        'Product Proper Shipping Name' => 'proper_shipping_name',
        'Product Hazard Identification Number' => 'hazard_identification_number',
        'Product GPSR Manual' => 'gpsr_manual',
        'Product GPSR Warnings' => 'gpsr_warnings',
        'Product GPSR EU Responsable' => 'gpsr_eu_responsible',
        'Product GPSR Class Category Danger' => 'gpsr_class_category_danger',
        'Product GPSR Languages' => 'gpsr_languages',
        'Product GPSR Manufacturer' => 'gpsr_manufacturer',
        'Product Special Characteristic' => 'special_characteristic',
        'Product Variant Short Name' => 'variant_short_name',
        'Product Show Variant' => 'show_variant',
        'Product Label in Family' => 'label_in_family',
        'Product UFI' => 'ufi',
        'Product Package Weight' => 'package_weight',
        'Product Next Supplier Shipment' => 'next_supplier_shipment',
    ];

    protected const array PRICE_FIELDS = ['price', 'rrp'];

    public static function classify(object $row): array
    {
        $action = $row->{'Action'} ?? null;

        return match ($action) {
            'created' => self::classifyCreated($row),
            'edit' => self::classifyLowercaseEdit($row),
            'edited' => self::classifyEdited($row),
            default => ['handling' => 'skip', 'event' => null, 'field' => null],
        };
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => self::extractCreatedValues($row),
            'associated' => ['old_values' => [], 'new_values' => [], 'data' => ['association' => self::normalizedAbstract($row)]],
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function normalizedAbstract(object $row): ?string
    {
        return HistoryValueExtractor::normalize((string) ($row->{'History Abstract'} ?? ''));
    }

    protected static function classifyCreated(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (in_array($abstract, ['Product Created', 'product created', 'Produkt bol vytvorený'], true)) {
            $details = trim((string) ($row->{'History Details'} ?? ''));
            if ($details === '') {
                return ['handling' => 'import', 'event' => 'created', 'field' => null];
            }
        }

        foreach (self::createdPatterns() as $pattern) {
            if (preg_match($pattern, $abstract)) {
                return ['handling' => 'import', 'event' => 'created', 'field' => null];
            }
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function createdPatterns(): array
    {
        return [
            '/^Product (.+) \(\d+\) created/',
            '/^Produkt(.+) byl vytvořen/',
            '/^Produkt(.+) bol vytvorený/',
            '/^(.+) product created/',
            '/^(.+) termék létrehozva/',
        ];
    }

    protected static function classifyLowercaseEdit(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (preg_match('/^Product associated with .+/', $abstract)) {
            return ['handling' => 'import', 'event' => 'associated', 'field' => null];
        }

        if (preg_match("/^Product's parts deleted$/", $abstract)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => 'parts'];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function classifyEdited(object $row): array
    {
        $indirectObject = $row->{'Indirect Object'} ?? null;

        if ($indirectObject === null || $indirectObject === '') {
            return ['handling' => 'skip', 'event' => null, 'field' => null];
        }

        if (array_key_exists($indirectObject, self::FIELD_MAP)) {
            return ['handling' => 'import', 'event' => 'updated', 'field' => self::FIELD_MAP[$indirectObject]];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    protected static function extractCreatedValues(object $row): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));

        if (in_array($abstract, ['Product Created', 'product created', 'Produkt bol vytvorený'], true)) {
            $details = trim((string) ($row->{'History Details'} ?? ''));
            if ($details === '') {
                return ['old_values' => [], 'new_values' => [], 'data' => ['name_unavailable' => true]];
            }
            $abstract = $details;
        }

        $data = [];
        if (preg_match('/change_view\(\'upload\/(\d+)/', $abstract, $matches)) {
            $data['upload_source_id'] = $matches[1];
        }

        foreach (self::createdPatterns() as $pattern) {
            if (preg_match($pattern, $abstract, $matches)) {
                $value = trim($matches[1]);
                if ($value === '') {
                    return ['old_values' => [], 'new_values' => [], 'data' => $data];
                }

                $field = str_word_count($value) > 1 ? 'name' : 'code';

                return ['old_values' => [], 'new_values' => [$field => $value], 'data' => $data];
            }
        }

        return ['old_values' => [], 'new_values' => [], 'data' => $data];
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        if ($field === 'parts') {
            return ['old_values' => ['parts' => ''], 'new_values' => ['parts' => ''], 'data' => ['note' => 'parts deleted']];
        }

        $details = (string) ($row->{'History Details'} ?? '');
        $table   = HistoryValueExtractor::extractTable($details);

        $old = $table['old'];
        $new = $table['new'];

        if ($old === null && $new === null) {
            $plain = HistoryValueExtractor::extractPlainSentence((string) ($row->{'History Details'} ?? ''));
            if ($plain !== null) {
                $old = $plain['old'];
                $new = $plain['new'];
            }
        }

        if ($old === null && $new === null) {
            [$old, $new] = self::extractFromAbstract($row, (string) $field);
        }

        $data = [];

        if ($field !== null && in_array($field, self::PRICE_FIELDS, true)) {
            [$old, $oldData] = self::postProcessPrice($field, $old);
            [$new, $newData] = self::postProcessPrice($field, $new);
            $data           = array_merge($data, $oldData, $newData);
        } elseif ($field === 'state') {
            [$old, $oldData] = self::postProcessState($old);
            [$new, $newData] = self::postProcessState($new);
            $data           = array_merge($data, $oldData, $newData);
        }

        $oldValues = [];
        $newValues = [];

        if ($field !== null) {
            $oldValues[$field] = $old;
            $newValues[$field] = $new;

            if ($old === null && $new !== null) {
                $oldValues[$field] = '';
            }
            if ($new === null && $old !== null) {
                $newValues[$field] = '';
            }
        }

        return ['old_values' => $oldValues, 'new_values' => $newValues, 'data' => $data];
    }

    protected static function extractFromAbstract(object $row, string $field): array
    {
        $abstract = trim((string) ($row->{'History Abstract'} ?? ''));
        $abstract = preg_replace('/\s+/', ' ', $abstract);

        if (preg_match('/^Product Name Changed \((.+)\)$/', $abstract, $matches)) {
            return [null, trim($matches[1])];
        }

        if (preg_match("/^Product's (.+) was changed to (.+)$/", $abstract, $matches)) {
            return [null, trim($matches[2])];
        }

        if (preg_match('/^Product &rArr; (.+) was changed to (.+)$/', $abstract, $matches)) {
            return [null, trim($matches[2])];
        }

        if (in_array($field, self::PRICE_FIELDS, true)) {
            if (preg_match('/changed from Price: .([\d.]+)/', $abstract, $oldMatches)) {
                $old = $oldMatches[1];
                $new = null;
                if (preg_match('/to Price: .([\d.]+)/', $abstract, $newMatches)) {
                    $new = $newMatches[1];
                }

                return [$old, $new];
            }

            if (preg_match('/price changed from .([\d.]+)/', $abstract, $oldMatches)) {
                $old = $oldMatches[1];
                $new = null;
                if (preg_match('/to .([\d.]+)/', $abstract, $newMatches)) {
                    $new = $newMatches[1];
                }

                return [$old, $new];
            }
        }

        return [null, null];
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

        $numeric = preg_replace('/margin\s+-?[\d.]+%/', '', $value);
        $numeric = str_replace(',', '', $numeric);
        if (preg_match('/([\d]+(?:\.\d+)?)/', $numeric, $matches)) {
            $value = $matches[1];
        }

        return [$value, $data];
    }

    protected static function postProcessState(?string $value): array
    {
        if ($value === null) {
            return [null, []];
        }

        $value = trim($value);

        $mapped = match ($value) {
            'InProcess' => ProductStateEnum::IN_PROCESS->value,
            'Active', 'Suspended', 'Aktiv', 'Aktív', 'Aktívny', 'Suspendované', 'Felfüggesztett' => ProductStateEnum::ACTIVE->value,
            'Discontinuing' => ProductStateEnum::DISCONTINUING->value,
            'Discontinued', 'aus dem Sortiment genommen', 'Vyradený' => ProductStateEnum::DISCONTINUED->value,
            default => null,
        };

        if ($mapped === null) {
            return [$value, ['unmapped_state' => true]];
        }

        return [$mapped, []];
    }
}
