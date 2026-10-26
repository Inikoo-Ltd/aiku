<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedSort;

/**
 * The sales lists of an agent in SQL: what each of its supplier products sold at cost in a chosen interval and in the same interval a
 * year earlier, the worth of the stock at cost and the stock cover verdict, one
 * sub-query per organisation because the verdict embeds that organisation's own lead time, then added up per product
 * and per supplier. Sales are at cost (ShowAgentSales::COST_OF_SALES) or at what customers paid, in the agent's
 * currency, GBP or EUR, as valuedBy() asks. Suppliers that log in themselves must only ever see the cost.
 * A supplier (independent or behind an agent) is read the same way, through its org suppliers, in its own currency.
 */
class GetAgentSalesProducts
{
    use AsObject;

    public const string DERIVED = 'case when reviews > 0 then rating_sum / reviews end as rating,
        case when invoices > 0 then 100.0 * refunds / invoices end as refund_percent,
        case when delivery_lines > 0 then 100.0 * short_lines / delivery_lines end as short_percent,
        case when last_year > 0 then (sold / last_year - 1) * 100 end as change_percent';

    public const array CURRENCIES = ['GBP', 'EUR'];

    public const string WHOLESALE = 'r.sales_grp_currency_external';

    private const array COLUMNS = ['supplier_product_id', 'code', 'name', 'supplier_id', 'supplier_code', 'supplier_name', 'supplier_slug', 'organisation_code', 'sold', 'last_year', 'stock_value', 'bucket', 'invoices', 'refunds', 'reviews', 'rating_sum'];

    private ?string $currencyCode = null;

    private bool $atWholesale = false;

    private ?Supplier $priceSupplier = null;

    /**
     * @var array<int, string>
     */
    private array $currencies = self::CURRENCIES;

    /**
     * @param  array<int, string>|null  $currencies  the currencies offered besides the seller's own, GBP and EUR unless told
     */
    public function valuedBy(?string $currencyCode, ?string $measure, ?array $currencies = null): static
    {
        $this->currencies   = $currencies ?? self::CURRENCIES;
        $this->currencyCode = in_array($currencyCode, $this->currencies, true) ? $currencyCode : null;
        $this->atWholesale  = $measure === 'sales';

        return $this;
    }

    /**
     * Values what sold at the supplier's own price for it, the same basis for a supplier behind an agent, whose price
     * leaves out the agent's fee and margin, and one we buy from directly.
     */
    public function atSupplierPrices(Supplier $supplier): static
    {
        $this->atWholesale   = false;
        $this->priceSupplier = $supplier;

        return $this;
    }

