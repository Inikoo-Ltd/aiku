<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Support\Facades\Route;

Route::name('staff.')->middleware('throttle:240,1')->group(function () {
    Route::get('/', \App\Actions\Chat\Staff\UI\ShowStaffMessaging::class)->name('index');
    Route::get('/reports', \App\Actions\Chat\Staff\UI\ShowStaffChatReports::class)->name('reports');
    Route::get('/c/{staffConversation}', [\App\Actions\Chat\Staff\UI\ShowStaffMessaging::class, 'inConversation'])->name('show');
    Route::get('/groups', \App\Actions\Chat\Staff\UI\ShowStaffMessaging::class)->name('groups.index');
    Route::get('/groups/c/{staffConversation}', [\App\Actions\Chat\Staff\UI\ShowStaffMessaging::class, 'inConversation'])->name('groups.show');
    Route::get('/groups/slack/{slackChannel}', [\App\Actions\Chat\Staff\UI\ShowStaffSlackChannels::class, 'inPrivateSlackChannel'])->name('groups.slack.show');
    Route::get('/d/slack/{slackChannel}', [\App\Actions\Chat\Staff\UI\ShowStaffSlackChannels::class, 'inDirectSlackChannel'])->name('direct.slack.show');
    Route::post('/groups/slack', \App\Actions\Chat\Staff\Slack\Write\StoreSlackPrivateChannel::class)->middleware('throttle:10,1,slack-verbration')->name('groups.slack.store');
    Route::get('/home', \App\Actions\Chat\Staff\Home\GetStaffHome::class)->name('home');
    Route::get('/conversations', \App\Actions\Chat\Staff\Json\GetStaffConversations::class)->name('conversations.index');
    Route::get('/cache-key', \App\Actions\Chat\Staff\Json\GetStaffChatCacheKey::class)->name('cache_key');
    Route::post('/conversations', \App\Actions\Chat\Staff\StoreStaffConversation::class)->name('conversations.store');
    Route::get('/conversations/{staffConversation}/messages', \App\Actions\Chat\Staff\Json\GetStaffMessages::class)->name('conversations.messages.index');
    Route::get('/conversations/{staffConversation}/files-links', \App\Actions\Chat\Staff\Json\GetStaffConversationFilesAndLinks::class)->name('conversations.files-links');
    Route::post('/conversations/{staffConversation}/messages', \App\Actions\Chat\Staff\SendStaffMessage::class)->name('conversations.messages.store');
    Route::post('/conversations/{staffConversation}/archive', \App\Actions\Chat\Staff\ArchiveStaffConversation::class)->name('conversations.archive');
    Route::post('/conversations/{staffConversation}/read', \App\Actions\Chat\Staff\MarkStaffConversationRead::class)->name('conversations.read');
    Route::post('/conversations/{staffConversation}/typing', \App\Actions\Chat\Staff\SendStaffTyping::class)->middleware('throttle:30,1,staff-typing')->name('conversations.typing');
    Route::post('/delivered', \App\Actions\Chat\Staff\MarkStaffConversationsDelivered::class)->middleware('throttle:60,1,staff-delivered')->name('conversations.delivered');
    Route::post('/conversations/{staffConversation}/watch', \App\Actions\Chat\Staff\ToggleStaffConversationWatch::class)->name('conversations.watch');
    Route::post('/messages/{staffMessage}/reactions', \App\Actions\Chat\Staff\ToggleStaffMessageReaction::class)->name('messages.reactions.toggle');
    Route::post('/context', \App\Actions\Chat\Staff\OpenStaffContextConversation::class)->name('context.open');
    Route::post('/team/toggle', \App\Actions\Chat\Staff\ToggleStaffTeamMember::class)->name('team.toggle');
    Route::get('/coworkers', \App\Actions\Chat\Staff\Json\GetStaffCoworkers::class)->name('coworkers.index');
    Route::get('/gifs', \App\Actions\Chat\Staff\Json\SearchGifs::class)->name('gifs.search');
    Route::post('/conversations/{staffConversation}/calls', \App\Actions\Chat\Staff\Call\StartStaffCall::class)->middleware('throttle:6,1,staff-call-start')->name('conversations.calls.store');
    Route::get('/calls/live', \App\Actions\Chat\Staff\Call\GetLiveStaffCalls::class)->name('calls.live');
    Route::post('/calls/{staffCall}/join', \App\Actions\Chat\Staff\Call\JoinStaffCall::class)->name('calls.join');
    Route::post('/calls/{staffCall}/decline', \App\Actions\Chat\Staff\Call\DeclineStaffCall::class)->name('calls.decline');
    Route::post('/calls/{staffCall}/leave', \App\Actions\Chat\Staff\Call\LeaveStaffCall::class)->name('calls.leave');
    Route::post('/calls/{staffCall}/ping', \App\Actions\Chat\Staff\Call\PingStaffCall::class)->name('calls.ping');
    Route::get('/slack', \App\Actions\Chat\Staff\UI\ShowStaffSlackChannels::class)->name('slack.index');
    Route::get('/slack/{slackChannel}', [\App\Actions\Chat\Staff\UI\ShowStaffSlackChannels::class, 'inSlackChannel'])->name('slack.show');
    Route::get('/slack/{slackChannel}/thread', \App\Actions\Chat\Staff\Json\GetSlackThread::class)->name('slack.thread');
    Route::get('/slack/{slackChannel}/versions', \App\Actions\Chat\Staff\Json\GetSlackMessageVersions::class)->name('slack.versions');
    Route::get('/slack/{slackChannel}/pins', \App\Actions\Chat\Staff\Json\GetSlackChannelPins::class)->name('slack.pins');
    Route::get('/slack/{slackChannel}/files-links', \App\Actions\Chat\Staff\Json\GetSlackChannelFilesAndLinks::class)->name('slack.files-links');
    Route::post('/slack/{slackChannel}/read', \App\Actions\Chat\Staff\Slack\MarkSlackChannelRead::class)->name('slack.read');
    Route::post('/slack-read-all', [\App\Actions\Chat\Staff\Slack\MarkSlackChannelRead::class, 'inAllChannels'])->name('slack.read-all');
    Route::middleware('throttle:10,1,slack-connect')->group(function () {
        Route::get('/slack-connect', \App\Actions\Chat\Staff\Slack\Write\StartSlackConnection::class)->name('slack.connect.start');
        Route::get('/slack-connect/callback', \App\Actions\Chat\Staff\Slack\Write\StoreSlackConnection::class)->name('slack.connect.callback');
        Route::delete('/slack-connect', \App\Actions\Chat\Staff\Slack\Write\DisconnectSlackAccount::class)->name('slack.connect.delete');
        Route::post('/slack-connect/private', \App\Actions\Chat\Staff\Slack\Write\StartSlackPrivateConnection::class)->name('slack.connect.private.start');
        Route::delete('/slack-connect/private', \App\Actions\Chat\Staff\Slack\Write\WithdrawSlackPrivateConsent::class)->name('slack.connect.private.delete');
    });
    Route::post('/slack/{slackChannel}/messages', \App\Actions\Chat\Staff\Slack\Write\SendSlackMessage::class)->middleware('throttle:20,1,slack-send')->name('slack.messages.store');
    Route::post('/slack/{slackChannel}/files', \App\Actions\Chat\Staff\Slack\Write\UploadSlackFile::class)->middleware('throttle:60,1,slack-upload')->name('slack.files.upload');
    Route::post('/slack/{slackChannel}/files/send', \App\Actions\Chat\Staff\Slack\Write\SendSlackFiles::class)->middleware('throttle:20,1,slack-send')->name('slack.files.send');
    Route::post('/slack/{slackChannel}/reactions', \App\Actions\Chat\Staff\Slack\Write\ToggleSlackMessageReaction::class)->middleware('throttle:40,1,slack-react')->name('slack.reactions.toggle');
    Route::post('/slack/{slackChannel}/pin', \App\Actions\Chat\Staff\Slack\Write\ToggleSlackMessagePin::class)->middleware('throttle:30,1,slack-pin')->name('slack.pin.toggle');
    Route::post('/slack/{slackChannel}/bookmarks', \App\Actions\Chat\Staff\Slack\Write\AddSlackChannelBookmark::class)->middleware('throttle:20,1,slack-bookmark')->name('slack.bookmarks.store');
    Route::patch('/slack/{slackChannel}/bookmarks/{bookmark}', \App\Actions\Chat\Staff\Slack\Write\EditSlackChannelBookmark::class)->middleware('throttle:20,1,slack-bookmark')->scopeBindings()->name('slack.bookmarks.update');
    Route::delete('/slack/{slackChannel}/bookmarks/{bookmark}', \App\Actions\Chat\Staff\Slack\Write\RemoveSlackChannelBookmark::class)->middleware('throttle:20,1,slack-bookmark')->scopeBindings()->name('slack.bookmarks.delete');
    Route::get('/slack/{slackChannel}/messages/own-text', \App\Actions\Chat\Staff\Slack\Write\GetOwnSlackMessageText::class)->middleware('throttle:60,1,slack-edit-text')->name('slack.messages.own-text');
    Route::patch('/slack/{slackChannel}/messages', \App\Actions\Chat\Staff\Slack\Write\EditSlackMessage::class)->middleware('throttle:20,1,slack-edit')->name('slack.messages.update');
    Route::delete('/slack/{slackChannel}/messages', \App\Actions\Chat\Staff\Slack\Write\DeleteSlackMessage::class)->middleware('throttle:20,1,slack-edit')->name('slack.messages.delete');
});

Route::name('staff.')->middleware('throttle:120,1,slack-search')->group(function () {
    Route::get('/slack-search', \App\Actions\Chat\Staff\Json\SearchSlackMessages::class)->name('slack.search');
    Route::get('/slack-search/people', [\App\Actions\Chat\Staff\Json\SearchSlackMessages::class, 'inPeople'])->name('slack.search.people');
});

Route::get('slack/messages/{slackMessage}/files/{slackFile}', \App\Actions\Chat\Staff\UI\ShowSlackMessageFile::class)
    ->whereNumber('slackMessage')
    ->where('slackFile', \App\Actions\Chat\Staff\Slack\GetSlackMessageFiles::ID_PATTERN)
    ->middleware('throttle:900,1,slack-files')
    ->name('staff.slack.files.show');
