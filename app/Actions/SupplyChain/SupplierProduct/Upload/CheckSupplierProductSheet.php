<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\Goods\Barcode\StoreBarcode;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum as Column;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Currency;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Turns the rows read from the sheet into plain values plus findings, without saving anything.
 *
 * Finding levels: error (fix the sheet), block (needs "I accept responsibility" or a fix in the preview),
 * link (confirm adding this supplier to an existing trade unit), warning (information only).
 */
class CheckSupplierProductSheet
{
    use AsObject;

    public const float TARGET_MARGIN            = 0.60;
    public const float MINIMUM_RETAILER_MARGIN  = 0.50;
    public const float LOW_RETAILER_MARGIN      = 0.53;
    public const float COST_CHANGE_LIMIT        = 0.20;
    public const float EURO_PRICE_DEVIATION     = 0.25;
    public const float HIGH_EXTRA_COSTS         = 0.60;
    public const float MINIMUM_DENSITY          = 0.01;
    public const float MAXIMUM_DENSITY          = 12.0;

    protected const string PACK_PATTERN = '/(^|\s|\()(\d+\s*x\b|x\s*\d+\b|\d+\s*(pcs|pieces|units|pack)\b|(pack|set|box|bundle|case)\s+of\b|bundle\b)/i';

    protected Supplier $supplier;

    /** @var array<string, ?float> */
    protected array $exchangeRates = [];

    /** @var Collection<int, Organisation>|null */
    protected ?Collection $organisations = null;

    /** @var array{delivery_time: ?float, minimum_carton_order: ?float, extra_costs: ?float, cbm: ?float}|null */
    protected ?array $supplierAverages = null;

    /**
     * @param array{rows: list<array{row: int, cells: array<string, array{value: mixed, text: ?string, currencies: list<string>}>, order: array<string, array{value: mixed, text: ?string, currencies: list<string>}>}>} $sheet
     *
     * @return list<array{row: int, values: array<string, mixed>, findings: list<array{level: string, code: string, column: ?string, message: string}>}>
     */
    public function handle(Supplier $supplier, array $sheet): array
    {
        $this->supplier = $supplier;

        $rows = [];
        foreach ($sheet['rows'] as $sheetRow) {
            $rows[] = $this->checkRow($sheetRow);
        }

        $this->checkAcrossRows($rows);

        return $rows;
    }