    /**
     * SKOs sold times the supplier's price for one SKO, in the group's currency. The SKOs are the cost of goods over
     * today's SKO value.
     * ponytail: today's sku_value drifts from the one each sale was costed at; read delivery_note_items if exact SKOs matter.
     */
    public static function supplierPriceSql(string $supplierId, string $orgStock, float $toGroup): string
    {
        return "(r.cogs_org_currency / nullif($orgStock.sku_value, 0)) * ".ShowAgentSales::EXTERNAL_SHARE." * coalesce((
            select min(price_sp.cost * coalesce(price_sp.units_per_pack, 1))
            from org_stock_has_org_supplier_products price_link
            join org_supplier_products price_osp on price_osp.id = price_link.org_supplier_product_id
            join supplier_products price_sp on price_sp.id = price_osp.supplier_product_id
            where price_link.org_stock_id = $orgStock.id and price_link.status and price_sp.supplier_id = $supplierId
        ), 0) / ".self::suppliersOfSql("$orgStock.id")." * ".sprintf('%.12F', $toGroup);
    }

    /**
     * How many suppliers an org stock is bought from. A SKO bought from two suppliers credits each with half of what
     * sold, so the same sale never counts twice in a supplier's figures or in the country ranking.
     * ponytail: an even split; split by the deliveries each supplier made if one supplier dominates a SKO.
     */
    public static function suppliersOfSql(string $orgStockId): string
    {
        return "greatest((
            select count(distinct sources_sp.supplier_id)
            from org_stock_has_org_supplier_products sources_link
            join org_supplier_products sources_osp on sources_osp.id = sources_link.org_supplier_product_id
            join supplier_products sources_sp on sources_sp.id = sources_osp.supplier_product_id
            where sources_link.org_stock_id = $orgStockId and sources_link.status
        ), 1)";
    }

    /**
     * The stock an organisation holds, in its own currency: at our cost, or the SKOs in stock at the supplier's price.
     */
    public function stockSql(OrgAgent|OrgSupplier $orgAgent): string
    {
        if (!$this->priceSupplier) {
            return 'coalesce(s.stock_value, 0)';
        }

        $toOrganisation = (float) GetCurrencyExchange::run($this->priceSupplier->currency, $orgAgent->organisation->currency) ?: 1.0;

        return 'coalesce(s.stock_value / nullif((select valued.sku_value from org_stocks valued where valued.id = os.id), 0) * sp.cost * coalesce(sp.units_per_pack, 1) / '.self::suppliersOfSql('os.id').', 0) * '.sprintf('%.12F', $toOrganisation);
    }

    public function amountSql(): string
    {
        if ($this->priceSupplier) {
            $toGroup = (float) GetCurrencyExchange::run($this->priceSupplier->currency, $this->priceSupplier->group->currency) ?: 1.0;

            return self::supplierPriceSql((string) $this->priceSupplier->id, 'sold_stock', $toGroup);
        }

        return $this->atWholesale ? self::WHOLESALE : ShowAgentSales::COST_OF_SALES;
    }

    public function soldLabel(): string
    {
        return $this->atWholesale ? __('Sold, wholesale') : __('Sold, at cost');
    }

    public function lastYearLabel(): string
    {
        return $this->atWholesale ? __('Same period last year, wholesale') : __('Same period last year, at cost');
    }

    /**
     * @return array{currency: string, measure: string, agent_currency: string, currencies: array<int, string>}
     */
    public function valuation(Agent|Supplier $seller): array
    {
        $agentCode = $this->sellerCurrency($seller)->code;
        $shownCode = $this->agentCurrency($seller)[0]->code;

        return [
            'currency'       => $shownCode === $agentCode ? 'agent' : $shownCode,
            'measure'        => $this->priceSupplier ? 'supplier' : ($this->atWholesale ? 'sales' : 'cost'),
            'agent_currency' => $agentCode,
            'currencies'     => $this->currencies,
        ];
    }

    /**
     * @return array{0: Currency, 1: float}
     */
    public function agentCurrency(Agent|Supplier $seller): array
    {
        $groupCurrency = $seller->group->currency;
        $requested     = $this->currencyCode ? Currency::where('code', $this->currencyCode)->first() : null;
        $toRequested   = $requested ? (float) GetCurrencyExchange::run($groupCurrency, $requested) : 0.0;
        if ($toRequested) {
            return [$requested, $toRequested];
        }

        $sellerCurrency = $this->sellerCurrency($seller);
        $toAgent        = (float) GetCurrencyExchange::run($groupCurrency, $sellerCurrency);

        return [$toAgent ? $sellerCurrency : $groupCurrency, $toAgent ?: 1.0];
    }

    public function sellerCurrency(Agent|Supplier $seller): Currency
    {
        return $seller instanceof Agent ? $seller->organisation->currency : $seller->currency;
    }

    private function problemSql(string $bucket = 'bucket'): string
    {
        $cases = collect(ShowAgentSales::PROBLEMS)->groupBy(fn (string $problem) => $problem)
            ->map(fn ($buckets, string $problem) => "when $bucket in ('".$buckets->keys()->implode("', '")."') then '$problem'")
            ->implode(' ');

        return "case $cases end";
    }

    private function bucketsOf(string $problem): string
    {
        return "'".collect(ShowAgentSales::PROBLEMS)->filter(fn (string $value) => $value === $problem)->keys()->implode("', '")."'";
    }

    /**
     * What customers publicly said about each org stock. Only product reviews that the website shows (published,
     * public and approved) count, and only for products made of a single org stock: a review of a kit says nothing
     * about one of its parts.
     */
    public function publicReviews(): Builder
    {
        return DB::table('reviews as review')
            ->join('product_has_org_stocks as phos', 'phos.product_id', 'review.product_id')
            ->where('review.scope', ReviewScopeEnum::PRODUCT->value)
            ->where('review.state', ReviewStateEnum::PUBLISHED->value)
            ->where('review.is_public', true)
            ->where('review.review_status', ReviewStatusEnum::APPROVED->value)
            ->whereNull('review.deleted_at')
            ->whereRaw('(select count(*) from product_has_org_stocks parts where parts.product_id = review.product_id) = 1');
    }

    private function reviewsByOrgStock(): Builder
    {
        return $this->publicReviews()
            ->groupBy('phos.org_stock_id')
            ->selectRaw('phos.org_stock_id, count(*) as reviews, sum(review.rating_main) as rating_sum');
    }

    /**
     * Delivery lines created in the interval that arrived short: never received, or checked in below what was ordered.
     * Lines still on their way (the quantity is only filled once checked) are left out of both counts.
     */
    private function shortLinesByProduct(Agent|Supplier $seller, DateIntervalEnum $interval): Builder
    {
        $settled = [StockDeliveryItemStateEnum::NOT_RECEIVED->value, StockDeliveryItemStateEnum::CHECKED->value, StockDeliveryItemStateEnum::PLACED->value];

        return DB::table('stock_delivery_items as line')
            ->join('supplier_products as line_sp', 'line_sp.id', 'line.supplier_product_id')
            ->join('suppliers as line_sup', fn ($join) => $join->on('line_sup.id', 'line_sp.supplier_id')->where($seller instanceof Agent ? 'line_sup.agent_id' : 'line_sup.id', $seller->id))
            ->whereNull('line.deleted_at')
            ->whereIn('line.state', $settled)
            ->when($this->range($interval), fn (Builder $query, array $range) => $query->whereBetween('line.created_at', $range))
            ->groupBy('line.supplier_product_id')
            ->selectRaw(
                'line.supplier_product_id as sp_id, count(*) as delivery_lines,
                count(*) filter (where line.state = ? or line.unit_quantity_checked < line.unit_quantity) as short_lines',
                [StockDeliveryItemStateEnum::NOT_RECEIVED->value]
            );
    }

    public function interval(?string $requested): DateIntervalEnum
    {
        $interval = DateIntervalEnum::tryFrom((string) $requested);

        return $interval && $interval !== DateIntervalEnum::CUSTOM ? $interval : DateIntervalEnum::ONE_YEAR;
    }

    /**
     * The dates an interval covers, as DateIntervalEnum::wherePeriod has them except that 1 year and 1 quarter are the 12
     * and 3 calendar months ending with the current one, the same 12 months the sales dashboard uses. Null for all time
     * when compared with a year earlier.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function range(DateIntervalEnum $interval, bool $lastYear = false): ?array
    {
        $now   = now();
        $range = match ($interval) {
            DateIntervalEnum::ONE_YEAR => [$now->copy()->subMonthsNoOverflow(11)->startOfMonth(), $now->copy()->endOfDay()],
            DateIntervalEnum::ONE_QUARTER => [$now->copy()->subMonthsNoOverflow(2)->startOfMonth(), $now->copy()->endOfDay()],
            DateIntervalEnum::ONE_MONTH => [$now->copy()->subMonth()->startOfDay(), $now->copy()->endOfDay()],
            DateIntervalEnum::ONE_WEEK => [$now->copy()->subWeek()->startOfDay(), $now->copy()->endOfDay()],
            DateIntervalEnum::THREE_DAYS => [$now->copy()->subDays(3)->startOfDay(), $now->copy()->endOfDay()],
            DateIntervalEnum::YEAR_TO_DAY => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            DateIntervalEnum::QUARTER_TO_DAY => [$now->copy()->startOfQuarter(), $now->copy()->endOfDay()],
            DateIntervalEnum::MONTH_TO_DAY => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            DateIntervalEnum::WEEK_TO_DAY => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            DateIntervalEnum::TODAY => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            DateIntervalEnum::YESTERDAY => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            DateIntervalEnum::LAST_MONTH => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            DateIntervalEnum::LAST_WEEK => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            default => null,
        };

        if ($range === null) {
            return $interval === DateIntervalEnum::ALL && !$lastYear ? [Carbon::create(2000), $now->copy()->endOfDay()] : null;
        }

        return $lastYear ? [$range[0]->copy()->subYearNoOverflow(), $range[1]->copy()->subYearNoOverflow()] : $range;
    }

    /**
     * The org stock time series records that add up to a date range: whole months from the monthly series, the days
     * at either end from the daily one, so a month still running or a range that starts mid-month is exact without
     * reading a year of daily records.
     *
     * @return array<int, array{0: string, 1: array<int, string>}>
     */
    private function recordsParts(?array $range): array
    {
        if ($range === null) {
            return [];
        }

        [$from, $to] = [$range[0]->copy()->startOfDay(), $range[1]->copy()->startOfDay()];
        $segment     = fn (TimeSeriesFrequencyEnum $frequency, string $format, Carbon $start, Carbon $end): array => ['(r.frequency = ? and r.period between ? and ?)', [$frequency->singleLetter(), $start->format($format), $end->format($format)]];
        $daily       = fn (Carbon $start, Carbon $end): array => $segment(TimeSeriesFrequencyEnum::DAILY, 'Y-m-d', $start, $end);
        $monthly     = fn (Carbon $start, Carbon $end): array => $segment(TimeSeriesFrequencyEnum::MONTHLY, 'Y-m', $start, $end);

        if ($from->diffInDays($to) <= 62) {
            $parts = [$daily($from, $to)];
        } else {
            $firstWholeMonth = $from->day === 1 ? $from->copy() : $from->copy()->addMonthNoOverflow()->startOfMonth();
            $lastWholeMonth  = $to->isSameDay($to->copy()->endOfMonth()) ? $to->copy() : $to->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay();
            $firstWholeYear  = $firstWholeMonth->month === 1 ? $firstWholeMonth->copy() : $firstWholeMonth->copy()->addYear()->startOfYear();
            $lastWholeYear   = $lastWholeMonth->month === 12 ? $lastWholeMonth->copy() : $lastWholeMonth->copy()->subYear()->endOfYear()->startOfDay();

            if ($firstWholeYear->lt($lastWholeYear)) {
                $parts = [$segment(TimeSeriesFrequencyEnum::YEARLY, 'Y', $firstWholeYear, $lastWholeYear)];
                if ($firstWholeMonth->lt($firstWholeYear)) {
                    $parts[] = $monthly($firstWholeMonth, $firstWholeYear->copy()->subDay());
                }
                if ($lastWholeYear->lt($lastWholeMonth)) {
                    $parts[] = $monthly($lastWholeYear->copy()->addDay(), $lastWholeMonth);
                }
            } else {
                $parts = [$monthly($firstWholeMonth, $lastWholeMonth)];
            }

            if ($from->lt($firstWholeMonth)) {
                $parts[] = $daily($from, $firstWholeMonth->copy()->subDay());
            }
            if ($lastWholeMonth->lt($to)) {
                $parts[] = $daily($lastWholeMonth->copy()->addDay(), $to);
            }
        }

        return $parts;
    }

    /**
     * @param  array<int, array{0: string, 1: array<int, string>}>  $parts
     * @return array{0: string, 1: array<int, string>}
     */
    private function joinParts(array $parts): array
    {
        return $parts === [] ? ['false', []] : ['('.collect($parts)->pluck(0)->implode(' or ').')', collect($parts)->pluck(1)->flatten()->all()];
    }

    /**
     * @return array<int, AllowedSort>
     */
    public function derivedSorts(): array
    {
        return collect(['rating' => 'rating', 'refund_percent' => 'refund_percent', 'short_percent' => 'short_percent', 'change_percent' => 'change_percent'])
            ->map(fn (string $column, string $name) => AllowedSort::callback(
                $name,
                fn ($query, bool $descending) => $query->orderByRaw($column.($descending ? ' desc' : ' asc').' nulls last')
            ))
            ->values()
            ->all();
    }

    private function perOrganisation(Agent|Supplier $seller, OrgAgent|OrgSupplier $orgAgent, Currency $currency, float $toAgent, ?int $supplierId, DateIntervalEnum $interval): Builder
    {
        $buckets      = GetAgentStockCoverBuckets::make();
        $stockToAgent = (float) (GetCurrencyExchange::run($orgAgent->organisation->currency, $currency) ?: 1);
        $cost         = $this->amountSql();

        $currentParts  = $this->recordsParts($this->range($interval));
        $previousParts = $this->recordsParts($this->range($interval, true));

        [$current, $currentBindings]   = $this->joinParts($currentParts);
        [$previous, $previousBindings] = $this->joinParts($previousParts);

        $parts  = [...$currentParts, ...$previousParts];
        $series = collect($parts)->map(fn (array $part) => $part[1][0])->unique()->map(function (string $letter) use ($seller, $supplierId, $orgAgent, $parts, $cost, $current, $previous, $currentBindings, $previousBindings) {
            $own = collect($parts)->filter(fn (array $part) => $part[1][0] === $letter);

            $records = DB::table('org_stock_time_series_records as r')
                ->whereColumn('r.org_stock_time_series_id', 'ts.id')
                ->where('r.frequency', $letter)
                ->whereRaw('('.$own->pluck(0)->implode(' or ').')', $own->pluck(1)->flatten()->all())
                ->selectRaw("r.period, r.frequency, $cost as cost, r.invoices, r.refunds");

            return DB::table('org_stock_time_series as ts')
                ->join('org_stocks as sold_stock', 'sold_stock.id', 'ts.org_stock_id')
                ->where('ts.frequency', collect(TimeSeriesFrequencyEnum::cases())->first(fn (TimeSeriesFrequencyEnum $frequency) => $frequency->singleLetter() === $letter)->value)
                ->where('sold_stock.organisation_id', $orgAgent->organisation_id)
                ->whereIn('ts.org_stock_id', ShowAgentSales::make()->linkedOrgStocks($seller, $supplierId))
                ->joinLateral($records, 'r')
                ->selectRaw(
                    "ts.org_stock_id,
                    case when $current then r.cost end as sold,
                    case when $previous then r.cost end as last_year,
                    case when $current then r.invoices end as invoices,
                    case when $current then r.refunds end as refunds",
                    [...$currentBindings, ...$previousBindings, ...$currentBindings, ...$currentBindings]
                );
        });

        $sold = DB::query()->fromSub($series->reduce(fn (?Builder $union, Builder $query) => $union ? $union->unionAll($query) : $query) ?? DB::query()->selectRaw('null::bigint as org_stock_id, null::numeric as sold, null::numeric as last_year, null::bigint as invoices, null::bigint as refunds')->whereRaw('false'), 'records')
            ->groupBy('org_stock_id')
            ->selectRaw('org_stock_id, sum(sold) as sold, sum(last_year) as last_year, sum(invoices) as invoices, sum(refunds) as refunds');

        return $buckets->scope(DB::table('org_supplier_products as osp'), $orgAgent, false)
            ->join('suppliers as sup', 'sup.id', 'sp.supplier_id')
            ->leftJoinSub($sold, 'sold', 'sold.org_stock_id', '=', 'os.id')
            ->leftJoinSub($this->reviewsByOrgStock(), 'reviewed', 'reviewed.org_stock_id', '=', 'os.id')
            ->whereNotNull('os.id')
            ->when($supplierId, fn ($query) => $query->where('sp.supplier_id', $supplierId))
            ->whereRaw('((osp.state = ? and osp.is_available) or sold.sold > 0)', [OrgSupplierProductStateEnum::ACTIVE->value])
            ->orderBy('os.id')
            ->orderBy('osp.id')
            ->selectRaw(
                'distinct on (os.id) sp.id as supplier_product_id, sp.code, sp.name, sp.supplier_id, sup.code as supplier_code, sup.name as supplier_name, sup.slug as supplier_slug,
                ?::text as organisation_code,
                coalesce(sold.sold, 0) * ? as sold, coalesce(sold.last_year, 0) * ? as last_year,
                coalesce(s.stock_value, 0) * ? as stock_value, '.$buckets->bucketSql($orgAgent).' as bucket,
                coalesce(sold.invoices, 0) as invoices, coalesce(sold.refunds, 0) as refunds,
                coalesce(reviewed.reviews, 0) as reviews, coalesce(reviewed.rating_sum, 0) as rating_sum',
                [$orgAgent->organisation->code, $toAgent, $toAgent, $stockToAgent]
            );
    }

    /**
     * One row per organisation and org stock the agent's products sit in.
     */
    public function rows(Agent|Supplier $seller, ?int $supplierId = null, DateIntervalEnum $interval = DateIntervalEnum::ONE_YEAR): Builder
    {
        [$currency, $toAgent] = $this->agentCurrency($seller);

        $union = null;
        foreach (ShowAgentSales::make()->relationships($seller) as $orgAgent) {
            $rows  = $this->perOrganisation($seller, $orgAgent, $currency, $toAgent, $supplierId, $interval);
            $union = $union ? $union->unionAll($rows) : $rows;
        }

        return $union ?? DB::query()->selectRaw(collect(self::COLUMNS)->map(fn (string $column) => "null as $column")->implode(', '))->whereRaw('false');
    }

    /**
     * One row per supplier product, added up over the organisations. The organisations column carries each one's
     * sales, stock and problem for the chips.
     */
    public function products(Agent|Supplier $seller, ?int $supplierId = null, bool $withOrganisations = true, DateIntervalEnum $interval = DateIntervalEnum::ONE_YEAR): Builder
    {
        $query = DB::query()->fromSub($this->rows($seller, $supplierId, $interval), 'rows')
            ->leftJoinSub($this->shortLinesByProduct($seller, $interval), 'shortfalls', 'shortfalls.sp_id', '=', 'rows.supplier_product_id')
            ->groupBy('rows.supplier_product_id', 'code', 'name', 'supplier_id', 'supplier_code', 'supplier_name', 'supplier_slug')
            ->selectRaw(
                "rows.supplier_product_id as id, code, name, supplier_id, supplier_code, supplier_name, supplier_slug,
                sum(sold) as sold, sum(last_year) as last_year, sum(stock_value) as stock_value,
                sum(invoices) as invoices, sum(refunds) as refunds, sum(reviews) as reviews, sum(rating_sum) as rating_sum,
                coalesce(max(shortfalls.short_lines), 0) as short_lines, coalesce(max(shortfalls.delivery_lines), 0) as delivery_lines,
                bool_or(bucket <> 'gone') as live,
                bool_or(bucket = 'out') as has_out,
                bool_or(bucket in (".$this->bucketsOf('low').")) as has_low,
                bool_or(bucket in (".$this->bucketsOf('dead').')) as has_dead'
            );

        if ($withOrganisations) {
            $query->selectRaw("json_agg(json_build_object('code', organisation_code, 'sold', sold, 'stock', stock_value, 'problem', ".$this->problemSql().') order by organisation_code) as organisations');
        }

        return $query;
    }

    /**
     * One row per supplier of the agent, with how many of its products are in trouble in at least one organisation.
     */
    public function suppliers(Agent $agent, DateIntervalEnum $interval = DateIntervalEnum::ONE_YEAR): Builder
    {
        return DB::query()->fromSub($this->products($agent, null, false, $interval), 'products')
            ->groupBy('supplier_id', 'supplier_code', 'supplier_name', 'supplier_slug')
            ->selectRaw(
                'supplier_id as id, supplier_code as code, supplier_name as name, supplier_slug as slug,
                count(*) filter (where live) as products, count(*) filter (where sold > 0) as selling,
                sum(sold) as sold, sum(last_year) as last_year,
                sum(invoices) as invoices, sum(refunds) as refunds, sum(reviews) as reviews, sum(rating_sum) as rating_sum,
                sum(short_lines) as short_lines, sum(delivery_lines) as delivery_lines,
                count(*) filter (where has_out) as out_of_stock, count(*) filter (where has_low) as low, count(*) filter (where has_dead) as dead'
            );
    }
}
