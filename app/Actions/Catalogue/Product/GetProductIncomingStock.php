<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sept 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerSupplyDurations;
use App\Actions\Production\PartnerShippingList\GetPartnerOrdersInTheMaking;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What is still on its way to the warehouse for a product's org stocks, and when it should land.
 *
 * Goods already turned into a stock delivery are counted there; a purchase order line is only
 * counted while no stock delivery past its draft holds that same org stock, so nothing is listed
 * twice and the rest of a partly delivered order stays visible.
 *
 * A supplier date is only ever one staff typed: the delivery's estimated receiving date, else its
 * purchase orders'. Goods from a partner organisation are dated from how far they got in the
 * partner (in stock, being made, not scheduled, picked, dispatched) plus the measured medians of
 * the legs still ahead, and are flagged is_estimate.
 */
class GetProductIncomingStock
{
    use AsObject;

    /** @var array<int, OrgPartner> */
    private array $orgPartners = [];

    private const array INCOMING_STOCK_DELIVERY_STATES = [
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
        StockDeliveryStateEnum::DISPATCHED,
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
    ];

    private const string PURCHASE_ORDER_TYPED_DATE = "coalesce(purchase_orders.estimated_received_at::date, nullif(purchase_orders.data->>'estimated_receiving_date', '')::date)";

    /**
     * @return array<int, array{type: string, supplier_name: string|null, supplier_code: string|null, supplier_type: string, reference: string, slug: string, org_stock_id: int, org_stock_code: string, org_stock_name: string, state: string, state_label: string, quantity: float, eta: string|null, organisation_slug: string}>
     */
    public function handle(Product $product, bool $isObscured = false): array
    {
        $lines = $this->forOrgStocks($product->orgStocks->pluck('id')->all());

        return $isObscured
            ? array_map(fn (array $line) => Arr::only($line, ['quantity', 'eta', 'is_estimate']), $lines)
            : $lines;
    }

    /**
     * @param  array<int, int> $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    public function forOrgStocks(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        return $this->sortedByEta($this->lines($orgStockIds));
    }

    /**
     * Everything on its way to an organisation, whatever product (if any) it is sold as.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forOrganisation(Organisation $organisation): array
    {
        return $this->sortedByEta($this->lines([], $organisation->id));
    }

    /**
     * @param  array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    private function sortedByEta(array $lines): array
    {
        usort($lines, fn ($a, $b) => [$a['eta'] === null, $a['eta']] <=> [$b['eta'] === null, $b['eta']]);

        return $lines;
    }

    /**
     * @param  array<int, int> $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $orgStockIds, ?int $organisationId = null): array
    {
        return array_merge(
            $this->stockDeliveryLines($orgStockIds, $organisationId),
            $this->purchaseOrderLines($orgStockIds, $organisationId),
            $this->partnerLines($orgStockIds, $organisationId)
        );
    }

    /**
     * The earliest date any of it should be on the shelf, or null when nothing is coming.
     */
    public function earliestEta(Product $product): ?string
    {
        foreach ($this->handle($product) as $line) {
            if ($line['eta']) {
                return $line['eta'];
            }
        }

        return null;
    }

    /**
     * The earliest date each product should be back on the shelf, worked out for a whole
     * page of products at once. Products with nothing on its way are left out.
     *
     * @param  array<int, int> $productIds
     * @return array<int, string>
     */
    public function earliestEtaByProduct(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $orgStockIdsByProduct = DB::table('product_has_org_stocks')
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'org_stock_id'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('org_stock_id')->all());

        $orgStockIds = $orgStockIdsByProduct->flatten()->unique()->values()->all();

        if (!$orgStockIds) {
            return [];
        }

        $earliestEtaByOrgStock = collect($this->lines($orgStockIds))
            ->filter(fn ($line) => $line['eta'])
            ->groupBy('org_stock_id')
            ->map(fn ($lines) => $lines->min('eta'));