    /**
     * @param array{row: int, cells: array<string, array{value: mixed, text: ?string, currencies: list<string>}>, order: array<string, array{value: mixed, text: ?string, currencies: list<string>}>} $sheetRow
     *
     * @return array{row: int, values: array<string, mixed>, findings: list<array{level: string, code: string, column: ?string, message: string}>}
     */
    protected function checkRow(array $sheetRow): array
    {
        $cells    = $sheetRow['cells'];
        $findings = [];
        $values   = [];

        $add = function (string $level, string $code, ?Column $column, string $message) use (&$findings): void {
            $findings[] = ['level' => $level, 'code' => $code, 'column' => $column?->value, 'message' => $message];
        };

        $text = fn (Column $column): ?string => Arr::get($cells, $column->value.'.text');

        foreach (Column::cases() as $column) {
            if ($column->isRequired() && $text($column) === null && !in_array($column, [Column::SUPPLIER_CODE, Column::UNIT_EXPENSE, Column::UNIT_BARCODE], true)) {
                $add('error', 'required_'.$column->value, $column, __(':column is required.', ['column' => $column->heading()]));
            }
        }

        foreach ([Column::UNIT_COST, Column::UNIT_EXPENSE, Column::RECOMMENDED_PRICE, Column::RECOMMENDED_RRP, Column::RECOMMENDED_PRICE_EUR, Column::RECOMMENDED_RRP_EUR] as $column) {
            $this->checkCurrency($column, Arr::get($cells, $column->value.'.currencies', []), $add);
        }

        $values['family']         = $text(Column::FAMILY);
        $values['part_reference'] = $text(Column::PART_REFERENCE);
        $values['unit_name']      = $text(Column::UNIT_NAME);
        $values['supplier_code']  = $text(Column::SUPPLIER_CODE) ?? $values['part_reference'];
        $values['unit_label']     = $text(Column::UNIT_LABEL);
        $values['materials']      = $text(Column::MATERIALS);

        if ($text(Column::SUPPLIER_CODE) === null && $values['part_reference'] !== null) {
            $add('warning', 'supplier_code_from_part_reference', Column::SUPPLIER_CODE, __('Supplier code taken from Part reference.'));
        }

        $values['units_per_sko']         = $this->wholeNumber(Column::UNITS_PER_SKO, $cells, $add, 1);
        $values['skos_per_outer']        = $this->wholeNumber(Column::SKOS_PER_OUTER, $cells, $add, 1);
        $values['skos_per_carton']       = $this->wholeNumber(Column::SKOS_PER_CARTON, $cells, $add, 1);
        $values['minimum_order_cartons'] = $this->wholeNumber(Column::MINIMUM_ORDER_CARTONS, $cells, $add, 1);
        $values['delivery_days']         = $this->wholeNumber(Column::DELIVERY_DAYS, $cells, $add, 0);

        $values['unit_cost']             = $this->positiveNumber(Column::UNIT_COST, $cells, $add);
        $values['unit_expense']          = $text(Column::UNIT_EXPENSE) === null ? 0.0 : $this->number(Column::UNIT_EXPENSE, $cells, $add, 0);
        $values['extra_costs']           = $this->percentage($cells, $add);
        $values['recommended_price']     = $this->positiveNumber(Column::RECOMMENDED_PRICE, $cells, $add);
        $values['recommended_rrp']       = $this->positiveNumber(Column::RECOMMENDED_RRP, $cells, $add);
        $values['recommended_price_eur'] = $this->positiveNumber(Column::RECOMMENDED_PRICE_EUR, $cells, $add);
        $values['recommended_rrp_eur']   = $this->positiveNumber(Column::RECOMMENDED_RRP_EUR, $cells, $add);

        $values['unit_weight']     = $this->grams(Column::UNIT_WEIGHT, $cells, $add);
        $values['sko_weight']      = $this->grams(Column::SKO_WEIGHT, $cells, $add);
        $values['carton_weight']   = $this->grams(Column::CARTON_WEIGHT, $cells, $add);
        $values['unit_dimensions'] = $this->dimensions(Column::UNIT_DIMENSIONS, $cells, $add);
        $values['sko_dimensions']  = $this->dimensions(Column::SKO_DIMENSIONS, $cells, $add);
        $values['carton_cbm']      = $text(Column::CARTON_CBM) === null ? null : $this->number(Column::CARTON_CBM, $cells, $add, 0);
        $values['tariff_code']     = $this->tariffCode($cells, $add);
        $values['unit_barcode']    = $this->barcode($cells, $add);

        $values['order'] = $this->order($sheetRow['order'], $add);

        $this->checkFamily($values, $add);
        $this->checkPartReference($values, $add);
        $this->checkUnitName($values, $add);
        $this->checkUnitLabel($values, $add);
        $this->checkSupplierProduct($values, $add);
        $this->checkPacking($values, $add);
        $this->checkPrices($values, $add);
        $this->checkPhysical($values, $add);
        $this->checkAgainstSupplier($values, $add);
        $this->checkOrder($values, $add);

        $values['sko_name'] = $values['units_per_sko'] > 1 && $values['unit_name'] !== null
            ? __('Pack of :units :name', ['units' => $values['units_per_sko'], 'name' => $values['unit_name']])
            : $values['unit_name'];
        if ($values['units_per_sko'] > 1) {
            $add('warning', 'sko_name_suggested', Column::UNITS_PER_SKO, __('SKO name suggested as ":name", check the wording.', ['name' => $values['sko_name']]));
        }

        return ['row' => $sheetRow['row'], 'values' => $values, 'findings' => $findings];
    }

    /**
     * @param list<array{row: int, values: array<string, mixed>, findings: list<array{level: string, code: string, column: ?string, message: string}>}> $rows
     */
    protected function checkAcrossRows(array &$rows): void
    {
        $duplicates = [
            'part_reference' => ['level' => 'error', 'column' => Column::PART_REFERENCE],
            'supplier_code'  => ['level' => 'error', 'column' => Column::SUPPLIER_CODE],
            'unit_name'      => ['level' => 'warning', 'column' => Column::UNIT_NAME],
        ];

        foreach ($duplicates as $key => $rule) {
            $groups = collect($rows)
                ->filter(fn (array $row) => $row['values'][$key] !== null)
                ->groupBy(fn (array $row) => mb_strtolower($row['values'][$key]), true)
                ->filter(fn (Collection $group) => $group->count() > 1);

            foreach ($groups as $group) {
                $sheetRows = $group->pluck('row')->implode(', ');
                foreach ($group->keys() as $index) {
                    $rows[$index]['findings'][] = [
                        'level'   => $rule['level'],
                        'code'    => 'duplicate_'.$key,
                        'column'  => $rule['column']->value,
                        'message' => __(':column :value appears in rows :rows.', ['column' => $rule['column']->heading(), 'value' => $rows[$index]['values'][$key], 'rows' => $sheetRows]),
                    ];
                }
            }
        }
    }

