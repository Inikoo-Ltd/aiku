<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jan 2024 17:12:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use Illuminate\Support\Facades\Route;

Route::prefix("customers")
    ->name("customers.")
    ->group(__DIR__."/customers.php");

Route::prefix("prospects")
    ->name("prospects.")
    ->group(__DIR__."/prospects.php");

Route::prefix("web-users")
    ->name("web_users.")
    ->group(__DIR__."/web_users.php");
