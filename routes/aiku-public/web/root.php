<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Feb 2024 09:06:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dropshipping\Tiktok\User\UI\OnboardingTiktokUser;
use App\Actions\UI\AikuPublic\SearchNotes;
use App\Actions\UI\AikuPublic\ShowBlog;
use App\Actions\UI\AikuPublic\ShowBlogPost;
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

Route::get('/', ShowHome::class)->name('home');
Route::get('blog', ShowBlog::class)->name('blog.index');
Route::get('blog/search.json', SearchNotes::class)->middleware('throttle:60,1')->name('search');
Route::get('blog/{slug}', ShowBlogPost::class)->name('blog.show');
Route::get('sitemap.xml', ShowSitemap::class)->name('sitemap');

Route::get('tiktok/onboarding', OnboardingTiktokUser::class)->name('tiktok.onboarding');
