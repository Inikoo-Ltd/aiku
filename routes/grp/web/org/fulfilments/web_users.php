<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Jun 2025 13:45:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\CRM\WebUser\IndexWebUsersInFulfilmentCRM;
use App\Actions\CRM\WebUser\ShowWebUser;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexWebUsersInFulfilmentCRM::class, 'inFulfilment'])->name('index');
Route::get('{webUser}', ShowWebUser::class)->name('show');
