<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 13:30:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\UI\Notification\IndexNotification;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__."/auth.php";

Route::middleware(["auth"])->group(function () {
    Route::get('/', function () {
        return redirect('/dashboard');
    });

    if (!app()->isProduction()) {
        Route::get('routes', function () {
            $routeCollection = Route::getRoutes();

            echo "<table style='width:100%'>";
            echo "<th>";
            echo "<td><h4>HTTP Method</h4></td>";
            echo "<td><h4>Route</h4></td>";
            echo "<td><h4>Name</h4></td>";
            echo "<td><h4>Corresponding Action</h4></td>";
            echo "</tr>";
            $i = 0;
            foreach ($routeCollection as $value) {
                $background = $i % 2 == 0 ? 'background-color: #EEEEEE;' : '';
                echo "<tr style='$background'>";
                echo "<td>".$value->methods()[0]."</td>";
                echo "<td>".$value->uri()."</td>";
                echo "<td>".$value->getName()."</td>";
                echo "<td>".preg_replace('/([^\\\\]+)$/', '<span style="background: #c790ff; padding: 0 2px">$1</span>', $value->getActionName())."</td>";
                echo "</tr>";
                $i++;
            }
            echo "</table>";
        });
    }


    Route::get('/notifications', IndexNotification::class)->name('notifications');
    Route::prefix("overview")
        ->name("overview.")
        ->group(__DIR__."/overview.php");
    Route::prefix("organisations")
        ->name("organisations.")
        ->group(__DIR__."/organisations.php");
    Route::prefix("dashboard")
        ->name("dashboard.")
        ->group(__DIR__."/dashboard.php");
    Route::prefix("supply-chain")
        ->name("supply-chain.")
        ->group(__DIR__."/supply-chain.php");
    Route::prefix("masters")
        ->name("masters.")
        ->group(__DIR__."/masters.php");
    Route::prefix("goods")
        ->name("goods.")
        ->group(__DIR__."/goods.php");
    Route::prefix("profile")
        ->name("profile.")
        ->group(__DIR__."/profile.php");
    Route::prefix("sysadmin")
        ->name("sysadmin.")
        ->group(__DIR__."/sysadmin.php");
    Route::prefix("org/{organisation}")
        ->name("org.")
        ->group(__DIR__."/org/org.php");
    Route::prefix("models")
        ->name("models.")
        ->group(__DIR__."/models.php");
    Route::prefix("search")
        ->name("search.")
        ->group(__DIR__."/search.php");
    Route::prefix("ask-bot")
        ->name("ask-bot.")
        ->group(__DIR__."/ask_bot.php");
    Route::prefix("media")
        ->name("media.")
        ->group(__DIR__."/media.php");
    Route::prefix("gallery")
        ->name("gallery.")
        ->group(__DIR__."/gallery.php");

    Route::prefix("json")
        ->name("json.")
        ->group(__DIR__."/json.php");

    Route::prefix("websites")
        ->name("websites.")
        ->group(__DIR__."/websites.php");

    Route::prefix("helpers")
        ->name("helpers.")
        ->group(__DIR__."/helpers.php");

    Route::fallback(function () {
        $status = 404;
        return Inertia::render('Errors/Error404', compact('status'))
            ->toResponse(request())
            ->setStatusCode($status);
    })->name('fallback');
});
