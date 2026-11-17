<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\UI;

use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Actions\Accounting\BankTransaction\ReconcileCodPayouts;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Http\Resources\Accounting\BankIncomingResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Every payment the organisation's banks received, each line in exactly one section: customers paying orders,
 * marketplace payouts, payment provider payouts, lines waiting for a person, and the rest.
 */
class IndexBankIncoming extends OrgAction
{
    use WithBankLines;

    public const array SECTIONS = ['customers', 'marketplaces', 'payment_accounts', 'other', 'needs_person'];

    public const string BUCKET_SQL = "case
        when bank_transactions.match_state = 'paid_by_marketplace' or (bank_transactions.match_state = 'unmatched' and bank_transactions.match_data->>'reason' = 'marketplace order not found') then 'marketplaces'
        when bank_transactions.match_state = 'card_processor_payout' or (bank_transactions.match_state = 'unmatched' and bank_transactions.match_data->>'reason' like 'PastPay payout%') then 'payment_accounts'
        when bank_transactions.payment_id is not null or bank_transactions.match_state = 'already_paid' then 'customers'
        when bank_transactions.match_state in ('review', 'unmatched', 'matched') then 'needs_person'
        else 'other' end";

    public const string PROVIDER_SQL = "case
        when bank_transactions.match_data->>'provider' = 'cod' then coalesce(bank_transactions.match_data->>'carrier', 'cod')
        when bank_transactions.match_state = 'unmatched' then 'pastpay'
        else coalesce(bank_transactions.match_data->>'provider', 'checkout') end";

    private string $section = 'needs_person';

    private ?string $provider = null;