    protected function checkCurrency(Column $column, array $currencies, callable $add): void
    {
        if ($currencies === []) {
            return;
        }

        $expected = $column->currency() === 'supplier' ? $this->supplier->currency?->code : $column->currency();
        if ($expected && !in_array($expected, $currencies, true)) {
            $add('error', 'currency_'.$column->value, $column, __(':column is in :found, it must be :expected.', [
                'column'   => $column->heading(),
                'found'    => implode('/', $currencies),
                'expected' => $expected,
            ]));
        }
    }

    protected function number(Column $column, array $cells, callable $add, float $minimum): ?float
    {
        $value = Arr::get($cells, $column->value.'.value');
        if ($value === null) {
            return null;
        }

        if (!is_numeric($value) || (float)$value < $minimum) {
            $add('error', 'invalid_'.$column->value, $column, __(':column ":value" must be a number of :minimum or more.', ['column' => $column->heading(), 'value' => $value, 'minimum' => $minimum]));

            return null;
        }

        return (float)$value;
    }

    protected function positiveNumber(Column $column, array $cells, callable $add): ?float
    {
        $value = Arr::get($cells, $column->value.'.value');
        if ($value === null) {
            return null;
        }

        if (!is_numeric($value) || (float)$value <= 0) {
            $add('error', 'invalid_'.$column->value, $column, __(':column ":value" must be a number above zero.', ['column' => $column->heading(), 'value' => $value]));

            return null;
        }

        return (float)$value;
    }

    protected function wholeNumber(Column $column, array $cells, callable $add, int $minimum): ?int
    {
        $value = Arr::get($cells, $column->value.'.value');
        if ($value === null) {
            return null;
        }

        if (!is_numeric($value) || (float)$value != (int)$value || (int)$value < $minimum) {
            $add('error', 'invalid_'.$column->value, $column, __(':column ":value" must be a whole number of :minimum or more.', ['column' => $column->heading(), 'value' => $value, 'minimum' => $minimum]));

            return null;
        }

        return (int)$value;
    }

    protected function percentage(array $cells, callable $add): ?float
    {
        $column = Column::EXTRA_COSTS;
        $value  = Arr::get($cells, $column->value.'.value');
        $text   = (string)Arr::get($cells, $column->value.'.text');
        if ($value === null) {
            return null;
        }

        if (is_string($value) && str_ends_with(trim($value), '%')) {
            $value = rtrim(trim($value), '%');
            $value = is_numeric($value) ? (float)$value / 100 : $value;
        }

        if (!is_numeric($value)) {
            $add('error', 'invalid_extra_costs', $column, __(':column ":value" is not a percentage.', ['column' => $column->heading(), 'value' => $text]));

            return null;
        }

        $value = (float)$value;
        if ($value > 1) {
            $add('warning', 'extra_costs_as_percent', $column, __(':column: read :value as :value%.', ['column' => $column->heading(), 'value' => $value]));
            $value /= 100;
        }

        if ($value < 0 || $value > 1) {
            $add('error', 'invalid_extra_costs', $column, __(':column must be between 0% and 100%.', ['column' => $column->heading()]));

            return null;
        }

        if ($value > self::HIGH_EXTRA_COSTS) {
            $add('block', 'high_extra_costs', $column, __(':column of :value% looks high.', ['column' => $column->heading(), 'value' => round($value * 100)]));
        }

        return $value;
    }

    protected function grams(Column $column, array $cells, callable $add): ?int
    {
        $kilograms = $this->number($column, $cells, $add, 0);

        return $kilograms === null ? null : (int)round($kilograms * 1000);
    }

    /**
     * @return array{l: float, w: float, h: float}|null
     */
    protected function dimensions(Column $column, array $cells, callable $add): ?array
    {
        $text = Arr::get($cells, $column->value.'.text');
        if ($text === null) {
            return null;
        }

        $parts = preg_split('/\s*[x×*]\s*/iu', str_replace(',', '.', $text));
        if (count($parts) !== 3 || collect($parts)->contains(fn ($part) => !is_numeric($part) || (float)$part <= 0)) {
            $add('warning', 'unreadable_'.$column->value, $column, __('Could not read :column ":value", nothing saved (use 20x10x5).', ['column' => $column->heading(), 'value' => $text]));

            return null;
        }

        return ['l' => (float)$parts[0], 'w' => (float)$parts[1], 'h' => (float)$parts[2]];
    }

    protected function tariffCode(array $cells, callable $add): ?string
    {
        $text = Arr::get($cells, Column::TARIFF_CODE->value.'.text');
        if ($text === null) {
            return null;
        }

        $digits = preg_replace('/[\s.]/', '', $text);
        if (!preg_match('/^\d{6,10}$/', $digits)) {
            $add('error', 'invalid_tariff_code', Column::TARIFF_CODE, __('Tariff code ":value" must be 6 to 10 digits.', ['value' => $text]));

            return null;
        }

        return $digits;
    }

