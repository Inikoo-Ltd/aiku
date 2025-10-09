<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Jul 2024 00:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Helpers\GoogleDrive\CallbackClientGoogleDrive;
use App\Actions\SysAdmin\Organisation\UI\EditOrganisationSettings;
use Illuminate\Support\Facades\Route;

Route::get('', EditOrganisationSettings::class)->name('edit');
Route::get("google-drive-callback", [CallbackClientGoogleDrive::class, 'callback'])->name('google_drive.callback');
