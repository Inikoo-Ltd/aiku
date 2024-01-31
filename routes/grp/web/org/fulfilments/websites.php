<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jan 2024 18:56:15 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Web\Webpage\IndexWebpages;
use App\Actions\Web\Website\UI\EditWebsite;
use App\Actions\Web\Website\UI\IndexWebsites;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Actions\Web\Website\UI\ShowWebsiteWorkshop;

Route::get('/', [IndexWebsites::class, 'inFulfilment'])->name('index');
Route::get('/{website}/show', [ShowWebsite::class,'inFulfilment'])->name('show');
Route::get('/{website}/edit', EditWebsite::class)->name('edit');
Route::get('/{website}/workshop', ShowWebsiteWorkshop::class)->name('workshop');

//Route::get('/{website}/workshop/preview', ShowWebsiteWorkshopPreview::class)->name('preview');
//Route::get('/{website}/blog/article/create', CreateArticle::class)->name('show.blog.article.create');

// Route::get('/{website}/webpages/create', CreateWebpage::class)->name('show.webpages.create');
Route::get('/{website}/webpages', [IndexWebpages::class, 'inWebsite'])->name('show.webpages.index');


//Route::get('/{website}/webpages/{webpage}/create', [CreateWebpage::class, 'inWebsiteInWebpage'])->name('show.webpages.show.webpages.create');
Route::get('/{website}/webpages/{webpage}/webpages', [IndexWebpages::class, 'inWebpage'])->name('show.webpages.show.webpages.index');
//Route::get('/{website}/webpages/{webpage}/edit', [EditWebpage::class, 'inWebsite'])->name('show.webpages.edit');
//Route::get('/{website}/webpages/{webpage}/workshop', [ShowWebpageWorkshop::class, 'inWebsite'])->name('show.webpages.workshop');
//Route::post('/{website}/webpages/{webpage}/workshop/images', [UploadImagesToWebpage::class, 'inWebsite'])->name('show.webpages.workshop.images.store');


//Route::get('/{website}/webpages/{webpage}/workshop/preview', [ShowWebpageWorkshopPreview::class, 'inWebsite'])->name('show.webpages.preview');
//Route::get('/{website}/webpages/{webpage}', [ShowWebpage::class, 'inWebsite'])->name('show.webpages.show');
