<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 13:30:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use Illuminate\Support\Facades\Route;

Route::middleware(["auth"])->group(function () {
    Route::get('/', function () {
        return redirect('/dashboard');
    });
    Route::prefix("overview")
        ->name("overview.")
        ->group(__DIR__ . "/overview.php");
    Route::prefix("organisations")
        ->name("organisations.")
        ->group(__DIR__ . "/organisations.php");
    Route::prefix("dashboard")
        ->name("dashboard.")
        ->group(__DIR__ . "/dashboard.php");
    Route::prefix("supply-chain")
        ->name("supply-chain.")
        ->group(__DIR__ . "/supply-chain.php");
    Route::prefix("goods")
        ->name("goods.")
        ->group(__DIR__ . "/goods.php");
    Route::prefix("profile")
        ->name("profile.")
        ->group(__DIR__ . "/profile.php");
    Route::prefix("sysadmin")
        ->name("sysadmin.")
        ->group(__DIR__ . "/sysadmin.php");
    Route::prefix("org/{organisation}")
        ->name("org.")
        ->group(__DIR__ . "/org/org.php");
    Route::prefix("models")
        ->name("models.")
        ->group(__DIR__ . "/models.php");
    Route::prefix("search")
        ->name("search.")
        ->group(__DIR__ . "/search.php");
    /*

    Route::prefix("account")
        ->name("account.")
        ->group(__DIR__."/account.php");
    Route::prefix("bi")
        ->name("reports.")
        ->group(__DIR__."/reports.php");
    Route::prefix("crm")
        ->name("crm.")
        ->group(__DIR__."/crm.php");
    Route::prefix("hr")
        ->name("hr.")
        ->group(__DIR__."/hr.php");
    Route::prefix("inventory")
        ->name("inventory.")
        ->group(__DIR__."/warehouses.php");
    Route::prefix("fulfilment")
        ->name("fulfilment.")
        ->group(__DIR__."/fulfilment.php");


    Route::prefix("web")
        ->name("web.")
        ->group(__DIR__."/web.php");


    Route::prefix("oms")
        ->name("oms.")
        ->group(__DIR__."/orders.php");
    Route::prefix("dispatch")
        ->name("dispatch.")
        ->group(__DIR__."/dispatch.php");

    Route::prefix("accounting")
        ->name("accounting.")
        ->group(__DIR__."/accounting.php");
    Route::prefix("marketing")
        ->name("marketing.")
        ->group(__DIR__."/marketing.php");


    Route::prefix("media")
        ->name("media.")
        ->group(__DIR__."/media.php");
    Route::prefix("json")
        ->name("json.")
        ->group(__DIR__."/json.php");
    Route::prefix("google")
        ->name("google.")
        ->group(__DIR__."/google.php");

*/
});
require __DIR__ . "/auth.php";


if(!app()->isProduction()) {
    Route::get('routes', function () {
        $routeCollection = Route::getRoutes();

        echo "<table style='width:100%'>";
        echo "<tr>";
        echo "<td width='10%'><h4>HTTP Method</h4></td>";
        echo "<td width='10%'><h4>Route</h4></td>";
        echo "<td width='10%'><h4>Name</h4></td>";
        echo "<td width='70%'><h4>Corresponding Action</h4></td>";
        echo "</tr>";
        foreach ($routeCollection as $value) {
            echo "<tr>";
            echo "<td>" . $value->methods()[0] . "</td>";
            echo "<td>" . $value->uri() . "</td>";
            echo "<td>" . $value->getName() . "</td>";
            echo "<td>" . $value->getActionName() . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    });
}
