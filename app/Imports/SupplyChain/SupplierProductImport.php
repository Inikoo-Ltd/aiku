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
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SupplierProductImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    protected Supplier $scope;

    public function __construct(Supplier $supplier, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope  = $supplier;
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
            'barcode'     => Arr::get($data, 'unit_barcode_ean_13_for_website'),
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

    protected function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim((string)$value) ?: null;
    }
}
