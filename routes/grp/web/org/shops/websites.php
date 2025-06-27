<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 21:22:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Comms\Mailshot\UI\ShowMailshot;
use App\Actions\Comms\Outbox\UI\IndexOutboxes;
use App\Actions\Comms\Outbox\UI\ShowOutbox;
use App\Actions\Comms\Outbox\UI\ShowOutboxWorkshop;
use App\Actions\CRM\WebUser\IndexWebUsers;
use App\Actions\CRM\WebUser\ShowWebUser;
use App\Actions\Helpers\Snapshot\UI\IndexSnapshots;
use App\Actions\Web\Banner\UI\CreateBanner;
use App\Actions\Web\Banner\UI\EditBanner;
use App\Actions\Web\Banner\UI\IndexBanners;
use App\Actions\Web\Banner\UI\ShowBanner;
use App\Actions\Web\Banner\UI\ShowBannerWorkshop;
use App\Actions\Web\Redirect\UI\CreateRedirect;
use App\Actions\Web\Redirect\UI\EditRedirect;
use App\Actions\Web\Redirect\UI\ShowRedirect;
use App\Actions\Web\Webpage\UI\CreateWebpage;
use App\Actions\Web\Webpage\UI\EditWebpage;
use App\Actions\Web\Webpage\UI\IndexDepartmentWebpages;
use App\Actions\Web\Webpage\UI\IndexFamilyWebpages;
use App\Actions\Web\Webpage\UI\IndexProductWebpages;
use App\Actions\Web\Webpage\UI\IndexSubDepartmentWebpages;
use App\Actions\Web\Webpage\UI\IndexWebpages;
use App\Actions\Web\Webpage\UI\ShowWorkshopBlueprint;
use App\Actions\Web\Webpage\UI\ShowFooterWorkshop;
use App\Actions\Web\Webpage\UI\ShowHeaderWorkshop;
use App\Actions\Web\Webpage\UI\ShowMenuWorkshop;
use App\Actions\Web\Webpage\UI\ShowWebpage;
use App\Actions\Web\Webpage\UI\ShowWebpagesTree;
use App\Actions\Web\Webpage\UI\ShowWebpageWorkshop;
use App\Actions\Web\Webpage\UI\ShowWebpageWorkshopPreview;
use App\Actions\Web\Website\UI\CreateWebsite;
use App\Actions\Web\Website\UI\EditWebsite;
use App\Actions\Web\Website\UI\IndexWebsites;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Actions\Web\Website\UI\ShowWebsiteWorkshop;
use App\Actions\Web\Website\UI\ShowWebsiteWorkshopPreview;
use Illuminate\Support\Facades\Route;

Route::name('websites.')->group(function () {
    Route::get('/', [IndexWebsites::class, 'inShop'])->name('index');
    Route::get('/create', [CreateWebsite::class, 'inShop'])->name('create');

    Route::prefix('{website}')
        ->group(function () {
            Route::get('', ShowWebsite::class)->name('show');
            Route::get('edit', EditWebsite::class)->name('edit');
            Route::get('outboxes', [IndexOutboxes::class, 'inWebsite'])->name('outboxes');
            Route::get('outboxes/{outbox}', [ShowOutbox::class, 'inWebsite'])->name('outboxes.show');
            Route::get('outboxes/{outbox}/workshop', [ShowOutboxWorkshop::class, 'inWebsite'])->name('outboxes.workshop');
            Route::name("outboxes.mailshots.")->prefix('outboxes/{outbox}/mailshots')
                ->group(function () {
                    Route::get('{mailshot}', [ShowMailshot::class, 'inOutboxInWebsite'])->name('show');
                });
            Route::name('workshop')->prefix('workshop')
                ->group(function () {
                    Route::get('', ShowWebsiteWorkshop::class)->name('');
                    Route::get('preview', ShowWebsiteWorkshopPreview::class)->name('.preview');
                    Route::get('footer', ShowFooterWorkshop::class)->name('.footer');
                    Route::get('header', ShowHeaderWorkshop::class)->name('.header');
                    Route::get('menu', ShowMenuWorkshop::class)->name('.menu');

                    Route::name('.snapshots')->prefix('snapshots')
                        ->group(function () {
                            Route::get('footer', [IndexSnapshots::class, 'inFooterWorkshop'])->name('.footer');
                            Route::get('header', [IndexSnapshots::class, 'inHeaderWorkshop'])->name('.header');
                            Route::get('menu', [IndexSnapshots::class, 'inMenuWorkshop'])->name('.menu');
                        });
                });
            Route::name('redirect')->prefix('redirect')
                ->group(function () {
                    Route::get('{redirect}', [ShowRedirect::class, 'inWebsite'])->name('.show');
                    Route::get('{redirect}/edit', [EditRedirect::class, 'inWebpage'])->name('.edit');
                });
        });
});


