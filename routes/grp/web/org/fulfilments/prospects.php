<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Jan 2024 17:46:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\CRM\Prospect\UI\CreateProspect;
use App\Actions\CRM\Prospect\UI\IndexProspects;

Route::get('/', [IndexProspects::class, 'inFulfilment'])->name('index');
Route::get('/create', [CreateProspect::class, 'inFulfilment'])->name('create');
