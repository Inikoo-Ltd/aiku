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

Route::get('/', [ShowTicketsDashboard::class, 'inShop'])->name('index');
Route::get('/list', [IndexTickets::class, 'inShop'])->name('list');
Route::get('/qa-list', [IndexQaTickets::class, 'inShop'])->name('qa_list');
Route::get('/board', [ShowTicketsBoard::class, 'inShop'])->name('board');
Route::get('/reports', [ShowTicketsReports::class, 'inShop'])->name('reports');
Route::get('/create', [CreateTicket::class, 'inShop'])->name('create');
Route::get('/{ticket:reference}', [ShowTicket::class, 'inShop'])->name('show')->withoutScopedBindings();