    protected function barcode(array $cells, callable $add): ?string
    {
        $text = Arr::get($cells, Column::UNIT_BARCODE->value.'.text');
        if ($text === null) {
            $add('block', 'no_barcode', Column::UNIT_BARCODE, __('No unit barcode.'));

            return null;
        }

        if (strtolower($text) === 'auto') {
            return 'auto';
        }

        $number = preg_replace('/\D/', '', $text);
        if (!preg_match('/^\d{8,14}$/', $number) || !StoreBarcode::make()->hasValidCheckDigit($number)) {
            $add('error', 'invalid_barcode', Column::UNIT_BARCODE, __('Unit barcode ":value" is not a valid barcode (8 to 14 digits with a correct check digit).', ['value' => $text]));

            return null;
        }

        $owner = TradeUnit::where('group_id', $this->supplier->group_id)->where('barcode', $number)->first();
        if ($owner && mb_strtolower($owner->code) !== mb_strtolower((string)Arr::get($cells, Column::PART_REFERENCE->value.'.text'))) {
            $add('error', 'barcode_taken', Column::UNIT_BARCODE, __('Unit barcode :barcode belongs to :code.', ['barcode' => $number, 'code' => $owner->code]));

            return null;
        }

        return $number;
    }

    /**
     * @param array<string, array{value: mixed, text: ?string, currencies: list<string>}> $orderCells
     *
     * @return array<string, int>
     */
    protected function order(array $orderCells, callable $add): array
    {
        $order = [];
        foreach ($orderCells as $key => $cell) {
            if ($cell['value'] === null || (is_numeric($cell['value']) && (float)$cell['value'] == 0)) {
                continue;
            }

            $heading = Column::ORDER_CARTONS_PREFIX.$key;
            if (!is_numeric($cell['value']) || (float)$cell['value'] != (int)$cell['value'] || (int)$cell['value'] < 0) {
                $add('error', 'invalid_order_'.$key, null, __('":heading" ":value" must be a whole number of cartons.', ['heading' => ucfirst($heading), 'value' => $cell['text']]));

                continue;
            }

            if (!$this->organisationForOrderColumn($this->supplier, $key)) {
                $add('error', 'order_organisation_'.$key, null, __('":heading": no organisation buying from :supplier matches ":key".', ['heading' => ucfirst($heading), 'supplier' => $this->supplier->name, 'key' => $key]));

                continue;
            }

            $order[$key] = (int)$cell['value'];
        }

        return $order;
    }

    protected function checkFamily(array &$values, callable $add): void
    {
        $code = $values['family'];
        if ($code === null) {
            return;
        }

        $family = StockFamily::where('group_id', $this->supplier->group_id)->whereRaw('lower(code) = lower(?)', [$code])->first();
        if ($family) {
            $values['family'] = $family->code;
            if ($family->code !== $code) {
                $add('warning', 'family_case', Column::FAMILY, __('Family :sheet matched as :family.', ['sheet' => $code, 'family' => $family->code]));
            }
            if (in_array($family->state, [StockFamilyStateEnum::DISCONTINUED, StockFamilyStateEnum::DISCONTINUING], true)) {
                $add('warning', 'family_discontinued', Column::FAMILY, __('Family :family is :state.', ['family' => $family->code, 'state' => $family->state->value]));
            }

            $this->checkFamilyPrefix($family, $values, $add);

            return;
        }

        $add('warning', 'family_new', Column::FAMILY, __('New family :family will be created.', ['family' => $code]));

        $similar = StockFamily::where('group_id', $this->supplier->group_id)
            ->whereRaw('length(code) between ? and ?', [mb_strlen($code) - 2, mb_strlen($code) + 2])
            ->pluck('code')
            ->map(fn (string $existing) => ['code' => $existing, 'distance' => levenshtein(mb_strtolower($existing), mb_strtolower($code))])
            ->filter(fn (array $candidate) => $candidate['distance'] <= 2)
            ->sortBy('distance')
            ->value('code');
        if ($similar) {
            $add('warning', 'family_similar', Column::FAMILY, __(':family looks like existing family :similar, typo?', ['family' => $code, 'similar' => $similar]));
        }
    }

    protected function checkFamilyPrefix(StockFamily $family, array $values, callable $add): void
    {
        $prefix = $this->codePrefix($values['part_reference']);
        if ($prefix === null) {
            return;
        }

        $familyPrefixes = $family->stocks()->pluck('code')->map(fn (string $code) => $this->codePrefix($code))->filter()->unique()->values();
        if ($familyPrefixes->isNotEmpty() && !$familyPrefixes->contains(mb_strtolower($prefix))) {
            $add('error', 'family_prefix', Column::FAMILY, __('Family :family holds :prefixes products, :prefix does not look like it belongs there.', [
                'family'   => $family->code,
                'prefixes' => $familyPrefixes->take(5)->implode(', '),
                'prefix'   => $prefix,
            ]));
        }
    }