    public function handle(Organisation|Group $organisation, string $section = 'needs_person', string $period = '90d', $prefix = null, ?string $currency = null, ?string $from = null, ?string $to = null, ?float $min = null, ?float $max = null, ?string $provider = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('bank_transactions.reference', $value)
                    ->orWhereAnyWordStartWith('bank_transactions.counterparty_name', $value)
                    ->orWhereWith('bank_connections.name', $value)
                    ->orWhere('orders.reference', '=', strtoupper($value));
                $amount = $this->searchedAmount($value);
                if ($amount !== null) {
                    $query->orWhere('bank_transactions.amount', $amount);
                }
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return $this->bankLines(QueryBuilder::for(BankTransaction::class), $organisation, $period, $currency, $from, $to, $min, $max)
            ->whereRaw('('.self::BUCKET_SQL.') = ?', [$section])
            ->when($provider, fn ($query) => $query->whereRaw('('.self::PROVIDER_SQL.') = ?', [$provider]))
            ->join('bank_connections', 'bank_connections.id', 'bank_transactions.bank_connection_id')
            ->leftJoin('orders', 'orders.id', 'bank_transactions.order_id')
            ->leftJoin('shops', 'shops.id', 'orders.shop_id')
            ->select([
                'bank_transactions.id',
                'bank_transactions.organisation_id',
                'bank_transactions.bank_connection_id',
                'bank_transactions.currency_id',
                'bank_transactions.order_id',
                'bank_transactions.booked_at',
                'bank_transactions.amount',
                'bank_transactions.counterparty_name',
                'bank_transactions.reference',
                'bank_transactions.match_state',
                'bank_transactions.match_data',
                'bank_transactions.payment_id',
                'bank_transactions.provider_type',
                'bank_connections.name as bank',
                'currencies.code as currency_code',
                'org_currencies.code as org_currency_code',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
                'orders.reference as order_reference',
                'orders.slug as order_slug',
                'shops.slug as shop_slug',
            ])
            ->selectRaw($this->orgAmountSql().' as org_amount')
            ->selectRaw(($organisation instanceof Group ? 'true' : 'false').' as read_only')
            ->defaultSort('-booked_at')
            ->allowedSorts(['booked_at', 'amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        return $this->inSection('needs_person', $organisation, $request);
    }

    public function customers(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        return $this->inSection('customers', $organisation, $request);
    }

    public function marketplaces(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        return $this->inSection('marketplaces', $organisation, $request);
    }

    public function paymentAccounts(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        return $this->inSection('payment_accounts', $organisation, $request);
    }

    public function other(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        return $this->inSection('other', $organisation, $request);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $slug = $request->query('org');
        $this->forGroup(group(), $request->user(), is_string($slug) ? $slug : null);
        $this->initialisationFromGroup(group(), $request);

        return $this->inSection(str($request->route()->getName())->afterLast('.')->toString(), group(), $request);
    }

    private function inSection(string $section, Organisation|Group $organisation, ActionRequest $request): LengthAwarePaginator
    {
        if ($organisation instanceof Organisation) {
            $this->initialisation($organisation, $request);
        }
        $this->section  = $section;
        $this->readFilters($organisation, $request);
        $this->provider = $section === 'payment_accounts' && is_string($request->query('provider')) && array_key_exists($request->query('provider'), self::providerLabels()) ? $request->query('provider') : null;

        return $this->handle($organisation, $this->section, $this->period, currency: $this->currency, from: $this->from, to: $this->to, min: $this->min, max: $this->max, provider: $this->provider);
    }

    /**
     * @return array<string, string>
     */
    public static function providerLabels(): array
    {
        return [
            'checkout' => 'Checkout',
            'pastpay'  => 'PastPay',
            ...collect(ReconcileCodPayouts::CARRIERS)->map(fn (array $carrier) => $carrier['name'])->all(),
            'cod'      => __('Cash on delivery'),
        ];
    }

    /**
     * The payment providers that paid out under the filters chosen, with how many lines each sent.
     *
     * @return array<int, array{value: string, label: string, count: int}>
     */
    public function providerCounts(Organisation|Group $organisation, string $period, ?string $currency, ?string $from, ?string $to, ?float $min = null, ?float $max = null): array
    {
        $labels = self::providerLabels();

        return $this->bankLines(BankTransaction::query(), $organisation, $period, $currency, $from, $to, $min, $max)
            ->whereRaw('('.self::BUCKET_SQL.") = 'payment_accounts'")
            ->selectRaw(self::PROVIDER_SQL.' as provider, count(*) as lines')
            ->groupByRaw('1')
            ->orderByDesc('lines')
            ->get()
            ->map(fn ($row) => ['value' => $row->provider, 'label' => $labels[$row->provider] ?? $row->provider, 'count' => (int) $row->lines])
            ->all();
    }

    /**
     * @return array<string, array{title: string, description: string}>
     */
    private function sectionTitle(): string
    {
        return match ($this->section) {
            'customers'        => __('Customers paying orders'),
            'marketplaces'     => __('Marketplaces'),
            'payment_accounts' => __('Provider payouts'),
            'other'            => __('Other'),
            default            => __('Needs a person'),
        };
    }

    private function emptyStates(): array
    {
        return [
            'customers'        => ['title' => __('No customer payments'), 'description' => __('No bank payment was placed on a customer order under these filters.')],
            'marketplaces'     => ['title' => __('No marketplace payouts'), 'description' => __('No marketplace paid out to the banks under these filters.')],
            'payment_accounts' => ['title' => __('No payment provider payouts'), 'description' => __('No card processor, PastPay or cash on delivery carrier paid out to the banks under these filters.')],
            'other'            => ['title' => __('Nothing else received'), 'description' => __('No payment marked not for an order, and no movement of the bank itself, under these filters.')],
            'needs_person'     => ['title' => __('Nothing waiting for a person'), 'description' => __('Every payment the banks received is placed on an order, marked already paid or marked not for an order.')],
        ];
    }

    public function htmlResponse(LengthAwarePaginator $bankTransactions, ActionRequest $request): Response
    {
        $parent        = $this->organisationIds === null ? $this->organisation : $this->group;
        $isGroup       = $parent instanceof Group;
        $filters       = [$parent, $this->period, $this->currency, $this->from, $this->to, $this->min, $this->max];
        $sectionTotals = $this->sectionTotals(...$filters);
        $providers     = $this->section === 'payment_accounts' ? $this->providerCounts(...$filters) : [];
        if (!$isGroup) {
            $this->suggestOrders($bankTransactions);
        }
        $query         = array_filter([
            'period'   => $this->from || $this->period === '90d' ? null : $this->period,
            'currency' => $this->currency,
            'from'     => $this->from,
            'to'       => $this->to,
            'min'      => $this->min,
            'max'      => $this->max,
            'org'      => $this->organisationSlug,
        ], fn ($value) => $value !== null);

        return Inertia::render(
            'Org/Accounting/BankIncoming',
            [
                'breadcrumbs' => $isGroup ? self::groupBreadcrumbs() : $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $this->sectionTitle(),
                'pageHead'    => [
                    'model'         => __('Bank incoming'),
                    'title'         => $this->sectionTitle(),
                    'icon'          => ['fal', 'fa-university'],
                    'subNavigation' => self::subNavigation($parent, array_map(fn (array $totals) => $totals['count'], $sectionTotals), $query),
                ],
                'canEdit'        => !$isGroup && $request->user()->authTo("accounting.{$this->organisation->id}.edit"),
                'organisationId' => $isGroup ? null : $this->organisation->id,
                'organisation'   => $this->organisationSlug,
                'organisations'  => $isGroup
                    ? self::accountingOrganisations($this->group, $request->user())->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->all()
                    : [],
                'section'        => $this->section,
                'sectionTotals'  => $sectionTotals,
                'summary'        => $this->provider
                    ? ['count' => (int) (collect($providers)->firstWhere('value', $this->provider)['count'] ?? 0), 'total' => null]
                    : $sectionTotals[$this->section],
                'provider'       => $this->provider,
                'providers'      => $providers,
                'currencyTotals' => $this->currencyTotals($parent, $this->section, $this->period, $this->from, $this->to, $this->min, $this->max, $this->provider),
                'period'         => $this->from ? null : $this->period,
                'currency'       => $this->currency,
                'currencies'     => $this->currencies($parent),
                'from'           => $this->from,
                'to'             => $this->to,
                'min'            => $this->min,
                'max'            => $this->max,
                'orgCurrency'    => $parent->currency->code,
                'data'           => BankIncomingResource::collection($bankTransactions),
            ]
        )->table($this->tableStructure());
    }

    /**
     * Lines naming no order get the orders of a customer named like the payer that this amount pays or already paid.
     */
    private function suggestOrders(LengthAwarePaginator $bankTransactions): void
    {
        $matcher = MatchBankTransactions::make();
        foreach ($bankTransactions->getCollection() as $bankTransaction) {
            if ($bankTransaction->order_id === null && $bankTransaction->payment_id === null
                && in_array($bankTransaction->match_state, [BankTransactionMatchStateEnum::UNMATCHED, BankTransactionMatchStateEnum::REVIEW], true)) {
                $bankTransaction->suggested_orders = $matcher->suggestedOrders($bankTransaction);
            }
        }
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withGlobalSearch()
                ->withEmptyState([...$this->emptyStates()[$this->section], 'count' => 0]);

            $table->column(key: 'booked_at', label: __('Date'), sortable: true);
            if ($this->organisationIds !== null) {
                $table->column(key: 'organisation_code', label: __('Organisation'));
            }
            $table->column(key: 'bank', label: __('Bank'));
            $table->column(key: 'amount', label: __('Amount'), sortable: true, align: 'right');
            $table->column(key: 'counterparty_name', label: __('Paid by'));
            $table->column(key: 'reference', label: __('Bank reference'));
            $table->column(key: 'order_reference', label: __('Order'));
            $table->column(key: 'actions', label: '');
        };
    }

    public static function groupBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']],
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.bank_incoming.index'], 'label' => __('Bank incoming')]],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.accounting.bank_incoming.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Bank incoming'),
                    ],
                ],
            ]
        );
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    public static function subNavigation(Organisation|Group $organisation, array $counts, array $query = []): array
    {
        return self::sectionsNavigation($organisation, 'bank_incoming', [
            'customers'        => [__('Customers paying orders'), ['fal', 'fa-user-friends']],
            'marketplaces'     => [__('Marketplaces'), ['fal', 'fa-share-alt']],
            'payment_accounts' => [__('Provider payouts'), ['fal', 'fa-box-usd']],
            'other'            => [__('Other'), ['fal', 'fa-dot-circle']],
            'needs_person'     => [__('Needs a person'), ['fal', 'fa-clipboard-list-check']],
        ], $counts, $query);
    }
}
