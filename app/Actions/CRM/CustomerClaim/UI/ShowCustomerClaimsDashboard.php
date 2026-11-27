<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim\UI;

use App\Actions\CRM\CustomerClaim\GetCustomerClaimSettings;
use App\Actions\Helpers\Ticket\UI\IndexTickets;
use App\Actions\Helpers\Ticket\UI\ShowTicketsReports;
use App\Actions\OrgAction;
use App\Enums\CRM\CustomerClaim\CustomerClaimKindEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimSourceEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Claims dashboard of a shop: the queue (waiting, oldest waiting), what was settled in the
 * period and how (automatically or by a person, rejected), how fast people review, and which
 * reasons, products and channels the claims come from, so packing and picking problems are seen.
 */
class ShowCustomerClaimsDashboard extends OrgAction
{
    use WithCustomerClaimsSection;

    public const int TOP = 10;

    public const int TOP_MIN_ORDERS = 5;

    public const int BAND_ROWS = 25;

    public function handle(Shop $shop, string $interval): array
    {
        $timezone    = $shop->timezone?->name ?? 'UTC';
        $zone        = DB::getPdo()->quote($timezone);
        $claims      = CustomerClaim::where('customer_claims.shop_id', $shop->id);
        [$from, $to] = ShowTicketsReports::make()->range($interval, (clone $claims)->min('created_at'), $timezone);
        $since       = $from;
        $inPeriod    = (clone $claims)->whereBetween('customer_claims.created_at', [$from, $to]);
        $all         = (clone $inPeriod)->where('customer_claims.kind', '!=', CustomerClaimKindEnum::TAX_CORRECTION);

        $waiting       = (clone $claims)->where('state', CustomerClaimStateEnum::IN_REVIEW);
        $oldestWaiting = (clone $waiting)->min('created_at');
        $settled       = (clone $all)->where('state', CustomerClaimStateEnum::SETTLED);
        $reviewHours   = (clone $all)->where('source', '!=', CustomerClaimSourceEnum::STAFF)->whereNotNull('reviewed_by_user_id')->whereNotNull('reviewed_at')
            ->selectRaw('avg(extract(epoch from (reviewed_at - created_at)) / 3600) as hours')->value('hours');

        $byReason = DB::table('customer_claim_lines')
            ->join('customer_claims', 'customer_claims.id', '=', 'customer_claim_lines.customer_claim_id')
            ->where('customer_claims.shop_id', $shop->id)->where('customer_claims.kind', CustomerClaimKindEnum::ITEMS->value)->whereBetween('customer_claims.created_at', [$from, $to])
            ->selectRaw('customer_claim_lines.reason, count(distinct customer_claims.id) as claims, sum(customer_claim_lines.quantity) as quantity')
            ->groupBy('customer_claim_lines.reason')->orderByDesc('claims')->get()
            ->map(fn ($row) => ['reason' => DeliveryNoteItemReplacementReasonEnum::tryFrom($row->reason)?->label() ?? $row->reason, 'claims' => (int) $row->claims, 'quantity' => (float) $row->quantity])
            ->all();

        $byProduct = DB::table('customer_claim_lines')
            ->join('customer_claims', 'customer_claims.id', '=', 'customer_claim_lines.customer_claim_id')
            ->where('customer_claims.shop_id', $shop->id)->where('customer_claims.kind', CustomerClaimKindEnum::ITEMS->value)->whereBetween('customer_claims.created_at', [$from, $to])
            ->selectRaw('customer_claim_lines.code, max(customer_claim_lines.name) as name, count(distinct customer_claims.id) as claims, sum(customer_claim_lines.quantity) as quantity')
            ->groupBy('customer_claim_lines.code')->orderByDesc('claims')->orderByDesc('quantity')->limit(10)->get()
            ->map(fn ($row) => ['code' => $row->code, 'name' => $row->name, 'claims' => (int) $row->claims, 'quantity' => (float) $row->quantity])
            ->all();

        $days   = (int) $from->copy()->setTimezone($timezone)->startOfDay()->diffInDays($to->copy()->setTimezone($timezone)->startOfDay()) + 1;
        $bucket = $days <= 62 ? 'day' : ($days <= 400 ? 'week' : 'month');
        $perBucket = fn ($query, string $column) => $query->selectRaw("to_char(date_trunc('$bucket', $column at time zone $zone), 'YYYY-MM-DD') as day, count(*) as total, coalesce(sum(value), 0) as value")->groupBy('day')->get()->keyBy('day');
        $madeByDay    = $perBucket((clone $all), 'created_at');
        $settledByDay = $perBucket((clone $claims)->where('kind', '!=', CustomerClaimKindEnum::TAX_CORRECTION)->whereNotNull('settled_at')->whereBetween('settled_at', [$from, $to]), 'settled_at');
        $series       = [];
        $cursor       = $from->copy()->setTimezone($timezone)->startOf($bucket);
        while ($cursor->lte($to)) {
            $day      = $cursor->toDateString();
            $series[] = [
                'date'          => $day,
                'made'          => (int) ($madeByDay[$day]->total ?? 0),
                'settled'       => (int) ($settledByDay[$day]->total ?? 0),
                'made_value'    => round((float) ($madeByDay[$day]->value ?? 0), 2),
                'settled_value' => round((float) ($settledByDay[$day]->value ?? 0), 2),
            ];
            $cursor->add(1, $bucket);
        }

        $states = [
            ['key' => 'in_review', 'label' => __('Waiting for review'), 'query' => (clone $all)->where('state', CustomerClaimStateEnum::IN_REVIEW)],
            ['key' => 'auto', 'label' => __('Settled automatically'), 'query' => (clone $all)->where('state', CustomerClaimStateEnum::SETTLED)->where('source', '!=', CustomerClaimSourceEnum::STAFF)->whereNull('reviewed_by_user_id')],
            ['key' => 'approved', 'label' => __('Approved by a person'), 'query' => (clone $all)->where('state', CustomerClaimStateEnum::SETTLED)->where('source', '!=', CustomerClaimSourceEnum::STAFF)->whereNotNull('reviewed_by_user_id')],
            ['key' => 'staff', 'label' => __('Made by staff'), 'query' => (clone $all)->where('source', CustomerClaimSourceEnum::STAFF)],
            ['key' => 'rejected', 'label' => __('Rejected'), 'query' => (clone $all)->where('state', CustomerClaimStateEnum::REJECTED)],
        ];
        $states = array_map(fn (array $state) => ['key' => $state['key'], 'label' => $state['label'], 'total' => (clone $state['query'])->count(), 'value' => round((float) (clone $state['query'])->sum('value'), 2)], $states);

        $orders          = Order::where('shop_id', $shop->id)->whereBetween('dispatched_at', [$from, $to]);
        $ordersCount     = (clone $orders)->count();
        $claimedOrders   = (clone $orders)->whereExists(fn ($query) => $query->selectRaw('1')->from('customer_claims')->whereColumn('customer_claims.order_id', 'orders.id'))->count();
        $invoiced        = (float) Invoice::where('shop_id', $shop->id)->where('type', InvoiceTypeEnum::INVOICE)->whereBetween('date', [$from, $to])->sum('total_amount');
        $lost            = (float) (clone $all)->where('state', '!=', CustomerClaimStateEnum::REJECTED)->sum('value');

        $firstOrders        = (clone $orders)->whereNotExists(fn ($query) => $query->selectRaw('1')->from('orders as earlier')->whereColumn('earlier.customer_id', 'orders.customer_id')->whereNotNull('earlier.dispatched_at')->whereColumn('earlier.dispatched_at', '<', 'orders.dispatched_at'));
        $firstOrdersCount   = (clone $firstOrders)->count();
        $firstOrdersClaimed = (clone $firstOrders)->whereExists(fn ($query) => $query->selectRaw('1')->from('customer_claims')->whereColumn('customer_claims.order_id', 'orders.id'))->count();

        return [
            'first_orders'         => $firstOrdersCount,
            'first_orders_claimed' => $firstOrdersClaimed,
            'orders'         => $ordersCount,
            'claimed_orders' => $claimedOrders,
            'invoiced'       => round($invoiced, 2),
            'lost'           => round($lost, 2),
            'top_customers'  => $this->topCustomers($shop, $from, $to),
            'claim_rates'    => $this->claimRates($shop, $from, $to),
            'interval'       => $interval,
            'bucket'         => $bucket,
            'from'           => $from->toIso8601String(),
            'series'         => $series,
            'states'         => $states,
            'total'          => (clone $all)->count(),
            'total_value'    => round((float) (clone $all)->sum('value'), 2),
            'currency_code'  => $shop->currency?->code,
            'waiting'        => (clone $waiting)->count(),
            'oldest_waiting' => $oldestWaiting,
            'made'           => (clone $all)->count(),
            'settled'        => (clone $settled)->count(),
            'auto_settled'   => (clone $settled)->where('source', '!=', CustomerClaimSourceEnum::STAFF)->whereNull('reviewed_by_user_id')->count(),
            'rejected'       => (clone $all)->where('state', CustomerClaimStateEnum::REJECTED)->count(),
            'settled_value'  => round((float) (clone $settled)->sum('value'), 2),
            'credited'       => round((float) (clone $settled)->where('kind', CustomerClaimKindEnum::ITEMS)->where('resolution', 'refund_to_balance')->sum('amount'), 2),
            'review_hours'   => $reviewHours !== null ? round((float) $reviewHours, 1) : null,
            'from_merlin'    => (clone $all)->where('source', CustomerClaimSourceEnum::MERLIN)->count(),
            'by_customer'    => (clone $all)->where('source', '!=', CustomerClaimSourceEnum::STAFF)->count(),
            'by_staff'       => (clone $all)->where('source', CustomerClaimSourceEnum::STAFF)->count(),
            'refunds'        => ['claims' => (clone $all)->where('kind', CustomerClaimKindEnum::REFUND)->count(), 'value' => round((float) (clone $all)->where('kind', CustomerClaimKindEnum::REFUND)->sum('value'), 2)],
            'tax_corrections' => ['claims' => (clone $inPeriod)->where('kind', CustomerClaimKindEnum::TAX_CORRECTION)->count(), 'value' => round((float) (clone $inPeriod)->where('kind', CustomerClaimKindEnum::TAX_CORRECTION)->sum('value'), 2)],
            'by_resolution'  => (clone $all)->selectRaw('resolution, count(*) as total')->groupBy('resolution')->pluck('total', 'resolution')
                ->map(fn ($total, $resolution) => ['label' => ShowCustomerClaim::resolutionLabel($resolution), 'claims' => (int) $total])->values()->all(),
            'by_reason'      => $byReason,
            'by_product'     => $byProduct,
            'by_carrier'     => $this->byDelivery($shop, $from, $to, 'carrier'),
            'by_warehouse'   => $this->byDelivery($shop, $from, $to, 'warehouse'),
            'by_packer'      => $this->byDelivery($shop, $from, $to, 'packer'),
            'auto_settled_merlin' => (clone $settled)->where('source', CustomerClaimSourceEnum::MERLIN)->whereNull('reviewed_by_user_id')->count(),
            'settings'       => GetCustomerClaimSettings::for($shop),
        ];
    }