    protected function codePrefix(?string $code): ?string
    {
        if ($code === null || !str_contains($code, '-')) {
            return null;
        }

        return mb_strtolower(substr($code, 0, strrpos($code, '-')));
    }

    protected function checkPartReference(array &$values, callable $add): void
    {
        $code = $values['part_reference'];
        if ($code === null) {
            return;
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
            $add('error', 'invalid_part_reference', Column::PART_REFERENCE, __('Part reference ":code" can only have letters, digits, "-", "_" and ".".', ['code' => $code]));

            return;
        }

        $tradeUnit = TradeUnit::where('group_id', $this->supplier->group_id)->whereRaw('lower(code) = lower(?)', [$code])->first();
        if (!$tradeUnit) {
            return;
        }

        $values['trade_unit_id'] = $tradeUnit->id;
        $stock                   = $tradeUnit->stocks()->first();
        $values['stock_id']      = $stock?->id;

        $add('link', 'link_trade_unit', Column::PART_REFERENCE, __(':code already exists (":name"), this supplier will be added to it as another source. Nothing on it is overwritten, only empty fields are filled.', [
            'code' => $tradeUnit->code,
            'name' => $tradeUnit->name,
        ]));

        if (in_array($tradeUnit->status, [TradeUnitStatusEnum::DISCONTINUED, TradeUnitStatusEnum::DISCONTINUING], true)
            || ($stock && in_array($stock->state, [StockStateEnum::DISCONTINUED, StockStateEnum::DISCONTINUING], true))) {
            $add('warning', 'trade_unit_discontinued', Column::PART_REFERENCE, __(':code is discontinued.', ['code' => $tradeUnit->code]));
        }

        if ($stock && $values['units_per_sko'] !== null && $stock->packed_in !== null && (int)$stock->packed_in !== $values['units_per_sko']) {
            $add('block', 'packed_in_differs', Column::UNITS_PER_SKO, __('SKO :code holds :current units, sheet says :sheet. The existing SKO is not changed.', [
                'code'    => $stock->code,
                'current' => $stock->packed_in,
                'sheet'   => $values['units_per_sko'],
            ]));
        }

        if ($values['unit_barcode'] !== null && $values['unit_barcode'] !== 'auto' && $tradeUnit->barcode && $tradeUnit->barcode !== $values['unit_barcode']) {
            $add('block', 'barcode_differs', Column::UNIT_BARCODE, __(':code already has barcode :current, sheet says :sheet. The existing barcode is kept.', [
                'code'    => $tradeUnit->code,
                'current' => $tradeUnit->barcode,
                'sheet'   => $values['unit_barcode'],
            ]));
        }

        if ($values['tariff_code'] !== null && $tradeUnit->tariff_code && preg_replace('/\D/', '', $tradeUnit->tariff_code) !== $values['tariff_code']) {
            $add('warning', 'tariff_code_differs', Column::TARIFF_CODE, __(':code has tariff code :current, sheet says :sheet. The existing one is kept.', [
                'code'    => $tradeUnit->code,
                'current' => $tradeUnit->tariff_code,
                'sheet'   => $values['tariff_code'],
            ]));
        }
    }

    protected function checkUnitName(array $values, callable $add): void
    {
        $name = $values['unit_name'];
        if ($name === null) {
            return;
        }

        if (mb_strlen($name) > 255) {
            $add('error', 'unit_name_too_long', Column::UNIT_NAME, __(':column is longer than 255 characters.', ['column' => Column::UNIT_NAME->heading()]));
        }

        if (preg_match(self::PACK_PATTERN, $name)) {
            $add('block', 'unit_name_pack', Column::UNIT_NAME, __('Unit name ":name" looks like a pack, not a single unit.', ['name' => $name]));
        }
    }

    protected function checkUnitLabel(array $values, callable $add): void
    {
        $label = $values['unit_label'];
        if ($label === null) {
            return;
        }

        if (mb_strlen($label) < 2 || preg_match('/\d/', $label) || in_array(mb_strtolower($label), ['pack', 'set', 'box', 'bundle', 'case'], true)) {
            $add('block', 'unit_label_odd', Column::UNIT_LABEL, __('Unit label ":label" does not look like a single unit (piece, bag, jar…).', ['label' => $label]));
        }
    }

