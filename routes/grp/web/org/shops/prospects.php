<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Jan 2024 17:46:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\CRM\Prospect\Mailshots\UI\CreateProspectMailshot;
use App\Actions\CRM\Prospect\Mailshots\UI\EditProspectMailshot;
use App\Actions\CRM\Prospect\Mailshots\UI\IndexProspectMailshots;
use App\Actions\CRM\Prospect\Mailshots\UI\ShowProspectMailshot;
use App\Actions\CRM\Prospect\Mailshots\UI\ShowProspectMailshotWorkshop;
use App\Actions\CRM\Prospect\UI\CreateProspect;
use App\Actions\CRM\Prospect\UI\EditProspect;
use App\Actions\CRM\Prospect\UI\ExportProspects;
use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Actions\CRM\Prospect\UI\ShowProspect;

Route::get('/', IndexProspects::class)->name('index');
Route::get('export', [ExportProspects::class, 'inShop'])->name('export');
Route::get('/create', CreateProspect::class)->name('create');
Route::get('/mailshots', IndexProspectMailshots::class)->name('mailshots.index');
Route::get('/mailshots/create', CreateProspectMailshot::class)->name('mailshots.create');
Route::get('/mailshots/{mailshot}', ShowProspectMailshot::class)->name('mailshots.show');
Route::get('/mailshots/{mailshot}/edit', EditProspectMailshot::class)->name('mailshots.edit');
Route::get('/mailshots/{mailshot}/workshop', ShowProspectMailshotWorkshop::class)->name('mailshots.workshop');
Route::get('/{prospect}', ShowProspect::class)->name('show');
Route::get('/{prospect}/edit', EditProspect::class)->name('edit');
