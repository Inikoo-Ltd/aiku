<?php


use App\Actions\Chat\Agent\StoreAgent;
use App\Actions\Chat\ChatSession\DeleteChatAgent;
use App\Actions\Chat\Agent\UI\CreateAgent;
use App\Actions\Chat\Agent\UI\EditAgent;
use App\Actions\Chat\Agent\UI\ShowAgent;
use App\Actions\Chat\Agent\UpdateAgent;
use App\Actions\Chat\Jira\GetChatAgentJiraSettings;
use App\Actions\Chat\Jira\GetChatSessionJiraFields;
use App\Actions\Chat\Jira\GetChatSessionJiraIssueTypes;
use App\Actions\Chat\Jira\GetChatSessionJiraLabels;
use App\Actions\Chat\Jira\GetChatSessionJiraPriorities;
use App\Actions\Chat\Jira\GetChatSessionJiraProjects;
use App\Actions\Chat\Jira\StoreChatSessionJiraTicket;
use App\Actions\Chat\Jira\UpdateChatAgentJiraSettings;
use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\ForceDeleteChatAgent;
use App\Actions\Chat\ChatSession\ForwardChatMessageToSlack;
use App\Actions\Chat\ChatSession\GetChatMessageSlackSettings;
use App\Actions\Chat\ChatSession\DeleteChatSessionPermanently;
use App\Actions\Chat\ChatSession\GetChatSessionSlackSettings;
use App\Actions\Chat\ChatSession\MarkChatSessionAsSpam;
use App\Actions\Chat\ChatSession\ReopenChatSession;
use App\Actions\Chat\ChatSession\RestoreChatSession;
use App\Actions\Chat\ChatSession\SetChatSessionPriority;
use App\Actions\Chat\ChatSession\ToggleChatSessionHighlight;
use App\Actions\Chat\ChatSession\TrashChatSession;
use App\Actions\Chat\ChatSession\UnmarkChatSessionAsSpam;
use App\Actions\Chat\ChatSession\RestoreChatAgent;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\UpdateChatMessage;
use App\Actions\Chat\ChatSession\UpdateChatSessionSlackSettings;
use App\Actions\Chat\ChatSession\VerifyChatImageMessage;
use App\Actions\Chat\ChatSession\StoreTicketFromChatSession;
use App\Actions\Chat\MetaChatSession\AssignMetaChatToAgent;
use App\Actions\Chat\MetaChatSession\CloseMetaChatSession;
use App\Actions\Chat\MetaChatSession\DeleteMetaChatSessionPermanently;
use App\Actions\Chat\MetaChatSession\MarkMetaChatSessionAsSpam;
use App\Actions\Chat\MetaChatSession\RestoreMetaChatSession;
use App\Actions\Chat\MetaChatSession\SetMetaChatSessionPriority;
use App\Actions\Chat\MetaChatSession\ToggleMetaChatSessionHighlight;
use App\Actions\Chat\MetaChatSession\TrashMetaChatSession;
use App\Actions\Chat\MetaChatSession\UnmarkMetaChatSessionAsSpam;
use App\Actions\Chat\MetaChatSession\ReopenMetaChatSession;
use App\Actions\Chat\MetaChatSession\SendMetaChatMessage;
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
    Route::post('/whatsapp/messages/{metaChatSession:ulid}/send', SendMetaChatMessage::class)->name('whatsapp.messages.send');
    Route::post('/whatsapp/{metaChatSession:ulid}/assign-to-self', [AssignMetaChatToAgent::class, 'assignToSelf'])
        ->name('whatsapp.assign.self');
    Route::patch('/whatsapp/{metaChatSession:ulid}/takeover', [AssignMetaChatToAgent::class, 'takeOver'])
        ->name('whatsapp.takeover');
    Route::patch('/whatsapp/{metaChatSession:ulid}/close', CloseMetaChatSession::class)
        ->name('whatsapp.sessions.close');
    Route::patch('/whatsapp/{metaChatSession:ulid}/reopen', ReopenMetaChatSession::class)
        ->name('whatsapp.sessions.reopen');
    Route::patch('/whatsapp/{metaChatSession:ulid}/spam', MarkMetaChatSessionAsSpam::class)
        ->name('whatsapp.sessions.spam');
    Route::patch('/whatsapp/{metaChatSession:ulid}/not-spam', UnmarkMetaChatSessionAsSpam::class)
        ->name('whatsapp.sessions.not_spam');
    Route::patch('/whatsapp/{metaChatSession:ulid}/priority', SetMetaChatSessionPriority::class)
        ->name('whatsapp.sessions.priority');
    Route::patch('/whatsapp/{metaChatSession:ulid}/highlight', ToggleMetaChatSessionHighlight::class)
        ->name('whatsapp.sessions.highlight');
    Route::delete('/whatsapp/{metaChatSession:ulid}/trash', TrashMetaChatSession::class)
        ->name('whatsapp.sessions.trash');
    Route::patch('/whatsapp/{metaChatSession:ulid}/restore', RestoreMetaChatSession::class)
        ->name('whatsapp.sessions.restore')->withTrashed();
    Route::delete('/whatsapp/{metaChatSession:ulid}/force', DeleteMetaChatSessionPermanently::class)
        ->name('whatsapp.sessions.force_delete')->withTrashed();
    Route::patch('/messages/{chatSession:ulid}/{chatMessage}/edit', UpdateChatMessage::class)->name('messages.update');
    Route::post('/messages/{chatMessage}/verify-image', VerifyChatImageMessage::class)->name('messages.verify_image');
    Route::get('/messages/{chatMessage}/slack-settings', GetChatMessageSlackSettings::class)->name('messages.slack_settings');
    Route::post('/messages/{chatMessage}/forward-slack', ForwardChatMessageToSlack::class)->name('messages.forward_slack');
    Route::patch('/sessions/{chatSession:ulid}/close', CloseChatSession::class)->name('sessions.close');
    Route::patch('/sessions/{chatSession:ulid}/reopen', ReopenChatSession::class)->name('sessions.reopen');
    Route::patch('/sessions/{chatSession:ulid}/spam', MarkChatSessionAsSpam::class)->name('sessions.spam');
    Route::patch('/sessions/{chatSession:ulid}/not-spam', UnmarkChatSessionAsSpam::class)->name('sessions.not_spam');
    Route::patch('/sessions/{chatSession:ulid}/priority', SetChatSessionPriority::class)->name('sessions.priority');
    Route::patch('/sessions/{chatSession:ulid}/highlight', ToggleChatSessionHighlight::class)->name('sessions.highlight');
    Route::delete('/sessions/{chatSession:ulid}/trash', TrashChatSession::class)->name('sessions.trash');
    Route::patch('/sessions/{chatSession:ulid}/restore', RestoreChatSession::class)->name('sessions.restore')->withTrashed();
    Route::delete('/sessions/{chatSession:ulid}/force', DeleteChatSessionPermanently::class)->name('sessions.force_delete')->withTrashed();
    Route::post('/sessions/{chatSession:ulid}/ticket', StoreTicketFromChatSession::class)->name('sessions.ticket');
    Route::name('sessions.jira.')->prefix('sessions/{chatSession:ulid}/jira')->group(function () {
        Route::get('/projects', GetChatSessionJiraProjects::class)->name('projects');
        Route::get('/projects/{project}/issue-types', GetChatSessionJiraIssueTypes::class)->name('issue_types');
        Route::get('/projects/{project}/issue-types/{issueType}/fields', GetChatSessionJiraFields::class)->name('fields');
        Route::get('/priorities', GetChatSessionJiraPriorities::class)->name('priorities');
        Route::get('/labels', GetChatSessionJiraLabels::class)->name('labels');
        Route::post('/ticket', StoreChatSessionJiraTicket::class)->name('ticket');
    });
    Route::name('sessions.slack.')->prefix('sessions/{chatSession:ulid}/slack')->group(function () {
        Route::get('/', GetChatSessionSlackSettings::class)->name('show');
        Route::put('/', UpdateChatSessionSlackSettings::class)->name('update');
    });
    Route::name('jira.settings.')->prefix('jira/settings')->group(function () {
        Route::get('/', GetChatAgentJiraSettings::class)->name('show');
        Route::put('/', UpdateChatAgentJiraSettings::class)->name('update');
    });
});
