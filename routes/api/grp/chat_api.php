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
use App\Actions\Chat\ChatSession\ToggleChatMessageReaction;
use App\Actions\Chat\ChatSession\TranslateSessionMessages;
use App\Actions\Chat\ChatSession\TranslateSingleMessage;
use App\Actions\Chat\ChatSession\UpdateChatAgent;
use App\Actions\Chat\ChatSession\UpdateChatSession;
use App\Actions\Chat\GetCrossChannelSessions;
use App\Actions\Chat\GetCustomerChatHistory;
use App\Actions\Chat\Whatsapp\SendWhatsappReaction;
use App\Actions\Chat\Whatsapp\TranslateSingleMetaChatMessage;
use App\Actions\Chat\MetaChatSession\GetMetaChatMessages;
use App\Actions\Chat\MetaChatSession\MarkMetaChatMessagesAsRead;
use App\Actions\Chat\MetaChatSession\GetMetaMessageTemplates;
use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Actions\Chat\MetaChatSession\GetMetaChatActivity;
use App\Actions\Chat\MetaChatSession\GetMetaChatCustomerProfile;
use App\Actions\Chat\MetaChatSession\GetMetaChatCustomerTimeline;
use App\Actions\Chat\MetaChatSession\SyncMetaChatSessionByPhone;
use App\Actions\Chat\MetaChatSession\UpdateMetaChatSession;
use App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');


Route::post('/sessions', StoreChatSession::class)->name('sessions.store');
Route::post('/offline-message', StoreOfflineMessage::class)->name('offline-message.store');
Route::post('/messages/{chatSession:ulid}/send', SendChatMessage::class)->name('messages.send');
Route::put('/sessions/{chatSession:ulid}/update', UpdateChatSession::class)->name('sessions.update');
Route::put('/sessions/{chatSession:ulid}/close', [CloseChatSession::class, 'asApiController'])->name('sessions.close');
Route::post('/sessions/{chatSession:ulid}/typing', HandleChatTyping::class)->name('sessions.typing');
Route::get('/sessions/{chatSession:ulid}/messages', GetChatMessages::class)->name('sessions.messages')->withTrashed();
Route::post('/sessions/{chatSession:ulid}/guest-profile', StoreGuestProfile::class)->name('sessions.guest_profile');
Route::post('/typing', HandleChatTyping::class)->name('typing');
Route::post('/read', HandleChatRead::class)->name('read');
Route::get('/status', GetChatStatus::class)->name('status');
Route::get('chat/attachment/{ulid}', DownloadChatAttachment::class)->name('chat.attachment.download');
Route::get('/languages', [GetLanguagesOptions::class, 'getLanguageJson'])->name('languages.index');
Route::post('/messages/{chatMessage}/translate', TranslateSingleMessage::class)->name('messages.translate');
Route::post('/messages/{chatMessage}/reactions', [ToggleChatMessageReaction::class, 'asController'])->name('messages.reactions.toggle');
Route::post('/sessions/{chatSession:ulid}/translate', TranslateSessionMessages::class)->name('sessions.translate');


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sessions', GetChatSessions::class)->name('sessions.index');
    Route::get('/all/sessions', GetCrossChannelSessions::class)->name('all.sessions.index');
    Route::get('/customer-chat-history', GetCustomerChatHistory::class)->name('customer.chat_history');
    Route::get('/meta/sessions', GetMetaChatSessions::class)->name('meta.sessions.index');
    Route::post('/meta/sessions', StoreMetaChatSession::class)->name('meta.sessions.store');
    Route::get('/meta/sessions/{metaChatSession:ulid}/messages', GetMetaChatMessages::class)->name('meta.sessions.messages')->withTrashed();
    Route::post('/meta/sessions/{metaChatSession:ulid}/read', MarkMetaChatMessagesAsRead::class)->name('meta.sessions.read');
    Route::put('/meta/sessions/{metaChatSession:ulid}/update', UpdateMetaChatSession::class)->name('meta.sessions.update')->withTrashed();
    Route::get('/meta/templates', GetMetaMessageTemplates::class)->name('meta.templates.index');
    Route::post('/meta/messages/{metaChatMessage}/reactions', SendWhatsappReaction::class)->name('meta.messages.reactions');
    Route::post('/meta/messages/{metaChatMessage}/translate', TranslateSingleMetaChatMessage::class)->name('meta.messages.translate');
    Route::get('/sessions/{chatSession:ulid}/activity', GetChatActivity::class)->name('sessions.activity')->withTrashed();
    Route::get('/sessions/{chatSession:ulid}/customer-profile', GetChatCustomerProfile::class)->name('sessions.customer_profile')->withTrashed();
    Route::get('/sessions/{chatSession:ulid}/customer-timeline', GetChatCustomerTimeline::class)->name('sessions.customer_timeline')->withTrashed();
    Route::post('/sessions/{chatSession:ulid}/share-to-slack', [ShareChatSessionToSlack::class, 'asController'])->name('sessions.share_to_slack');
    Route::put('/sessions/{chatSession:ulid}/sync-by-email', SyncChatSessionByEmail::class)->name('sessions.sync_by_email');
    Route::put('/meta/sessions/{metaChatSession:ulid}/sync-by-phone', SyncMetaChatSessionByPhone::class)->name('meta.sessions.sync_by_phone');
    Route::get('/meta/sessions/{metaChatSession:ulid}/customer-profile', GetMetaChatCustomerProfile::class)->name('meta.sessions.customer_profile')->withTrashed();
    Route::get('/meta/sessions/{metaChatSession:ulid}/customer-timeline', GetMetaChatCustomerTimeline::class)->name('meta.sessions.customer_timeline')->withTrashed();
    Route::get('/meta/sessions/{metaChatSession:ulid}/activity', GetMetaChatActivity::class)->name('meta.sessions.activity')->withTrashed();

    Route::get('/agents', GetChatAgents::class)->name('agents.index');
    Route::get('/agents/specializations', GetChatAgentSpecializations::class)->name('agent.specializations');
    Route::post('/agents/store', StoreChatAgent::class)->name('agents.store');
    Route::put('/agents/{id}/update', UpdateChatAgent::class)->name('agents.update');
    Route::get('/agents/{id}', GetChatAgentByUserId::class)->name('agent.show');

    Route::get('/users/{id}/unread-messages', GetAgentUnreadMessagesSummary::class)->name('user.unread-messages');
    Route::get('/users/{id}/agent-notifications', GetAgentChatNotifications::class)->name('agent.notifications');
});
