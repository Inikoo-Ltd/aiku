<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\SeoApi;

use App\Models\Web\SeoApiRequest;

/**
 * One monthly budget for every paid SEO API (DataForSEO now, Apify and the AI gateway for AI
 * visibility later), not one per provider or feature. The spend is the cost logged in
 * `seo_api_requests` since the start of the month; a provider client stops making billable calls
 * once it reaches the budget.
 */
class SeoApiBudget
{
    public static function monthSpend(): float
    {
        return (float) SeoApiRequest::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost');
    }

    public static function monthlyBudget(): float
    {
        return (float) config('services.seo_api.monthly_budget');
    }

    public static function isReached(): bool
    {
        return self::monthSpend() >= self::monthlyBudget();
    }
}
