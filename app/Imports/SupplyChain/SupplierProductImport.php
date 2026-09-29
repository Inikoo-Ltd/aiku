<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-16h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
 */

namespace App\Imports\SupplyChain;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\SyncSupplierProductTradeUnits;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Imports\WithImport;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Country;
use App\Models\Helpers\Upload;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

class SupplierProductImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents, WithMultipleSheets, WithCalculatedFormulas
{
    use WithImport;

    protected Supplier $scope;

    public function __construct(Supplier $supplier, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope  = $supplier;
    }

    protected ?Collection $productRows = null;

    /** @var array<int, array<int, mixed>>|null */
    protected ?array $orderSheetRows = null;

    /** @var Collection<int, Organisation>|null */
    protected ?Collection $supplierOrganisations = null;

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => fn (BeforeImport $event) => $this->orderSheetRows = $this->orderSheetRows($event->getReader()->getDelegate()),
            AfterImport::class  => fn () => $this->processSheets(),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>|null
     */
    protected function orderSheetRows(Spreadsheet $spreadsheet): ?array
    {
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if (strcasecmp(trim($worksheet->getTitle()), 'order') === 0) {
                try {
                    return $worksheet->toArray(null, true, false, false);
                } catch (Throwable) {
                    return $worksheet->toArray(null, false, false, false);
                }
            }
        }