    /**
     * Item claims in the period by where the claimed items left from: the carrier that took the
     * parcel, the warehouse, or the person who packed it, read from the delivery note items each
     * claim line points at. Lines with no delivery note item (old backfilled claims) are left out.
     *
     * @return array<int, array{label: string, claims: int, lines: int}>
     */
    private function byDelivery(Shop $shop, $from, $to, string $by): array
    {
        $query = DB::table('customer_claim_lines')
            ->join('customer_claims', 'customer_claims.id', '=', 'customer_claim_lines.customer_claim_id')
            ->crossJoin(DB::raw('lateral jsonb_array_elements(coalesce(customer_claim_lines.delivery_note_items::jsonb, \'[]\'::jsonb)) as claimed_item'))
            ->join('delivery_note_items', 'delivery_note_items.id', '=', DB::raw("(claimed_item->>'id')::bigint"))
            ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->where('customer_claims.shop_id', $shop->id)->where('customer_claims.kind', CustomerClaimKindEnum::ITEMS->value)->whereBetween('customer_claims.created_at', [$from, $to]);

        $label = match ($by) {
            'carrier'   => 'shippers.name',
            'warehouse' => 'warehouses.name',
            default     => 'coalesce(nullif(users.contact_name, \'\'), users.username)',
        };

        match ($by) {
            'carrier'   => $query->join('model_has_shipments', fn ($join) => $join->on('model_has_shipments.model_id', '=', 'delivery_notes.id')->where('model_has_shipments.model_type', 'DeliveryNote'))
                ->join('shipments', 'shipments.id', '=', 'model_has_shipments.shipment_id')
                ->join('shippers', 'shippers.id', '=', 'shipments.shipper_id'),
            'warehouse' => $query->join('warehouses', 'warehouses.id', '=', 'delivery_notes.warehouse_id'),
            default     => $query->join('users', 'users.id', '=', 'delivery_notes.packer_user_id'),
        };

        return $query->selectRaw("$label as label, count(distinct customer_claims.id) as claims, count(distinct customer_claim_lines.id) as lines")
            ->groupByRaw($label)->orderByDesc('claims')->limit(self::TOP)->get()
            ->map(fn ($row) => ['label' => (string) $row->label, 'claims' => (int) $row->claims, 'lines' => (int) $row->lines])
            ->all();
    }

