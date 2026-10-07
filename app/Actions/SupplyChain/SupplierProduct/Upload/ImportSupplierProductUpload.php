<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\Goods\Barcode\AssignNextBarcodeToTradeUnit;
use App\Actions\Goods\Barcode\StoreBarcode;
use App\Actions\Goods\Barcode\SyncBarcodeToTradeUnit;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\TradeUnit\AttachTradeUnitsToTradeUnitFamily;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Goods\TradeUnitFamily\StoreTradeUnitFamily;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\SyncSupplierProductTradeUnits;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Enums\Helpers\Barcode\BarcodeTypeEnum;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\Helpers\Import\UploadStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Creates what a confirmed supplier product upload preview describes: families, trade units, barcodes,
 * SKOs, supplier products and the draft purchase orders from the order columns.
 */
class ImportSupplierProductUpload
{
    use AsAction;

    public function handle(Upload $upload): Upload
    {
        /** @var Supplier $supplier */
        $supplier = $upload->parent;

        $problems = $this->problems($upload);
        if ($problems !== []) {
            throw ValidationException::withMessages(['upload' => $problems]);
        }

        $upload->update(['state' => UploadStateEnum::IMPORTING]);

        $records = $this->importableRecords($upload);
        foreach ($records as $record) {
            try {
                DB::transaction(fn () => $this->importRecord($supplier, $record));
                $record->update(['status' => UploadRecordStatusEnum::COMPLETE, 'errors' => []]);
            } catch (Throwable $e) {
                $record->update(['status' => UploadRecordStatusEnum::FAILED, 'errors' => [$this->errorText($e)]]);
            }
        }

        $this->createDraftPurchaseOrders($supplier, $upload, $records->filter(fn (UploadRecord $record) => Arr::get($record->data, 'supplier_product_id')));

        $upload->update([
            'state'          => UploadStateEnum::IMPORTED,
            'uploaded_at'    => now(),
            'number_success' => $upload->records()->where('status', UploadRecordStatusEnum::COMPLETE)->count(),
            'number_fails'   => $upload->records()->where('status', UploadRecordStatusEnum::FAILED)->count(),
        ]);

        return $upload;
    }

    /**
     * @return list<string>
     */
    public function problems(Upload $upload): array
    {
        if ($upload->state !== UploadStateEnum::WAITING_CONFIRMATION) {
            return [__('This upload is not waiting for confirmation.')];
        }

        $problems = [];
        if (in_array(Arr::get($upload->data, 'ai'), ['queued', 'running'], true)) {
            $problems[] = __('The AI checks are still running.');
        }

        foreach ($upload->records()->where('status', UploadRecordStatusEnum::PREVIEW)->orderBy('row_number')->get() as $record) {
            if (Arr::get($record->data, 'skip')) {
                continue;
            }

            foreach (Arr::get($record->data, 'findings', []) as $finding) {
                if ($finding['level'] === 'error') {
                    $problems[] = __('Row :row: :message', ['row' => $record->row_number, 'message' => $finding['message']]);
                } elseif (in_array($finding['level'], ['block', 'link'], true) && !Arr::get($record->data, 'decisions.'.$finding['code'].'.accepted')) {
                    $problems[] = __('Row :row needs a decision: :message', ['row' => $record->row_number, 'message' => $finding['message']]);
                }
            }

            if (blank(Arr::get($record->values, 'sko_name'))) {
                $problems[] = __('Row :row: the SKO name is missing.', ['row' => $record->row_number]);
            }
        }

        return $problems;
    }

    /**
     * @return Collection<int, UploadRecord>
     */
    protected function importableRecords(Upload $upload): Collection
    {
        $records = $upload->records()->where('status', UploadRecordStatusEnum::PREVIEW)->orderBy('row_number')->get();

        foreach ($records->filter(fn (UploadRecord $record) => Arr::get($record->data, 'skip')) as $record) {
            $record->update(['status' => UploadRecordStatusEnum::SKIPPED]);
        }

        return $records->reject(fn (UploadRecord $record) => Arr::get($record->data, 'skip'))->values();
    }

    /**
     * @throws Throwable
     */
    protected function importRecord(Supplier $supplier, UploadRecord $record): void
    {
        ['supplier_product' => $supplierProduct, 'trade_unit' => $tradeUnit] = $this->importValues($supplier, $record->values);

        $record->update(['data' => array_merge($record->data ?? [], ['supplier_product_id' => $supplierProduct->id, 'trade_unit_id' => $tradeUnit->id])]);
    }

