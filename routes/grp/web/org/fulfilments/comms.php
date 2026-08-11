<?php

use App\Actions\Comms\DispatchedEmail\UI\ShowDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\UI\ShowEmailBulkRun;
use App\Actions\Comms\OrgPostRoom\UI\IndexOrgPostRooms;
use App\Actions\Comms\OrgPostRoom\UI\ShowOrgPostRoom;
use App\Actions\Comms\Outbox\UI\EditOutbox;
use App\Actions\Comms\Outbox\UI\IndexOutboxes;
use App\Actions\Comms\Outbox\UI\ShowOutbox;
use App\Actions\Comms\Outbox\UI\ShowOutboxWorkshop;
use App\Actions\Comms\UI\ShowCommsDashboard;
use Illuminate\Support\Facades\Route;

Route::get('', [ShowCommsDashboard::class, 'inFulfilment'])->name('dashboard');
Route::get('outboxes', [IndexOutboxes::class, 'inFulfilment'])->name('outboxes');
Route::get('outboxes/{outbox}', [ShowOutbox::class, 'inFulfilment'])->name('outboxes.show');
Route::get('outboxes/{outbox}/edit', [EditOutbox::class, 'inFulfilment'])->name('outboxes.edit');

Route::get('outboxes/{outbox}/workshop', [ShowOutboxWorkshop::class, 'inFulfilment'])->name('outboxes.workshop');
Route::get('outboxes/{outbox}/dispatched-emails/{dispatchedEmail:id}', [ShowDispatchedEmail::class, 'inOutboxInFulfilment'])->name('outboxes.dispatched-email.show');
Route::get('outboxes/{outbox}/email-bulk-runs/{emailBulkRun}', [ShowEmailBulkRun::class, 'inOutbox'])->name('outboxes.show.email-bulk-runs.show');

Route::get('post-rooms', [IndexOrgPostRooms::class, 'inFulfilment'])->name('post-rooms.index');
Route::get('post-rooms/{orgPostRoom}', [ShowOrgPostRoom::class, 'inFulfilment'])->name('post-rooms.show');
