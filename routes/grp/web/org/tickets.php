<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

use App\Actions\Helpers\Ticket\UI\CreateTicket;
use App\Actions\Helpers\Ticket\UI\IndexQaTickets;
use App\Actions\Helpers\Ticket\UI\IndexTickets;
use App\Actions\Helpers\Ticket\UI\ShowTicket;
use App\Actions\Helpers\Ticket\UI\ShowTicketsBoard;
use App\Actions\Helpers\Ticket\UI\ShowTicketsDashboard;
use App\Actions\Helpers\Ticket\UI\ShowTicketsReports;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShowTicketsDashboard::class, 'inOrganisation'])->name('index');
Route::get('/list', [IndexTickets::class, 'inOrganisation'])->name('list');
Route::get('/qa-list', [IndexQaTickets::class, 'inOrganisation'])->name('qa_list');
Route::get('/board', [ShowTicketsBoard::class, 'inOrganisation'])->name('board');
Route::get('/reports', [ShowTicketsReports::class, 'inOrganisation'])->name('reports');
Route::get('/create', [CreateTicket::class, 'inOrganisation'])->name('create');
Route::get('/{ticket:reference}', [ShowTicket::class, 'inOrganisation'])->name('show')->withoutScopedBindings();
