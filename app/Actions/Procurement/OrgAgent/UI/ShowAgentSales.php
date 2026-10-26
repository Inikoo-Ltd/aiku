<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * How an agent's products sell in the organisations that buy them, valued at what the goods cost, or, on the
 * toggle every user of the agent views has, at what the organisations' customers paid (suppliers logging in
 * themselves must never get that toggle). Month by month, plus the products in trouble (out of stock,
 * about to run out, not selling). Goods one organisation passes to another are not a sale, the organisation that
 * receives them sells them, so a month's cost is kept in the proportion its sales went to its own customers.
 * Amounts are in the agent's currency, GBP or EUR; agent views may switch to what customers paid instead of the cost.
 */
class ShowAgentSales extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private const int MONTHS = 12;

    private const int RANKING = 10;

    public const array PROBLEMS = [
        'out'  => 'out',
        'w1'   => 'low',
        'w2'   => 'low',
        'dead' => 'dead',
    ];

    public const string EXTERNAL_SHARE = 'case
        when r.sales_grp_currency_external + r.sales_grp_currency_internal > 0
            then r.sales_grp_currency_external / (r.sales_grp_currency_external + r.sales_grp_currency_internal)
        else 1 end';

    public const string COST_OF_SALES = 'r.cogs_grp_currency * '.self::EXTERNAL_SHARE;

    private ?Supplier $supplier = null;

    private ?GetAgentSalesProducts $valuation = null;

    /**
     * The cost-of-sales records of every org stock the agent's products are linked to, narrowed to one supplier's
     * products when asked, in any frequency. A supplier on its own is read whether it sells through an agent or not.
     */
    public function linkedOrgStocks(Agent|Supplier $seller, ?int $supplierId = null): Builder
    {
        if ($seller instanceof Supplier) {
            $supplierId = $seller->id;
        }

        return DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_supplier_products as linked', 'linked.id', 'link.org_supplier_product_id')
            ->when($seller instanceof Agent, fn ($query) => $query
                ->join('org_agents', 'org_agents.id', 'linked.org_agent_id')
                ->where('org_agents.agent_id', $seller->id))
            ->where('link.status', true)
            ->when($supplierId, fn ($query) => $query
                ->join('supplier_products as linked_sp', 'linked_sp.id', 'linked.supplier_product_id')
                ->where('linked_sp.supplier_id', $supplierId))
            ->select('link.org_stock_id');
    }

    /**
     * What ties each organisation to the seller: its org agents, or its org suppliers.
     *
     * @return Collection<int, OrgAgent|OrgSupplier>
     */
    public function relationships(Agent|Supplier $seller): Collection
    {
        return ($seller instanceof Agent ? OrgAgent::where('agent_id', $seller->id) : OrgSupplier::where('supplier_id', $seller->id))
            ->with('organisation.currency')
            ->get();
    }

    public function records(Agent|Supplier $agent, ?int $supplierId = null): Builder
    {
        return DB::table('org_stock_time_series as ts')
            ->join('org_stock_time_series_records as r', 'r.org_stock_time_series_id', 'ts.id')
            ->join('org_stocks as sold_stock', 'sold_stock.id', 'ts.org_stock_id')
            ->whereIn('ts.org_stock_id', $this->linkedOrgStocks($agent, $supplierId));
    }

    public function monthlySales(Agent|Supplier $agent, CarbonInterface $from, ?int $supplierId = null): Builder
    {
        return $this->records($agent, $supplierId)
            ->where('ts.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->where('r.period', '>=', $from->format('Y-m'));
    }

    /**
     * Day by day this month so far and the same month a year earlier, per organisation, in the group's currency.
     *
     * @return Collection<int, Collection<string, float>>
     */
    private function dailySales(Agent|Supplier $seller, ?int $supplierId, string $amount): Collection
    {
        $thisMonth = now()->startOfMonth();
        $lastYear  = now()->subYearNoOverflow()->startOfMonth();

        return $this->records($seller, $supplierId)
            ->where('ts.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where(fn (Builder $query) => $query
                ->whereBetween('r.period', [$thisMonth->toDateString(), now()->toDateString()])
                ->orWhereBetween('r.period', [$lastYear->toDateString(), $lastYear->copy()->endOfMonth()->toDateString()]))
            ->selectRaw("sold_stock.organisation_id, r.period as day, sum($amount) as sales")
            ->groupBy('sold_stock.organisation_id', 'r.period')
            ->get()
            ->groupBy('organisation_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn ($row) => [substr((string) $row->day, 0, 10) => (float) $row->sales]));
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Agent|Supplier $agent, ?Supplier $supplier = null, ?GetAgentSalesProducts $valuation = null): array
    {
        $valuation              = $valuation ?? GetAgentSalesProducts::make();
        $amount                 = $valuation->amountSql();
        [$currency, $toAgent]   = $valuation->agentCurrency($agent);
        $from         = now()->startOfMonth()->subMonths(self::MONTHS - 1);
        $orgAgents    = $this->relationships($agent);

        $year = now()->year;

        $byMonth = $this->monthlySales($agent, now()->subYear()->startOfYear(), $supplier?->id)
            ->selectRaw("sold_stock.organisation_id, r.period as month, sum($amount) as sales")
            ->groupBy('sold_stock.organisation_id', 'r.period')
            ->get()
            ->groupBy('organisation_id')
            ->map(fn (Collection $rows) => $rows->pluck('sales', 'month'));

        $byDay = $this->dailySales($agent, $supplier?->id, $amount);
        $onDay = fn (OrgAgent|OrgSupplier $orgAgent, CarbonInterface $month, int $days): array => collect(range(1, $days))
            ->map(fn (int $day) => round($toAgent * (float) ($byDay->get($orgAgent->organisation_id)[$month->copy()->setDay($day)->toDateString()] ?? 0), 2))
            ->all();
        $lastYearMonth = now()->subYearNoOverflow()->startOfMonth();

        $salesIn = fn (OrgAgent|OrgSupplier $orgAgent, string $period): float => round($toAgent * (float) ($byMonth->get($orgAgent->organisation_id)[$period] ?? 0), 2);
        $inYear  = fn (OrgAgent|OrgSupplier $orgAgent, int $ofYear, int $untilMonth): array => collect(range(1, $untilMonth))
            ->map(fn (int $month) => $salesIn($orgAgent, sprintf('%d-%02d', $ofYear, $month)))
            ->all();

        $products      = [];
        $organisations = [];
        foreach ($orgAgents as $orgAgent) {
            $code        = $orgAgent->organisation->code;
            $stockToAgent = (float) (GetCurrencyExchange::run($orgAgent->organisation->currency, $currency) ?: 1);
            $rows        = $this->productRows($agent, $orgAgent, $from, $supplier, $amount, $valuation->stockSql($orgAgent));

            $organisations[] = [
                'code'     => $code,
                'name'     => $orgAgent->organisation->name,
                'products' => $rows->where('bucket', '!=', 'gone')->count(),
                'selling'  => $rows->where('sold', '>', 0)->count(),
                'sold'      => round(collect(range(0, self::MONTHS - 1))->sum(fn (int $offset) => $salesIn($orgAgent, $from->copy()->addMonths($offset)->format('Y-m'))), 2),
                'this_year' => $inYear($orgAgent, $year, now()->month),
                'last_year' => $inYear($orgAgent, $year - 1, 12),
                'month_this_year' => $onDay($orgAgent, now()->startOfMonth(), now()->day),
                'month_last_year' => $onDay($orgAgent, $lastYearMonth, $lastYearMonth->daysInMonth),
                'problems' => collect(['out', 'low', 'dead'])->mapWithKeys(fn (string $problem) => [
                    $problem => $rows->filter(fn ($row) => (self::PROBLEMS[$row->bucket] ?? null) === $problem)->count(),
                ])->all(),
            ];

            foreach ($rows as $row) {
                $products[$row->supplier_product_id] ??= ['code' => $row->code, 'name' => $row->name, 'sold' => 0.0, 'organisations' => []];
                $products[$row->supplier_product_id]['sold'] += $toAgent * (float) $row->sold;
                $products[$row->supplier_product_id]['organisations'][] = [
                    'code'    => $code,
                    'sold'    => round($toAgent * (float) $row->sold, 2),
                    'stock'   => round($stockToAgent * (float) $row->stock_value, 2),
                    'problem' => self::PROBLEMS[$row->bucket] ?? null,
                ];
            }
        }

        $products = collect($products)->map(fn (array $product) => [...$product, 'sold' => round($product['sold'], 2)])->values();

        return [
            'currency'      => $currency->code,
            'valuation'     => $valuation->valuation($agent),
            'from'          => $from->toDateString(),
            'year'          => $year,
            'month'         => now()->month,
            'days_in_month' => now()->daysInMonth,
            'products'      => $products->count(),
            'selling'       => $products->where('sold', '>', 0)->count(),
            'organisations' => $organisations,
            'top_sellers'   => $products->where('sold', '>', 0)->sortByDesc('sold')->take(self::RANKING)->values()->all(),
            'out'           => $this->ranking($products, 'out', 'sold'),
            'low'           => $this->ranking($products, 'low', 'sold'),
            'dead'          => $this->ranking($products, 'dead', 'stock'),
        ];
    }

    /**
     * One row per org stock the organisation holds of this agent's products, with what it sold in the period, in the
     * group's currency, the value of what it holds, in its own, and the stock cover verdict its own buyers see.
     */
    private function productRows(Agent|Supplier $agent, OrgAgent|OrgSupplier $orgAgent, CarbonInterface $from, ?Supplier $supplier, string $amount, string $stock): Collection
    {
        $buckets = GetAgentStockCoverBuckets::make();

        return $buckets->scope(DB::table('org_supplier_products as osp'), $orgAgent, false)
            ->leftJoinSub(
                $this->monthlySales($agent, $from, $supplier?->id)
                    ->where('sold_stock.organisation_id', $orgAgent->organisation_id)
                    ->groupBy('ts.org_stock_id')
                    ->selectRaw("ts.org_stock_id, sum($amount) as sold"),
                'sold',
                'sold.org_stock_id',
                '=',
                'os.id'
            )
            ->whereNotNull('os.id')
            ->when($supplier, fn ($query) => $query->where('sp.supplier_id', $supplier->id))
            ->whereRaw('((osp.state = ? and osp.is_available) or sold.sold > 0)', [OrgSupplierProductStateEnum::ACTIVE->value])
            ->selectRaw('sp.id as supplier_product_id, sp.code, sp.name, os.id as org_stock_id, '.$stock.' as stock_value, coalesce(sold.sold, 0) as sold, '.$buckets->bucketSql($orgAgent).' as bucket')
            ->get()
            ->unique('org_stock_id')
            ->values();
    }

    /**
     * The products with a given problem in at least one organisation, the ones that matter most first: by what it sold
     * where the problem is, or by the value of the stock sitting there for the ones that do not sell.
     *
     * @return array{count: int, rows: array<int, array<string, mixed>>}
     */
    private function ranking(Collection $products, string $problem, string $weight): array
    {
        $affected = $products
            ->map(function (array $product) use ($problem, $weight) {
                $where = collect($product['organisations'])->where('problem', $problem)->values();

                return [...$product, 'organisations' => $where->all(), 'weight' => $where->sum($weight)];
            })
            ->filter(fn (array $product) => $product['organisations'] !== []);

        return [
            'count' => $affected->count(),
            'rows'  => $affected->sortByDesc('weight')->take(self::RANKING)->values()->all(),
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): Agent
    {
        $this->initialisation($organisation, $request);
        $this->valuation = GetAgentSalesProducts::make()->valuedBy($request->input('currency'), $request->input('measure'));
        $agent           = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $agent;
    }

    public function inSupplier(Organisation $organisation, Supplier $supplier, ActionRequest $request): Agent
    {
        $agent = $this->asController($organisation, $request);
        abort_unless($supplier->agent_id === $agent->id, 404);
        $this->supplier = $supplier;

        return $agent;
    }

    /**
     * The links of the "See all" actions: the products list, narrowed to the supplier on a supplier dashboard and to
     * one problem for each ranking.
     *
     * @return array<string, string>
     */
    private function productLinks(): array
    {
        $parameters = array_filter(['organisation' => $this->organisation->slug, 'supplier' => $this->supplier?->id]);

        return collect(['all' => null, 'out' => 'out', 'low' => 'low', 'dead' => 'dead'])
            ->map(fn (?string $problem) => route('grp.org.agent.sales.products.index', $problem ? [...$parameters, 'elements' => ['problem' => $problem]] : $parameters))
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $trail
     * @return array<int, array<string, mixed>>
     */
    public function getBreadcrumbs(array $routeParameters, array $trail = []): array
    {
        $parameters = Arr::only($routeParameters, 'organisation');

        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($parameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Sales'),
                        'route' => [
                            'name'       => 'grp.org.agent.sales.dashboard',
                            'parameters' => $parameters,
                        ],
                    ],
                ],
            ],
            $trail
        );
    }

    public function htmlResponse(Agent $agent, ActionRequest $request): Response
    {
        $parameters = $request->route()->originalParameters();
        $trail      = $this->supplier ? [
            [
                'type'   => 'simple',
                'simple' => [
                    'label' => __('Suppliers'),
                    'route' => [
                        'name'       => 'grp.org.agent.sales.suppliers.index',
                        'parameters' => Arr::only($parameters, 'organisation'),
                    ],
                ],
            ],
            [
                'type'   => 'simple',
                'simple' => [
                    'label' => $this->supplier->code,
                    'route' => [
                        'name'       => 'grp.org.agent.sales.suppliers.show',
                        'parameters' => $parameters,
                    ],
                ],
            ],
        ] : [];

        return Inertia::render(
            'Org/Procurement/AgentSales',
            [
                'title'       => $this->supplier?->name ?? __('Sales'),
                'breadcrumbs' => $this->getBreadcrumbs($parameters, $trail),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Sales'),
                        'icon'  => $this->supplier ? 'fal fa-person-dolly' : 'fal fa-chart-line',
                    ],
                    'title' => $this->supplier?->name ?? __('Sales'),
                    'model' => $this->supplier ? __('Sales') : null,
                ],
                'supplier'    => $this->supplier ? ['code' => $this->supplier->code, 'name' => $this->supplier->name] : null,
                'links'       => $this->productLinks(),
                'valuation'   => $this->valuation->valuation($agent),
                'sales'       => Inertia::defer(fn () => $this->handle($agent, $this->supplier, $this->valuation)),
                'feedback'    => Inertia::defer(fn () => GetAgentSalesFeedback::run($agent, $this->supplier), 'feedback'),
            ]
        );
    }
}
