<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 May 2024 21:00:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\UI\Notification\IndexNotification;
use App\Actions\UI\Notification\ShowNotification;
use App\Actions\UI\Profile\GetMayaProfile;

Route::get('/', GetMayaProfile::class)->name('show');

Route::get('/notifications', IndexNotification::class)->name('notifications.index');
Route::get('/notifications/{notification}', ShowNotification::class)->name('notifications.show');
