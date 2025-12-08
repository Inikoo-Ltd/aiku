<?php


use App\Events\BroadcastRealtimeChat;
use Illuminate\Support\Facades\Route;
use App\Models\CRM\Livechat\ChatMessage;
use App\Actions\CRM\ChatSession\StoreChatAgent;
use App\Actions\CRM\ChatSession\GetChatMessages;
use App\Actions\CRM\ChatSession\GetChatSessions;
use App\Actions\CRM\ChatSession\SendChatMessage;
use App\Actions\CRM\ChatSession\UpdateChatAgent;
use App\Actions\CRM\ChatSession\CloseChatSession;
use App\Actions\CRM\ChatSession\StoreChatSession;
use App\Actions\CRM\ChatSession\AssignChatToAgent;
use App\Actions\CRM\ChatSession\UpdateChatSession;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::get('/sessions', GetChatSessions::class)->name('sessions.index');
Route::post('/sessions', StoreChatSession::class)->name('sessions.store');
Route::post('/messages/{chatSession:ulid}/send', SendChatMessage::class)->name('messages.send');
Route::put('/sessions/{chatSession:ulid}/update', UpdateChatSession::class)
    ->name('sessions.update');

Route::put('/sessions/{chatSession:ulid}/close', CloseChatSession::class)->name('sessions.close');

Route::get('/sessions/{chatSession:ulid}/messages', GetChatMessages::class)->name('sessions.messages');

Route::post('/sessions/{chatSession:ulid}/assign', AssignChatToAgent::class)
    ->name('sessions.assign');

Route::middleware('web')->post('/sessions/{chatSession:ulid}/assign-to-self', [AssignChatToAgent::class, 'assignToSelf'])
    ->name('sessions.assign.self');

Route::post('/agents/store', StoreChatAgent::class, 'agents.store')
    ->name('agents.store');

Route::put('/agents/{chatAgent:id}/update', UpdateChatAgent::class, 'agents.update')
    ->name('agents.update');

Route::get('/test-broadcast', function () {
    $chatMessage = ChatMessage::first();
    BroadcastRealtimeChat::dispatch($chatMessage);
});
