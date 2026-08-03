<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 24 Jul 2025 15:58:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel;
use App\Actions\Dropshipping\UI\IndexPlatforms;
use App\Actions\Dropshipping\UI\ShowPlatform;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexPlatforms::class)->name('index');
Route::get('{platform}', ShowPlatform::class)->name('show');
Route::get('{platform}/customer-sales-channels/{customerSalesChannel}', [ShowCustomerSalesChannel::class, 'inPlatform'])->name('show.customer_sales_channels.show');
