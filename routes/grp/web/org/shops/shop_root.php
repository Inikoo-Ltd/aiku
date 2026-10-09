<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Dec 2023 22:12:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\UI\CreateExternalShop;
use App\Actions\Catalogue\Shop\UI\CreateShop;
use App\Actions\Catalogue\Shop\UI\IndexShops;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\UI\ShowCrmDashboard;
use App\Actions\Web\WebsitePageView\UI\IndexWebsitePageViews;
use App\Actions\Web\Crawl\UI\IndexSiteAuditIssuePages;
use App\Actions\Web\Crawl\UI\ShowSiteAudit;
use App\Actions\Web\Seo\UI\ShowSeoKeywords;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Actions\Web\WebsiteNotFoundPath\UI\IndexWebsiteNotFoundPaths;
use App\Actions\Web\WebsiteVisitor\UI\IndexWebsiteVisitors;
use Illuminate\Support\Facades\Route;

Route::get('', IndexShops::class)->name('index');
Route::get('create', CreateShop::class)->name('create');
Route::get('external/{engine}/create', CreateExternalShop::class)->name('external.create');
Route::get('{shop}', ShowShop::class)->name('show');

Route::get('{shop}', function ($organisation, $shop) {
    return redirect()->route('grp.org.shops.show.dashboard.show', [$organisation, $shop]);
});


Route::prefix('{shop}')->name('show.')
    ->group(function () {

        Route::name("dashboard.")->prefix('dashboard')
            ->group(__DIR__ . "/dashboard.php");

        Route::name("catalogue.")->prefix('catalogue')
            ->group(__DIR__ . "/catalogue.php");

        Route::name("billables.")->prefix('billables')
            ->group(__DIR__ . "/billables.php");



        Route::name("crm.")->prefix('crm')->group(
            function () {
                Route::get('', ShowCrmDashboard::class)->name('dashboard');
                Route::prefix("customers")
                    ->name("customers.")
                    ->group(__DIR__ . "/customers.php");
                Route::prefix("web-users")
                    ->name("web_users.")
                    ->group(__DIR__ . "/web_users.php");
                Route::prefix("prospects")
                    ->name("prospects.")
                    ->group(__DIR__ . "/prospects.php");
                Route::prefix("polls")
                    ->name("polls.")
                    ->group(__DIR__ . "/polls.php");
                Route::prefix("appointments")
                    ->name("appointments.")
                    ->group(__DIR__ . "/appointments.php");
                Route::prefix("platforms")
                    ->name("platforms.")
                    ->group(__DIR__ . "/platforms.php");
                Route::prefix("self-filled-tags")
                    ->name("self_filled_tags.")
                    ->group(__DIR__."/self_filled_tags.php");
                Route::prefix("internal-tags")
                    ->name("internal_tags.")
                    ->group(__DIR__."/internal_tags.php");
                Route::prefix("system-tags")
                    ->name("system_tags.")
                    ->group(__DIR__."/system_tags.php");
                Route::prefix("countries")
                    ->name("countries.")
                    ->group(__DIR__ . "/countries.php");
                Route::prefix("chat-sessions")
                    ->name("chat_sessions.")
                    ->group(__DIR__ . "/chat_sessions.php");
            }
        );


        Route::name("ordering.")->prefix('ordering')
            ->group(__DIR__ . "/ordering.php");

        Route::name("discounts.")->prefix('offers')
            ->group(__DIR__ . "/discounts.php");

        Route::name("marketing.")->prefix('marketing')
            ->group(function () {
                Route::prefix("traffic-sources")
                    ->name("traffic_sources.")
                    ->group(__DIR__ . "/traffic_sources.php");
                Route::prefix("google-ads")
                    ->name("google_ads.")
                    ->group(__DIR__ . "/google_ads.php");

                Route::prefix("whatsapp-campaigns")
                    ->name("whatsapp_campaigns.")
                    ->group(__DIR__ . "/whatsapp_campaigns.php");
            })
            ->group(__DIR__ . "/marketing.php");

        Route::prefix("web")
            ->name("web.")
            ->group(__DIR__ . "/websites.php");

        Route::prefix("seo")
            ->name("seo.")
            ->group(function () {
                Route::get('', ShowSeoDashboard::class)->name('dashboard');
                Route::get('visitors', [IndexWebsiteVisitors::class, 'inSeo'])->name('visitors.index');
                Route::get('visitors/webpages/{webpage}', [IndexWebsiteVisitors::class, 'inSeoWebpage'])->name('visitors.webpage')->withoutScopedBindings();
                Route::get('page-views', IndexWebsitePageViews::class)->name('page_views.index');
                Route::get('page-views/visitors/{websiteVisitor}', [IndexWebsitePageViews::class, 'inVisitor'])->name('page_views.visitor')->withoutScopedBindings();
                Route::get('site-audit', ShowSiteAudit::class)->name('site_audit.show');
                Route::get('site-audit/issues/{issueType}', IndexSiteAuditIssuePages::class)->name('site_audit.issue');
                Route::get('missing-pages', IndexWebsiteNotFoundPaths::class)->name('not_found.index');
                Route::get('keywords', ShowSeoKeywords::class)->name('keywords.show');
            });

        Route::prefix("settings")
            ->name("settings.")
            ->group(__DIR__ . "/settings.php");

        Route::prefix("chat")
            ->name("chat.")
            ->group(__DIR__ . "/chat.php");

        Route::prefix("tasks")
            ->name("tasks.")
            ->group(__DIR__ . "/tasks.php");

        Route::prefix("tickets")
            ->name("tickets.")
            ->group(__DIR__ . "/tickets.php");

        Route::prefix("reviews")
            ->name("reviews.")
            ->group(__DIR__ . "/reviews.php");
    });