    /**
     * The customers who claim most in the period, three ways: the value they claimed, how many
     * claims they made, the share of their own orders dispatched in the period that had a claim, and
     * what they claimed as a share of what they were invoiced (the last two only for customers with at
     * least TOP_MIN_ORDERS orders, so one order with one claim is not 100%).
     *
     * @return array{value: array<int, array<string, mixed>>, claims: array<int, array<string, mixed>>, share: array<int, array<string, mixed>>, lost: array<int, array<string, mixed>>}
     */
    private function topCustomers(Shop $shop, $from, $to): array
    {
        $rows = DB::table('customer_claims')
            ->join('customers', 'customers.id', '=', 'customer_claims.customer_id')
            ->where('customer_claims.shop_id', $shop->id)
            ->whereBetween('customer_claims.created_at', [$from, $to])
            ->where('customer_claims.state', '!=', CustomerClaimStateEnum::REJECTED->value)
            ->where('customer_claims.kind', '!=', CustomerClaimKindEnum::TAX_CORRECTION->value)
            ->groupBy('customers.id', 'customers.name', 'customers.slug', 'customers.reference')
            ->selectRaw('customers.id, customers.name, customers.slug, customers.reference, count(*) as claims, coalesce(sum(customer_claims.value), 0) as value, count(distinct customer_claims.order_id) as claimed_orders')
            ->get();

        $orders = Order::where('shop_id', $shop->id)->whereBetween('dispatched_at', [$from, $to])
            ->selectRaw('customer_id, count(*) as total')->groupBy('customer_id')->pluck('total', 'customer_id');
        $invoiced = Invoice::where('shop_id', $shop->id)->where('type', InvoiceTypeEnum::INVOICE)->whereBetween('date', [$from, $to])
            ->selectRaw('customer_id, sum(total_amount) as total')->groupBy('customer_id')->pluck('total', 'customer_id');

        $rows = $rows->map(fn ($row) => [
            'name'      => $row->name,
            'reference' => $row->reference,
            'route'     => ['name' => 'grp.org.shops.show.crm.customers.show', 'parameters' => [$shop->organisation->slug, $shop->slug, $row->slug]],
            'claims'    => (int) $row->claims,
            'value'     => round((float) $row->value, 2),
            'orders'    => (int) ($orders[$row->id] ?? 0),
            'share'     => ($orders[$row->id] ?? 0) > 0 ? round(min(1, $row->claimed_orders / $orders[$row->id]) * 100, 1) : null,
            'invoiced'  => round((float) ($invoiced[$row->id] ?? 0), 2),
            'lost'      => (float) ($invoiced[$row->id] ?? 0) > 0 ? round((float) $row->value / (float) $invoiced[$row->id] * 100, 1) : null,
        ]);

        return [
            'value'  => $rows->sortByDesc('value')->take(self::TOP)->values()->all(),
            'claims' => $rows->sortByDesc('claims')->take(self::TOP)->values()->all(),
            'share'  => $rows->filter(fn ($row) => $row['orders'] >= self::TOP_MIN_ORDERS && $row['share'] !== null)->sortByDesc('share')->take(self::TOP)->values()->all(),
            'lost'   => $rows->filter(fn ($row) => $row['orders'] >= self::TOP_MIN_ORDERS && $row['lost'] !== null)->sortByDesc('lost')->take(self::TOP)->values()->all(),
        ];
    }

