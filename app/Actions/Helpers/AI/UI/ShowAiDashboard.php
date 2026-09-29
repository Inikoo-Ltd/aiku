<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI\UI;

use App\Actions\Helpers\AI\GetOpenRouterBalance;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAiDashboard extends OrgAction
{
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title = __('AI Dashboard');

        return Inertia::render(
            'Ai/Dashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-robot'],
                        'title' => $title,
                    ],
                ],
                'balance'     => GetOpenRouterBalance::run(),
                'features'    => $this->getFeatures(),
                'daily'       => $this->getDaily(),
            ]
        );
    }

    /**
     * @return array<int, array{feature: string, label: string, today: float, week: float, month: float, year: float, calls_month: int, tokens_month: int}>
     */
    public function getFeatures(): array
    {
        $columns = [
            'today' => TimeSeriesFrequencyEnum::DAILY,
            'week'  => TimeSeriesFrequencyEnum::WEEKLY,
            'month' => TimeSeriesFrequencyEnum::MONTHLY,
            'year'  => TimeSeriesFrequencyEnum::YEARLY,
        ];

        $features = [];

        foreach ($columns as $column => $frequency) {
            $records = DB::table('ai_time_series_records')
                ->join('ai_time_series', 'ai_time_series.id', 'ai_time_series_records.ai_time_series_id')
                ->where('ai_time_series_records.frequency', $frequency->singleLetter())
                ->where('ai_time_series_records.period', TimeSeriesPeriodCalculator::resolvePeriodFromDate(now(), $frequency)['period'])
                ->select('ai_time_series.feature', 'ai_time_series_records.cost', 'ai_time_series_records.number_calls', DB::raw('ai_time_series_records.prompt_tokens + ai_time_series_records.completion_tokens as tokens'))
                ->get();

            foreach ($records as $record) {
                $features[$record->feature] ??= [
                    'feature'      => $record->feature,
                    'label'        => Str::headline($record->feature),
                    'today'        => 0.0,
                    'week'         => 0.0,
                    'month'        => 0.0,
                    'year'         => 0.0,
                    'calls_month'  => 0,
                    'tokens_month' => 0,
                ];
                $features[$record->feature][$column] = (float) $record->cost;

                if ($column === 'month') {
                    $features[$record->feature]['calls_month']  = (int) $record->number_calls;
                    $features[$record->feature]['tokens_month'] = (int) $record->tokens;
                }
            }
        }

        return collect($features)->sortByDesc('year')->values()->all();
    }

    /**
     * @return array<int, array{day: string, cost: float, calls: int}>
     */
    public function getDaily(): array
    {
        return DB::table('ai_time_series_records')
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->where('from', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('period as day, sum(cost) as cost, sum(number_calls) as calls')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['day' => $row->day, 'cost' => (float) $row->cost, 'calls' => (int) $row->calls])
            ->all();
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-robot',
                        'route' => [
                            'name' => 'grp.ai.dashboard',
                        ],
                        'label' => __('AI Dashboard'),
                    ],
                ],
            ]
        );
    }
}