    protected function checkSupplierProduct(array &$values, callable $add): void
    {
        if ($values['supplier_code'] === null) {
            return;
        }

        /** @var SupplierProduct|null $supplierProduct */
        $supplierProduct = $this->supplier->supplierProducts()->whereRaw('lower(code) = lower(?)', [$values['supplier_code']])->first();
        if (!$supplierProduct) {
            return;
        }

        $values['supplier_product_id'] = $supplierProduct->id;
        $add('block', 'update_supplier_product', Column::SUPPLIER_CODE, __(':supplier already has :code, it will be updated with this row.', ['supplier' => $this->supplier->code, 'code' => $supplierProduct->code]));

        if ($values['unit_cost'] !== null && (float)$supplierProduct->cost > 0) {
            $change = ($values['unit_cost'] - (float)$supplierProduct->cost) / (float)$supplierProduct->cost;
            if (abs($change) > self::COST_CHANGE_LIMIT) {
                $add('block', 'cost_change', Column::UNIT_COST, __('Cost was :old, sheet says :new (:change%).', ['old' => (float)$supplierProduct->cost, 'new' => $values['unit_cost'], 'change' => sprintf('%+d', round($change * 100))]));
            }
        }

        $unitsPerCarton = $values['units_per_sko'] && $values['skos_per_carton'] ? $values['units_per_sko'] * $values['skos_per_carton'] : null;
        if ($unitsPerCarton && $supplierProduct->units_per_carton && (int)$supplierProduct->units_per_carton !== $unitsPerCarton) {
            $add('block', 'carton_changed', Column::SKOS_PER_CARTON, __('Carton was :old units, sheet says :new. Open purchase orders keep their quantities.', ['old' => $supplierProduct->units_per_carton, 'new' => $unitsPerCarton]));
        }
    }

    protected function checkPacking(array $values, callable $add): void
    {
        $outer  = $values['skos_per_outer'];
        $carton = $values['skos_per_carton'];
        if ($outer === null || $carton === null) {
            return;
        }

        if ($outer > $carton) {
            $add('block', 'outer_bigger_than_carton', Column::SKOS_PER_OUTER, __('An outer of :outer SKOs needs more than one carton of :carton SKOs.', ['outer' => $outer, 'carton' => $carton]));
        } elseif ($carton % $outer !== 0) {
            $add('block', 'carton_not_split_into_outers', Column::SKOS_PER_CARTON, __('A carton of :carton SKOs does not split into outers of :outer, :left left over.', ['carton' => $carton, 'outer' => $outer, 'left' => $carton % $outer]));
        }
    }

    protected function checkPrices(array $values, callable $add): void
    {
        $price = $values['recommended_price'];
        $rrp   = $values['recommended_rrp'];

        $landed = $this->landedCost($values, 'GBP');
        if ($price !== null && $landed !== null) {
            $this->checkMargin($price, $landed, '£', Column::RECOMMENDED_PRICE, $add);
        }

        $landedEur = $this->landedCost($values, 'EUR');
        if ($values['recommended_price_eur'] !== null && $landedEur !== null) {
            $this->checkMargin($values['recommended_price_eur'], $landedEur, '€', Column::RECOMMENDED_PRICE_EUR, $add);
        }

        $this->checkRetailerMargin($price, $rrp, '£', Column::RECOMMENDED_RRP, $add);
        $this->checkRetailerMargin($values['recommended_price_eur'], $values['recommended_rrp_eur'], '€', Column::RECOMMENDED_RRP_EUR, $add);

        $rate = $this->exchangeRate('GBP', 'EUR');
        if ($price !== null && $values['recommended_price_eur'] !== null && $rate) {
            $converted = $price * $rate;
            if (abs($values['recommended_price_eur'] - $converted) / $converted > self::EURO_PRICE_DEVIATION) {
                $add('block', 'euro_price_far_from_pound', Column::RECOMMENDED_PRICE_EUR, __('€:eur is far from the £ price (£:gbp = €:converted).', [
                    'eur'       => $values['recommended_price_eur'],
                    'gbp'       => $price,
                    'converted' => round($converted, 2),
                ]));
            }
        }
    }

    protected function landedCost(array $values, string $currency): ?float
    {
        if ($values['unit_cost'] === null || $values['extra_costs'] === null || !$this->supplier->currency) {
            return null;
        }

        $rate = $this->exchangeRate($this->supplier->currency->code, $currency);

        return $rate ? ($values['unit_cost'] + ($values['unit_expense'] ?? 0)) * (1 + $values['extra_costs']) * $rate : null;
    }

    protected function checkMargin(float $price, float $landed, string $symbol, Column $column, callable $add): void
    {
        $margin = ($price - $landed) / $price;
        if ($landed >= $price) {
            $add('block', 'below_cost_'.$column->value, $column, __('Selling below cost: landed :symbol:landed vs price :symbol:price.', ['symbol' => $symbol, 'landed' => round($landed, 2), 'price' => $price]));
        } elseif ($margin < self::TARGET_MARGIN) {
            $add('block', 'low_margin_'.$column->value, $column, __(':symbol margin :margin%: landed :symbol:landed vs price :symbol:price, target :target%.', [
                'margin' => round($margin * 100),
                'symbol' => $symbol,
                'landed' => round($landed, 2),
                'price'  => $price,
                'target' => self::TARGET_MARGIN * 100,
            ]));
        }
    }