    /**
     * Creates one checked product (a sheet row or the New supplier product form). Run it inside a transaction.
     *
     * @param array<string, mixed> $values
     *
     * @return array{supplier_product: SupplierProduct, trade_unit: TradeUnit}
     * @throws Throwable
     */
    public function importValues(Supplier $supplier, array $values): array
    {
        $stockFamily     = $this->stockFamily($supplier, $values['family']);
        $tradeUnitFamily = $this->tradeUnitFamily($supplier, $values['family']);
        $tradeUnit       = $this->tradeUnit($supplier, $values);

        if (!$tradeUnit->trade_unit_family_id) {
            AttachTradeUnitsToTradeUnitFamily::make()->handle($tradeUnitFamily, ['trade_units' => [$tradeUnit->id]]);
        }

        $this->barcode($supplier, $tradeUnit, $values['unit_barcode']);
        $this->stock($tradeUnit, $stockFamily, $values);

        $supplierProduct = $this->supplierProduct($supplier, $values);
        SyncSupplierProductTradeUnits::run($supplierProduct, [$tradeUnit->id => ['quantity' => $values['units_per_sko']]]);

        return ['supplier_product' => $supplierProduct, 'trade_unit' => $tradeUnit];
    }

    protected function stockFamily(Supplier $supplier, string $code): StockFamily
    {
        return StockFamily::where('group_id', $supplier->group_id)->whereRaw('lower(code) = lower(?)', [$code])->first()
            ?? StoreStockFamily::make()->action($supplier->group, ['code' => $code, 'name' => $code], strict: false);
    }

