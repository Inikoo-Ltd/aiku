<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 26 Sep 2024 13:20:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\UI\Websites\WebsitesDashboard;
use App\Actions\Web\Crawl\UI\CompareCrawls;
use App\Actions\Web\Crawl\UI\ExportCrawlReport;
use App\Actions\Web\Crawl\UI\ExportCrawlTab;
use App\Actions\Web\Crawl\UI\IndexSiteCrawls;
use App\Actions\Web\Crawl\UI\ShowCrawl;
use App\Actions\Web\Crawl\UI\ShowCrawlStructure;
use App\Actions\Web\GoogleTagManager\ConnectGoogleTagManager;
use App\Actions\Web\GoogleTagManager\UI\ShowGoogleTagManager;
use App\Actions\Web\Seo\ExportSeoPortfolio;
use App\Actions\Web\Seo\UI\ShowSeoApiUsage;
use App\Actions\Web\Seo\UI\ShowSeoPortfolio;
use App\Actions\Web\ShoppingAssistant\UI\ShowShoppingAssistantConversation;
use App\Actions\Web\ShoppingAssistant\UI\ShowShoppingAssistantPerformance;
use App\Actions\Web\Webpage\UI\ShowFooterPreview;
use App\Actions\Web\Webpage\UI\ShowHeaderPreview;
use App\Actions\Web\Webpage\UI\ShowSidebarPreview;
use App\Actions\Web\Webpage\UI\ShowWebpageWorkshopPreview;
use App\Actions\Web\Webpage\UI\ShowWebsitePreview;

Route::get('/', WebsitesDashboard::class)->name('index');
Route::get('seo/portfolio', ShowSeoPortfolio::class)->name('seo.portfolio');
Route::get('seo/portfolio/export', ExportSeoPortfolio::class)->name('seo.portfolio.export');
Route::get('seo/api-usage', ShowSeoApiUsage::class)->name('seo.api_usage');
Route::get('crawler', IndexSiteCrawls::class)->name('crawler.index');
Route::get('crawler/{crawl:id}', ShowCrawl::class)->name('crawler.show');
Route::get('crawler/{crawl:id}/export', ExportCrawlTab::class)->name('crawler.export');
Route::get('crawler/{crawl:id}/report', ExportCrawlReport::class)->name('crawler.report');
Route::get('crawler/{crawl:id}/compare', CompareCrawls::class)->name('crawler.compare');
Route::get('crawler/{crawl:id}/structure', ShowCrawlStructure::class)->name('crawler.structure');
Route::get('ai-assistant', ShowShoppingAssistantPerformance::class)->name('shopping_assistant.show');
Route::get('ai-assistant/conversations/{conversation:ulid}', ShowShoppingAssistantConversation::class)->name('shopping_assistant.conversations.show');
Route::get('google-tag-manager', ShowGoogleTagManager::class)->name('google_tag_manager.index');
Route::get('google-tag-manager/connect', ConnectGoogleTagManager::class)->name('google_tag_manager.connect');
Route::get('{website}/webpages/{webpage}/workshop/preview', [ShowWebpageWorkshopPreview::class, 'inWebsite'])->name('webpage.preview');
Route::get('{website}/webpages/{webpage}/website/preview', ShowWebsitePreview::class)->name('preview');
Route::get('{website}/footer/preview', ShowFooterPreview::class)->name('footer.preview');
Route::get('{website}/header/preview', ShowHeaderPreview::class)->name('header.preview');
Route::get('{website}/sidebar/preview', ShowSidebarPreview::class)->name('sidebar.preview');
