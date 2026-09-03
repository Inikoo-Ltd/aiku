<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Ticket\UI\CreateTicket;
use App\Actions\Helpers\Ticket\UI\IndexTickets;
use App\Actions\Helpers\Ticket\UI\ShowTicket;
use App\Actions\Helpers\Ticket\UI\ShowTicketsBoard;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexTickets::class)->name('index');
Route::get('/board', ShowTicketsBoard::class)->name('board');
Route::get('/create', CreateTicket::class)->name('create');
Route::get('/{ticket:reference}', ShowTicket::class)->name('show');
