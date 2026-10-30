<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-30 14:38
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Reads a container sheet and proposes which purchase order lines its rows fill, changing nothing: the page lets the
 * user choose the purchase orders of each code and then adds through AddPurchaseOrderItemsToStockDelivery, which
 * checks every line again. Rows of the same code and sheet PO are added up, a "(PART OF)" or "(A)" after a code is
 * the same product.
 */
class PreviewStockDeliveryItemsImport extends OrgAction
{
    private StockDelivery $stockDelivery;

    public function authorize(ActionRequest $request): bool
    {
        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDelivery, $request->user());
    }

    public function afterValidator(Validator $validator): void
    {
        if (!AddPurchaseOrdersToStockDelivery::acceptsPurchaseOrders($this->stockDelivery)) {
            $validator->errors()->add('file', __('Items can only be added to an agent container in process'));
        }
    }

    public function rules(): array
    {
        return [
            'file'  => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:153600'],
            'sheet' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(StockDelivery $stockDelivery, UploadedFile $file, ?string $sheet = null): array
    {
        $read = ReadStockDeliveryItemsSheet::make()->handle($file->getRealPath(), $sheet);

        $preview = [
            ...collect($read)->except('rows')->all(),
            'file'      => $file->getClientOriginalName(),
            'rows_read' => count($read['rows']),
            'lines'     => [],
            'items'     => [],
            'ignored'   => [],
        ];

        if ($read['errors'] !== []) {
            return $preview;
        }

        $addable = AddPurchaseOrderItemsToStockDelivery::addableItems($stockDelivery)
            ->with([
                'purchaseOrder:id,slug,reference,parent_name,confirmed_at',
                'supplierProduct:id,code,name,units_per_pack,units_per_carton',
                'orgStock:id,code,name,packed_in,barcode,unit_barcode',
            ])
            ->orderBy('purchase_order_transactions.purchase_order_id')
            ->orderBy('purchase_order_transactions.id')
            ->get();

        $byCode    = [];
        $byBarcode = [];
        foreach ($addable as $transaction) {
            foreach (array_unique(array_filter([$transaction->supplierProduct?->code, $transaction->orgStock?->code])) as $code) {
                $byCode[mb_strtolower($code)][] = $transaction;
            }
            foreach (array_unique(array_filter([$transaction->orgStock?->barcode, $transaction->orgStock?->unit_barcode])) as $barcode) {
                $byBarcode[(string) $barcode][] = $transaction;
            }
        }

        $inContainer = $this->unitsInContainer($stockDelivery);

        $groups = [];
        foreach ($read['rows'] as $row) {
            $code = $row['code'] === null ? null : $this->baseCode($row['code']);

            if ($code === null) {
                $preview['ignored'][] = $this->ignored($row, __('Total or note row'));
                continue;
            }
            if (!$row['quantity']) {
                $preview['ignored'][] = $this->ignored($row, $code !== $row['code']
                    ? __('Extra carton of the row above')
                    : __('No quantity'));
                continue;
            }

            [$candidates, $matchedBy] = $this->candidates($code, $row['barcode'], $byCode, $byBarcode);
            $productKey               = $candidates ? 'item:'.$candidates[0]->id : 'code:'.mb_strtolower($code);
            $key                      = $productKey.'|'.mb_strtolower($row['purchase_order'] ?? '');

            $groups[$key] ??= [
                'key'            => $key,
                'code'           => $code,
                'description'    => $row['description'],
                'rows'           => [],
                'quantity'       => 0.0,
                'purchase_order' => $row['purchase_order'],
                'units_per_sko'  => $row['units_per_sko'],
                'matched_by'     => $matchedBy,
                'candidates'     => $candidates,
            ];
            $groups[$key]['rows'][]   = $row['row'];
            $groups[$key]['quantity'] += $row['quantity'];
        }

        $unmatched = $this->unmatched($stockDelivery, collect($groups)->filter(fn (array $group) => !$group['candidates'])->pluck('code')->all());

        foreach ($groups as $group) {
            if (!$group['candidates']) {
                $preview['ignored'][] = [
                    'rows'                 => $group['rows'],
                    'code'                 => $group['code'],
                    'quantity'             => $group['quantity'],
                    'sheet_purchase_order' => $group['purchase_order'],
                    ...$this->whyUnmatched($unmatched[mb_strtolower($group['code'])] ?? null, $group['purchase_order'], $stockDelivery),
                ];
                continue;
            }

            $preview['lines'][] = $this->line($group, $read['quantity_in'], $inContainer);

            foreach ($group['candidates'] as $transaction) {
                $preview['items'][$transaction->id] ??= [
                    ...AddPurchaseOrderItemsToStockDelivery::addableItemRow($transaction),
                    'confirmed_at'        => $transaction->purchaseOrder->confirmed_at,
                    'purchase_order_slug' => $transaction->purchaseOrder->slug,
                ];
            }
        }

        usort($preview['ignored'], fn (array $a, array $b) => $a['rows'][0] <=> $b['rows'][0]);

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<int, float>  $inContainer
     *
     * @return array<string, mixed>
     */
    private function line(array $group, string $quantityIn, array $inContainer): array
    {
        /** @var list<PurchaseOrderTransaction> $candidates */
        $candidates = $group['candidates'];
        $first      = AddPurchaseOrderItemsToStockDelivery::addableItemRow($candidates[0]);
        $warnings   = [];

        $units = match ($quantityIn) {
            'skos'    => $group['quantity'] * $first['units_per_sko'],
            'cartons' => $first['units_per_carton'] > 0 ? $group['quantity'] * $first['units_per_carton'] : null,
            default   => $group['quantity'],
        };
        if ($units === null) {
            $warnings[] = __('The sheet counts cartons and Aiku has no carton size for this product: type the units');
        }

        if ($group['units_per_sko'] && abs($group['units_per_sko'] - $first['units_per_sko']) > 0.001) {
            $warnings[] = __('The sheet says :sheet units per SKO, Aiku :aiku', ['sheet' => $group['units_per_sko'] + 0, 'aiku' => $first['units_per_sko'] + 0]);
        }

        if ($group['matched_by'] !== 'code') {
            $warnings[] = $group['matched_by'] === 'barcode'
                ? __('Found by its barcode, the code :code is not in Aiku', ['code' => $group['code']])
                : __('Found by the last word of :code', ['code' => $group['code']]);
        }

        $preferred = [];
        if ($group['purchase_order'] !== null) {
            $preferred = collect($candidates)
                ->filter(fn (PurchaseOrderTransaction $transaction) => $this->isSamePurchaseOrder($group['purchase_order'], $transaction->purchaseOrder->reference))
                ->pluck('purchase_order_id')->unique()->values()->all();
            if ($preferred === [] && count(array_unique(array_map(fn ($transaction) => $transaction->purchase_order_id, $candidates))) > 1) {
                $warnings[] = __('The sheet PO ":purchaseOrder" is none of the purchase orders below', ['purchaseOrder' => $group['purchase_order']]);
            }
        }

        return [
            'key'                          => $group['key'],
            'code'                         => $first['code'] ?? $group['code'],
            'sheet_code'                   => $group['code'],
            'name'                         => $first['name'] ?? $group['description'],
            'rows'                         => $group['rows'],
            'sheet_quantity'               => $group['quantity'],
            'units'                        => $units,
            'sheet_purchase_order'         => $group['purchase_order'],
            'preferred_purchase_order_ids' => $preferred,
            'in_container_units'           => collect($candidates)->pluck('supplier_product_id')->unique()->sum(fn (?int $supplierProductId) => $inContainer[$supplierProductId] ?? 0),
            'item_ids'                     => collect($candidates)->pluck('id')->all(),
            'warnings'                     => $warnings,
        ];
    }

    /**
     * @param  array<string, list<PurchaseOrderTransaction>>  $byCode
     * @param  array<string, list<PurchaseOrderTransaction>>  $byBarcode
     *
     * @return array{0: list<PurchaseOrderTransaction>, 1: ?string}
     */
    private function candidates(string $code, ?string $barcode, array $byCode, array $byBarcode): array
    {
        if ($candidates = $byCode[mb_strtolower($code)] ?? null) {
            return [$candidates, 'code'];
        }

        $lastWord = last(preg_split('/\s+/', $code));
        if ($lastWord !== $code && ($candidates = $byCode[mb_strtolower($lastWord)] ?? null)) {
            return [$candidates, 'last_word'];
        }

        $byThisBarcode = $barcode === null ? [] : ($byBarcode[$barcode] ?? []);
        $products      = collect($byThisBarcode)->map(fn (PurchaseOrderTransaction $transaction) => $transaction->supplier_product_id ?? 'org-stock-'.$transaction->org_stock_id)->unique();
        if ($products->count() === 1) {
            return [$byThisBarcode, 'barcode'];
        }

        return [[], null];
    }

    private function baseCode(string $code): ?string
    {
        $code = trim(preg_replace('/(\s*\([^)]*\))+\s*$/u', '', $code));

        return $code === '' ? null : $code;
    }

    /**
     * Sheets write the agent's own PO numbers ("PO-059 ES" for AWIndo-059-ES), so after the whole reference the
     * first number of the sheet is looked for among the numbers of the reference.
     */
    private function isSamePurchaseOrder(string $sheetPurchaseOrder, string $reference): bool
    {
        $squash = fn (string $text) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($text));
        if ($squash($sheetPurchaseOrder) !== '' && ($squash($sheetPurchaseOrder) === $squash($reference) || str_contains($squash($reference), $squash($sheetPurchaseOrder)))) {
            return true;
        }

        if (!preg_match('/\d+/', $sheetPurchaseOrder, $number) || (int) $number[0] === 0) {
            return false;
        }

        preg_match_all('/\d+/', $reference, $referenceNumbers);

        return in_array((int) $number[0], array_map('intval', $referenceNumbers[0]), true);
    }

    /**
     * @return array<int, float> units already in the container by supplier product
     */
    private function unitsInContainer(StockDelivery $stockDelivery): array
    {
        return $stockDelivery->items()
            ->whereNotIn('state', [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value])
            ->whereNotNull('supplier_product_id')
            ->groupBy('supplier_product_id')
            ->selectRaw('supplier_product_id, sum(unit_quantity) as units')
            ->pluck('units', 'supplier_product_id')
            ->map(fn ($units) => (float) $units)
            ->all();
    }

    /**
     * What the agent's purchase orders hold of each code the container can not take, newest first, and the units of
     * it already in this container.
     *
     * @param  list<string>  $codes
     *
     * @return array<string, array{in_container: float, lines: Collection<int, object>}>
     */
    private function unmatched(StockDelivery $stockDelivery, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $lowerCodes = array_values(array_unique(array_map('mb_strtolower', $codes)));

        $inContainer = $stockDelivery->items()
            ->join('supplier_products', 'supplier_products.id', 'stock_delivery_items.supplier_product_id')
            ->whereNotIn('stock_delivery_items.state', [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value])
            ->whereIn(DB::raw('lower(supplier_products.code)'), $lowerCodes)
            ->groupBy(DB::raw('lower(supplier_products.code)'))
            ->selectRaw('lower(supplier_products.code) as code, sum(stock_delivery_items.unit_quantity) as units')
            ->pluck('units', 'code');

        $lines = DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
            ->where('purchase_orders.organisation_id', $stockDelivery->organisation_id)
            ->where('purchase_orders.agent_id', $stockDelivery->agent_id)
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn(DB::raw('lower(supplier_products.code)'), $lowerCodes)
            ->orderByDesc('purchase_orders.id')
            ->get([
                DB::raw('lower(supplier_products.code) as code'),
                'purchase_orders.reference',
                'purchase_orders.slug',
                'purchase_orders.state',
                'purchase_orders.currency_id',
                'purchase_order_transactions.state as line_state',
            ])
            ->groupBy('code');

        return collect($lowerCodes)->mapWithKeys(fn (string $code) => [$code => [
            'in_container' => (float) ($inContainer[$code] ?? 0),
            'lines'        => $lines->get($code, collect()),
        ]])->all();
    }

    /**
     * One short reason, taken from the purchase order the sheet names when it is among them, else the newest.
     *
     * @param  array{in_container: float, lines: Collection<int, object>}|null  $unmatched
     *
     * @return array{reason: string, purchase_orders: list<array{reference: string, slug: string, state: string}>}
     */
    private function whyUnmatched(?array $unmatched, ?string $sheetPurchaseOrder, StockDelivery $stockDelivery): array
    {
        $lines = $unmatched['lines'] ?? collect();
        if ($sheetPurchaseOrder !== null) {
            $named = $lines->filter(fn (object $line) => $this->isSamePurchaseOrder($sheetPurchaseOrder, $line->reference));
            $lines = $named->isNotEmpty() ? $named : $lines;
        }
        $lines = $lines->unique('reference')->values();

        $purchaseOrders = $lines->take(3)->map(fn (object $line) => [
            'reference' => $line->reference,
            'slug'      => $line->slug,
            'state'     => $line->line_state === 'cancelled' ? 'cancelled' : $line->state,
        ])->all();

        $latest = $lines->first();
        $reason = match (true) {
            ($unmatched['in_container'] ?? 0) > 0                            => __('Already in this container (:units units)', ['units' => $unmatched['in_container'] + 0]),
            $latest === null                                                 => __('Code not on any PO of this agent'),
            $latest->line_state === 'cancelled', $latest->state === 'cancelled' => __('Cancelled'),
            in_array($latest->state, ['in_process', 'submitted'], true)      => __('PO not confirmed yet'),
            $latest->state === 'settled'                                     => __('PO already completed'),
            $latest->state === 'not_received'                                => __('PO marked not received'),
            $latest->currency_id !== $stockDelivery->currency_id             => __('PO in another currency'),
            default                                                          => __('Nothing left to ship, already in a container'),
        };

        return ['reason' => $reason, 'purchase_orders' => $purchaseOrders];
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @return array{rows: list<int>, code: ?string, quantity: ?float, sheet_purchase_order: ?string, purchase_orders: list<array<string, string>>, reason: string}
     */
    private function ignored(array $row, string $reason): array
    {
        return [
            'rows'                 => [$row['row']],
            'code'                 => $row['code'],
            'quantity'             => $row['quantity'],
            'sheet_purchase_order' => $row['purchase_order'],
            'purchase_orders'      => [],
            'reason'               => $reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StockDelivery $stockDelivery, ActionRequest $request): array
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData['file'], $this->validatedData['sheet'] ?? null);
    }
}
