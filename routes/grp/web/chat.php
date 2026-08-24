<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jun 2026 19:25:55 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\Agent\Presence\TrackChatAgentPresence;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Chat\Agent\UI\ShowGroupAgents;
use App\Actions\Chat\ChatSession\UI\RedirectToOrgChatInbox;
use App\Actions\Chat\ChatSession\UI\ShowGroupChatDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowGroupChatDashboard::class)->name('dashboard');
Route::get('/agents', ShowGroupAgents::class)->name('agents.show');
Route::get('/inbox', RedirectToOrgChatInbox::class)->name('inbox');
Route::post('/presence', TrackChatAgentPresence::class)->name('presence.track');
Route::get('/languages', [GetLanguagesOptions::class, 'getLanguageJson'])->name('languages.index');

Route::prefix('staff')->name('staff.')->middleware('throttle:240,1')->group(function () {
    Route::get('/', \App\Actions\Chat\Staff\UI\ShowStaffMessaging::class)->name('index');
    Route::get('/c/{staffConversation}', [\App\Actions\Chat\Staff\UI\ShowStaffMessaging::class, 'inConversation'])->name('show');
    Route::get('/conversations', \App\Actions\Chat\Staff\Json\GetStaffConversations::class)->name('conversations.index');
    Route::post('/conversations', \App\Actions\Chat\Staff\StoreStaffConversation::class)->name('conversations.store');
    Route::get('/conversations/{staffConversation}/messages', \App\Actions\Chat\Staff\Json\GetStaffMessages::class)->name('conversations.messages.index');
    Route::post('/conversations/{staffConversation}/messages', \App\Actions\Chat\Staff\SendStaffMessage::class)->name('conversations.messages.store');
    Route::post('/conversations/{staffConversation}/archive', \App\Actions\Chat\Staff\ArchiveStaffConversation::class)->name('conversations.archive');
    Route::post('/conversations/{staffConversation}/read', \App\Actions\Chat\Staff\MarkStaffConversationRead::class)->name('conversations.read');
    Route::post('/messages/{staffMessage}/reactions', \App\Actions\Chat\Staff\ToggleStaffMessageReaction::class)->name('messages.reactions.toggle');
    Route::post('/context', \App\Actions\Chat\Staff\OpenStaffContextConversation::class)->name('context.open');
    Route::post('/team/toggle', \App\Actions\Chat\Staff\ToggleStaffTeamMember::class)->name('team.toggle');
    Route::get('/coworkers', \App\Actions\Chat\Staff\Json\GetStaffCoworkers::class)->name('coworkers.index');
    Route::get('/gifs', \App\Actions\Chat\Staff\Json\SearchGifs::class)->name('gifs.search');
});