    /**
     * How many customers (every customer with an order dispatched in the period, first orders included)
     * claimed what
     * share of what they were invoiced, in bands, with the customers of each band most claimed first.
     * Customers with no claim are in the 0% band, so the bars cover every regular customer.
     *
     * @return array<int, array{key: string, label: string, customers: int, rows: array<int, array<string, mixed>>}>
     */
    private function claimRates(Shop $shop, $from, $to): array
    {
        $orders = Order::where('shop_id', $shop->id)->whereBetween('dispatched_at', [$from, $to])
            ->selectRaw('customer_id, count(*) as total')->groupBy('customer_id')->pluck('total', 'customer_id');
        if ($orders->isEmpty()) {
            return [];
        }

        $invoiced = Invoice::where('shop_id', $shop->id)->where('type', InvoiceTypeEnum::INVOICE)->whereBetween('date', [$from, $to])
            ->selectRaw('customer_id, sum(total_amount) as total')->groupBy('customer_id')->pluck('total', 'customer_id');
        $claimed  = CustomerClaim::where('shop_id', $shop->id)->whereBetween('created_at', [$from, $to])->where('state', '!=', CustomerClaimStateEnum::REJECTED)->where('kind', '!=', CustomerClaimKindEnum::TAX_CORRECTION)
            ->selectRaw('customer_id, count(*) as claims, coalesce(sum(value), 0) as value, count(distinct order_id) as claimed_orders')->groupBy('customer_id')->get()->keyBy('customer_id');

        $bands = [
            ['key' => 'none', 'label' => '0%', 'max' => 0.0],
            ['key' => 'lt1', 'label' => '< 1%', 'max' => 1.0],
            ['key' => '1to2', 'label' => '1–2%', 'max' => 2.0],
            ['key' => '2to5', 'label' => '2–5%', 'max' => 5.0],
            ['key' => '5to10', 'label' => '5–10%', 'max' => 10.0],
            ['key' => '10to20', 'label' => '10–20%', 'max' => 20.0],
            ['key' => 'gt20', 'label' => '> 20%', 'max' => INF],
        ];
        $members = array_fill_keys(array_column($bands, 'key'), []);
        foreach ($orders as $customerId => $orderCount) {
            $value = (float) ($claimed[$customerId]->value ?? 0);
            $sales = (float) ($invoiced[$customerId] ?? 0);
            $rate  = $sales > 0 ? $value / $sales * 100 : ($value > 0 ? INF : 0.0);
            foreach ($bands as $band) {
                if ($band['key'] === 'none' ? $value <= 0 : $rate <= $band['max']) {
                    $members[$band['key']][] = ['id' => $customerId, 'rate' => $rate, 'value' => $value, 'sales' => $sales, 'orders' => (int) $orderCount, 'claims' => (int) ($claimed[$customerId]->claims ?? 0), 'claimed_orders' => (int) ($claimed[$customerId]->claimed_orders ?? 0)];
                    break;
                }
            }
        }

        $shown     = collect($members)->flatMap(fn ($rows) => collect($rows)->sortByDesc('rate')->take(self::BAND_ROWS))->pluck('id');
        $customers = DB::table('customers')->whereIn('id', $shown)->get(['id', 'name', 'slug', 'reference'])->keyBy('id');

        return array_map(fn (array $band) => [
            'key'       => $band['key'],
            'label'     => $band['label'],
            'customers' => count($members[$band['key']]),
            'rows'      => collect($members[$band['key']])->sortByDesc('rate')->take(self::BAND_ROWS)->map(fn (array $row) => [
                'name'      => $customers[$row['id']]->name ?? '',
                'reference' => $customers[$row['id']]->reference ?? '',
                'route'     => ['name' => 'grp.org.shops.show.crm.customers.show', 'parameters' => [$shop->organisation->slug, $shop->slug, $customers[$row['id']]->slug ?? '']],
                'claims'    => $row['claims'],
                'value'     => round($row['value'], 2),
                'orders'    => $row['orders'],
                'share'     => round(min(1, $row['claimed_orders'] / max(1, $row['orders'])) * 100, 1),
                'invoiced'  => round($row['sales'], 2),
                'lost'      => is_finite($row['rate']) ? round($row['rate'], 1) : null,
            ])->values()->all(),
        ], $bands);
    }

