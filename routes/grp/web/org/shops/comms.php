<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:06:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Comms\DispatchedEmail\UI\ShowDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\UI\ShowEmailBulkRun;
use App\Actions\Comms\OrgPostRoom\UI\IndexOrgPostRooms;
use App\Actions\Comms\OrgPostRoom\UI\ShowOrgPostRoom;
use App\Actions\Comms\Outbox\UI\IndexOutboxes;
use App\Actions\Comms\Outbox\UI\ShowOutbox;
use App\Actions\Comms\Outbox\UI\ShowOutboxWorkshop;
use App\Actions\Comms\UI\ShowCommsDashboard;
use Illuminate\Support\Facades\Route;
use App\Actions\Comms\Outbox\UI\EditOutboxInShop;

Route::get('', ShowCommsDashboard::class)->name('dashboard');
Route::get('outboxes', [IndexOutboxes::class, 'inShop'])->name('outboxes.index');
Route::get('newsletter-outboxes', [IndexOutboxes::class, 'inShopNewsletter'])->name('newsletter_outboxes.index');
Route::get('marketing-outboxes', [IndexOutboxes::class, 'inShopMarketing'])->name('marketing_outboxes.index');
Route::get('marketing-notification-outboxes', [IndexOutboxes::class, 'inShopMarketingNotification'])->name('marketing_notification_outboxes.index');
Route::get('customer-notification-outboxes', [IndexOutboxes::class, 'inShopCustomerNotification'])->name('customer_notification_outboxes.index');
Route::get('cold-email-outboxes', [IndexOutboxes::class, 'inShopColdEmail'])->name('cold_email_outboxes.index');
Route::get('user-notification-outboxes', [IndexOutboxes::class, 'inShopUserNotification'])->name('user_notification_outboxes.index');
Route::get('push-outboxes', [IndexOutboxes::class, 'inShopPush'])->name('push_outboxes.index');
Route::get('test-outboxes', [IndexOutboxes::class, 'inShopTest'])->name('test_outboxes.index');
Route::get('outboxes/{outbox}', [ShowOutbox::class, 'inShop'])->name('outboxes.show');
Route::get('outboxes/{outbox}/edit', EditOutboxInShop::class)->name('outboxes.edit');
Route::get('outboxes/{outbox}/workshop', ShowOutboxWorkshop::class)->name('outboxes.workshop');
Route::get('outboxes/{outbox}/email-bulk-runs/{emailBulkRun}', [ShowEmailBulkRun::class, 'inOutbox'])->name('outboxes.show.email-bulk-runs.show');
Route::get('outboxes/{outbox}/dispatched-emails/{dispatchedEmail:id}', [ShowDispatchedEmail::class, 'inOutboxInShop'])->name('outboxes.dispatched-email.show');
Route::get('post-rooms', [IndexOrgPostRooms::class, 'inShop'])->name('post-rooms.index');
Route::get('post-rooms/{orgPostRoom}', [ShowOrgPostRoom::class, 'inShop'])->name('post-rooms.show');
