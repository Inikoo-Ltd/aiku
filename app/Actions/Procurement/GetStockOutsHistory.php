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
     * Daily rows summed across the parent's shop organisations, lost revenue converted to the parent's currency at today's rate.
     */
    private function dailyRows(Group|Organisation $parent, ?Carbon $from): Collection
    {
        $organisations = $this->organisations($parent);
        if ($organisations->isEmpty()) {
            return collect();
        }

        $rates = $organisations->mapWithKeys(fn (Organisation $organisation) => [
            $organisation->id => $organisation->currency_id === $parent->currency_id
                ? 1.0
                : (GetCurrencyExchange::run($organisation->currency, $parent->currency) ?? 1.0),
        ]);

        $lostExpression = $rates->map(fn (float $rate, int $organisationId) => "when $organisationId then estimated_lost_revenue_org_currency * $rate")->implode(' ');

        return DB::table('organisation_stock_histories')
            ->whereIn('organisation_id', $organisations->pluck('id'))
            ->when($from, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date, sum(number_out_of_stock_org_stocks) as out_of_stock, sum(number_org_stocks) as skos')
            ->selectRaw("sum(case organisation_id $lostExpression end) as lost_per_day")
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Group|Organisation $parent, string $period): array
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

        $rows   = $this->dailyRows($parent, $from);
        $latest = $rows->last() ?? $this->dailyRows($parent, null)->last();

        $from ??= $rows->isNotEmpty() ? Carbon::parse($rows->first()->date) : $today;
        $end  = $latest ? Carbon::parse($latest->date) : $today;
        $unit = match (true) {
            $from->diffInDays($end) <= 92  => 'day',
            $from->diffInDays($end) <= 731 => 'week',
            default                        => 'month',
        };

        $lostTotal = 0.0;
        $series    = $rows->groupBy(fn ($row) => Carbon::parse($row->date)->startOf($unit)->toDateString())
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

        return [
            'period'     => $period,
            'periods'    => $this->periodOptions(),
            'unit'       => $unit,
            'currency'   => $parent->currency->code,
            'lost_total' => round($lostTotal),
            'now'        => $latest ? [
                'date'         => $latest->date,
                'out_of_stock' => (int) $latest->out_of_stock,
                'percentage'   => $latest->skos ? round($latest->out_of_stock / $latest->skos * 100, 1) : 0,
                'lost_per_day' => $latest->lost_per_day === null ? null : (float) $latest->lost_per_day,
            ] : null,
            'series'     => $series,
        ];
    }
}
