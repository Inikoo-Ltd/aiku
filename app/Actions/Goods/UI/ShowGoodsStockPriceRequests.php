<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Goods\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateEffectiveCost;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\RepriceOpenPurchaseOrderTransactions;
use App\Actions\SupplyChain\SupplierSubmission\DecideSupplierSubmission;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\Goods\Stock;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierSubmission;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The new supplier prices agents asked for a group stock that wait for the buying organisation's answer,
 * beside the master products selling that stock with their main prices and RRPs, so the buyer can answer
 * and re-price the product in one place.
 */
class ShowGoodsStockPriceRequests extends OrgAction
{
    use AsAction;

    private const array ALWAYS_SHOWN_CURRENCIES = ['GBP', 'EUR'];

    /** @var array<string, ?float> */
    private array $costExchanges = [];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('goods.view');
    }

    public function rules(): array
    {
        return [
            'price_ratio' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'lt:100'],
        ];
    }

    public function asController(Stock $stock, ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        $priceRatio = $this->validatedData['price_ratio'] ?? null;

        return $this->handle($stock, $request->user(), $priceRatio !== null ? (float) $priceRatio : null);
    }

    /**
     * Lines still waiting for an answer that the organisation can still answer: the same conditions
     * AnswerPurchaseOrderTransactionPriceChange checks before saving.
     */
    public static function pendingQuery(): Builder
    {
        return self::openLinesQuery()
            ->where('purchase_order_transactions.price_query_state', PurchaseOrderTransactionPriceQueryStateEnum::PENDING->value);
    }

    private static function openLinesQuery(): Builder
    {
        return PurchaseOrderTransaction::query()
            ->whereIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::SUBMITTED->value, PurchaseOrderTransactionStateEnum::CONFIRMED->value])
            ->whereHas('purchaseOrder', fn (Builder $query) => $query->whereIn('state', PurchaseOrderStateEnum::openValues()));
    }

    /**
     * @return array<int, int> pending lines by group stock id
     */
    public static function tallyByStock(int $groupId): array
    {
        return self::pendingQuery()
            ->join('org_stocks', 'org_stocks.id', 'purchase_order_transactions.org_stock_id')
            ->where('purchase_order_transactions.group_id', $groupId)
            ->whereNotNull('org_stocks.stock_id')
            ->groupBy('org_stocks.stock_id')
            ->select('org_stocks.stock_id', DB::raw('count(*) as pending_lines'))
            ->pluck('pending_lines', 'stock_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array{code: string, name: ?string, image: ?array, requests: array, supplier_price_changes: array, new_price: ?array, master_products: array, can_edit_master_prices: bool}
     */
    /**
     * A price ratio given here is the change just accepted in the dialog (new cost ÷ old cost): the margins and the
     * suggested selling prices use it instead of the latest agent line.
     */
    public function handle(Stock $stock, User $user, ?float $priceRatio = null): array
    {
        $newPriceLine = $priceRatio === null ? $this->newPriceLine($stock) : null;
        $priceRatio ??= $newPriceLine ? $this->priceRatio($newPriceLine) : null;

        return [
            'code'                   => $stock->code,
            'name'                   => $stock->name,
            'image'                  => ($stock->image_id ? $stock : $stock->tradeUnits()->first())?->imageSources(160, 160),
            'requests'               => $this->requests($stock, $user),
            'supplier_price_changes' => $this->supplierPriceChanges($stock, $user),
            'new_price'              => $newPriceLine ? $this->newPrice($newPriceLine) : null,
            'master_products'        => $this->masterProducts($stock, $priceRatio),
            'can_edit_master_prices' => $user->authTo('masters.edit'),
        ];
    }

    /**
     * The latest new price asked or agreed for this stock on a line not yet received. effective_cost follows
     * what was paid at goods in, so until those goods arrive the margin is shown at this price instead.
     */
    private function newPriceLine(Stock $stock): ?PurchaseOrderTransaction
    {
        return self::openLinesQuery()
            ->whereIn('purchase_order_transactions.price_query_state', [
                PurchaseOrderTransactionPriceQueryStateEnum::PENDING->value,
                PurchaseOrderTransactionPriceQueryStateEnum::ACCEPTED->value,
                PurchaseOrderTransactionPriceQueryStateEnum::ADJUSTED->value,
            ])
            ->whereHas('orgStock', fn (Builder $query) => $query->where('stock_id', $stock->id))
            ->with(['purchaseOrder.organisation', 'purchaseOrder.currency', 'currency'])
            ->orderByDesc('price_queried_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (PurchaseOrderTransaction $transaction) => $this->priceRatio($transaction) !== null);
    }

    private function newUnitCost(PurchaseOrderTransaction $transaction): float
    {
        return (float) ($transaction->price_query_state === PurchaseOrderTransactionPriceQueryStateEnum::PENDING
            ? $transaction->price_query_unit_cost
            : $transaction->unit_cost);
    }

    private function previousUnitCost(PurchaseOrderTransaction $transaction): float
    {
        return (float) ($transaction->price_query_previous_unit_cost ?? $transaction->unit_cost);
    }

    private function priceRatio(PurchaseOrderTransaction $transaction): ?float
    {
        $previousUnitCost = $this->previousUnitCost($transaction);
        $newUnitCost      = $this->newUnitCost($transaction);

        return $previousUnitCost > 0 && $newUnitCost > 0 ? $newUnitCost / $previousUnitCost : null;
    }

    /**
     * @return array{state: string, organisation: string, purchase_order: string, currency: ?string, unit_cost: float, previous_unit_cost: float}
     */
    private function newPrice(PurchaseOrderTransaction $transaction): array
    {
        return [
            'state'              => $transaction->price_query_state->value,
            'organisation'       => $transaction->purchaseOrder->organisation->code,
            'purchase_order'     => $transaction->purchaseOrder->reference,
            'currency'           => $transaction->currencyCodeIn($transaction->purchaseOrder),
            'unit_cost'          => $this->newUnitCost($transaction),
            'previous_unit_cost' => $this->previousUnitCost($transaction),
        ];
    }

    private function requests(Stock $stock, User $user): array
    {
        $canUpdateSupplierCost = $user->authTo('supply-chain.edit');

        return self::pendingQuery()
            ->whereHas('orgStock', fn (Builder $query) => $query->where('stock_id', $stock->id))
            ->with(['purchaseOrder.organisation', 'purchaseOrder.agent', 'purchaseOrder.currency', 'purchaseOrder.parent', 'supplierProduct.supplier', 'priceQueriedBy', 'currency', 'orgStock'])
            ->orderBy('price_queried_at')
            ->get()
            ->map(function (PurchaseOrderTransaction $transaction) use ($user, $canUpdateSupplierCost) {
                $purchaseOrder = $transaction->purchaseOrder;
                $canAnswer     = $user->authTo("procurement.$transaction->organisation_id.edit");

                return [
                    'id'                       => $transaction->id,
                    'organisation'             => $purchaseOrder->organisation->code,
                    'purchase_order'           => [
                        'reference' => $purchaseOrder->reference,
                        'state'     => $purchaseOrder->state->value,
                        'url'       => route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]),
                    ],
                    'agent'                    => $purchaseOrder->agent?->name,
                    'supplier'                 => $transaction->supplierProduct?->supplier?->name ?? $purchaseOrder->parent?->name,
                    'code'                     => $transaction->supplierProduct?->code ?? $transaction->orgStock?->code,
                    'units_per_carton'         => (float) ($transaction->supplierProduct?->units_per_carton ?: 1),
                    'quantity_ordered'         => (float) $transaction->quantity_ordered,
                    'currency'                 => $transaction->currencyCodeIn($purchaseOrder),
                    'unit_cost'                => (float) $transaction->unit_cost,
                    'asked_unit_cost'          => (float) $transaction->price_query_unit_cost,
                    'previous_unit_cost'       => $transaction->price_query_previous_unit_cost !== null ? (float) $transaction->price_query_previous_unit_cost : null,
                    'note'                     => $transaction->price_query_note,
                    'asked_by'                 => $transaction->priceQueriedBy?->contact_name ?: $transaction->priceQueriedBy?->username,
                    'asked_at'                 => $transaction->price_queried_at,
                    'can_update_supplier_cost' => $canAnswer && $canUpdateSupplierCost && $transaction->supplierProduct !== null,
                    'answer_route'             => $canAnswer ? [
                        'name'       => 'grp.models.purchase-order.transaction.price_change.answer',
                        'parameters' => [
                            'purchaseOrder'            => $transaction->purchase_order_id,
                            'purchaseOrderTransaction' => $transaction->id,
                        ],
                        'method'     => 'patch',
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Price and MOQ changes suppliers proposed on their portal for a supplier product of this stock, still waiting
     * for a decision: the same states the Goods dashboard counts.
     */
    private function supplierPriceChanges(Stock $stock, User $user): array
    {
        return SupplierSubmission::query()
            ->where('group_id', $this->group->id)
            ->where('type', SupplierSubmissionTypeEnum::PRICE_CHANGE)
            ->whereIn('state', [SupplierSubmissionStateEnum::RECEIVED, SupplierSubmissionStateEnum::UNDER_REVIEW])
            ->whereIn('supplier_product_id', DB::table('stock_has_supplier_products')->where('stock_id', $stock->id)->select('supplier_product_id'))
            ->with(['supplier', 'supplierProduct.currency'])
            ->orderBy('submitted_at')
            ->get()
            ->map(function (SupplierSubmission $supplierSubmission) use ($user) {
                $supplierProduct = $supplierSubmission->supplierProduct;
                $data            = $supplierSubmission->data ?? [];
                $canDecide       = $supplierProduct !== null && DecideSupplierSubmission::canDecide($user, $supplierSubmission);

                return [
                    'id'                   => $supplierSubmission->id,
                    'state'                => $supplierSubmission->state->value,
                    'supplier'             => $supplierSubmission->supplier?->name,
                    'supplier_code'        => $supplierSubmission->supplier?->code,
                    'code'                 => $supplierProduct?->code,
                    'currency'             => $supplierProduct?->currency?->code,
                    'cost'                 => $supplierProduct ? (float) $supplierProduct->cost : null,
                    'proposed_cost'        => (float) ($data['proposed_price'] ?? 0),
                    'units_per_carton'     => (float) ($supplierProduct?->units_per_carton ?: 1),
                    'minimum_carton_order' => $supplierProduct?->minimum_carton_order,
                    'proposed_moq'         => isset($data['moq']) ? (int) $data['moq'] : null,
                    'effective_from'       => $data['effective_from'] ?? null,
                    'effective_until'      => $data['effective_until'] ?? null,
                    'reason'               => $data['reason'] ?? null,
                    'submitted_at'         => $supplierSubmission->submitted_at,
                    'url'                  => route('grp.supply-chain.supplier_submissions.show', [$supplierSubmission->id]),
                    'open_purchase_order_lines' => $canDecide ? $this->openPurchaseOrderLines($supplierProduct, $user, (float) ($data['proposed_price'] ?? 0)) : [],
                    'decide_route'         => $canDecide ? [
                        'name'       => 'grp.models.supplier_submission.decide',
                        'parameters' => ['supplierSubmission' => $supplierSubmission->id],
                        'method'     => 'post',
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, organisation: string, reference: string, state: string, url: string, quantity_ordered: float, unit_cost: float}>
     */
    private function openPurchaseOrderLines(SupplierProduct $supplierProduct, User $user, float $proposedCost): array
    {
        return RepriceOpenPurchaseOrderTransactions::repriceableLines($supplierProduct, $proposedCost, $user)
            ->map(fn (PurchaseOrderTransaction $transaction) => [
                'id'               => $transaction->id,
                'organisation'     => $transaction->purchaseOrder->organisation->code,
                'reference'        => $transaction->purchaseOrder->reference,
                'state'            => $transaction->purchaseOrder->state->value,
                'url'              => route('grp.org.procurement.purchase_orders.show', [$transaction->purchaseOrder->organisation->slug, $transaction->purchaseOrder->slug]),
                'quantity_ordered' => (float) $transaction->quantity_ordered,
                'unit_cost'        => (float) $transaction->unit_cost,
            ])
            ->values()
            ->all();
    }

    private function masterProducts(Stock $stock, ?float $priceRatio): array
    {
        $effectiveCost = MasterAssetHydrateEffectiveCost::make();

        return MasterAsset::query()
            ->whereHas('stocks', fn (Builder $query) => $query->where('stocks.id', $stock->id))
            ->where('type', MasterAssetTypeEnum::PRODUCT)
            ->where('status', true)
            ->with(['masterShop', 'masterFamily', ...($priceRatio !== null ? ['tradeUnits.orgStocks'] : [])])
            ->addSelect([
                'stocks_in_product' => DB::table('master_asset_has_stocks')
                    ->selectRaw('count(distinct stock_id)')
                    ->whereColumn('master_asset_has_stocks.master_asset_id', 'master_assets.id'),
                'stock_quantity'    => DB::table('master_asset_has_stocks')
                    ->selectRaw('max(quantity)')
                    ->whereColumn('master_asset_has_stocks.master_asset_id', 'master_assets.id')
                    ->where('master_asset_has_stocks.stock_id', $stock->id),
            ])
            ->get()
            ->sortBy(fn (MasterAsset $masterAsset) => [(int) $masterAsset->stocks_in_product > 1, $masterAsset->code])
            ->map(function (MasterAsset $masterAsset) use ($stock, $priceRatio, $effectiveCost) {
                $exchanges           = collect($masterAsset->masterShop?->price_exchanges ?? []);
                $mainCurrencies      = $exchanges->filter(fn (array $exchange) => $exchange['is_major'] ?? false)->keys()->values();
                $followingCurrencies = $exchanges
                    ->filter(fn (array $exchange, string $currencyCode) => !($exchange['is_major'] ?? false) && in_array($currencyCode, self::ALWAYS_SHOWN_CURRENCIES, true) && isset($exchange['major'], $exchange['exchange']))
                    ->map(fn (array $exchange, string $currencyCode) => ['currency' => $currencyCode, 'major' => $exchange['major'], 'exchange' => (float) $exchange['exchange']])
                    ->values();
                $shownCurrencies     = $mainCurrencies->merge($followingCurrencies->pluck('currency'));
                $costAtNewPrice = $priceRatio !== null ? $effectiveCost->costWithStockPriceChange($masterAsset, $stock->id, $priceRatio) : null;

                return [
                    'id'              => $masterAsset->id,
                    'code'            => $masterAsset->code,
                    'name'            => $masterAsset->name,
                    'master_shop'     => $masterAsset->masterShop?->code,
                    'units'           => (float) $masterAsset->units,
                    'stocks_in_product' => (int) $masterAsset->stocks_in_product,
                    'stock_quantity'  => (float) $masterAsset->stock_quantity,
                    'cost'            => $masterAsset->effective_cost !== null ? round((float) $masterAsset->effective_cost, 2) : null,
                    'cost_at_new_price' => $costAtNewPrice !== null ? round($costAtNewPrice, 2) : null,
                    'cost_currency'   => $this->group->currency->code,
                    'main_currencies' => $mainCurrencies->all(),
                    'cost_exchanges'  => $shownCurrencies->mapWithKeys(fn (string $currencyCode) => [$currencyCode => $this->costExchange($currencyCode)])->all(),
                    'following_currencies' => $followingCurrencies->all(),
                    'other_currencies' => $exchanges->keys()->diff($shownCurrencies)->values()->all(),
                    'prices'          => $shownCurrencies->mapWithKeys(fn (string $currencyCode) => [$currencyCode => $this->value($masterAsset->master_prices, $currencyCode)])->all(),
                    'rrps'            => $mainCurrencies->mapWithKeys(fn (string $currencyCode) => [$currencyCode => $this->value($masterAsset->master_rrps, $currencyCode)])->all(),
                    'url'             => $masterAsset->masterShop ? route('grp.masters.master_shops.show.master_products.show', [$masterAsset->masterShop->slug, $masterAsset->slug]) : null,
                    'master_family'   => $masterAsset->masterShop && $masterAsset->masterFamily ? [
                        'code'        => $masterAsset->masterFamily->code,
                        'pricing_url' => route('grp.masters.master_shops.show.master_families.master_products.index', [$masterAsset->masterShop->slug, $masterAsset->masterFamily->slug, 'tab' => 'pricing']),
                    ] : null,
                    'update_route'    => [
                        'name'       => 'grp.models.master_asset.main_prices.update',
                        'parameters' => ['masterAsset' => $masterAsset->id],
                        'method'     => 'patch',
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function costExchange(string $currencyCode): ?float
    {
        if (!array_key_exists($currencyCode, $this->costExchanges)) {
            $currency                           = Currency::where('code', $currencyCode)->first();
            $this->costExchanges[$currencyCode] = $currency ? GetCurrencyExchange::make()->cachedOrStored($this->group->currency, $currency) : null;
        }

        return $this->costExchanges[$currencyCode];
    }

    private function value(?array $record, string $currencyCode): ?float
    {
        $value = data_get($record, "$currencyCode.value");

        return $value !== null ? (float) $value : null;
    }
}