    protected function checkRetailerMargin(?float $price, ?float $rrp, string $symbol, Column $column, callable $add): void
    {
        if ($price === null || $rrp === null) {
            return;
        }

        if ($rrp <= $price) {
            $add('block', 'rrp_not_above_price_'.$column->value, $column, __('RRP :symbol:rrp is not above the price :symbol:price, the retailer makes no money.', ['symbol' => $symbol, 'rrp' => $rrp, 'price' => $price]));

            return;
        }

        $margin = ($rrp - $price) / $rrp;
        if ($margin < self::MINIMUM_RETAILER_MARGIN) {
            $add('block', 'low_retailer_margin_'.$column->value, $column, __(':symbol retailer margin :margin% (RRP :symbol:rrp, price :symbol:price), our usual is about 58%.', ['symbol' => $symbol, 'margin' => round($margin * 100), 'rrp' => $rrp, 'price' => $price]));
        } elseif ($margin < self::LOW_RETAILER_MARGIN) {
            $add('warning', 'retailer_margin_'.$column->value, $column, __(':symbol retailer margin :margin% is on the low side (usual about 58%).', ['symbol' => $symbol, 'margin' => round($margin * 100)]));
        }
    }

    protected function checkPhysical(array $values, callable $add): void
    {
        $units = $values['units_per_sko'];

        if ($values['unit_weight'] && $values['sko_weight'] && $units) {
            $expected = $values['unit_weight'] * $units;
            if ($values['sko_weight'] < $expected * 0.95) {
                $add('warning', 'sko_lighter_than_units', Column::SKO_WEIGHT, __('SKO weighs :sko g but :units units × :unit g = :expected g.', ['sko' => $values['sko_weight'], 'units' => $units, 'unit' => $values['unit_weight'], 'expected' => $expected]));
            } elseif ($values['sko_weight'] > $expected * 3) {
                $add('warning', 'sko_much_heavier_than_units', Column::SKO_WEIGHT, __('SKO weighs :sko g, more than 3× its :units units (:expected g). Heavy packaging or a typo?', ['sko' => $values['sko_weight'], 'units' => $units, 'expected' => $expected]));
            }
        }

        if ($values['sko_weight'] && $values['carton_weight'] && $values['skos_per_carton']) {
            $expected = $values['sko_weight'] * $values['skos_per_carton'];
            if ($values['carton_weight'] < $expected * 0.95) {
                $add('warning', 'carton_lighter_than_skos', Column::CARTON_WEIGHT, __('Carton weighs :carton g but :skos SKOs × :sko g = :expected g.', ['carton' => $values['carton_weight'], 'skos' => $values['skos_per_carton'], 'sko' => $values['sko_weight'], 'expected' => $expected]));
            } elseif ($values['carton_weight'] > $expected * 3) {
                $add('warning', 'carton_much_heavier_than_skos', Column::CARTON_WEIGHT, __('Carton weighs :carton g, more than 3× its :skos SKOs (:expected g).', ['carton' => $values['carton_weight'], 'skos' => $values['skos_per_carton'], 'expected' => $expected]));
            }
        }

        $unitVolume   = $this->volume($values['unit_dimensions']);
        $skoVolume    = $this->volume($values['sko_dimensions']);
        $cartonVolume = $values['carton_cbm'] ? $values['carton_cbm'] * 1_000_000 : null;

        $this->checkDensity($values['unit_weight'], $unitVolume, 'unit', Column::UNIT_DIMENSIONS, $add);
        $this->checkDensity($values['sko_weight'], $skoVolume, 'SKO', Column::SKO_DIMENSIONS, $add);
        $this->checkDensity($values['carton_weight'], $cartonVolume, 'carton', Column::CARTON_CBM, $add);

        if ($unitVolume && $skoVolume && $units && $skoVolume < $unitVolume * $units * 0.9) {
            $add('warning', 'units_do_not_fit_sko', Column::SKO_DIMENSIONS, __(':units units of :unit cm³ do not fit in an SKO of :sko cm³.', ['units' => $units, 'unit' => round($unitVolume), 'sko' => round($skoVolume)]));
        }

        if ($skoVolume && $cartonVolume && $values['skos_per_carton'] && $cartonVolume < $skoVolume * $values['skos_per_carton'] * 0.9) {
            $add('warning', 'skos_do_not_fit_carton', Column::CARTON_CBM, __(':skos SKOs of :sko cm³ do not fit in a carton of :cbm m³.', ['skos' => $values['skos_per_carton'], 'sko' => round($skoVolume), 'cbm' => $values['carton_cbm']]));
        }
    }