        return $orgStockIdsByProduct
            ->map(fn ($productOrgStockIds) => $earliestEtaByOrgStock->only($productOrgStockIds)->min())
            ->filter()
            ->all();
    }

    /**
     * @param  array<int, int> $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    private function stockDeliveryLines(array $orgStockIds, ?int $organisationId): array
    {
        return DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->join('org_stocks', 'org_stocks.id', 'stock_delivery_items.org_stock_id')
            ->join('organisations', 'organisations.id', 'stock_deliveries.organisation_id')
            ->when(
                $organisationId,
                fn ($query) => $query->where('stock_deliveries.organisation_id', $organisationId),
                fn ($query) => $query->whereIn('stock_delivery_items.org_stock_id', $orgStockIds)
            )
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->whereIn('stock_deliveries.state', self::INCOMING_STOCK_DELIVERY_STATES)
            ->select([
                'stock_deliveries.reference',
                'stock_deliveries.slug',
                'stock_deliveries.state',
                'stock_deliveries.parent_type',
                'stock_deliveries.parent_id',
                'stock_deliveries.parent_name',
                'stock_deliveries.parent_code',
                'stock_deliveries.dispatched_at',
                'stock_deliveries.received_at',
                DB::raw("coalesce(
                    nullif(stock_deliveries.data->>'estimated_receiving_date', '')::date,
                    (select min(".self::PURCHASE_ORDER_TYPED_DATE.")
                        from purchase_order_stock_delivery
                        join purchase_orders on purchase_orders.id = purchase_order_stock_delivery.purchase_order_id
                        where purchase_order_stock_delivery.stock_delivery_id = stock_deliveries.id)
                ) as typed_eta"),
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'organisations.slug as organisation_slug',
                DB::raw('(stock_delivery_items.unit_quantity - coalesce(stock_delivery_items.unit_quantity_placed, 0)) as quantity'),
            ])
            ->get()
            ->filter(fn ($row) => $row->quantity > 0)
            ->map(fn ($row) => [
                'type'              => 'stock_delivery',
                'supplier_name'     => $row->parent_name,
                'supplier_code'     => $row->parent_code,
                'supplier_type'     => $row->parent_type,
                'reference'         => $row->reference,
                'slug'              => $row->slug,
                'org_stock_id'      => $row->org_stock_id,
                'org_stock_code'    => $row->org_stock_code,
                'org_stock_name'    => $row->org_stock_name,
                'state'             => $row->state,
                'state_label'       => StockDeliveryStateEnum::labels()[$row->state],
                'quantity'          => (float) $row->quantity,
                'eta'               => $row->typed_eta || $row->parent_type !== 'OrgPartner'
                    ? $this->eta($row->typed_eta)
                    : $this->partnerDeliveryEta($row),
                'is_estimate'       => !$row->typed_eta && $row->parent_type === 'OrgPartner',
                'organisation_slug' => $row->organisation_slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int> $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    private function purchaseOrderLines(array $orgStockIds, ?int $organisationId): array
    {
        return DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('org_stocks', 'org_stocks.id', 'purchase_order_transactions.org_stock_id')
            ->join('organisations', 'organisations.id', 'purchase_orders.organisation_id')
            ->when(
                $organisationId,
                fn ($query) => $query->where('purchase_orders.organisation_id', $organisationId),
                fn ($query) => $query->whereIn('purchase_order_transactions.org_stock_id', $orgStockIds)
            )
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
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('purchase_order_stock_delivery')
                    ->join('stock_deliveries', 'stock_deliveries.id', 'purchase_order_stock_delivery.stock_delivery_id')
                    ->join('stock_delivery_items', 'stock_delivery_items.stock_delivery_id', 'stock_deliveries.id')
                    ->whereColumn('purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
                    ->whereColumn('stock_delivery_items.org_stock_id', 'purchase_order_transactions.org_stock_id')
                    ->whereNull('stock_delivery_items.deleted_at')
                    ->whereNull('stock_deliveries.deleted_at')
                    ->whereNotIn('stock_deliveries.state', [
                        StockDeliveryStateEnum::IN_PROCESS->value,
                        StockDeliveryStateEnum::CANCELLED->value,
                        StockDeliveryStateEnum::NOT_RECEIVED->value,
                    ]);
            })
            ->select([
                'purchase_orders.reference',
                'purchase_orders.slug',
                'purchase_orders.parent_name',
                'purchase_orders.parent_code',
                'purchase_orders.parent_type',
                'purchase_orders.agent_id',
                'purchase_orders.delivery_state',
                DB::raw(self::PURCHASE_ORDER_TYPED_DATE.' as typed_eta'),
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'organisations.slug as organisation_slug',
                DB::raw('(coalesce(purchase_order_transactions.quantity_ordered, 0) - coalesce(purchase_order_transactions.quantity_cancelled, 0)) as quantity'),
            ])
            ->get()
            ->filter(fn ($row) => $row->quantity > 0)
            ->map(fn ($row) => [
                'type'              => 'purchase_order',
                'supplier_name'     => $row->parent_name,
                'supplier_code'     => $row->parent_code,
                'supplier_type'     => $row->agent_id !== null ? 'OrgAgent' : $row->parent_type,
                'reference'         => $row->reference,
                'slug'              => $row->slug,
                'org_stock_id'      => $row->org_stock_id,
                'org_stock_code'    => $row->org_stock_code,
                'org_stock_name'    => $row->org_stock_name,
                'state'             => $row->delivery_state,
                'state_label'       => PurchaseOrderDeliveryStateEnum::labels()[$row->delivery_state],
                'quantity'          => (float) $row->quantity,
                'eta'               => $this->eta($row->typed_eta),
                'is_estimate'       => false,
                'organisation_slug' => $row->organisation_slug,
            ])
            ->values()
            ->all();
    }

    private function partnerDeliveryEta(object $row): string
    {
        $durations = GetPartnerSupplyDurations::run($this->orgPartners[$row->parent_id] ??= OrgPartner::find($row->parent_id));

        if ($row->received_at) {
            return $this->estimate(now(), 0);
        }

        if ($row->dispatched_at) {
            return $this->estimate(Carbon::parse($row->dispatched_at), $durations['transit']);
        }

        return $this->estimate(now(), $durations['dispatch'] + $durations['transit']);
    }

    /**
     * @param  array<int, int> $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    private function partnerLines(array $orgStockIds, ?int $organisationId): array
    {
        $items = DB::table('partner_shopping_list_items')
            ->join('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
            ->join('organisations', 'organisations.id', 'partner_shopping_list_items.organisation_id')
            ->join('organisations as partners', 'partners.id', 'partner_shopping_list_items.partner_organisation_id')
            ->leftJoin('org_stocks as partner_org_stocks', function ($join) {
                $join->on('partner_org_stocks.stock_id', 'partner_shopping_list_items.stock_id')
                    ->on('partner_org_stocks.organisation_id', 'partner_shopping_list_items.partner_organisation_id');
            })
            ->leftJoin('job_orders', function ($join) {
                $join->on('job_orders.id', 'partner_shopping_list_items.job_order_id')
                    ->whereNull('job_orders.deleted_at')
                    ->whereIn('job_orders.state', [JobOrderStateEnum::IN_PROCESS->value, JobOrderStateEnum::SUBMITTED->value, JobOrderStateEnum::CONFIRMED->value]);
            })
            ->leftJoin('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
            ->leftJoin('orders', 'orders.id', 'transactions.order_id')
            ->when(
                $organisationId,
                fn ($query) => $query->where('partner_shopping_list_items.organisation_id', $organisationId),
                fn ($query) => $query->whereIn('partner_shopping_list_items.org_stock_id', $orgStockIds)
            )
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->where(function ($query) {
                $query->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN)
                    ->orWhere(function ($query) {
                        $query->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::ORDERED)
                            ->whereNull('transactions.deleted_at')
                            ->whereIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::SUBMITTED->value]);
                    });
            })
            ->get([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.state',
                'partner_shopping_list_items.org_partner_id',
                'partner_shopping_list_items.partner_organisation_id',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.created_at',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.measured_lead_time_days',
                'org_stocks.estimated_lead_time_days',
                'organisations.slug as organisation_slug',
                'partners.name as partner_name',
                'partners.code as partner_code',
                'partner_org_stocks.quantity_available as partner_quantity_available',
                'orders.reference as order_reference',
                'job_orders.reference as job_order_reference',
                'job_orders.employee_id as job_order_employee_id',
                DB::raw('coalesce(job_orders.confirmed_at, job_orders.created_at) as job_order_started_at'),
            ]);

        if ($items->isEmpty()) {
            return [];
        }

        $allocations = $items->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->pluck('partner_organisation_id')
            ->unique()
            ->flatMap(fn ($sellerId) => GetPartnerOrdersInTheMaking::make()->allocations(Organisation::find($sellerId)))
            ->keyBy(fn ($allocation) => $allocation['line']->id);

        $artisans = DB::table('employees')
            ->whereIn('id', $items->pluck('job_order_employee_id')->filter()->unique())
            ->pluck('contact_name', 'id');

        $orgPartners   = OrgPartner::whereIn('id', $items->pluck('org_partner_id')->unique())->get()->keyBy('id');
        $leadTimeDays  = $orgPartners->map(fn (OrgPartner $orgPartner) => GetPartnerLeadTime::run($orgPartner)['days']);

        $lines = [];
        foreach ($items as $item) {
            $orgPartner = $orgPartners->get($item->org_partner_id);
            $durations  = GetPartnerSupplyDurations::run($orgPartner);
            $toBuyer    = $durations['dispatch'] + $durations['transit'];
            $line       = fn (string $reference, string $label, float $quantity, string $eta) => [
                'type'              => 'partner_request',
                'supplier_name'     => $item->partner_name,
                'supplier_code'     => $item->partner_code,
                'supplier_type'     => 'OrgPartner',
                'org_partner_id'    => $item->org_partner_id,
                'reference'         => $reference,
                'slug'              => null,
                'org_stock_id'      => $item->org_stock_id,
                'org_stock_code'    => $item->org_stock_code,
                'org_stock_name'    => $item->org_stock_name,
                'state'             => $item->state,
                'state_label'       => $label,
                'quantity'          => round($quantity, 3),
                'eta'               => $eta,
                'is_estimate'       => true,
                'organisation_slug' => $item->organisation_slug,
            ];

            if ($item->state === ShoppingListItemStateEnum::ORDERED->value) {
                $lines[] = $line($item->order_reference, __('Picked by :partner', ['partner' => $item->partner_name]), (float) $item->quantity, $this->estimate(now(), $toBuyer));
                continue;
            }

            $allocation = $allocations->get($item->id);
            $ready      = match (true) {
                (bool) $allocation              => $allocation['in_the_bay'] + $allocation['on_the_shelves'],
                (bool) $item->job_order_reference => 0.0,
                default                         => min((float) $item->quantity, max(0.0, (float) $item->partner_quantity_available)),
            };
            $waiting    = (float) $item->quantity - $ready;

            if ($ready > 0) {
                $pickedAround = Carbon::parse($item->created_at)->addMinutes((int) round($durations['pick_wait'] * 1440))->max(now());
                $lines[]      = $line($item->partner_name, __('In stock at :partner, waiting to be picked', ['partner' => $item->partner_name]), $ready, $this->estimate($pickedAround, $toBuyer));
            }

            if ($waiting <= 0) {
                continue;
            }

            if ($item->job_order_reference) {
                $madeAround = Carbon::parse($item->job_order_started_at)->addMinutes((int) round($durations['production'] * 1440))->max(now());
                $artisan    = $artisans->get($item->job_order_employee_id);
                $label      = $artisan
                    ? __('Being made at :partner by :artisan', ['partner' => $item->partner_name, 'artisan' => $artisan])
                    : __('Being made at :partner', ['partner' => $item->partner_name]);
                $lines[]    = $line($item->job_order_reference, $label, $waiting, $this->estimate($madeAround, $toBuyer));
                continue;
            }

            $byLeadTime   = Carbon::parse($item->created_at)->addDays((int) ($item->measured_lead_time_days ?? $item->estimated_lead_time_days ?? $leadTimeDays->get($item->org_partner_id)));
            $byProduction = now()->addMinutes((int) round(($durations['production'] + $toBuyer) * 1440));
            $lines[]      = $line($item->partner_name, __('Not scheduled yet at :partner', ['partner' => $item->partner_name]), $waiting, $byLeadTime->max($byProduction)->max(now()->addDay())->toDateString());
        }

        return $lines;
    }

    private function estimate(Carbon $from, float $days): string
    {
        return $from->copy()->addMinutes((int) round($days * 1440))->max(now()->addDay())->toDateString();
    }

    /**
     * A typed date already past is reported as tomorrow: the goods are late, not gone.
     */
    private function eta(?string $typedEta): ?string
    {
        if (!$typedEta) {
            return null;
        }

        return Carbon::parse($typedEta)->max(now()->addDay())->toDateString();
    }
}
