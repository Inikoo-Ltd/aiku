<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jun 2026 11:11:38 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


use App\Actions\DevOps\Server\GetServerInfo;
use Illuminate\Support\Facades\Route;

Route::get('/{server}', GetServerInfo::class)->name('info');
