<?php


use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\DownloadChatAttachment;
use App\Actions\Chat\ChatSession\GetAgentChatNotifications;
use App\Actions\Chat\ChatSession\GetAgentUnreadMessagesSummary;
use App\Actions\Chat\ChatSession\GetChatActivity;
use App\Actions\Chat\ChatSession\GetChatAgentByUserId;
use App\Actions\Chat\ChatSession\GetChatAgents;
use App\Actions\Chat\ChatSession\GetChatAgentSpecializations;
use App\Actions\Chat\ChatSession\GetChatCustomerProfile;
use App\Actions\Chat\ChatSession\GetChatCustomerTimeline;
use App\Actions\Chat\ChatSession\GetChatMessages;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\GetChatStatus;
use App\Actions\Chat\ChatSession\HandleChatRead;
use App\Actions\Chat\ChatSession\HandleChatTyping;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\ShareChatSessionToSlack;
use App\Actions\Chat\ChatSession\StoreChatAgent;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreGuestProfile;
use App\Actions\Chat\ChatSession\StoreOfflineMessage;
use App\Actions\Chat\ChatSession\SyncChatSessionByEmail;
use App\Actions\Chat\ChatSession\TranslateSessionMessages;
use App\Actions\Chat\ChatSession\TranslateSingleMessage;
use App\Actions\Chat\ChatSession\UpdateChatAgent;
use App\Actions\Chat\ChatSession\UpdateChatSession;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');



Route::get('/sessions', GetChatSessions::class)->name('sessions.index');
Route::post('/sessions', StoreChatSession::class)->name('sessions.store');
Route::post('/offline-message', StoreOfflineMessage::class)->name('offline-message.store');
Route::post('/messages/{chatSession:ulid}/send', SendChatMessage::class)->name('messages.send');
Route::put('/sessions/{chatSession:ulid}/update', UpdateChatSession::class)
    ->name('sessions.update');
Route::put('/sessions/{chatSession:ulid}/close', [CloseChatSession::class, 'asApiController'])
    ->name('sessions.close');

Route::post('/sessions/{chatSession:ulid}/typing', HandleChatTyping::class)
    ->name('sessions.typing');

Route::get('/sessions/{chatSession:ulid}/activity', GetChatActivity::class)->name('sessions.activity');
Route::get('/sessions/{chatSession:ulid}/customer-profile', GetChatCustomerProfile::class)->name('sessions.customer_profile');
Route::get('/sessions/{chatSession:ulid}/customer-timeline', GetChatCustomerTimeline::class)->name('sessions.customer_timeline');
Route::get('/sessions/{chatSession:ulid}/messages', GetChatMessages::class)->name('sessions.messages');

Route::post('/sessions/{chatSession:ulid}/guest-profile', StoreGuestProfile::class)
    ->name('sessions.guest_profile');

Route::put('/sessions/{chatSession:ulid}/sync-by-email', SyncChatSessionByEmail::class)
    ->name('sessions.sync_by_email');

Route::post('/sessions/{chatSession:ulid}/share-to-slack', [ShareChatSessionToSlack::class, 'asController'])
    ->name('sessions.share_to_slack');

Route::get('agents', GetChatAgents::class)->name('agents.index');
Route::get('/agents/specializations', GetChatAgentSpecializations::class)->name('agent.specializations');
Route::get('/users/{id}/unread-messages', GetAgentUnreadMessagesSummary::class)->name('user.unread-messages');
Route::get('/users/{id}/agent-notifications', GetAgentChatNotifications::class)->name('agent.notifications');

Route::post('/agents/store', StoreChatAgent::class, 'agents.store')
    ->name('agents.store');

Route::put('/agents/{id}/update', UpdateChatAgent::class)->name('agents.update');

Route::get('/agents/{id}', GetChatAgentByUserId::class)->name('agent.show');

Route::post('/typing', HandleChatTyping::class, 'typing')
    ->name('typing');

Route::post('/read', HandleChatRead::class, 'read')
    ->name('read');
Route::get('/status', GetChatStatus::class)->name('status');

Route::get('chat/attachment/{ulid}', DownloadChatAttachment::class)
    ->name('chat.attachment.download');

Route::get('/languages', [GetLanguagesOptions::class, 'getLanguageJson'])->name('languages.index');
Route::post('/messages/{chatMessage}/translate', TranslateSingleMessage::class)->name('messages.translate');
Route::post('/sessions/{chatSession:ulid}/translate', TranslateSessionMessages::class)->name('sessions.translate');
