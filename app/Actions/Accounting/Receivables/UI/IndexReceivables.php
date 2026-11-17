<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Receivables\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Accounting\ReceivablesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The lists behind Receivables: the unpaid invoices (by age, owed by customers, by our own companies or by marketplaces, or legacy from Aurora), the debtors
 * (one row per customer), the customer balances (credit we hold for them, or below zero) and the cash on delivery
 * invoices still unpaid, with the courier and tracking of their parcels, oldest dispatch first, for staff to chase.
 */
class IndexReceivables extends OrgAction
{
    use WithReceivables;

    public const array SECTIONS = ['invoices', 'debtors', 'balances', 'cash_on_delivery'];

    private string $section = 'invoices';

    private ?string $age = null;

    private string $owedBy = 'customers';

    private ?string $side = null;

    private ?string $courier = null;

    /**
     * @param Collection<int, Organisation> $organisations
     */
    public function handle(Organisation|Group $parent, Collection $organisations, string $section = 'invoices', ?string $age = null, string $owedBy = 'customers', ?string $side = null, ?Carbon $today = null, $prefix = null, ?string $courier = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
        $customerSelect = [
            'customers.name as customer_name',
            'customers.reference as customer_reference',
            'customers.slug',
            'shops.code as shop_code',
            'shops.slug as shop_slug',
            'shops.type as shop_type',
            'fulfilments.slug as fulfilment_slug',
            'organisations.slug as organisation_slug',
            'organisations.code as organisation_code',
            'currencies.code as currency_code',
        ];
        $customerSearch = fn (array $more = []) => AllowedFilter::callback('global', function ($query, $value) use ($more) {
            $query->where(function ($query) use ($value, $more) {
                $query->whereAnyWordStartWith('customers.name', $value)->orWhere('customers.reference', '=', $value);
                foreach ($more as $column) {
                    $query->orWhereWith($column, $value);
                }
            });
        });

        $query = match ($section) {
            'debtors'  => QueryBuilder::for(Customer::class)
                ->joinSub($this->owedPerCustomer($parent, $organisations, $today ?? now()), 'owed', 'owed.customer_id', 'customers.id')
                ->join('shops', 'shops.id', 'customers.shop_id')
                ->join('currencies', 'currencies.id', 'shops.currency_id')
                ->join('organisations', 'organisations.id', 'customers.organisation_id')
                ->select(['customers.id', 'customers.as_organisation_id', 'owed.amount', 'owed.native', 'owed.invoices', 'owed.oldest', 'owed.days_overdue', ...$customerSelect])
                ->defaultSort('-amount')
                ->allowedSorts(['amount', 'invoices', 'oldest', 'customer_name'])
                ->allowedFilters([$customerSearch()]),
            'balances' => QueryBuilder::for(self::customerBalances($organisations))
                ->when($side, fn ($query) => $query->where('customers.balance', $side === 'credit' ? '>' : '<', 0))
                ->select(['customers.id', 'customers.balance', ...$customerSelect])
                ->selectRaw(self::convertedBalanceSql($parent).' as amount')
                ->defaultSort('-balance')
                ->allowedSorts(['balance', 'amount', 'customer_name'])
                ->allowedFilters([$customerSearch()]),
            'cash_on_delivery' => QueryBuilder::for($this->cashOnDelivery($parent, $organisations, $courier))
                ->leftJoinLateral($this->parcels(), 'parcels')
                ->select(['invoices.id', 'invoices.reference', 'invoices.slug as invoice_slug', 'invoices.total_amount', 'orders.reference as order_reference', 'orders.slug as order_slug', 'orders.dispatched_at', 'parcels.couriers', 'parcels.tracking', ...$customerSelect])
                ->selectRaw(self::OWED_SQL.' as native, '.self::convertedSql($parent).' as amount, '.self::daysOutstandingSql($today ?? now()).' as days_outstanding')
                ->defaultSort('-days_outstanding', 'reference')
                ->allowedSorts(['days_outstanding', 'native', 'amount', 'reference'])
                ->allowedFilters([$customerSearch(['invoices.reference', 'orders.reference'])]),
            default    => QueryBuilder::for($this->invoices($parent, $organisations, $age, $owedBy, $today ?? now()))
                ->select(['invoices.id', 'invoices.reference', 'invoices.slug as invoice_slug', 'invoices.date', 'invoices.total_amount', 'invoices.is_cash_on_delivery', 'invoices.is_pastpay', ...$customerSelect])
                ->selectRaw(self::OWED_SQL.' as native, '.self::convertedSql($parent).' as amount, '.self::daysOverdueSql($today ?? now()).' as days_overdue')
                ->defaultSort('-days_overdue', 'reference')
                ->allowedSorts(['date', 'days_overdue', 'native', 'amount', 'total_amount', 'reference'])
                ->allowedFilters([$customerSearch(['invoices.reference'])]),
        };

        return $query
            ->leftJoin('fulfilments', 'fulfilments.shop_id', 'shops.id')
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @param Collection<int, Organisation> $organisations
     */
    private function invoices(Organisation|Group $parent, Collection $organisations, ?string $age, string $owedBy, Carbon $today): \Illuminate\Database\Eloquent\Builder
    {
        return self::owedInvoices(Invoice::query(), $parent, $organisations, $owedBy)
            ->when($age, fn ($query) => $query->whereRaw('('.self::ageSql($today).') = ?', [$age]));
    }

    /**
     * Unpaid cash on delivery invoices of dispatched orders; with a courier, only those with a parcel sent with it.
     *
     * @param Collection<int, Organisation> $organisations
     */
    private function cashOnDelivery(Organisation|Group $parent, Collection $organisations, ?string $courier = null): \Illuminate\Database\Eloquent\Builder
    {
        return self::owedInvoices(Invoice::query(), $parent, $organisations, 'customers')
            ->join('orders', 'orders.id', 'invoices.order_id')
            ->whereNotNull('orders.dispatched_at')
            ->where('invoices.is_cash_on_delivery', true)
            ->when($courier, fn ($query) => $query->whereExists($this->parcelsQuery()->join('shippers', 'shippers.id', 'shipments.shipper_id')->where('shippers.name', $courier)));
    }

    private function parcelsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('delivery_note_order')
            ->join('model_has_shipments', fn ($join) => $join->on('model_has_shipments.model_id', 'delivery_note_order.delivery_note_id')->where('model_has_shipments.model_type', 'DeliveryNote'))
            ->join('shipments', 'shipments.id', 'model_has_shipments.shipment_id')
            ->whereColumn('delivery_note_order.order_id', 'invoices.order_id');
    }

