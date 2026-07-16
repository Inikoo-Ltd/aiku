<?php


use App\Actions\Chat\Agent\StoreAgent;
use App\Actions\Chat\ChatSession\DeleteChatAgent;
use App\Actions\Chat\Agent\UI\CreateAgent;
use App\Actions\Chat\Agent\UI\EditAgent;
use App\Actions\Chat\Agent\UI\ShowAgent;
use App\Actions\Chat\Agent\UpdateAgent;
use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\ForceDeleteChatAgent;
use App\Actions\Chat\ChatSession\ReopenChatSession;
use App\Actions\Chat\ChatSession\RestoreChatAgent;
use App\Actions\Chat\ChatSession\SendChatMessage;
use Illuminate\Support\Facades\Route;

Route::name('agents.')->prefix('agents')->group(function () {
    Route::get('/', ShowAgent::class)->name('show');
    Route::get('/create', CreateAgent::class)->name('create');
    Route::get('/{agentId}/edit', EditAgent::class)->name('edit');
    Route::post('/store', StoreAgent::class)->name('store');
    Route::patch('/update/{agent:id}', UpdateAgent::class)->name('update')->withoutScopedBindings();
    Route::delete('/delete/{agent:id}', DeleteChatAgent::class)->name('delete')->withoutScopedBindings();
    Route::patch('/restore/{agent:id}', RestoreChatAgent::class)->name('restore')->withoutScopedBindings();
    Route::delete('/force-delete/{agent:id}', ForceDeleteChatAgent::class)->name('force_delete')->withoutScopedBindings();
    Route::post('/{ulid}/assign-to-self', [AssignChatToAgent::class, 'assignToSelf'])
        ->name('assign.self');
    Route::patch('{chatSession:ulid}/assign', AssignChatToAgent::class)
        ->name('assign');
    Route::patch('{chatSession:ulid}/takeover', [AssignChatToAgent::class, 'takeOver'])
        ->name('takeover');
    Route::post('/messages/{chatSession:ulid}/send', SendChatMessage::class)->name('messages.send');
    Route::patch('/sessions/{chatSession:ulid}/close', CloseChatSession::class)->name('sessions.close');
    Route::patch('/sessions/{chatSession:ulid}/reopen', ReopenChatSession::class)->name('sessions.reopen');
});
