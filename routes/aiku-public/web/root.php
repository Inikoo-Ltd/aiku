<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Feb 2024 09:06:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dropshipping\Tiktok\User\UI\OnboardingTiktokUser;
use App\Actions\UI\AikuPublic\LogPublicVisit;
use App\Actions\UI\AikuPublic\SearchNotes;
use App\Actions\UI\AikuPublic\ShowBlog;
use App\Actions\UI\AikuPublic\ShowBlogPost;
use App\Actions\UI\AikuPublic\ShowFeed;
use App\Actions\UI\AikuPublic\ShowHome;
use App\Actions\UI\AikuPublic\ShowSitemap;
use Illuminate\Support\Facades\Route;

Route::get('robots.txt', function () {
    return response(
        "User-agent: *\nAllow: /\nSitemap: ".route('aiku-public.sitemap')."\n",
        200,
        [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]
    );
})->name('robots');

Route::get('llms.txt', function () {
    return response(
        "# aiku\n\n> Open source operating system for commerce: ERP, warehouse and dispatch, fulfilment, storefronts, marketplaces, dropshipping, CRM, marketing and accounting in one Laravel codebase. Licensed AGPL-3.0.\n\n"
        ."- [Home](".route('aiku-public.home').")\n"
        ."- [Engineering notes](".route('aiku-public.blog.index').") — short notes on how the system is built and run\n"
        ."- [RSS feed](".route('aiku-public.feed').")\n"
        ."- [Sitemap](".route('aiku-public.sitemap').")\n"
        ."- [Source code](https://github.com/Inikoo-Ltd/aiku)\n"
        ."- [License](https://github.com/Inikoo-Ltd/aiku/blob/main/LICENSE)\n",
        200,
        [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]
    );
})->name('llms');

Route::get('/', ShowHome::class)->name('home');
Route::get('blog', ShowBlog::class)->name('blog.index');
Route::get('blog/search.json', SearchNotes::class)->middleware('throttle:60,1')->name('search');
Route::get('visit.json', LogPublicVisit::class)->middleware('throttle:30,1')->name('visit');
Route::get('blog/{slug}', ShowBlogPost::class)->name('blog.show');
Route::get('sitemap.xml', ShowSitemap::class)->name('sitemap');
Route::get('feed.xml', ShowFeed::class)->name('feed');
Route::get(config('services.indexnow.key', 'indexnow-key-not-set').'.txt', function () {
    return response(config('services.indexnow.key'), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('indexnow-key');

Route::get('tiktok/onboarding', OnboardingTiktokUser::class)->name('tiktok.onboarding');
