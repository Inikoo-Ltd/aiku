<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockOutsHistory
{
    use AsObject;

    public const DEFAULT_PERIOD = '1y';

    /**
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return [
            '1m'  => __('1 Month'),
            '1q'  => __('1 Quarter'),
            '6m'  => __('6 Months'),
            '1y'  => __('1 Year'),
            '3y'  => __('3 Years'),
            'all' => __('All'),
        ];
    }

    public function period(?string $period): string
    {
        return array_key_exists((string) $period, $this->periodOptions()) ? $period : self::DEFAULT_PERIOD;
    }

    /**
     * @return Collection<int, Organisation>
     */
    public function organisations(Group|Organisation $parent): Collection
    {
        if ($parent instanceof Organisation) {
            return collect([$parent]);
        }

        return $parent->organisations()->where('type', OrganisationTypeEnum::SHOP->value)->with('currency')->get();
    }

    /**
     * Daily rows per shop organisation, lost revenue converted to the parent's currency at today's rate.
     */
    private function dailyRows(Group|Organisation $parent, Collection $organisations, ?Carbon $from, ?string $source): Collection
    {
        if ($organisations->isEmpty()) {
            return collect();
        }

        $lostExpression = $organisations->map(function (Organisation $organisation) use ($parent) {
            $rate = $organisation->currency_id === $parent->currency_id
                ? 1.0
                : (GetCurrencyExchange::run($organisation->currency, $parent->currency) ?? 1.0);

            return "when $organisation->id then estimated_lost_revenue_org_currency * $rate";
        })->implode(' ');

        return DB::table($source ? 'organisation_stock_history_sources' : 'organisation_stock_histories')
            ->whereIn('organisation_id', $organisations->pluck('id'))
            ->when($source, fn ($query) => $query->where('source', $source))
            ->where('number_org_stocks', '>', 0)
            ->when($from, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->orderBy('date')
            ->selectRaw('date, organisation_id, number_out_of_stock_org_stocks as out_of_stock, number_org_stocks as skos')
            ->selectRaw("case organisation_id $lostExpression end as lost_per_day")
            ->get();
    }

    private function sumByDate(Collection $rows): Collection
    {
        $rowsByDate = $rows->groupBy('date');
        while ($rowsByDate->count() > 1 && $rowsByDate->last()->count() < $rowsByDate->slice(-2, 1)->first()->count()) {
            $rowsByDate->pop();
        }

        return $rowsByDate->map(function (Collection $dateRows, string $date) {
            $lostRows = $dateRows->whereNotNull('lost_per_day');

            return (object) [
                'date'         => $date,
                'out_of_stock' => $dateRows->sum('out_of_stock'),
                'skos'         => $dateRows->sum('skos'),
                'lost_per_day' => $lostRows->isEmpty() ? null : $lostRows->sum('lost_per_day'),
            ];
        })->values();
    }

    /**
     * @return array{series: array<int, array<string, mixed>>, lost_total: float}
     */
    private function series(Collection $dailyRows, string $unit, Carbon $from, Carbon $end): array
    {
        $lostTotal = 0.0;
        $series    = $dailyRows->groupBy(fn ($row) => Carbon::parse($row->date)->startOf($unit)->toDateString())
            ->map(function ($bucketRows, string $bucketStart) use ($unit, $from, $end, &$lostTotal) {
                $skos        = $bucketRows->sum('skos');
                $lostRows    = $bucketRows->whereNotNull('lost_per_day');
                $lostPerDay  = $lostRows->isEmpty() ? null : round($lostRows->avg('lost_per_day'), 2);
                $bucketStart = Carbon::parse($bucketStart);
                $daysCovered = (int) $bucketStart->copy()->max($from)->diffInDays($bucketStart->copy()->endOf($unit)->min($end)) + 1;
                $lostTotal   += ($lostPerDay ?? 0) * $daysCovered;

                return [
                    'date'         => $bucketStart->toDateString(),
                    'out_of_stock' => (int) round($bucketRows->avg('out_of_stock')),
                    'percentage'   => $skos ? round($bucketRows->sum('out_of_stock') / $skos * 100, 1) : 0,
                    'lost_per_day' => $lostPerDay,
                ];
            })->values()->all();

        return ['series' => $series, 'lost_total' => round($lostTotal)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function now(?object $latest): ?array
    {
        return $latest ? [
            'date'         => $latest->date,
            'out_of_stock' => (int) $latest->out_of_stock,
            'skos'         => (int) $latest->skos,
            'percentage'   => $latest->skos ? round($latest->out_of_stock / $latest->skos * 100, 1) : 0,
            'lost_per_day' => $latest->lost_per_day === null ? null : round((float) $latest->lost_per_day, 2),
        ] : null;
    }

    /**
     * Each source with its SKOs out of stock on the latest day of every organisation.
     *
     * @return array<string, array{label: string, out_of_stock: int}>
     */
    private function sources(Group|Organisation $parent, Collection $organisations): array
    {
        $outOfStock = DB::table('organisation_stock_history_sources as sources')
            ->whereIn('sources.organisation_id', $organisations->pluck('id'))
            ->whereRaw('sources.date = (select max(latest.date) from organisation_stock_history_sources as latest where latest.organisation_id = sources.organisation_id)')
            ->groupBy('sources.source')
            ->selectRaw('sources.source, sum(sources.number_out_of_stock_org_stocks) as out_of_stock')
            ->pluck('out_of_stock', 'source');

        return collect(GetOrganisationStockCoverBuckets::make()->sourceOptions($parent instanceof Group ? $parent->id : $parent->group_id))
            ->map(fn (string $label, string $source) => ['label' => $label, 'out_of_stock' => (int) ($outOfStock[$source] ?? 0)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Group|Organisation $parent, string $period, ?string $source = null): array
    {
        $today = today();
        $from  = match ($period) {
            '1m'    => $today->copy()->subMonth(),
            '1q'    => $today->copy()->subMonths(3),
            '6m'    => $today->copy()->subMonths(6),
            '1y'    => $today->copy()->subYear(),
            '3y'    => $today->copy()->subYears(3),
            default => null,
        };

        $organisations = $this->organisations($parent);
        $orgRows       = $this->dailyRows($parent, $organisations, $from, $source);
        if ($orgRows->isEmpty()) {
            $orgRows = $this->dailyRows($parent, $organisations, null, $source)->groupBy('date')->last() ?? collect();
        }
        $rows   = $this->sumByDate($orgRows);
        $latest = $rows->last();

        $from ??= $rows->isNotEmpty() ? Carbon::parse($rows->first()->date) : $today;
        $end  = $latest ? Carbon::parse($latest->date) : $today;
        $unit = match (true) {
            $from->diffInDays($end) <= 92  => 'day',
            $from->diffInDays($end) <= 731 => 'week',
            default                        => 'month',
        };

        $total = $this->series($rows, $unit, $from, $end);

        $result = [
            'period'     => $period,
            'periods'    => $this->periodOptions(),
            'source'     => $source,
            'sources'    => $this->sources($parent, $organisations),
            'unit'       => $unit,
            'currency'   => $parent->currency->code,
            'lost_total' => $total['lost_total'],
            'now'        => $this->now($latest),
            'series'     => $total['series'],
        ];

        if ($parent instanceof Group) {
            $rowsByOrganisation = $orgRows->groupBy('organisation_id');

            $result['organisations'] = $organisations->map(function (Organisation $organisation) use ($rowsByOrganisation, $unit, $from, $end) {
                $organisationRows = $rowsByOrganisation->get($organisation->id, collect());
                $organisationSeries = $this->series($organisationRows, $unit, $from, $end);

                return [
                    'slug'       => $organisation->slug,
                    'code'       => $organisation->code,
                    'name'       => $organisation->name,
                    'lost_total' => $organisationSeries['lost_total'],
                    'now'        => $this->now($organisationRows->last()),
                    'series'     => $organisationSeries['series'],
                ];
            })->values()->all();
        }

        return $result;
    }
}
