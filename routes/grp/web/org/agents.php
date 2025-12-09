<?php


use App\Actions\CRM\Agent\StoreAgent;
use Illuminate\Support\Facades\Route;
use App\Actions\CRM\Agent\DeleteAgent;
use App\Actions\CRM\Agent\UpdateAgent;
use App\Actions\CRM\Agent\UI\EditAgent;
use App\Actions\CRM\Agent\UI\ShowAgent;
use App\Actions\CRM\Agent\UI\CreateAgent;
use App\Actions\CRM\ChatSession\SendChatMessage;
use App\Actions\CRM\ChatSession\CloseChatSession;
use App\Actions\CRM\ChatSession\AssignChatToAgent;

Route::name('agents.')->prefix('agents')->group(function () {
    Route::get('/', ShowAgent::class)->name('show');
    Route::get('/create', CreateAgent::class)->name('create');
    Route::get('/{agentId}/edit', EditAgent::class)->name('edit');
    Route::post('/store', StoreAgent::class)->name('store');
    Route::patch('/update/{agent:id}', UpdateAgent::class)->name('update')->withoutScopedBindings();
    Route::delete('/delete/{agent:id}', DeleteAgent::class)->name('delete')->withoutScopedBindings();
    Route::post('/{ulid}/assign-to-self', [AssignChatToAgent::class, 'assignToSelf'])
        ->name('assign.self');
    Route::post('/messages/{chatSession:ulid}/send', SendChatMessage::class)->name('messages.send');
    Route::patch('/sessions/{chatSession:ulid}/close', CloseChatSession::class)->name('sessions.close');
});