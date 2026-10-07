<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 15:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Helpers\TicketProject\UI\IndexTicketProjects;
use App\Actions\Helpers\TicketProject\UI\ShowTicketProject;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexTicketProjects::class)->name('index');
Route::get('/{ticketProject:slug}', ShowTicketProject::class)->name('show');
