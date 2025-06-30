<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 15:54:18 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\CRM\WebUser\IndexWebUsersInCRM;
use App\Actions\CRM\WebUser\ShowWebUser;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexWebUsersInCRM::class, 'inShop'])->name('index');
Route::get('{webUser}', ShowWebUser::class)->name('show');
