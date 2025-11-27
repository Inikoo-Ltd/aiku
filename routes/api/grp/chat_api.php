<?php


use Illuminate\Support\Facades\Route;
use App\Actions\CRM\ChatSession\GetChatMessages;
use App\Actions\CRM\ChatSession\GetChatSessions;
use App\Actions\CRM\ChatSession\SendChatMessage;
use App\Actions\CRM\ChatSession\StoreChatSession;
use App\Actions\CRM\ChatSession\AssignChatToAgent;


Route::get('/sessions', GetChatSessions::class)->name('sessions.index');
Route::post('/sessions', StoreChatSession::class)->name('chats.sessions.store');
Route::post('/messages/{chatSession:ulid}/send', SendChatMessage::class)->name('chat.messages.send');
Route::get('/sessions/{chatSession:ulid}/messages', GetChatMessages::class)->name('chat.sessions.messages');

Route::post('/sessions/{chatSession:ulid}/assign', AssignChatToAgent::class)
    ->name('chat.sessions.assign');

Route::post('/sessions/{chatSession:ulid}/assign-to-self', [AssignChatToAgent::class, 'assignToSelf'])
    ->name('chat.sessions.assign.self');
