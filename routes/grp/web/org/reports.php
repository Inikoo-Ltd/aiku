<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jan 2024 03:24:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Accounting\Intrastat\ExportIntrastatXml;
use App\Actions\Accounting\Intrastat\UI\IndexIntrastatReport;
use App\Actions\Reports\PostRoomRoutes;
use App\Actions\Reports\ShowOrganisationSalesReport;
use App\Actions\UI\Reports\IndexReports;
use App\Stubs\UIDummies\IndexDummies;
use Illuminate\Support\Facades\Route;

//Route::get('/', IndexReports::class)->name('index');
//Temporary
Route::get('/', function () {
    return redirect()->route('grp.org.reports.intrastat', Route::current()->parameters());
})->name('index');


Route::get('/sales', ShowOrganisationSalesReport::class)->name('sales');

Route::get('/intrastat', IndexIntrastatReport::class)->name('intrastat');
Route::get('/intrastat/export', ExportIntrastatXml::class)->name('intrastat.export');


Route::name("sent_emails.")->prefix('sent-emails')
    ->group(function () {
        $postRoomRoutes = new PostRoomRoutes();
        $postRoomRoutes('organisation');
        //  Route::get('shops', IndexDummies::class)->name('shops.index');
        Route::name("shops.")->prefix('shops')
            ->group(function () {
                Route::get('', IndexDummies::class)->name('index');

                Route::get('{shop}', IndexDummies::class)->name('shop');

                Route::name("show.")->prefix('{shop}')
                    ->group(
                        function () {
                            $postRoomRoutes = new PostRoomRoutes();
                            $postRoomRoutes('shop');
                        }
                    );
            });
    });