    public function htmlResponse(array $dashboard, ActionRequest $request): Response
    {
        $shop = $this->shop;

        return Inertia::render('Org/Shop/CRM/CustomerClaimsDashboard', [
            'breadcrumbs'       => $this->claimsBreadcrumbs($shop),
            'title'             => __('Claims'),
            'pageHead'          => [
                'title'         => __('Claims'),
                'icon'          => ['icon' => ['fal', 'fa-box-fragile'], 'title' => __('Claims')],
                'model'         => $shop->name,
                'subNavigation' => $this->claimsSubNavigation($shop),
            ],
            'dashboard'         => $dashboard,
            'intervals'         => IndexTickets::make()->createdIntervalOptions(),
            'can_edit_settings' => $request->user()->authTo('org-admin.'.$shop->organisation_id),
            'settings_route'    => ['name' => 'grp.org.shops.show.claims.settings.update', 'parameters' => [$shop->organisation->slug, $shop->slug]],
            'pending_route'     => ['name' => 'grp.org.shops.show.claims.pending', 'parameters' => [$shop->organisation->slug, $shop->slug]],
        ]);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);
        $interval = (string) $request->query('created', '1m');

        return $this->handle($shop, array_key_exists($interval, IndexTickets::make()->createdIntervalOptions()) ? $interval : '1m');
    }
}
