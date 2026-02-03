<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 11:23:04 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\UIDummies\EditDummy;
use App\Stubs\UIDummies\ShowDummy;
use App\Stubs\UIDummies\CreateDummy;
use App\Stubs\UIDummies\IndexDummies;
use Illuminate\Support\Facades\Route;
use App\Actions\Comms\Mailshot\UI\EditMailshot;
use App\Actions\Comms\Mailshot\UI\ShowMailshot;
use App\Actions\Comms\Mailshot\UI\CreateMailshot;
use App\Actions\Comms\Mailshot\UI\CreateMailshotTemplate;
use App\Actions\Comms\Mailshot\UI\CreateNewsletter;
use App\Actions\Comms\Mailshot\UI\ShowMailshotWorkshop;
// use App\Actions\Comms\Mailshot\UI\UpdateMailshotRecipients;
use App\Actions\Comms\Mailshot\UI\ShowMailshotRecipients;
use App\Actions\Comms\Mailshot\UI\EditMailshotTemplate;
use App\Actions\Comms\Mailshot\UI\IndexMailshotTemplates;
use App\Actions\Comms\Mailshot\UI\IndexMarketingMailshots;
use App\Actions\Comms\Mailshot\UI\IndexNewsletterMailshots;
use App\Actions\Comms\Mailshot\UI\ShowMailshotTemplateWorkshop;
use App\Actions\UI\Dropshipping\Marketing\ShowMarketingDashboard;

Route::get('', ShowMarketingDashboard::class)->name('dashboard');
Route::name("newsletters.")->prefix('newsletters')
    ->group(function () {
        Route::get('', IndexNewsletterMailshots::class)->name('index');
        Route::get('create', CreateNewsletter::class)->name('create');
        Route::get('{mailshot}', ShowMailshot::class)->name('show');
        Route::get('{mailshot}/edit', EditMailshot::class)->name('edit');
        Route::get('{mailshot}/workshop', ShowMailshotWorkshop::class)->name('workshop');
    });
Route::name("mailshots.")->prefix('mailshots')
    ->group(function () {
        Route::get('', [IndexMarketingMailshots::class, 'inShop'])->name('index');
        Route::get('create', CreateMailshot::class)->name('create');
        Route::get('{mailshot}', ShowMailshot::class)->name('show');
        Route::get('{mailshot}/workshop', ShowMailshotWorkshop::class)->name('workshop');
        Route::get('{mailshot}/edit', EditMailshot::class)->name('edit');
        Route::get('{mailshot}/recipients', ShowMailshotRecipients::class)->name('recipients');
        // Route::put('{mailshot}/recipients', UpdateMailshotRecipients::class)->name('recipients.update');
    });
Route::name("notifications.")->prefix('notifications')
    ->group(function () {
        Route::get('', IndexDummies::class)->name('index');
        Route::get('create', CreateDummy::class)->name('create');
        Route::get('{mailshot}', ShowDummy::class)->name('show');
        Route::get('{mailshot}/edit', EditDummy::class)->name('edit');
    });
Route::name("templates.")->prefix('templates')
    ->group(function () {
        Route::get('', IndexMailshotTemplates::class)->name('index');
        Route::get('create', CreateMailshotTemplate::class)->name('create');
        Route::get('{emailTemplate}/workshop', ShowMailshotTemplateWorkshop::class)->name('workshop');
        Route::get('{emailTemplate}/edit', EditMailshotTemplate::class)->name('edit');
    });