Route::prefix('{website}/webpages')->name('webpages.')->group(function () {
    Route::get('', IndexWebpages::class)->name('index');
    Route::get('tree', ShowWebpagesTree::class)->name('tree');
    Route::get('/type/catalogue', [IndexWebpages::class, 'catalogue'])->name('index.type.catalogue');
    Route::get('/type/content', [IndexWebpages::class, 'content'])->name('index.type.content');
    Route::get('/type/info', [IndexWebpages::class, 'info'])->name('index.type.info');
    Route::get('/type/blog', [IndexWebpages::class, 'blog'])->name('index.type.blog');
    Route::get('/type/operations', [IndexWebpages::class, 'operations'])->name('index.type.operations');
    Route::get('/sub-type/department', IndexDepartmentWebpages::class)->name('index.sub_type.department');
    Route::get('/sub-type/department/{scope}/sub-departments', [IndexSubDepartmentWebpages::class, 'inDepartmentWebpages'])->name('index.sub_type.department.sub_departments');
    Route::get('/sub-type/department/{scope}/families', [IndexFamilyWebpages::class, 'inDepartmentWebpages'])->name('index.sub_type.department.families');
    Route::get('/sub-type/department/{scope}/products', [IndexProductWebpages::class, 'inDepartmentWebpages'])->name('index.sub_type.department.products');
    Route::get('/sub-type/sub-department', IndexSubDepartmentWebpages::class)->name('index.sub_type.sub_department');
    Route::get('/sub-type/sub-department/{scope}/families', [IndexFamilyWebpages::class, 'inSubDepartmentWebpages'])->name('index.sub_type.sub_department.families');
    Route::get('/sub-type/sub-department/{scope}/products', [IndexProductWebpages::class, 'inSubDepartmentWebpages'])->name('index.sub_type.sub_department.products');
    Route::get('/sub-type/family', IndexFamilyWebpages::class)->name('index.sub_type.family');
    Route::get('/sub-type/family/{scope}/products', [IndexProductWebpages::class, 'inFamilyWebpages'])->name('index.sub_type.family.products');
    Route::get('/sub-type/product', IndexProductWebpages::class)->name('index.sub_type.product');

    Route::get('create', CreateWebpage::class)->name('create');
    Route::prefix('{webpage}')
        ->group(function () {
            Route::get('edit', EditWebpage::class)->name('edit');
            Route::get('', ShowWebpage::class)->name('show');
            Route::get('blueprint', ShowWorkshopBlueprint::class)->name('show.blueprint.show');
            Route::get('workshop', ShowWebpageWorkshop::class)->name('workshop');
            Route::get('workshop/preview', ShowWebpageWorkshopPreview::class)->name('preview');
            Route::get('webpages', [IndexWebpages::class, 'inWebpage'])->name('show.webpages.index');

            Route::name('redirect')->prefix('redirect')
            ->group(function () {
                Route::get('create', [CreateRedirect::class, 'inWebpage'])->name('.create');
                Route::get('{redirect}', [ShowRedirect::class, 'inWebpage'])->name('.show');
                Route::get('{redirect}/edit', [EditRedirect::class, 'inWebpage'])->name('.edit');
            });
        });
});

Route::prefix('{website}/web-users')->name('web_users.')->group(function () {
    Route::get('', IndexWebUsers::class)->name('index');
    Route::get('{webUser}', ShowWebUser::class)->name('show');
});

Route::prefix('{website}/banners')->name('banners.')->group(function () {
    Route::get('', IndexBanners::class)->name('index');
    Route::get('/create', CreateBanner::class)->name('create');
    Route::get('/{banner}/workshop', ShowBannerWorkshop::class)->name('workshop');
    Route::get('/{banner}/edit', EditBanner::class)->name('edit');
    Route::get('/{banner}', ShowBanner::class)->name('show');
});
