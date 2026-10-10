<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\SeoApi;

use App\Models\SysAdmin\Group;
use App\Models\Web\SeoApiRequest;
use Illuminate\Support\Carbon;

/**
 * One monthly budget for every paid SEO API (DataForSEO and the AI gateway calls of the SEO tools),
 * not one per provider or feature. The spend is the cost logged in `seo_api_requests` since the
 * start of the month; a provider client stops making billable calls once it reaches the budget. The
 * budget is set on the SEO API usage page and kept in the group settings, with
 * `SEO_API_MONTHLY_BUDGET` as the default.
 */
class SeoApiBudget
{
    public const string SETTING = 'seo.api_monthly_budget';

    public static function monthSpend(?Carbon $month = null): float
    {
        $month ??= now();

        return (float) SeoApiRequest::query()
            ->where('created_at', '>=', $month->copy()->startOfMonth())
            ->where('created_at', '<', $month->copy()->startOfMonth()->addMonth())
            ->sum('cost');
    }

    public static function monthlyBudget(): float
    {
        $budget = data_get(Group::query()->orderBy('id')->first(['id', 'settings'])?->settings, self::SETTING);

        return is_numeric($budget) ? (float) $budget : (float) config('services.seo_api.monthly_budget');
    }

    public static function isReached(): bool
    {
        return self::monthSpend() >= self::monthlyBudget();
    }
}
