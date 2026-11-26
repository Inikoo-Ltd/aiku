<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jun 2026 19:25:55 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\Agent\Presence\TrackChatAgentPresence;
use App\Actions\Chat\Reports\StoreChatInboxTimings;
use App\Actions\Chat\PhoneCall\CancelChatPhoneCall;
use App\Actions\Chat\PhoneCall\EndChatPhoneCall;
use App\Actions\Chat\PhoneCall\GetActiveChatPhoneCall;
use App\Actions\Chat\PhoneCall\GetChatPhoneCallGuests;
use App\Actions\Chat\PhoneCall\Json\GetChatPhoneCallCustomers;
use App\Actions\Chat\PhoneCall\Json\GetChatPhoneCallsForInbox;
use App\Actions\Chat\PhoneCall\StartChatPhoneCall;
use App\Actions\Chat\PhoneCall\UI\ShowGroupChatPhoneCalls;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Chat\ChatSession\UI\RedirectToOrgChatInbox;
use App\Actions\Chat\ChatSession\UI\ShowGroupChatDashboard;
use App\Actions\Chat\UI\FlagChatAiDraft;
use App\Actions\Chat\UI\IndexChatAiAcceptance;
use App\Actions\Chat\UI\IndexChatAiCoverageChats;
use App\Actions\Chat\UI\IndexChatAikuDrafts;
use App\Actions\Chat\UI\IndexChatVerifiedFacts;
use App\Actions\Chat\UI\UpdateChatVerifiedFactStatus;
use App\Actions\Chat\UI\FlagChatAutomatedMessage;
use App\Actions\Chat\UI\IndexChatAiConfidenceMap;
use App\Actions\Chat\UI\ShowGroupChatAutomation;
use Illuminate\Support\Facades\Route;

Route::get('/reports', ShowGroupChatDashboard::class)->name('reports');
Route::get('/ai', ShowGroupChatAutomation::class)->name('ai.dashboard');
Route::get('/ai/coverage', IndexChatAiCoverageChats::class)->name('ai.coverage');
Route::get('/ai/acceptance', IndexChatAiAcceptance::class)->name('ai.acceptance');
Route::get('/ai/confidence', IndexChatAiConfidenceMap::class)->name('ai.confidence');
Route::get('/ai/sent', [ShowGroupChatAutomation::class, 'inSent'])->name('ai.sent');
Route::get('/ai/noise-checks', [ShowGroupChatAutomation::class, 'inNoiseChecks'])->name('ai.noise_checks');
Route::get('/ai/aiku-drafts', IndexChatAikuDrafts::class)->name('ai.aiku_drafts');
Route::get('/ai/verified-facts', IndexChatVerifiedFacts::class)->name('ai.verified_facts');
Route::post('/ai/verified-facts/{chatKnowledgeEntry}/status', UpdateChatVerifiedFactStatus::class)->name('ai.verified_facts.status');
Route::post('/ai/drafts/{chatAiDraft}/flag', FlagChatAiDraft::class)->name('ai.drafts.flag');
Route::post('/ai/sent/{channel}/{messageId}/flag', FlagChatAutomatedMessage::class)->where('channel', 'chat|whatsapp')->name('ai.sent.flag');
Route::get('/inbox', RedirectToOrgChatInbox::class)->name('inbox');
Route::post('/presence', TrackChatAgentPresence::class)->name('presence.track');
Route::post('/inbox-timings', StoreChatInboxTimings::class)->name('inbox_timings.store')->middleware('throttle:60,1');
Route::get('/languages', [GetLanguagesOptions::class, 'getLanguageJson'])->name('languages.index');

Route::prefix('phone-calls')->name('phone_calls.')->group(function () {
    Route::get('/', ShowGroupChatPhoneCalls::class)->name('index');
    Route::get('/active', GetActiveChatPhoneCall::class)->name('active');
    Route::get('/guests', GetChatPhoneCallGuests::class)->name('guests');
    Route::get('/customers/{shop:id}', GetChatPhoneCallCustomers::class)->name('customers');
    Route::get('/list', GetChatPhoneCallsForInbox::class)->name('list');
    Route::post('/start', StartChatPhoneCall::class)->name('start');
    Route::post('/end', EndChatPhoneCall::class)->name('end');
    Route::post('/cancel', CancelChatPhoneCall::class)->name('cancel');
});