    protected function volume(?array $dimensions): ?float
    {
        return $dimensions ? $dimensions['l'] * $dimensions['w'] * $dimensions['h'] : null;
    }

    protected function checkDensity(?int $grams, ?float $volume, string $what, Column $column, callable $add): void
    {
        if (!$grams || !$volume) {
            return;
        }

        $density = $grams / $volume;
        if ($density < self::MINIMUM_DENSITY || $density > self::MAXIMUM_DENSITY) {
            $add('warning', 'density_'.$column->value, $column, __('The :what weighs :grams g in :volume cm³ (:density g/cm³), check the units of weight and size.', [
                'what'    => $what,
                'grams'   => $grams,
                'volume'  => round($volume, 1),
                'density' => round($density, 4),
            ]));
        }
    }

    protected function checkAgainstSupplier(array $values, callable $add): void
    {
        $others = $this->supplierAverages();

        $compare = function (?float $value, ?float $typical, string $code, Column $column, string $unit) use ($add): void {
            if ($value === null || !$typical) {
                return;
            }
            if ($value < $typical / 2 || $value > $typical * 2) {
                $add('warning', $code, $column, __(':column :value:unit, this supplier\'s other products are about :typical:unit.', ['column' => $column->heading(), 'value' => $value, 'typical' => round($typical, 2), 'unit' => $unit]));
            }
        };

        $compare($values['delivery_days'], $others['delivery_time'], 'delivery_unusual', Column::DELIVERY_DAYS, ' days');
        $compare($values['minimum_order_cartons'], $others['minimum_carton_order'], 'minimum_order_unusual', Column::MINIMUM_ORDER_CARTONS, '');
        $compare($values['extra_costs'] === null ? null : $values['extra_costs'] * 100, $others['extra_costs'] === null ? null : $others['extra_costs'] * 100, 'extra_costs_unusual', Column::EXTRA_COSTS, '%');
        $compare($values['carton_cbm'], $others['cbm'], 'cbm_unusual', Column::CARTON_CBM, ' m³');
    }

    /**
     * @return array{delivery_time: ?float, minimum_carton_order: ?float, extra_costs: ?float, cbm: ?float}
     */
    protected function supplierAverages(): array
    {
        return $this->supplierAverages ??= (array)$this->supplier->supplierProducts()
            ->toBase()
            ->selectRaw("percentile_cont(0.5) within group (order by (data->>'delivery_time')::numeric) filter (where data->>'delivery_time' ~ '^[0-9.]+$') as delivery_time")
            ->selectRaw("percentile_cont(0.5) within group (order by (data->>'minimum_carton_order')::numeric) filter (where data->>'minimum_carton_order' ~ '^[0-9.]+$') as minimum_carton_order")
            ->selectRaw('percentile_cont(0.5) within group (order by extra_costs) filter (where extra_costs > 0) as extra_costs')
            ->selectRaw('percentile_cont(0.5) within group (order by cbm) filter (where cbm > 0) as cbm')
            ->first();
    }

    protected function checkOrder(array $values, callable $add): void
    {
        $total = array_sum($values['order']);
        if ($total > 0 && $values['minimum_order_cartons'] && $total < $values['minimum_order_cartons']) {
            $add('block', 'below_minimum_order', Column::MINIMUM_ORDER_CARTONS, __('Ordered :total cartons, supplier minimum is :minimum.', ['total' => $total, 'minimum' => $values['minimum_order_cartons']]));
        }
    }

    public function organisationForOrderColumn(Supplier $supplier, string $key): ?Organisation
    {
        $this->organisations ??= Organisation::whereIn('id', $supplier->orgSuppliers()->select('organisation_id'))->with('country')->get();

        $key = strtoupper($key);

        return $this->organisations->first(fn (Organisation $organisation) => strtoupper($organisation->code) === $key)
            ?? $this->organisations->first(fn (Organisation $organisation) => $organisation->country?->code === ($key === 'UK' ? 'GB' : $key) && strtoupper($organisation->code) !== 'AROMA');
    }

    protected function exchangeRate(string $from, string $to): ?float
    {
        $key = $from.'-'.$to;
        if (!array_key_exists($key, $this->exchangeRates)) {
            try {
                $base   = Currency::where('code', $from)->first();
                $target = Currency::where('code', $to)->first();

                $this->exchangeRates[$key] = $base && $target ? GetCurrencyExchange::run($base, $target) : null;
            } catch (Throwable) {
                $this->exchangeRates[$key] = null;
            }
        }

        return $this->exchangeRates[$key];
    }
}
