<?php


use Illuminate\Support\Facades\Route;
use App\Actions\CRM\ChatSession\GetChatAgents;
use App\Actions\CRM\ChatSession\HandleChatRead;
use App\Actions\CRM\ChatSession\StoreChatAgent;
use App\Actions\CRM\ChatSession\GetChatActivity;
use App\Actions\CRM\ChatSession\GetChatMessages;
use App\Actions\CRM\ChatSession\GetChatSessions;
use App\Actions\CRM\ChatSession\SendChatMessage;
use App\Actions\CRM\ChatSession\UpdateChatAgent;
use App\Actions\CRM\ChatSession\HandleChatTyping;
use App\Actions\CRM\ChatSession\StoreChatSession;
use App\Actions\CRM\ChatSession\StoreGuestProfile;
use App\Actions\CRM\ChatSession\UpdateChatSession;
use App\Actions\CRM\ChatSession\DownloadChatAttachment;
use App\Actions\CRM\ChatSession\SyncChatSessionByEmail;
use App\Actions\CRM\ChatSession\TranslateSingleMessage;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\CRM\ChatSession\TranslateSessionMessages;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::get('/sessions', GetChatSessions::class)->name('sessions.index');
Route::post('/sessions', StoreChatSession::class)->name('sessions.store');
Route::post('/messages/{chatSession:ulid}/send', SendChatMessage::class)->name('messages.send');
Route::put('/sessions/{chatSession:ulid}/update', UpdateChatSession::class)
    ->name('sessions.update');

Route::post('/sessions/{chatSession:ulid}/typing', HandleChatTyping::class)
    ->name('sessions.typing');

Route::get('/sessions/{chatSession:ulid}/activity', GetChatActivity::class)->name('sessions.activity');
Route::get('/sessions/{chatSession:ulid}/messages', GetChatMessages::class)->name('sessions.messages');

Route::post('/sessions/{chatSession:ulid}/guest-profile', StoreGuestProfile::class)
    ->name('sessions.guest_profile');

Route::put('/sessions/{chatSession:ulid}/sync-by-email', SyncChatSessionByEmail::class)
    ->name('sessions.sync_by_email');

Route::get('agents', GetChatAgents::class)->name('agents.index');

Route::post('/agents/store', StoreChatAgent::class, 'agents.store')
    ->name('agents.store');

Route::put('/agents/{chatAgent:id}/update', UpdateChatAgent::class, 'agents.update')
    ->name('agents.update');

Route::post('/typing', HandleChatTyping::class, 'typing')
    ->name('typing');

Route::post('/read', HandleChatRead::class, 'read')
    ->name('read');

Route::get('chat/attachment/{ulid}', DownloadChatAttachment::class)
    ->name('chat.attachment.download');

Route::get('/languages', [GetLanguagesOptions::class, 'getLanguageJson'])->name('languages.index');
Route::post('/messages/{chatMessage}/translate', TranslateSingleMessage::class)->name('messages.translate');
Route::post('/sessions/{chatSession:ulid}/translate', TranslateSessionMessages::class)->name('sessions.translate');
