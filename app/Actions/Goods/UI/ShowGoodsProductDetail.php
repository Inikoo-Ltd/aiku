<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Goods\Stock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Everything the slide-over drawer shows for one group stock: per organisation the live stock,
 * inbound purchase orders and deliveries, the shops it is for sale in, the last 12 months of sales
 * and the recent status changes, so an editor never has to open five separate screens to see it.
 */
class ShowGoodsProductDetail extends OrgAction
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

        return $this->handle($stock);
    }

    public function handle(Stock $stock): array
    {
        $orgStocks = DB::table('org_stocks')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->where('org_stocks.stock_id', $stock->id)
            ->whereNull('org_stocks.deleted_at')
            ->orderBy('organisations.code')
            ->select([
                'org_stocks.id',
                'org_stocks.state',
                'org_stocks.quantity_in_locations',
                'org_stocks.quantity_available',
                'org_stock_stats.days_of_cover',
                'organisations.code as organisation_code',
                'organisations.name as organisation_name',
            ])
            ->get();

        $orgStockIds = $orgStocks->pluck('id')->all();

        $inboundLines = $this->inboundLines($orgStockIds);
        $shops        = $this->shopsFor($orgStockIds);
        $monthlySales = $this->monthlySales($orgStockIds);

        [$groupState, $allRetired] = $this->groupState($orgStocks);

        return [
            'id'            => $stock->id,
            'code'          => $stock->code,
            'name'          => $stock->name,
            'group_state'   => $groupState,
            'all_retired'   => $allRetired,
            'organisations' => $orgStocks->map(function ($orgStock) use ($inboundLines, $shops, $monthlySales, $groupState) {
                $lines = $inboundLines[$orgStock->id] ?? [];

                return [
                    'organisation'       => $orgStock->organisation_code,
                    'name'               => $orgStock->organisation_name,
                    'on_hand'            => (float) $orgStock->quantity_in_locations,
                    'available'          => (float) $orgStock->quantity_available,
                    'allocated'          => max(0.0, (float) $orgStock->quantity_in_locations - (float) $orgStock->quantity_available),
                    'inbound'            => array_sum(array_column($lines, 'quantity')),
                    'next_expected_at'   => collect($lines)->pluck('eta')->filter()->sort()->first(),
                    'inbound_lines'      => $lines,
                    'days_of_cover'      => $orgStock->days_of_cover === null ? null : (float) $orgStock->days_of_cover,
                    'state'              => $orgStock->state,
                    'differs_from_group' => $orgStock->state !== $groupState,
                    'shops'              => $shops[$orgStock->id] ?? [],
                    'monthly_sales'      => $monthlySales[$orgStock->id] ?? [],
                ];
            })->values()->all(),
            'status_history' => $this->statusHistory($orgStockIds),
        ];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function groupState(Collection $orgStocks): array
    {
        $nonRetired = $orgStocks->reject(fn ($orgStock) => $orgStock->state === OrgStockStateEnum::DISCONTINUED->value);

        if ($nonRetired->isEmpty()) {
            return [OrgStockStateEnum::DISCONTINUED->value, true];
        }

        $priority = [
            OrgStockStateEnum::ACTIVE->value        => 0,
            OrgStockStateEnum::SUSPENDED->value     => 1,
            OrgStockStateEnum::DISCONTINUING->value => 2,
            OrgStockStateEnum::DISCONTINUED->value  => 3,
        ];

        $counts = $nonRetired->countBy('state');
        $max    = $counts->max();

        return [$counts->filter(fn ($count) => $count === $max)->keys()->sortBy(fn ($state) => $priority[$state] ?? 99)->first(), false];
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function inboundLines(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $byOrgStock = [];
        foreach (array_merge($this->stockDeliveryLines($orgStockIds), $this->purchaseOrderLines($orgStockIds)) as $line) {
            $byOrgStock[$line['org_stock_id']][] = $line;
        }

        return $byOrgStock;
    }

    private function stockDeliveryLines(array $orgStockIds): array
    {
        return DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->whereIn('stock_delivery_items.org_stock_id', $orgStockIds)
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->whereIn('stock_deliveries.state', array_keys(ShowGoodsDashboard::DELIVERY_DAYS_TO_ARRIVE))
            ->select([
                'stock_delivery_items.org_stock_id',
                'stock_deliveries.reference',
                'stock_deliveries.state',
                DB::raw('(stock_delivery_items.unit_quantity - coalesce(stock_delivery_items.unit_quantity_placed, 0)) as quantity'),
            ])
            ->get()
            ->filter(fn ($row) => $row->quantity > 0)
            ->map(fn ($row) => [
                'org_stock_id' => $row->org_stock_id,
                'type'         => 'stock_delivery',
                'reference'    => $row->reference,
                'state'        => $row->state,
                'state_label'  => StockDeliveryStateEnum::labels()[$row->state],
                'quantity'     => (float) $row->quantity,
                'eta'          => now()->addDays(ShowGoodsDashboard::DELIVERY_DAYS_TO_ARRIVE[$row->state])->toDateString(),
            ])
            ->values()
            ->all();
    }

    private function purchaseOrderLines(array $orgStockIds): array
    {
        return DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('org_stocks', 'org_stocks.id', 'purchase_order_transactions.org_stock_id')
            ->whereIn('purchase_order_transactions.org_stock_id', $orgStockIds)
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNull('purchase_orders.deleted_at')
            ->whereNotIn('purchase_orders.state', [
                PurchaseOrderStateEnum::IN_PROCESS->value,
                PurchaseOrderStateEnum::CANCELLED->value,
                PurchaseOrderStateEnum::NOT_RECEIVED->value,
            ])
            ->whereNotIn('purchase_orders.delivery_state', [
                PurchaseOrderDeliveryStateEnum::PLACED->value,
                PurchaseOrderDeliveryStateEnum::CANCELLED->value,
                PurchaseOrderDeliveryStateEnum::NOT_RECEIVED->value,
            ])
            ->whereNotIn('purchase_orders.id', function ($query) {
                $query->select('purchase_order_stock_delivery.purchase_order_id')
                    ->from('purchase_order_stock_delivery')
                    ->join('stock_deliveries', 'stock_deliveries.id', 'purchase_order_stock_delivery.stock_delivery_id')
                    ->whereNull('stock_deliveries.deleted_at')
                    ->whereNotIn('stock_deliveries.state', [
                        StockDeliveryStateEnum::IN_PROCESS->value,
                        StockDeliveryStateEnum::CANCELLED->value,
                        StockDeliveryStateEnum::NOT_RECEIVED->value,
                    ]);
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('stock_deliveries as sd')
                    ->whereColumn('sd.organisation_id', 'purchase_orders.organisation_id')
                    ->whereColumn('sd.reference', 'purchase_orders.reference')
                    ->whereNull('sd.deleted_at')
                    ->whereNotIn('sd.state', [
                        StockDeliveryStateEnum::IN_PROCESS->value,
                        StockDeliveryStateEnum::CANCELLED->value,
                        StockDeliveryStateEnum::NOT_RECEIVED->value,
                    ]);
            })
            ->select([
                'purchase_order_transactions.org_stock_id',
                'purchase_orders.reference',
                'purchase_orders.delivery_state',
                'purchase_orders.submitted_at',
                'purchase_orders.estimated_received_at',
                'org_stocks.measured_lead_time_days',
                DB::raw('(coalesce(purchase_order_transactions.quantity_ordered, 0) - coalesce(purchase_order_transactions.quantity_cancelled, 0)) as quantity'),
            ])
            ->get()
            ->filter(fn ($row) => $row->quantity > 0)
            ->map(fn ($row) => [
                'org_stock_id' => $row->org_stock_id,
                'type'         => 'purchase_order',
                'reference'    => $row->reference,
                'state'        => $row->delivery_state,
                'state_label'  => PurchaseOrderDeliveryStateEnum::labels()[$row->delivery_state],
                'quantity'     => (float) $row->quantity,
                'eta'          => $this->purchaseOrderEta($row),
            ])
            ->values()
            ->all();
    }

    private function purchaseOrderEta(object $row): ?string
    {
        $eta = $row->estimated_received_at
            ? Carbon::parse($row->estimated_received_at)
            : ($row->submitted_at
                ? Carbon::parse($row->submitted_at)->addDays((int) ($row->measured_lead_time_days ?? ShowGoodsDashboard::DEFAULT_LEAD_TIME_DAYS))
                : null);

        if (!$eta) {
            return null;
        }

        return $eta->max(now()->addDay())->toDateString();
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function shopsFor(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        return DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->join('shops', 'shops.id', 'products.shop_id')
            ->whereIn('product_has_org_stocks.org_stock_id', $orgStockIds)
            ->whereNull('products.deleted_at')
            ->select([
                'product_has_org_stocks.org_stock_id',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'shops.state as shop_state',
                'products.code as product_code',
                'products.is_for_sale',
            ])
            ->get()
            ->groupBy('org_stock_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => [
                'shop_code'    => $row->shop_code,
                'shop_name'    => $row->shop_name,
                'shop_state'   => $row->shop_state,
                'product_code' => $row->product_code,
                'is_for_sale'  => (bool) $row->is_for_sale,
            ])->values()->all())
            ->all();
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array<int, array{month: string, sales: float}>>
     */
    private function monthlySales(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        return DB::table('org_stock_time_series')
            ->join('org_stock_time_series_records as r', 'r.org_stock_time_series_id', 'org_stock_time_series.id')
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->where('r.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
            ->whereIn('org_stock_time_series.org_stock_id', $orgStockIds)
            ->where('r.from', '>=', now()->subMonths(12)->startOfMonth())
            ->orderBy('r.from')
            ->select(['org_stock_time_series.org_stock_id', 'r.from', 'r.sales_grp_currency_external'])
            ->get()
            ->groupBy('org_stock_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => [
                'month' => Carbon::parse($row->from)->format('Y-m'),
                'sales' => (float) $row->sales_grp_currency_external,
            ])->values()->all())
            ->all();
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    private function statusHistory(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        return DB::table('audits')
            ->join('org_stocks', 'org_stocks.id', 'audits.auditable_id')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->leftJoin('users', 'users.id', 'audits.user_id')
            ->where('audits.auditable_type', 'OrgStock')
            ->whereIn('audits.auditable_id', $orgStockIds)
            ->where(function ($query) {
                $query->where('audits.event', 'state_change')
                    ->orWhere(function ($query) {
                        $query->where('audits.event', 'updated')
                            ->whereRaw('audits.new_values::text like ?', ['%"state"%']);
                    });
            })
            ->orderByDesc('audits.created_at')
            ->limit(20)
            ->select([
                'audits.old_values',
                'audits.new_values',
                'audits.created_at',
                'organisations.code as organisation_code',
                'users.username',
            ])
            ->get()
            ->map(function ($row) {
                $newValues = json_decode((string) $row->new_values, true) ?? [];
                $oldValues = json_decode((string) $row->old_values, true) ?? [];

                return [
                    'organisation' => $row->organisation_code,
                    'from'         => $oldValues['state'] ?? null,
                    'to'           => $newValues['to_state'] ?? $newValues['state'] ?? null,
                    'reason'       => $newValues['reason'] ?? null,
                    'source'       => $newValues['source'] ?? null,
                    'who'          => $newValues['requested_by'] ?? $row->username,
                    'at'           => $row->created_at,
                ];
            })
            ->all();
    }
}
