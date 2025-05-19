<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 26 Sep 2024 13:20:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Web\Webpage\UI\ShowFooterPreview;
use App\Actions\Web\Webpage\UI\ShowHeaderPreview;
use App\Actions\Web\Webpage\UI\ShowWebpageWorkshopPreview;
use App\Actions\Web\Webpage\UI\ShowWebsitePreview;

Route::get('{website}/webpages/{webpage}/workshop/preview', [ShowWebpageWorkshopPreview::class, 'inWebsite'])->name('webpage.preview');
Route::get('{website}/webpages/{webpage}/website/preview', ShowWebsitePreview::class)->name('preview');
Route::get('{website}/footer/preview', ShowFooterPreview::class)->name('footer.preview');
Route::get('{website}/header/preview', ShowHeaderPreview::class)->name('header.preview');