        return null;
    }

    public function collection(Collection $collection): void
    {
        $this->productRows = $collection
            ->map(fn (Collection $row) => $this->cleanRow($row))
            ->filter(fn (Collection $row) => $row->contains(fn ($value) => $this->cleanString($value) !== null));
    }

    public function processSheets(): void
    {
        $rows  = $this->productRows ?? collect();
        $order = $this->orderSheetRows === null ? null : $this->readOrderSheet($this->orderSheetRows);

        $this->upload->update(['number_rows' => $rows->count()]);

        $mistakes      = $this->sheetMistakes($rows);
        $orderMistakes = $order === null ? [] : $this->orderSheetMistakes($order, $rows);

        if ($mistakes === [] && $orderMistakes === []) {
            foreach ($rows as $index => $row) {
                $this->storeModel($row, $this->createUploadRecord($row, $index + 2));
            }

            if ($order !== null) {
                $this->createDraftPurchaseOrders($order);
            }

            return;
        }

        foreach ($rows as $index => $row) {
            $this->setRecordAsFailed(
                $this->createUploadRecord($row, $index + 2),
                $mistakes[$index] ?? [__('Not created: other rows in this sheet have mistakes. Fix them and upload the whole sheet again.')]
            );
        }

        foreach ($orderMistakes as $mistake) {
            $this->addSheetRecord(['sheet' => 'ORDER'], UploadRecordStatusEnum::FAILED, [$mistake]);
        }
    }

    /**
     * @param array<int, array<int, mixed>> $sheetRows
     *
     * @return array{missing?: string, headings: array<int, string>, organisations: array<int, Organisation>, lines: array<int, array{code: string, cost: ?string, carton: ?string, cartons: array<int, ?string>}>, totals: array<int, array<int, ?string>>}
     */
    protected function readOrderSheet(array $sheetRows): array
    {
        $order = ['headings' => [], 'organisations' => [], 'lines' => [], 'totals' => []];

        $headerIndex = collect($sheetRows)->search(fn (array $cells) => collect($cells)->contains(fn ($cell) => in_array($this->heading($cell), ['product code', 'code'], true)));
        if ($headerIndex === false) {
            return $order + ['missing' => __('ORDER tab: there is no "Product Code" column heading.')];
        }

        $headings     = array_map(fn ($cell) => $this->heading($cell), $sheetRows[$headerIndex]);
        $codeColumn   = array_search('product code', $headings, true);
        $codeColumn   = $codeColumn === false ? array_search('code', $headings, true) : $codeColumn;
        $costColumn   = array_search('unit cost', $headings, true);
        $cartonColumn = array_search('carton', $headings, true);

        foreach ($headings as $column => $heading) {
            $organisation = $this->organisationForHeading($heading);
            if ($organisation && !collect($order['organisations'])->contains('id', $organisation->id)) {
                $order['organisations'][$column] = $organisation;
                $order['headings'][$column]      = $this->cleanString($sheetRows[$headerIndex][$column]);
            }
        }

        foreach (array_slice($sheetRows, $headerIndex + 1, null, true) as $index => $cells) {
            $cartons = [];
            foreach (array_keys($order['organisations']) as $column) {
                $cartons[$column] = $this->cleanString($cells[$column] ?? null);
            }

            $code = $this->cleanString($cells[$codeColumn] ?? null);
            if ($code !== null) {
                $order['lines'][$index + 1] = [
                    'code'    => $code,
                    'cost'    => $costColumn === false ? null : $this->cleanString($cells[$costColumn] ?? null),
                    'carton'  => $cartonColumn === false ? null : $this->cleanString($cells[$cartonColumn] ?? null),
                    'cartons' => $cartons,
                ];
            } elseif (array_filter($cartons, fn ($value) => $value !== null) !== []) {
                $order['totals'][$index + 1] = $cartons;
            }
        }

        return $order;
    }

    /**
     * @param array{missing?: string, headings: array<int, string>, organisations: array<int, Organisation>, lines: array<int, array{code: string, cost: ?string, carton: ?string, cartons: array<int, ?string>}>, totals: array<int, array<int, ?string>>} $order
     * @param Collection<int, Collection> $productRows
     *
     * @return list<string>
     */
    protected function orderSheetMistakes(array $order, Collection $productRows): array
    {
        if (isset($order['missing'])) {
            return [$order['missing']];
        }

        if ($order['organisations'] === []) {
            return [__('ORDER tab: no column heading matches an organisation buying from this supplier (:organisations).', [
                'organisations' => $this->supplierOrganisations()->pluck('code')->implode(', '),
            ])];
        }

        $productsTab = $productRows
            ->filter(fn (Collection $row) => $this->cleanString($row->get('suppliers_product_code')) !== null)
            ->mapWithKeys(fn (Collection $row, int $index) => [
                strtolower($this->cleanString($row->get('suppliers_product_code'))) => [
                    'source'           => __('products tab row :row', ['row' => $index + 2]),
                    'cost'             => $this->cleanString($row->get('unit_cost')),
                    'units_per_carton' => (((int)$row->get('units_per_sko')) ?: 1) * (((int)$row->get('skos_per_carton')) ?: 1),
                ],
            ]);

        $mistakes = [];
        $seen     = [];
        $sums     = array_fill_keys(array_keys($order['organisations']), 0.0);

        foreach ($order['lines'] as $row => $line) {
            $at  = __('ORDER tab row :row', ['row' => $row]);
            $key = strtolower($line['code']);

            if (isset($seen[$key])) {
                $mistakes[] = __(':at: :code is also on row :other.', ['at' => $at, 'code' => $line['code'], 'other' => $seen[$key]]);
            }
            $seen[$key] = $row;

            $product = $productsTab->get($key) ?? $this->existingProductFacts($line['code']);
            if (!$product) {
                $mistakes[] = __(':at: :code is not in the products tab and is not a product of this supplier.', ['at' => $at, 'code' => $line['code']]);

                continue;
            }

            if (is_numeric($line['cost']) && is_numeric($product['cost']) && abs((float)$line['cost'] - (float)$product['cost']) > 0.005) {
                $mistakes[] = __(':at: unit cost is :cost, but :source says :expected.', ['at' => $at, 'cost' => (float)$line['cost'], 'source' => $product['source'], 'expected' => (float)$product['cost']]);
            }

            if (is_numeric($line['carton']) && (int)$line['carton'] !== (int)$product['units_per_carton']) {
                $mistakes[] = __(':at: :carton pieces per carton, but :source says :expected.', ['at' => $at, 'carton' => (int)$line['carton'], 'source' => $product['source'], 'expected' => (int)$product['units_per_carton']]);
            }

            foreach ($line['cartons'] as $column => $cartons) {
                if ($cartons === null) {
                    continue;
                }

                if (!is_numeric($cartons) || (float)$cartons < 0) {
                    $mistakes[] = __(':at: :heading quantity ":value" is not a number of cartons.', ['at' => $at, 'heading' => $order['headings'][$column], 'value' => $cartons]);

                    continue;
                }

                $sums[$column] += (float)$cartons;
            }
        }

        foreach ($order['totals'] as $row => $totals) {
            foreach ($totals as $column => $total) {
                if (is_numeric($total) && abs((float)$total - $sums[$column]) > 0.0001) {
                    $mistakes[] = __('ORDER tab row :row: the :heading total is :total cartons, but the lines add up to :sum.', ['row' => $row, 'heading' => $order['headings'][$column], 'total' => (float)$total, 'sum' => $sums[$column]]);
                }
            }
        }

        return $mistakes;
    }

    /**
     * @return array{source: string, cost: mixed, units_per_carton: int}|null
     */
    protected function existingProductFacts(string $code): ?array
    {
        $supplierProduct = $this->supplierProductByCode($code);

        return $supplierProduct ? [
            'source'           => __("the supplier's product"),
            'cost'             => $supplierProduct->cost,
            'units_per_carton' => (int)$supplierProduct->units_per_carton,
        ] : null;
    }

    protected function supplierProductByCode(string $code): ?SupplierProduct
    {
        return $this->scope->supplierProducts()->whereRaw('lower(code) = lower(?)', [$code])->first();
    }

    /**
     * @param array{missing?: string, headings: array<int, string>, organisations: array<int, Organisation>, lines: array<int, array{code: string, cost: ?string, carton: ?string, cartons: array<int, ?string>}>, totals: array<int, array<int, ?string>>} $order
     */
    protected function createDraftPurchaseOrders(array $order): void
    {
        foreach ($order['organisations'] as $column => $organisation) {
            $heading = $order['headings'][$column];
            $lines   = array_filter($order['lines'], fn (array $line) => (float)($line['cartons'][$column] ?? 0) > 0);
            if ($lines === []) {
                continue;
            }

            $orgSupplier = $this->scope->orgSuppliers()->where('organisation_id', $organisation->id)->first();
            $parent      = $orgSupplier->org_agent_id ? $orgSupplier->orgAgent : $orgSupplier;

            try {
                $purchaseOrder = $parent->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->first()
                    ?? StorePurchaseOrder::make()->action($parent, array_filter(['buyer_id' => $this->upload->user_id]));
            } catch (Throwable $e) {
                $this->addSheetRecord(['sheet' => 'ORDER', 'organisation' => $heading], UploadRecordStatusEnum::FAILED, [
                    __('Draft order for :heading not created: :error', ['heading' => $heading, 'error' => $this->errorText($e)]),
                ]);

                continue;
            }

            $numberLines = 0;
            foreach ($lines as $row => $line) {
                try {
                    $supplierProduct    = $this->supplierProductByCode($line['code']);
                    $orgSupplierProduct = OrgSupplierProduct::where('organisation_id', $organisation->id)->where('supplier_product_id', $supplierProduct?->id)->firstOrFail();
                    $quantity           = (float)$line['cartons'][$column] * $supplierProduct->units_per_carton;
                    $transaction        = $purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $supplierProduct->id)->first();

                    if ($transaction) {
                        UpdatePurchaseOrderTransaction::make()->action($transaction, ['quantity_ordered' => $quantity]);
                    } else {
                        StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($purchaseOrder, $orgSupplierProduct, ['quantity_ordered' => $quantity]);
                    }
                    $numberLines++;
                } catch (Throwable $e) {
                    $this->addSheetRecord(['sheet' => 'ORDER', 'row' => $row, 'organisation' => $heading], UploadRecordStatusEnum::FAILED, [
                        __('ORDER tab row :row: :code not added to :reference (:heading): :error', ['row' => $row, 'code' => $line['code'], 'reference' => $purchaseOrder->reference, 'heading' => $heading, 'error' => $this->errorText($e)]),
                    ]);
                }
            }

            $this->addSheetRecord([
                'sheet'          => 'ORDER',
                'organisation'   => $heading,
                'purchase_order' => $purchaseOrder->reference,
                'lines'          => $numberLines,
            ], UploadRecordStatusEnum::COMPLETE);
        }
    }

    protected function errorText(Throwable $e): string
    {
        return $e instanceof ValidationException ? collect($e->errors())->flatten()->implode(' ') : $e->getMessage();
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $errors
     */
    protected function addSheetRecord(array $values, UploadRecordStatusEnum $status, array $errors = []): void
    {
        $this->upload->records()->create([
            'values' => $values,
            'status' => $status,
            'errors' => $errors,
        ]);
        $this->updateStats();
    }

    /**
     * @return Collection<int, Organisation>
     */
    protected function supplierOrganisations(): Collection
    {
        return $this->supplierOrganisations ??= Organisation::whereIn('id', $this->scope->orgSuppliers()->select('organisation_id'))
            ->with('country')
            ->orderBy('id')
            ->get();
    }

    protected function organisationForHeading(string $heading): ?Organisation
    {
        $heading = strtoupper($heading);
        if ($heading === '') {
            return null;
        }

        $organisations = $this->supplierOrganisations();

        $organisation = $organisations->first(fn (Organisation $organisation) => strtoupper($organisation->code) === $heading || strtoupper($organisation->slug) === $heading);
        if ($organisation) {
            return $organisation;
        }

        $countryCode = $heading === 'UK' ? 'GB' : $heading;

        return $organisations->first(fn (Organisation $organisation) => $organisation->country?->code === $countryCode);
    }

    protected function heading(mixed $cell): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim((string)$cell)));
    }

    /**
     * @param Collection<int, Collection> $rows
     *
     * @return array<int, list<string>>
     */
    protected function sheetMistakes(Collection $rows): array
    {
        $mistakes = [];
        $newRows  = $rows->filter(fn (Collection $row) => strtolower((string)$this->cleanString($row->get('id_supplier_part_key'))) === 'new');

        foreach ($newRows as $index => $row) {
            $code = $this->cleanString($row->get('suppliers_product_code'));
            if ($code === null) {
                $mistakes[$index][] = __("Supplier's product code is missing.");
            } elseif ($this->scope->supplierProducts()->whereRaw('lower(code) = lower(?)', [$code])->exists()) {
                $mistakes[$index][] = __(':code already exists for this supplier, use its Id instead of "new".', ['code' => $code]);
            }

            if ($this->cleanString($row->get('part_reference')) === null) {
                $mistakes[$index][] = __('Part reference is missing.');
            }

            $cost = $this->cleanString($row->get('unit_cost'));
            if (!is_numeric($cost) || (float)$cost <= 0) {
                $mistakes[$index][] = __('Unit cost must be a number above zero, found ":cost".', ['cost' => $cost]);
            }
        }

        foreach ($rows as $index => $row) {
            $barcode = $this->cleanString($row->get('unit_barcode_ean_13_for_website'));
            if ($barcode !== null && strtolower($barcode) !== 'auto' && $this->gtinOrNull($barcode) === null) {
                $mistakes[$index][] = __('Unit barcode ":barcode" is not a barcode (8 to 14 digits).', ['barcode' => $barcode]);
            }
        }

        foreach (['suppliers_product_code' => __("Supplier's product code"), 'part_reference' => __('Part reference')] as $column => $label) {
            $newRows->groupBy(fn (Collection $row) => strtolower((string)$this->cleanString($row->get($column))), true)
                ->filter(fn (Collection $group, string $value) => $value !== '' && $group->count() > 1)
                ->each(function (Collection $group) use (&$mistakes, $column, $label) {
                    foreach ($group->keys() as $index) {
                        $mistakes[$index][] = __(':label :value appears in rows :rows.', ['label' => $label, 'value' => $group->first()->get($column), 'rows' => $this->rowList($group->keys())]);
                    }
                });
        }

        $newRows->groupBy(fn (Collection $row) => (string)$this->codePrefix($row->get('part_reference')), true)
            ->filter(fn (Collection $group, string $prefix) => $prefix !== '')
            ->each(function (Collection $group, string $prefix) use (&$mistakes) {
                $families = $group->groupBy(fn (Collection $row) => (string)$this->cleanString($row->get('family')), true);

                if ($families->count() > 1) {
                    $summary = $families->map(fn (Collection $rows, string $family) => ($family ?: __('no family')).' ('.__('rows').' '.$this->rowList($rows->keys()).')')->implode(', ');
                    foreach ($group->keys() as $index) {
                        $mistakes[$index][] = __(':prefix products have different families in this sheet: :summary. Use one family.', ['prefix' => $prefix, 'summary' => $summary]);
                    }

                    return;
                }

                $familyCode = $families->keys()->first();
                $family     = $familyCode === '' ? null : StockFamily::where('group_id', $this->scope->group_id)->where('code', $familyCode)->first();
                if (!$family) {
                    return;
                }

                $familyPrefixes = $family->stocks()->pluck('code')->map(fn ($code) => $this->codePrefix($code))->filter()->unique();
                if ($familyPrefixes->isNotEmpty() && !$familyPrefixes->contains($prefix)) {
                    foreach ($group->keys() as $index) {
                        $mistakes[$index][] = __('Family :family holds :prefixes products, :prefix does not look like it belongs there.', ['family' => $familyCode, 'prefixes' => $familyPrefixes->implode(', '), 'prefix' => $prefix]);
                    }
                }
            });

        return $mistakes;
    }

    protected function codePrefix(mixed $code): ?string
    {
        $code = $this->cleanString($code);
        if ($code === null || !str_contains($code, '-')) {
            return null;
        }

        return strtoupper(substr($code, 0, strrpos($code, '-')));
    }

    /**
     * @param Collection<int, int> $indexes
     */
    protected function rowList(Collection $indexes): string
    {
        $ranges = [];
        foreach ($indexes->map(fn (int $index) => $index + 2)->sort()->values() as $row) {
            $last = array_key_last($ranges);
            if ($last !== null && $ranges[$last][1] === $row - 1) {
                $ranges[$last][1] = $row;
            } else {
                $ranges[] = [$row, $row];
            }
        }

        return collect($ranges)->map(fn (array $range) => $range[0] === $range[1] ? $range[0] : $range[0].'-'.$range[1])->implode(', ');
    }

    public function storeModel(Collection $row, $uploadRecord): void
    {
        $data = $row->all();

        try {
            $tradeUnit = $this->resolveOrCreateTradeUnit($data);

            $stockFamily = $this->resolveOrCreateStockFamily($data);
            $this->resolveOrCreateStock($tradeUnit, $stockFamily, $data);

            $unitsPerSko   = ((int)Arr::get($data, 'units_per_sko')) ?: 1;
            $skosPerCarton = ((int)Arr::get($data, 'skos_per_carton')) ?: 1;

            $modelData = $this->onlyFilled([
                'code'                 => Arr::get($data, 'suppliers_product_code'),
                'name'                 => Arr::get($data, 'suppliers_unit_description'),
                'cost'                 => Arr::get($data, 'unit_cost'),
                'cbm'                  => Arr::get($data, 'carton_cbm'),
                'extra_costs'          => Arr::get($data, 'unit_extra_costs'),
                'minimum_carton_order' => Arr::get($data, 'minimum_order_cartons'),
                'delivery_time'        => Arr::get($data, 'average_delivery_time_days'),
            ]);

            $modelData['units_per_pack']   = $unitsPerSko;
            $modelData['units_per_carton'] = $unitsPerSko * $skosPerCarton;

            $seed = $this->onlyFilled([
                'recommended_price' => Arr::get($data, 'unit_recommended_price'),
                'recommended_rrp'   => Arr::get($data, 'unit_recommended_rrp'),
            ]);
            if ($seed !== []) {
                $modelData['data']['seed'] = $seed;
            }

            $sourceImport = $this->onlyFilled([
                'unit_expense'                       => Arr::get($data, 'unit_expense'),
                'recommended_skos_per_selling_outer' => Arr::get($data, 'recommended_skos_per_selling_outer'),
            ]);
            if ($sourceImport !== []) {
                $modelData['data']['source_import'] = $sourceImport;
            }

            $availability = $this->cleanString(Arr::get($data, 'availability'));
            if ($availability !== null) {
                $modelData['is_available'] = strtolower($availability) === 'available';
            }

            $supplierProduct = $this->storeOrUpdate(Arr::get($data, 'id_supplier_part_key'), $modelData);

            if ($tradeUnit) {
                SyncSupplierProductTradeUnits::run($supplierProduct, [
                    $tradeUnit->id => ['quantity' => $unitsPerSko],
                ]);
            }

            $this->setRecordAsCompleted($uploadRecord);
        } catch (\Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'id_supplier_part_key'                 => ['sometimes', 'nullable'],
            'suppliers_product_code'                => ['sometimes', 'nullable'],
            'suppliers_unit_description'             => ['sometimes', 'nullable'],
            'family'                                => ['sometimes', 'nullable'],
            'part_reference'                        => ['sometimes', 'nullable'],
            'unit_label'                            => ['sometimes', 'nullable'],
            'units_per_sko'                         => ['sometimes', 'nullable'],
            'sko_description_picking_aid'           => ['sometimes', 'nullable'],
            'sko_barcode'                           => ['sometimes', 'nullable'],
            'skos_per_carton'                       => ['sometimes', 'nullable'],
            'recommended_skos_per_selling_outer'    => ['sometimes', 'nullable'],
            'minimum_order_cartons'                 => ['sometimes', 'nullable'],
            'average_delivery_time_days'            => ['sometimes', 'nullable'],
            'carton_cbm'                            => ['sometimes', 'nullable'],
            'unit_cost'                             => ['sometimes', 'nullable'],
            'unit_expense'                          => ['sometimes', 'nullable'],
            'unit_extra_costs'                      => ['sometimes', 'nullable'],
            'unit_recommended_price'                => ['sometimes', 'nullable'],
            'unit_recommended_rrp'                  => ['sometimes', 'nullable'],
            'unit_recommended_description_website'  => ['sometimes', 'nullable'],
            'unit_barcode_ean_13_for_website'        => ['sometimes', 'nullable'],
            'unit_weight_kg'                        => ['sometimes', 'nullable'],
            'unit_dimensions_l_x_w_x_h_in_cm'        => ['sometimes', 'nullable'],
            'sko_weight_kg'                          => ['sometimes', 'nullable'],
            'sko_dimensions_l_x_w_x_h_in_cm'         => ['sometimes', 'nullable'],
            'materials'                              => ['sometimes', 'nullable'],
            'country_of_origin'                      => ['sometimes', 'nullable'],
            'tariff_code'                            => ['sometimes', 'nullable'],
            'duty_rate'                              => ['sometimes', 'nullable'],
            'htsus'                                  => ['sometimes', 'nullable'],
            'un_number'                              => ['sometimes', 'nullable'],
            'un_class'                               => ['sometimes', 'nullable'],
            'packing_group'                          => ['sometimes', 'nullable'],
            'proper_shipping_name'                   => ['sometimes', 'nullable'],
            'hazard_identification_number'           => ['sometimes', 'nullable'],
            'cpnp_number'                            => ['sometimes', 'nullable'],
            'ufi'                                    => ['sometimes', 'nullable'],
            'carton_weight'                          => ['sometimes', 'nullable'],
            'carton_barcode'                         => ['sometimes', 'nullable'],
            'availability'                           => ['sometimes', 'nullable'],
        ];
    }

    /**
     * @param array<string, mixed> $modelData
     *
     * @throws \Throwable
     */
    protected function storeOrUpdate(mixed $partKey, array $modelData): SupplierProduct
    {
        if (is_numeric($partKey)) {
            $supplierProduct = $this->scope->supplierProducts()->find((int)$partKey);

            if (!$supplierProduct) {
                throw new Exception("Supplier product not found: $partKey");
            }

            return UpdateSupplierProduct::make()->action($supplierProduct, $modelData, strict: false);
        }

        if (strtolower((string)$this->cleanString($partKey)) === 'new') {
            return StoreSupplierProduct::make()->action($this->scope, $modelData, strict: false);
        }

        throw new Exception('Part key not found, use an existing key or "new"');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Throwable
     */
    protected function resolveOrCreateTradeUnit(array $data): ?TradeUnit
    {
        $reference = $this->cleanString(Arr::get($data, 'part_reference'));

        if ($reference === null) {
            return null;
        }

        $tradeUnit = TradeUnit::where('group_id', $this->scope->group_id)
            ->where('code', $reference)
            ->first();

        if ($tradeUnit) {
            return $tradeUnit;
        }

        $modelData = $this->onlyFilled([
            'code'        => $reference,
            'name'        => Arr::get($data, 'unit_label'),
            'description' => Arr::get($data, 'unit_recommended_description_website'),
            'barcode'     => $this->gtinOrNull(Arr::get($data, 'unit_barcode_ean_13_for_website')),
            'tariff_code' => Arr::get($data, 'tariff_code'),
        ]);

        $weight = Arr::get($data, 'unit_weight_kg');
        if ($weight !== null && $weight !== '') {
            $grams                     = (int)round(((float)$weight) * 1000);
            $modelData['net_weight']   = $grams;
            $modelData['gross_weight'] = $grams;
        }

        $dimensions = $this->parseDimensions(Arr::get($data, 'unit_dimensions_l_x_w_x_h_in_cm'));
        if ($dimensions !== null) {
            $modelData['marketing_dimensions'] = $dimensions;
        }

        $countryOfOrigin = $this->cleanString(Arr::get($data, 'country_of_origin'));
        if ($countryOfOrigin !== null) {
            $country = Country::where('iso3', strtoupper($countryOfOrigin))
                ->orWhere('code', strtoupper($countryOfOrigin))
                ->orWhere('name', $countryOfOrigin)
                ->first();
            if ($country) {
                $modelData['origin_country_id'] = $country->id;
            }
        }

        $sourceImport = $this->onlyFilled([
            'materials'                    => Arr::get($data, 'materials'),
            'duty_rate'                    => Arr::get($data, 'duty_rate'),
            'hts_us'                       => Arr::get($data, 'htsus'),
            'un_number'                    => Arr::get($data, 'un_number'),
            'un_class'                     => Arr::get($data, 'un_class'),
            'packing_group'                => Arr::get($data, 'packing_group'),
            'proper_shipping_name'         => Arr::get($data, 'proper_shipping_name'),
            'hazard_identification_number' => Arr::get($data, 'hazard_identification_number'),
            'cpnp_number'                  => Arr::get($data, 'cpnp_number'),
            'ufi_number'                   => Arr::get($data, 'ufi'),
            'sko_weight_kg'                => Arr::get($data, 'sko_weight_kg'),
            'sko_dimensions'               => Arr::get($data, 'sko_dimensions_l_x_w_x_h_in_cm'),
            'carton_weight'                => Arr::get($data, 'carton_weight'),
            'carton_barcode'               => Arr::get($data, 'carton_barcode'),
        ]);
        if ($sourceImport !== []) {
            $modelData['data']['source_import'] = $sourceImport;
        }

        return StoreTradeUnit::make()->action($this->scope->group, $modelData, strict: false);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Throwable
     */
    protected function resolveOrCreateStockFamily(array $data): ?StockFamily
    {
        $family = $this->cleanString(Arr::get($data, 'family'));

        if ($family === null) {
            return null;
        }

        $stockFamily = StockFamily::where('group_id', $this->scope->group_id)
            ->where('code', $family)
            ->first();

        if ($stockFamily) {
            return $stockFamily;
        }

        return StoreStockFamily::make()->action($this->scope->group, [
            'code' => $family,
            'name' => $family,
        ], strict: false);
    }

    /**
     * @throws \Throwable
     */
    protected function resolveOrCreateStock(?TradeUnit $tradeUnit, ?StockFamily $stockFamily, array $data): ?Stock
    {
        if (!$tradeUnit) {
            return null;
        }

        $stock = $tradeUnit->stocks()->first();

        if ($stock) {
            return $stock;
        }

        $unitsPerSko = ((int)Arr::get($data, 'units_per_sko')) ?: 1;

        $stock = StoreStock::make()->action($stockFamily ?? $this->scope->group, [
            'code' => $tradeUnit->code,
            'name' => $tradeUnit->name,
        ], strict: false);

        SyncStockTradeUnits::run($stock, [
            $tradeUnit->id => ['quantity' => $unitsPerSko],
        ]);

        return $stock;
    }

    protected function parseDimensions(mixed $value): ?array
    {
        $value = $this->cleanString($value);
        if ($value === null) {
            return null;
        }

        $parts = preg_split('/\s*x\s*/i', $value);
        if (count($parts) !== 3 || !is_numeric($parts[0]) || !is_numeric($parts[1]) || !is_numeric($parts[2])) {
            return null;
        }

        return [
            'l' => (float)$parts[0],
            'w' => (float)$parts[1],
            'h' => (float)$parts[2],
        ];
    }

    /**
     * @param array<string, mixed> $modelData
     *
     * @return array<string, mixed>
     */
    protected function onlyFilled(array $modelData): array
    {
        return array_filter($modelData, fn ($value) => $value !== null && $value !== '');
    }

    protected function gtinOrNull(mixed $value): ?string
    {
        $value = $this->cleanString($value);

        return $value !== null && preg_match('/^\d{8,14}$/', $value) ? $value : null;
    }

    protected function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim((string)$value) ?: null;
    }
}
