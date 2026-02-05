<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jan 2024 03:24:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Intrastat\ExportIntrastatXml;
use App\Actions\Accounting\Intrastat\ExportIntrastatXmlSlovakia;
use App\Actions\Accounting\Intrastat\UI\IndexIntrastatExportReport;
use App\Actions\Accounting\Intrastat\UI\IndexIntrastatImportReport;
use App\Actions\Accounting\MontanaInvoices\ExportMontanaInvoices;
use App\Actions\Accounting\MontanaInvoices\UI\IndexMontanaInvoicesReport;
use App\Actions\Accounting\SageInvoices\ExportSageInvoices;
use App\Actions\Accounting\SageInvoices\UI\IndexSageInvoicesReport;
use App\Actions\Dispatching\Reports\IndexPackerPerformanceReport;
use App\Actions\Dispatching\Reports\IndexPickerPerformanceReport;
use App\Actions\Reports\PostRoomRoutes;
use App\Actions\Reports\ShowOrganisationSalesReport;
use App\Actions\UI\Reports\IndexReports;
use App\Stubs\UIDummies\IndexDummies;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexReports::class)->name('index');

Route::get('/picker-performance', IndexPickerPerformanceReport::class)->name('picker-performance');
Route::get('/packer-performance', IndexPackerPerformanceReport::class)->name('packer-performance');

Route::get('/sales', ShowOrganisationSalesReport::class)->name('sales');

Route::get('/intrastat/exports', IndexIntrastatExportReport::class)->name('intrastat.exports');
Route::get('/intrastat/exports/export-xml', ExportIntrastatXml::class)->name('intrastat.exports.export');
Route::get('/intrastat/exports/export-slovakia', ExportIntrastatXmlSlovakia::class)->name('intrastat.exports.export-slovakia');

Route::get('/intrastat/imports', IndexIntrastatImportReport::class)->name('intrastat.imports');

Route::get('/sage-invoices', IndexSageInvoicesReport::class)->name('sage-invoices');
Route::get('/sage-invoices/export', ExportSageInvoices::class)->name('sage-invoices.export');

Route::get('/montana-invoices', IndexMontanaInvoicesReport::class)->name('montana-invoices');
Route::get('/montana-invoices/export', ExportMontanaInvoices::class)->name('montana-invoices.export');

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
