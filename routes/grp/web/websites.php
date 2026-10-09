<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 26 Sep 2024 13:20:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\UI\Websites\WebsitesDashboard;
use App\Actions\Web\Seo\ExportSeoPortfolio;
use App\Actions\Web\Seo\UI\ShowSeoApiUsage;
use App\Actions\Web\Seo\UI\ShowSeoPortfolio;
use App\Actions\Web\Webpage\UI\ShowFooterPreview;
use App\Actions\Web\Webpage\UI\ShowHeaderPreview;
use App\Actions\Web\Webpage\UI\ShowSidebarPreview;
use App\Actions\Web\Webpage\UI\ShowWebpageWorkshopPreview;
use App\Actions\Web\Webpage\UI\ShowWebsitePreview;

Route::get('/', WebsitesDashboard::class)->name('index');
Route::get('seo/portfolio', ShowSeoPortfolio::class)->name('seo.portfolio');
Route::get('seo/portfolio/export', ExportSeoPortfolio::class)->name('seo.portfolio.export');
Route::get('seo/api-usage', ShowSeoApiUsage::class)->name('seo.api_usage');
Route::get('{website}/webpages/{webpage}/workshop/preview', [ShowWebpageWorkshopPreview::class, 'inWebsite'])->name('webpage.preview');
Route::get('{website}/webpages/{webpage}/website/preview', ShowWebsitePreview::class)->name('preview');
Route::get('{website}/footer/preview', ShowFooterPreview::class)->name('footer.preview');
Route::get('{website}/header/preview', ShowHeaderPreview::class)->name('header.preview');
Route::get('{website}/sidebar/preview', ShowSidebarPreview::class)->name('sidebar.preview');
