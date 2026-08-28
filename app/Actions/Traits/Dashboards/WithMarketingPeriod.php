<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 11 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Dashboards;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * The marketing dashboards take their period from the same saved interval every other dashboard uses,
 * so the settings bar at the top of the screen governs them all and the choice survives a reload.
 */
trait WithMarketingPeriod
{
    use WithDashboardIntervalOption;
    use WithPerformanceDateResolution;

    protected DateIntervalEnum $interval = DateIntervalEnum::ONE_MONTH;

    protected ?Carbon $periodFrom = null;

    protected ?Carbon $periodTo = null;

    protected function setMarketingPeriod(array $userSettings): void
    {
        $this->interval = DateIntervalEnum::tryFrom((string) Arr::get($userSettings, 'selected_interval'))
            ?? DateIntervalEnum::ONE_MONTH;

        [$from, $to] = $this->resolvePerformanceDates($this->interval, $userSettings);

        $this->periodFrom = $from ? Carbon::parse($from)->startOfDay() : null;
        $this->periodTo   = $to ? Carbon::parse($to)->endOfDay() : null;
    }

    /** @return array{options: array<int, array<string, mixed>>, value: string, range_interval: string} */
    protected function intervalsProp(array $userSettings): array
    {
        return [
            'options'        => $this->dashboardIntervalOption(),
            'value'          => $this->interval->value,
            'range_interval' => DashboardIntervalFilters::run($this->interval, $userSettings),
        ];
    }

    /** @return array{period: string, period_label: string} */
    protected function periodLabels(): array
    {
        return [
            'period'       => $this->interval->value,
            'period_label' => DateIntervalEnum::labels()[$this->interval->value] ?? __('Custom range'),
        ];
    }
}
