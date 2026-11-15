<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\UI;

use App\Actions\Accounting\BankTransaction\ReconcileCodPayouts;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Http\Resources\Accounting\BankIncomingResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Support\Carbon;
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
    use WithAccountingPagesAuthorisation;

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

    public const array PERIODS = ['90d', '1y', 'all'];

    private string $section = 'needs_person';

    private ?string $provider = null;

    private string $period = '90d';

    private ?string $currency = null;

    private ?string $from = null;

    private ?string $to = null;

    private ?float $min = null;

    private ?float $max = null;

    public function handle(Organisation $organisation, string $section = 'needs_person', string $period = '90d', $prefix = null, ?string $currency = null, ?string $from = null, ?string $to = null, ?float $min = null, ?float $max = null, ?string $provider = null): LengthAwarePaginator
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

        return $this->linesReceived(QueryBuilder::for(BankTransaction::class), $organisation, $period, $currency, $from, $to, $min, $max)
            ->whereRaw('('.self::BUCKET_SQL.') = ?', [$section])
            ->when($provider, fn ($query) => $query->whereRaw('('.self::PROVIDER_SQL.') = ?', [$provider]))
            ->join('bank_connections', 'bank_connections.id', 'bank_transactions.bank_connection_id')
            ->leftJoin('orders', 'orders.id', 'bank_transactions.order_id')
            ->leftJoin('shops', 'shops.id', 'orders.shop_id')
            ->select([
                'bank_transactions.id',
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
                'orders.reference as order_reference',
                'orders.slug as order_slug',
                'shops.slug as shop_slug',
            ])
            ->selectRaw($this->orgAmountSql().' as org_amount')
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

    private function inSection(string $section, Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $this->section  = $section;
        $this->period   = in_array($request->query('period'), self::PERIODS, true) ? $request->query('period') : '90d';
        $this->currency = in_array($request->query('currency'), $this->currencies($organisation), true) ? $request->query('currency') : null;
        $this->from     = $this->validDate($request->query('from'));
        $this->to       = $this->from ? $this->validDate($request->query('to')) : null;
        $this->min      = $this->validAmount($request->query('min'));
        $this->max      = $this->validAmount($request->query('max'));
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
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|QueryBuilder
     * @param  TQuery  $query
     * @return TQuery
     */
    public function linesReceived($query, Organisation $organisation, string $period, ?string $currency, ?string $from, ?string $to, ?float $min = null, ?float $max = null)
    {
        $since = match (true) {
            $from !== null    => Carbon::parse($from)->startOfDay(),
            $period === 'all' => null,
            $period === '1y'  => now()->subYear(),
            default           => now()->subDays(90),
        };

        return $query
            ->where('bank_transactions.organisation_id', $organisation->id)
            ->where('bank_transactions.direction', BankTransactionDirectionEnum::IN)
            ->where('bank_transactions.status', BankTransactionStatusEnum::BOOKED)
            ->when($since, fn ($query) => $query->where('bank_transactions.booked_at', '>=', $since))
            ->when($to, fn ($query) => $query->where('bank_transactions.booked_at', '<=', Carbon::parse($to)->endOfDay()))
            ->join('currencies', 'currencies.id', 'bank_transactions.currency_id')
            ->join('organisations', 'organisations.id', 'bank_transactions.organisation_id')
            ->join('currencies as org_currencies', 'org_currencies.id', 'organisations.currency_id')
            ->when($currency, fn ($query) => $query->where('currencies.code', $currency))
            ->when($min !== null, fn ($query) => $query->whereRaw($this->orgAmountSql().' >= ?', [$min]))
            ->when($max !== null, fn ($query) => $query->whereRaw($this->orgAmountSql().' <= ?', [$max]));
    }

    /**
     * The line in the organisation's currency, at the last rate known on the day it was booked; null when no rate is known.
     */
    public function orgAmountSql(): string
    {
        $rate = fn (string $currencyId) => "case when $currencyId = (select id from currencies where code = '".addslashes(config('app.currency_exchange.pivot'))."') then 1 else (select ce.exchange from currency_exchanges ce where ce.currency_id = $currencyId and ce.date <= bank_transactions.booked_at::date order by ce.date desc limit 1) end";

        return "case when bank_transactions.currency_id = organisations.currency_id then bank_transactions.amount else round(bank_transactions.amount * ({$rate('organisations.currency_id')}) / nullif(({$rate('bank_transactions.currency_id')}), 0), 2) end";
    }

    /**
     * How many lines each section holds and what they add up to in the organisation's currency, under the filters chosen.
     *
     * @return array<string, array{count: int, total: float}>
     */
    public function sectionTotals(Organisation $organisation, string $period, ?string $currency, ?string $from, ?string $to, ?float $min = null, ?float $max = null): array
    {
        $bySection = $this->linesReceived(BankTransaction::query(), $organisation, $period, $currency, $from, $to, $min, $max)
            ->selectRaw(self::BUCKET_SQL.' as section, count(*) as lines, sum('.$this->orgAmountSql().') as total')
            ->groupByRaw('1')
            ->get()
            ->keyBy('section');

        return collect(self::SECTIONS)->mapWithKeys(fn (string $section) => [
            $section => ['count' => (int) ($bySection->get($section)->lines ?? 0), 'total' => round((float) ($bySection->get($section)->total ?? 0), 2)],
        ])->all();
    }

    /**
     * How many lines the section holds in each currency and what they add up to in the organisation's currency.
     *
     * @return array<string, array{count: int, total: float}>
     */
    public function currencyTotals(Organisation $organisation, string $section, string $period, ?string $from, ?string $to, ?float $min = null, ?float $max = null, ?string $provider = null): array
    {
        return $this->linesReceived(BankTransaction::query(), $organisation, $period, null, $from, $to, $min, $max)
            ->whereRaw('('.self::BUCKET_SQL.') = ?', [$section])
            ->when($provider, fn ($query) => $query->whereRaw('('.self::PROVIDER_SQL.') = ?', [$provider]))
            ->selectRaw('currencies.code, count(*) as lines, sum('.$this->orgAmountSql().') as total')
            ->groupBy('currencies.code')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->code => ['count' => (int) $row->lines, 'total' => round((float) $row->total, 2)]])
            ->all();
    }

    /**
     * The payment providers that paid out under the filters chosen, with how many lines each sent.
     *
     * @return array<int, array{value: string, label: string, count: int}>
     */
    public function providerCounts(Organisation $organisation, string $period, ?string $currency, ?string $from, ?string $to, ?float $min = null, ?float $max = null): array
    {
        $labels = self::providerLabels();

        return $this->linesReceived(BankTransaction::query(), $organisation, $period, $currency, $from, $to, $min, $max)
            ->whereRaw('('.self::BUCKET_SQL.") = 'payment_accounts'")
            ->selectRaw(self::PROVIDER_SQL.' as provider, count(*) as lines')
            ->groupByRaw('1')
            ->orderByDesc('lines')
            ->get()
            ->map(fn ($row) => ['value' => $row->provider, 'label' => $labels[$row->provider] ?? $row->provider, 'count' => (int) $row->lines])
            ->all();
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    public static function subNavigation(Organisation $organisation, array $counts, array $query = []): array
    {
        $entry = fn (string $label, string $route, array $icon, ?int $number = null) => array_filter([
            'label'    => $label,
            'root'     => "grp.org.accounting.bank_incoming.$route",
            'route'    => [
                'name'       => "grp.org.accounting.bank_incoming.$route",
                'parameters' => ['organisation' => $organisation->slug, ...($route === 'index' ? array_intersect_key($query, ['period' => true]) : $query)],
            ],
            'leftIcon' => ['icon' => $icon, 'tooltip' => $label],
            'number'   => $number,
        ], fn ($value) => $value !== null);

        return [
            $entry(__('Dashboard'), 'index', ['fal', 'fa-chart-line']),
            $entry(__('Customers paying orders'), 'customers', ['fal', 'fa-user-friends'], $counts['customers'] ?? 0),
            $entry(__('Marketplaces'), 'marketplaces', ['fal', 'fa-share-alt'], $counts['marketplaces'] ?? 0),
            $entry(__('Provider payouts'), 'payment_accounts', ['fal', 'fa-box-usd'], $counts['payment_accounts'] ?? 0),
            $entry(__('Other'), 'other', ['fal', 'fa-dot-circle'], $counts['other'] ?? 0),
            $entry(__('Needs a person'), 'needs_person', ['fal', 'fa-clipboard-list-check'], $counts['needs_person'] ?? 0),
        ];
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

    /**
     * @return array<int, string>
     */
    private function currencies(Organisation $organisation): array
    {
        return BankTransaction::query()
            ->join('currencies', 'currencies.id', 'bank_transactions.currency_id')
            ->where('bank_transactions.organisation_id', $organisation->id)
            ->where('bank_transactions.direction', BankTransactionDirectionEnum::IN)
            ->distinct()
            ->orderBy('currencies.code')
            ->pluck('currencies.code')
            ->all();
    }

    private function validAmount(mixed $amount): ?float
    {
        return is_numeric($amount) && $amount >= 0 ? (float) $amount : null;
    }

    private function validDate(mixed $date): ?string
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : null;
    }

    /**
     * Reads "57 277,82", "1.419,30" or "1,419.30" as an amount; whatever is not a number is not searched as one.
     */
    private function searchedAmount(string $value): ?string
    {
        $amount = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $value);
        if (str_contains($amount, ',') && str_contains($amount, '.')) {
            $amount = strrpos($amount, ',') > strrpos($amount, '.') ? str_replace('.', '', $amount) : str_replace(',', '', $amount);
        }
        $amount = str_replace(',', '.', $amount);

        return preg_match('/^\d{1,12}(\.\d{1,2})?$/', $amount) ? $amount : null;
    }

    public function htmlResponse(LengthAwarePaginator $bankTransactions, ActionRequest $request): Response
    {
        $filters       = [$this->organisation, $this->period, $this->currency, $this->from, $this->to, $this->min, $this->max];
        $sectionTotals = $this->sectionTotals(...$filters);
        $providers     = $this->section === 'payment_accounts' ? $this->providerCounts(...$filters) : [];
        $query         = array_filter([
            'period'   => $this->from || $this->period === '90d' ? null : $this->period,
            'currency' => $this->currency,
            'from'     => $this->from,
            'to'       => $this->to,
            'min'      => $this->min,
            'max'      => $this->max,
        ], fn ($value) => $value !== null);

        return Inertia::render(
            'Org/Accounting/BankIncoming',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $this->sectionTitle(),
                'pageHead'    => [
                    'model'         => __('Bank incoming'),
                    'title'         => $this->sectionTitle(),
                    'icon'          => ['fal', 'fa-university'],
                    'subNavigation' => self::subNavigation($this->organisation, array_map(fn (array $totals) => $totals['count'], $sectionTotals), $query),
                ],
                'canEdit'        => $request->user()->authTo("accounting.{$this->organisation->id}.edit"),
                'organisationId' => $this->organisation->id,
                'section'        => $this->section,
                'sectionTotals'  => $sectionTotals,
                'summary'        => $this->provider
                    ? ['count' => (int) (collect($providers)->firstWhere('value', $this->provider)['count'] ?? 0), 'total' => null]
                    : $sectionTotals[$this->section],
                'provider'       => $this->provider,
                'providers'      => $providers,
                'currencyTotals' => $this->currencyTotals($this->organisation, $this->section, $this->period, $this->from, $this->to, $this->min, $this->max, $this->provider),
                'period'         => $this->from ? null : $this->period,
                'currency'       => $this->currency,
                'currencies'     => $this->currencies($this->organisation),
                'from'           => $this->from,
                'to'             => $this->to,
                'min'            => $this->min,
                'max'            => $this->max,
                'orgCurrency'    => $this->organisation->currency->code,
                'data'           => BankIncomingResource::collection($bankTransactions),
            ]
        )->table($this->tableStructure());
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
            $table->column(key: 'bank', label: __('Bank'));
            $table->column(key: 'amount', label: __('Amount'), sortable: true, align: 'right');
            $table->column(key: 'counterparty_name', label: __('Paid by'));
            $table->column(key: 'reference', label: __('Bank reference'));
            $table->column(key: 'order_reference', label: __('Order'));
            $table->column(key: 'actions', label: '');
        };
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
}
