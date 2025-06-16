<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Jan 2024 17:46:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\CRM\Prospect\Mailshots\UI\IndexProspectMailshots;
use App\Actions\CRM\Prospect\UI\CreateProspect;
use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Actions\CRM\Prospect\UI\ShowProspect;

Route::get('/', IndexProspects::class)->name('index');
Route::get('/create', CreateProspect::class)->name('create');
Route::get('/mailshots', [IndexProspectMailshots::class, 'inShop'])->name('mailshots.index');
Route::get('/{prospect}', ShowProspect::class)->name('show');
