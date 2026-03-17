<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\Google\Analytics\GetAnalytics;
use App\Models\Portfolio\Banner;
use App\Models\Portfolio\PortfolioWebsite;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FetchBannerAnalytics
{
    use AsAction;
    use WithAttributes;
    use AsCommand;

    public $commandSignature = 'banner:analytics';

    public function handle(): void
    {
        $portfolioWebsites = PortfolioWebsite::all();

        foreach ($portfolioWebsites as $portfolioWebsite) {
            // TODO: Get Property ID from $portfolioWebsite

            /** @var \App\Models\Portfolio\PortfolioWebsite $portfolioWebsite */
            $analytics = GetAnalytics::run(config('services.analytics.property_id'), $portfolioWebsite->created_at);
            $bannerUlids = collect($analytics->toArray())->pluck('bannerId')->filter()->unique();
            $banner = Banner::whereIn('ulid', $bannerUlids)->get()->keyBy('ulid');

            foreach ($analytics->toArray() as $analytic) {
                $banner = $banner->get($analytic['bannerId']);

                $banner?->stats()->update([
                    'number_views' => $analytic['pageViews'],
                    'number_users' => $analytic['users']
                ]);
            }
        }
    }
}