    private function parcels(): \Illuminate\Database\Query\Builder
    {
        return $this->parcelsQuery()
            ->leftJoin('shippers', 'shippers.id', 'shipments.shipper_id')
            ->selectRaw("string_agg(distinct shippers.name, ', ') as couriers, string_agg(distinct shipments.tracking, ', ') as tracking");
    }

    public static function daysOutstandingSql(Carbon $today): string
    {
        return "(date '{$today->toDateString()}' - orders.dispatched_at::date)";
    }

    /**
     * The couriers of the unpaid cash on delivery parcels, with how many invoices each.
     *
     * @return array<int, array{value: string, label: string, count: int}>
     */
    private function couriers(): array
    {
        return $this->cashOnDelivery($this->parent(), $this->readOrganisations)
            ->join('delivery_note_order', 'delivery_note_order.order_id', 'orders.id')
            ->join('model_has_shipments', fn ($join) => $join->on('model_has_shipments.model_id', 'delivery_note_order.delivery_note_id')->where('model_has_shipments.model_type', 'DeliveryNote'))
            ->join('shipments', 'shipments.id', 'model_has_shipments.shipment_id')
            ->join('shippers', 'shippers.id', 'shipments.shipper_id')
            ->selectRaw('shippers.name, count(distinct invoices.id) as count')
            ->groupBy('shippers.name')
            ->orderByDesc('count')
            ->toBase()
            ->get()
            ->map(fn ($row) => ['value' => $row->name, 'label' => $row->name, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @param Collection<int, Organisation> $organisations
     */
    private function owedPerCustomer(Organisation|Group $parent, Collection $organisations, Carbon $today): \Illuminate\Database\Query\Builder
    {
        return self::owedInvoices(DB::table('invoices'), $parent, $organisations, 'customers')
            ->selectRaw('invoices.customer_id, sum('.self::convertedSql($parent).') as amount, sum('.self::OWED_SQL.') as native, count(*) as invoices, min(invoices.date) as oldest, max('.self::daysOverdueSql($today).') as days_overdue')
            ->groupBy('invoices.customer_id');
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        return $this->inSection($this->readOrganisation($organisation, $request), $request);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        return $this->inSection($this->readGroup($request), $request);
    }

    private function inSection(Organisation|Group $parent, ActionRequest $request): LengthAwarePaginator
    {
        $this->section      = str($request->route()->getName())->afterLast('.')->toString();
        $this->age          = $this->section === 'invoices' && in_array($request->query('age'), self::AGES, true) ? $request->query('age') : null;
        $this->owedBy       = $this->section === 'invoices' && in_array($request->query('owed_by'), self::OWED_BY, true) ? $request->query('owed_by') : 'customers';
        $this->side         = $this->section === 'balances' && in_array($request->query('side'), ['credit', 'debt'], true) ? $request->query('side') : null;
        $this->courier      = $this->section === 'cash_on_delivery' && is_string($request->query('courier')) ? $request->query('courier') : null;

        return $this->handle($parent, $this->readOrganisations, $this->section, $this->age, $this->owedBy, $this->side, courier: $this->courier);
    }

    private function sectionTitle(): string
    {
        return match ($this->section) {
            'debtors'  => __('Debtors'),
            'balances' => __('Customer balances'),
            'cash_on_delivery' => __('Cash on delivery not paid'),
            default    => __('Unpaid invoices'),
        };
    }

    /**
     * How many rows the filters chosen leave and what they add up to, in the currency of the page.
     *
     * @return array{count: int, total: float}
     */
    private function summary(): array
    {
        $parent  = $this->parent();
        $summary = match ($this->section) {
            'debtors'  => DB::query()->fromSub($this->owedPerCustomer($parent, $this->readOrganisations, now()), 'owed')->selectRaw('count(*) as count, sum(owed.amount) as total')->first(),
            'balances' => self::customerBalances($this->readOrganisations)
                ->when($this->side, fn ($query) => $query->where('customers.balance', $this->side === 'credit' ? '>' : '<', 0))
                ->selectRaw('count(*) as count, sum('.self::convertedBalanceSql($parent).') as total')->toBase()->first(),
            'cash_on_delivery' => $this->cashOnDelivery($parent, $this->readOrganisations, $this->courier)
                ->selectRaw('count(*) as count, sum('.self::convertedSql($parent).') as total')->toBase()->first(),
            default    => $this->invoices($parent, $this->readOrganisations, $this->age, $this->owedBy, now())
                ->selectRaw('count(*) as count, sum('.self::convertedSql($parent).') as total')->toBase()->first(),
        };

        return ['count' => (int) $summary->count, 'total' => round((float) $summary->total, 2)];
    }

    public function htmlResponse(LengthAwarePaginator $rows, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/Receivables',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => $this->sectionTitle(),
                'pageHead'      => [
                    'model'         => __('Receivables'),
                    'title'         => $this->sectionTitle(),
                    'icon'          => ['fal', 'fa-hand-holding-usd'],
                    'subNavigation' => $this->subNavigation(),
                ],
                'section'       => $this->section,
                'organisation'  => $this->organisationSlug,
                'organisations' => $this->organisationOptions(),
                'isGroup'       => $this->isGroup(),
                'currency'      => $this->parent()->currency->code,
                'age'           => $this->age,
                'owedBy'        => $this->owedBy,
                'side'          => $this->side,
                'courier'       => $this->courier,
                'couriers'      => $this->section === 'cash_on_delivery' ? $this->couriers() : [],
                'summary'       => $this->summary(),
                'data'          => ReceivablesResource::collection($rows),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withGlobalSearch()->withEmptyState(match ($this->section) {
                'debtors'  => ['title' => __('No customer owes us'), 'description' => __('Every invoice of a customer is paid.'), 'count' => 0],
                'cash_on_delivery' => ['title' => __('No cash on delivery left to collect'), 'description' => __('Every dispatched cash on delivery order is paid under these filters.'), 'count' => 0],
                'balances' => ['title' => __('No customer balances'), 'description' => __('No customer holds credit with us or is below zero under these filters.'), 'count' => 0],
                default    => ['title' => __('No unpaid invoices'), 'description' => __('No invoice is left to pay under these filters.'), 'count' => 0],
            });

            if ($this->section === 'cash_on_delivery') {
                $table->column(key: 'order_reference', label: __('Order'));
                $table->column(key: 'dispatched_at', label: __('Dispatched'));
                $table->column(key: 'days_outstanding', label: __('Days outstanding'), sortable: true, align: 'right');
            }
            if ($this->section === 'invoices') {
                $table->column(key: 'reference', label: __('Invoice'), sortable: true);
                $table->column(key: 'date', label: __('Date'), sortable: true);
            }
            if ($this->isGroup()) {
                $table->column(key: 'organisation_code', label: __('Organisation'));
            }
            $table->column(key: 'shop_code', label: __('Shop'));
            $table->column(key: 'customer_name', label: __('Customer'), sortable: !in_array($this->section, ['invoices', 'cash_on_delivery'], true));
            if ($this->section === 'cash_on_delivery') {
                $table->column(key: 'couriers', label: __('Courier'));
                $table->column(key: 'tracking', label: __('Tracking'));
                $table->column(key: 'reference', label: __('Invoice'), sortable: true);
                $table->column(key: 'amount', label: __('Cash on delivery'), sortable: true, align: 'right');
            } elseif ($this->section === 'invoices') {
                $table->column(key: 'days_overdue', label: __('Overdue'), sortable: true, align: 'right');
                $table->column(key: 'total_amount', label: __('Total'), sortable: true, align: 'right');
                $table->column(key: 'amount', label: __('Owed'), sortable: true, align: 'right');
            } elseif ($this->section === 'debtors') {
                $table->column(key: 'invoices', label: __('Invoices'), sortable: true, align: 'right');
                $table->column(key: 'oldest', label: __('Oldest unpaid'), sortable: true);
                $table->column(key: 'amount', label: __('Owed'), sortable: true, align: 'right');
            } else {
                $table->column(key: 'balance', label: __('Balance'), sortable: true, align: 'right');
            }
        };
    }
}