    protected function tradeUnitFamily(Supplier $supplier, string $code): TradeUnitFamily
    {
        return TradeUnitFamily::where('group_id', $supplier->group_id)->whereRaw('lower(code) = lower(?)', [$code])->first()
            ?? StoreTradeUnitFamily::make()->action($supplier->group, ['code' => $code, 'name' => $code]);
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function tradeUnit(Supplier $supplier, array $values): TradeUnit
    {
        $sheetValues = array_filter([
            'name'                  => $values['unit_name'],
            'description'           => $values['unit_name'],
            'type'                  => $values['unit_label'],
            'gross_weight'          => $values['unit_weight'],
            'net_weight'            => $values['unit_weight'],
            'marketing_dimensions'  => $values['unit_dimensions'],
            'tariff_code'           => $values['tariff_code'],
            'marketing_ingredients' => $values['materials'],
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $tradeUnit = TradeUnit::where('group_id', $supplier->group_id)->whereRaw('lower(code) = lower(?)', [$values['part_reference']])->first();
        if (!$tradeUnit) {
            return StoreTradeUnit::make()->action($supplier->group, ['code' => $values['part_reference']] + $sheetValues, strict: false);
        }

        $emptyFields = array_filter($sheetValues, fn ($value, string $field) => blank($tradeUnit->{$field}) || $tradeUnit->{$field} === [], ARRAY_FILTER_USE_BOTH);
        if ($emptyFields !== []) {
            $tradeUnit = UpdateTradeUnit::make()->action($tradeUnit, $emptyFields, strict: false);
        }

        return $tradeUnit;
    }

    protected function barcode(Supplier $supplier, TradeUnit $tradeUnit, ?string $barcode): void
    {
        if ($barcode === null || filled($tradeUnit->barcode)) {
            return;
        }

        if ($barcode === 'auto') {
            AssignNextBarcodeToTradeUnit::make()->action($tradeUnit);

            return;
        }

        $poolBarcode = Barcode::where('group_id', $supplier->group_id)->where('number', $barcode)->first();
        if ($poolBarcode) {
            SyncBarcodeToTradeUnit::make()->action($poolBarcode, $tradeUnit);

            return;
        }

        StoreBarcode::make()->action($supplier->group, [
            'number'     => $barcode,
            'status'     => BarcodeStatusEnum::USED,
            'type'       => BarcodeTypeEnum::EAN,
            'data'       => ['external' => true],
            'trade_unit' => $tradeUnit->id,
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function stock(TradeUnit $tradeUnit, StockFamily $stockFamily, array $values): Stock
    {
        $physical = array_filter([
            'gross_weight' => $values['sko_weight'],
            'dimensions'   => $values['sko_dimensions'],
        ], fn ($value) => $value !== null);

        /** @var Stock|null $stock */
        $stock = $tradeUnit->stocks()->first();
        if ($stock) {
            $emptyFields = array_filter($physical, fn ($value, string $field) => blank($stock->{$field}), ARRAY_FILTER_USE_BOTH);
            if ($emptyFields !== []) {
                $stock->update($emptyFields);
            }

            return $stock;
        }

        $stock = StoreStock::make()->action($stockFamily, [
            'code' => $tradeUnit->code,
            'name' => $values['sko_name'],
        ], strict: false);

        SyncStockTradeUnits::run($stock, [$tradeUnit->id => ['quantity' => $values['units_per_sko']]]);

        if ($physical !== []) {
            $stock->update($physical);
        }

        return $stock;
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function supplierProduct(Supplier $supplier, array $values): SupplierProduct
    {
        $modelData = array_filter([
            'code'                 => $values['supplier_code'],
            'name'                 => $values['unit_name'],
            'cost'                 => $values['unit_cost'],
            'units_per_pack'       => $values['units_per_sko'],
            'units_per_carton'     => $values['units_per_sko'] * $values['skos_per_carton'],
            'minimum_carton_order' => $values['minimum_order_cartons'],
            'extra_costs'          => $values['extra_costs'],
            'unit_expense'         => $values['unit_expense'],
            'delivery_time'        => $values['delivery_days'],
            'cbm'                  => $values['carton_cbm'],
            'carton_weight'        => $values['carton_weight'],
        ], fn ($value) => $value !== null);

        $modelData['data'] = [
            'seed' => array_filter([
                'recommended_price'         => $values['recommended_price'],
                'recommended_rrp'           => $values['recommended_rrp'],
                'recommended_price_eur'     => $values['recommended_price_eur'],
                'recommended_rrp_eur'       => $values['recommended_rrp_eur'],
                'recommended_skos_per_outer' => $values['skos_per_outer'],
            ], fn ($value) => $value !== null),
        ];

        $supplierProduct = Arr::get($values, 'supplier_product_id') ? SupplierProduct::find($values['supplier_product_id']) : null;
        if ($supplierProduct) {
            return UpdateSupplierProduct::make()->action($supplierProduct, Arr::except($modelData, 'code'), strict: false);
        }

        return StoreSupplierProduct::make()->action($supplier, $modelData, strict: false);
    }

    /**
     * Each organisation's lines go on its open draft (the org supplier's, or the org agent's when it buys
     * through an agent), or on a new draft when the preview asked for one. The sheet sets the quantity.
     *
     * @param Collection<int, UploadRecord> $records
     */
    protected function createDraftPurchaseOrders(Supplier $supplier, Upload $upload, Collection $records): void
    {
        $orders = [];
        foreach ($records as $record) {
            foreach (Arr::get($record->values, 'order', []) as $key => $cartons) {
                $orders[$key][] = ['record' => $record, 'cartons' => $cartons];
            }
        }

        $summary = [];
        foreach ($orders as $key => $lines) {
            $organisation = CheckSupplierProductSheet::make()->organisationForOrderColumn($supplier, $key);
            $orgSupplier  = $organisation ? $supplier->orgSuppliers()->where('organisation_id', $organisation->id)->first() : null;
            if (!$orgSupplier) {
                $summary[$key] = ['error' => __('No organisation buying from this supplier matches ":key".', ['key' => $key])];

                continue;
            }

            try {
                $parent        = $orgSupplier->org_agent_id ? $orgSupplier->orgAgent : $orgSupplier;
                $purchaseOrder = $this->draftPurchaseOrder($parent, $upload, $key);
            } catch (Throwable $e) {
                $summary[$key] = ['error' => $this->errorText($e)];

                continue;
            }

            $added  = 0;
            $errors = [];
            foreach ($lines as $line) {
                try {
                    $supplierProduct    = SupplierProduct::findOrFail($line['record']->data['supplier_product_id']);
                    $orgSupplierProduct = OrgSupplierProduct::where('organisation_id', $organisation->id)->where('supplier_product_id', $supplierProduct->id)->firstOrFail();
                    $quantity           = $line['cartons'] * $supplierProduct->units_per_carton;
                    $transaction        = $purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $supplierProduct->id)->first();

                    if ($transaction) {
                        UpdatePurchaseOrderTransaction::make()->action($transaction, ['quantity_ordered' => $quantity]);
                    } else {
                        StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($purchaseOrder, $orgSupplierProduct, ['quantity_ordered' => $quantity]);
                    }
                    $added++;
                } catch (Throwable $e) {
                    $errors[] = __('Row :row: :error', ['row' => $line['record']->row_number, 'error' => $this->errorText($e)]);
                }
            }

            $summary[$key] = ['purchase_order' => $purchaseOrder->reference, 'purchase_order_id' => $purchaseOrder->id, 'lines' => $added, 'errors' => $errors];
        }

        $upload->update(['data' => array_merge($upload->data ?? [], ['purchase_orders' => $summary])]);
    }

    protected function draftPurchaseOrder(mixed $parent, Upload $upload, string $key): PurchaseOrder
    {
        if (!Arr::get($upload->data, 'new_draft.'.$key)) {
            $openDraft = $parent->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest('id')->first();
            if ($openDraft) {
                return $openDraft;
            }
        }

        return StorePurchaseOrder::make()->action($parent, array_filter(['buyer_id' => $upload->user_id]));
    }

    /**
     * What staff see on the preview page: validation messages as they are, anything else without URLs,
     * which can carry service keys (a currency lookup that timed out showed its api_key).
     */
    protected function errorText(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())->flatten()->implode(' ');
        }

        Log::warning('Supplier product upload import: '.$e->getMessage());

        if ($e instanceof ConnectionException || str_contains($e->getMessage(), 'cURL error')) {
            return __('An outside service did not answer in time, please try again.');
        }

        return trim(preg_replace('#\bhttps?://\S+#i', '[link removed]', $e->getMessage()));
    }
}
