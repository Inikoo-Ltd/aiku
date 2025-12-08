<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Oct 2023 16:06:07 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Web\WebBlock;

trait WithFirstBanner
{
    public function getFirstBannerWidget(WebBlock $scope): ?array
    {
        $firstBanner = null;

        $textHtml = "<h1 class='text-xl'>Let's get started</h1><p>Our banner creator platform is the perfect way to create engaging and effective banners for all your online marketing needs.</p>";

        if (class_basename($scope) == 'PortfolioWebsite') {

            if ($scope->stats->number_banners == 0) {
                $firstBanner = [
                    'text' => $textHtml,
                    'createRoute' => [
                        'name' => 'customer.models.portfolio-website.banner.store',
                        'parameters' => $scope->id,
                    ],
                ];
            }
        } else {

            if ($scope->stats->number_banners == 0) {
                $numberPortfolioWebsites = $scope->portfolioStats->number_portfolio_websites;
                if ($numberPortfolioWebsites == 1) {
                    $portfolioWebsiteID = PortfolioWebsite::first()->pluck('id');
                    $firstBanner = [
                        'text' => $textHtml,
                        'createRoute' => [
                            'name' => 'customer.models.portfolio-website.banner.store',
                            'parameters' => $portfolioWebsiteID,
                        ],
                    ];
                } elseif ($numberPortfolioWebsites > 1) {
                    $firstBanner = [
                        'text' => $textHtml,
                        'websiteOptions' => GetPortfolioWebsitesOptions::run(),
                        'createRoute' => [
                            'name' => 'customer.models.banner.store',

                        ],
                    ];
                }
            }
        }

        return $firstBanner;
    }
}
