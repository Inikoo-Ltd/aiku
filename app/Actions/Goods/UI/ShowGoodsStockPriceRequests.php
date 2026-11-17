<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Goods\UI;

use App\Actions\OrgAction;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Goods\Stock;
use App\Models\Masters\MasterAsset;
use App\Models\Procurement\PurchaseOrderTransaction;
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

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('goods.view');
    }

    public function rules(): array
    {
        return [];
    }

    public function asController(Stock $stock, ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($stock, $request->user());
    }

    /**
     * Lines still waiting for an answer that the organisation can still answer: the same conditions
     * AnswerPurchaseOrderTransactionPriceChange checks before saving.
     */
    public static function pendingQuery(): Builder
    {
        return PurchaseOrderTransaction::query()
            ->where('purchase_order_transactions.price_query_state', PurchaseOrderTransactionPriceQueryStateEnum::PENDING->value)
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
     * @return array{code: string, name: ?string, requests: array, master_products: array, can_edit_master_prices: bool}
     */
    public function handle(Stock $stock, User $user): array
    {
        return [
            'code'                   => $stock->code,
            'name'                   => $stock->name,
            'requests'               => $this->requests($stock, $user),
            'master_products'        => $this->masterProducts($stock),
            'can_edit_master_prices' => $user->authTo('masters.edit'),
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

    private function masterProducts(Stock $stock): array
    {
        return MasterAsset::query()
            ->whereHas('stocks', fn (Builder $query) => $query->where('stocks.id', $stock->id))
            ->where('type', MasterAssetTypeEnum::PRODUCT)
            ->where('status', true)
            ->with('masterShop')
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
            ->map(function (MasterAsset $masterAsset) {
                $exchanges      = collect($masterAsset->masterShop?->price_exchanges ?? []);
                $mainCurrencies = $exchanges->filter(fn (array $exchange) => $exchange['is_major'] ?? false)->keys()->values();

                return [
                    'id'              => $masterAsset->id,
                    'code'            => $masterAsset->code,
                    'name'            => $masterAsset->name,
                    'master_shop'     => $masterAsset->masterShop?->code,
                    'units'           => (float) $masterAsset->units,
                    'stocks_in_product' => (int) $masterAsset->stocks_in_product,
                    'stock_quantity'  => (float) $masterAsset->stock_quantity,
                    'cost'            => $masterAsset->effective_cost !== null ? round((float) $masterAsset->effective_cost, 2) : null,
                    'cost_currency'   => $this->group->currency->code,
                    'main_currencies' => $mainCurrencies->all(),
                    'other_currencies' => $exchanges->reject(fn (array $exchange) => $exchange['is_major'] ?? false)->keys()->values()->all(),
                    'prices'          => $mainCurrencies->mapWithKeys(fn (string $currencyCode) => [$currencyCode => $this->value($masterAsset->master_prices, $currencyCode)])->all(),
                    'rrps'            => $mainCurrencies->mapWithKeys(fn (string $currencyCode) => [$currencyCode => $this->value($masterAsset->master_rrps, $currencyCode)])->all(),
                    'url'             => $masterAsset->masterShop ? route('grp.masters.master_shops.show.master_products.show', [$masterAsset->masterShop->slug, $masterAsset->slug]) : null,
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

    private function value(?array $record, string $currencyCode): ?float
    {
        $value = data_get($record, "$currencyCode.value");

        return $value !== null ? (float) $value : null;
    }
}
