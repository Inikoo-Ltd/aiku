<?php


use Illuminate\Support\Facades\Route;
use App\Actions\CRM\ChatSession\GetChatMessages;
use App\Actions\CRM\ChatSession\GetChatSessions;
use App\Actions\CRM\ChatSession\SendChatMessage;
use App\Actions\CRM\ChatSession\StoreChatSession;
use App\Actions\CRM\ChatSession\AssignChatToAgent;
use App\Actions\CRM\ChatSession\UpdateChatSession;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::get('/sessions', GetChatSessions::class)->name('sessions.index');
Route::post('/sessions', StoreChatSession::class)->name('sessions.store');
Route::post('/messages/{chatSession:ulid}/send', SendChatMessage::class)->name('messages.send');
Route::get('/sessions/{chatSession:ulid}/messages', GetChatMessages::class)->name('sessions.messages');

Route::post('/sessions/{chatSession:ulid}/assign', AssignChatToAgent::class)
    ->name('sessions.assign');

Route::post('/sessions/{chatSession:ulid}/assign-to-self', [AssignChatToAgent::class, 'assignToSelf'])
    ->name('sessions.assign.self');

Route::put('/sessions/{chatSession:ulid}/update', UpdateChatSession::class)
    ->name('sessions.update');
