<?php


use App\Actions\Chat\Agent\StoreAgent;
use App\Actions\Chat\Agent\UI\CreateAgent;
use App\Actions\Chat\Agent\UI\EditAgent;
use App\Actions\Chat\Agent\UI\ShowAgent;
use App\Actions\Chat\Agent\UpdateAgent;
use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\ForwardChatMessageToSlack;
use App\Actions\Chat\ChatSession\ForwardChatSessionByEmail;
use App\Actions\Chat\ChatSession\ForwardChatSessionToColleague;
use App\Actions\Chat\ChatSession\MoveChatSessionToShop;
use App\Actions\Chat\ChatSession\WaitForCustomerReply;
use App\Actions\Chat\ChatSession\GetChatMessageSlackSettings;
use App\Actions\Chat\ChatSession\GetChatSessionSlackSettings;
use App\Actions\Chat\ChatSession\MarkChatSessionAsRubbish;
use App\Actions\Chat\ChatSession\MoveChatSessionToCouriers;
use App\Actions\Chat\ChatSession\MarkChatSessionAsSpam;
use App\Actions\Chat\ChatSession\MarkChatSessionAsUnread;
use App\Actions\Chat\ChatSession\RedactChatMessage;
use App\Actions\Chat\ChatSession\ReleaseChatSession;
use App\Actions\Chat\ChatSession\ReopenChatSession;
use App\Actions\Chat\ChatSession\RetractChatMessage;
use App\Actions\Chat\ChatSession\RestoreChatSession;
use App\Actions\Chat\ChatSession\SetChatSessionPriority;
use App\Actions\Chat\ChatSession\ToggleChatSessionHighlight;
use App\Actions\Chat\ChatSession\TrashChatSession;
use App\Actions\Chat\ChatSession\UnmarkChatSessionAsSpam;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\UpdateChatSessionSlackSettings;
use App\Actions\Chat\ChatSession\VerifyChatImageMessage;
use App\Actions\Chat\ChatSession\StoreTicketFromChatSession;
use App\Actions\Chat\ChatSession\StoreStaffTaskFromChatSession;
use App\Actions\Chat\MetaChatSession\AssignMetaChatToAgent;
use App\Actions\Chat\SavedReply\DeleteChatSavedReply;
use App\Actions\Chat\SavedReply\IndexChatSavedReplies;
use App\Actions\Chat\SavedReply\StoreChatSavedReply;
use App\Actions\Chat\SavedReply\UpdateChatSavedReply;
use App\Actions\Chat\Whatsapp\Calls\AnswerWhatsappCall;
use App\Actions\Chat\Whatsapp\Calls\EndWhatsappCall;
use App\Actions\Chat\Whatsapp\Calls\GetWhatsappCallOffer;
use App\Actions\Chat\Whatsapp\Calls\StartWhatsappCall;
use App\Actions\Chat\MetaChatSession\CloseMetaChatSession;
use App\Actions\Chat\MetaChatSession\MarkMetaChatSessionAsSpam;
use App\Actions\Chat\MetaChatSession\RestoreMetaChatSession;
use App\Actions\Chat\MetaChatSession\SetMetaChatSessionPriority;
use App\Actions\Chat\MetaChatSession\ToggleMetaChatSessionHighlight;
use App\Actions\Chat\MetaChatSession\TrashMetaChatSession;
use App\Actions\Chat\MetaChatSession\UnmarkMetaChatSessionAsSpam;
use App\Actions\Chat\MetaChatSession\ReopenMetaChatSession;
use App\Actions\Chat\MetaChatSession\SendMetaChatMessage;
use App\Actions\Comms\Mailbox\ImportPendingGmailAttachments;
use App\Actions\Chat\EmailContact\DeleteChatEmailContact;
use App\Actions\Chat\EmailContact\SearchChatEmailContacts;
use App\Actions\Chat\EmailContact\StoreChatEmailContact;
use App\Actions\Chat\EmailContact\UpdateChatEmailContact;
use App\Actions\Chat\ChatSession\GetChatSearchSuggestions;
use App\Actions\Chat\Label\DeleteChatLabel;
use App\Actions\Chat\Label\GetChatLabels;
use App\Actions\Chat\Label\StoreChatLabel;
use App\Actions\Chat\Label\ToggleChatSessionLabel;
use Illuminate\Support\Facades\Route;

Route::name('agents.')->prefix('agents')->group(function () {
    Route::get('/', ShowAgent::class)->name('show');
    Route::get('/create', CreateAgent::class)->name('create');
    Route::get('/{agentId}/edit', EditAgent::class)->name('edit');
    Route::post('/store', StoreAgent::class)->name('store');
    Route::patch('/update/{agent:id}', UpdateAgent::class)->name('update')->withoutScopedBindings();
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
    Route::patch('/whatsapp/{metaChatSession:ulid}/unread', [MarkChatSessionAsUnread::class, 'inWhatsapp'])
        ->name('whatsapp.sessions.unread');
    Route::patch('/whatsapp/{metaChatSession:ulid}/wait-for-customer', [WaitForCustomerReply::class, 'inWhatsapp'])
        ->name('whatsapp.sessions.wait_for_customer');
    Route::delete('/whatsapp/{metaChatSession:ulid}/trash', TrashMetaChatSession::class)
        ->name('whatsapp.sessions.trash');
    Route::patch('/whatsapp/{metaChatSession:ulid}/restore', RestoreMetaChatSession::class)
        ->name('whatsapp.sessions.restore')->withTrashed();
    Route::post('/whatsapp/{metaChatSession:ulid}/calls', StartWhatsappCall::class)
        ->name('whatsapp.calls.start');
    Route::get('/whatsapp/calls/{metaChatCall}/offer', GetWhatsappCallOffer::class)
        ->name('whatsapp.calls.offer');
    Route::post('/whatsapp/calls/{metaChatCall}/answer', AnswerWhatsappCall::class)
        ->name('whatsapp.calls.answer');
    Route::post('/whatsapp/calls/{metaChatCall}/end', EndWhatsappCall::class)
        ->name('whatsapp.calls.end');
    Route::patch('/messages/{chatSession:ulid}/{chatMessage}/redact', RedactChatMessage::class)->name('messages.redact');
    Route::post('/messages/{chatSession:ulid}/{chatMessage}/pending-attachments', ImportPendingGmailAttachments::class)->name('messages.pending_attachments');
    Route::delete('/messages/{chatSession:ulid}/{chatMessage}/redact-attachment', [RedactChatMessage::class, 'inAttachment'])->name('messages.redact_attachment');
    Route::delete('/messages/{chatSession:ulid}/{chatMessage}/retract', RetractChatMessage::class)->name('messages.retract');
    Route::post('/messages/{chatMessage}/verify-image', VerifyChatImageMessage::class)->name('messages.verify_image');
    Route::get('/messages/{chatMessage}/slack-settings', GetChatMessageSlackSettings::class)->name('messages.slack_settings');
    Route::post('/messages/{chatMessage}/forward-slack', ForwardChatMessageToSlack::class)->name('messages.forward_slack');
    Route::patch('/sessions/{chatSession:ulid}/close', CloseChatSession::class)->name('sessions.close');
    Route::patch('/sessions/{chatSession:ulid}/reopen', ReopenChatSession::class)->name('sessions.reopen');
    Route::patch('/sessions/{chatSession:ulid}/spam', MarkChatSessionAsSpam::class)->name('sessions.spam');
    Route::patch('/sessions/{chatSession:ulid}/not-spam', UnmarkChatSessionAsSpam::class)->name('sessions.not_spam');
    Route::patch('/sessions/{chatSession:ulid}/rubbish', MarkChatSessionAsRubbish::class)->name('sessions.rubbish');
    Route::patch('/sessions/{chatSession:ulid}/not-rubbish', [MarkChatSessionAsRubbish::class, 'unmark'])->name('sessions.not_rubbish');
    Route::patch('/sessions/{chatSession:ulid}/couriers', MoveChatSessionToCouriers::class)->name('sessions.couriers');
    Route::patch('/sessions/{chatSession:ulid}/priority', SetChatSessionPriority::class)->name('sessions.priority');
    Route::patch('/sessions/{chatSession:ulid}/highlight', ToggleChatSessionHighlight::class)->name('sessions.highlight');
    Route::get('/search-suggestions', GetChatSearchSuggestions::class)->name('search_suggestions');
    Route::get('/labels', GetChatLabels::class)->name('labels.index');
    Route::post('/labels', StoreChatLabel::class)->name('labels.store');
    Route::delete('/labels/{chatLabel}', DeleteChatLabel::class)->name('labels.delete')->withoutScopedBindings();
    Route::patch('/sessions/{chatSession:ulid}/labels/{chatLabel}', ToggleChatSessionLabel::class)->name('sessions.label')->withoutScopedBindings();
    Route::patch('/sessions/{chatSession:ulid}/unread', MarkChatSessionAsUnread::class)->name('sessions.unread');
    Route::patch('/sessions/{chatSession:ulid}/wait-for-customer', WaitForCustomerReply::class)->name('sessions.wait_for_customer');
    Route::delete('/sessions/{chatSession:ulid}/trash', TrashChatSession::class)->name('sessions.trash');
    Route::patch('/sessions/{chatSession:ulid}/restore', RestoreChatSession::class)->name('sessions.restore')->withTrashed();
    Route::patch('/sessions/{chatSession:ulid}/release', ReleaseChatSession::class)->name('sessions.release');
    Route::post('/sessions/{chatSession:ulid}/forward', ForwardChatSessionToColleague::class)->name('sessions.forward');
    Route::get('/sessions/{chatSession:ulid}/forward-email', [ForwardChatSessionByEmail::class, 'preview'])->name('sessions.forward_email.preview');
    Route::post('/sessions/{chatSession:ulid}/forward-email', ForwardChatSessionByEmail::class)->name('sessions.forward_email');
    Route::get('/sessions/{chatSession:ulid}/move-to-shop', [MoveChatSessionToShop::class, 'targetShops'])->name('sessions.move_to_shop.shops');
    Route::patch('/sessions/{chatSession:ulid}/move-to-shop', MoveChatSessionToShop::class)->name('sessions.move_to_shop');
    Route::post('/sessions/{chatSession:ulid}/ticket', StoreTicketFromChatSession::class)->name('sessions.ticket');
    Route::post('/whatsapp/{metaChatSession:ulid}/ticket', [StoreTicketFromChatSession::class, 'inMetaChatSession'])
        ->name('whatsapp.sessions.ticket');
    Route::post('/sessions/{chatSession:ulid}/task', StoreStaffTaskFromChatSession::class)->name('sessions.task');
    Route::post('/whatsapp/{metaChatSession:ulid}/task', [StoreStaffTaskFromChatSession::class, 'inMetaChatSession'])
        ->name('whatsapp.sessions.task');
    Route::name('saved_replies.')->prefix('saved-replies')->group(function () {
        Route::get('/', IndexChatSavedReplies::class)->name('index');
        Route::post('/', StoreChatSavedReply::class)->name('store');
        Route::patch('/{chatSavedReply}', UpdateChatSavedReply::class)->name('update');
        Route::delete('/{chatSavedReply}', DeleteChatSavedReply::class)->name('destroy');
    });
    Route::name('email_contacts.')->prefix('email-contacts')->group(function () {
        Route::get('/shop/{shop:id}', SearchChatEmailContacts::class)->name('search');
        Route::post('/shop/{shop:id}', StoreChatEmailContact::class)->name('store');
        Route::patch('/{chatEmailContact:id}', UpdateChatEmailContact::class)->name('update');
        Route::delete('/{chatEmailContact:id}', DeleteChatEmailContact::class)->name('delete');
    });
    Route::name('sessions.slack.')->prefix('sessions/{chatSession:ulid}/slack')->group(function () {
        Route::get('/', GetChatSessionSlackSettings::class)->name('show');
        Route::put('/', UpdateChatSessionSlackSettings::class)->name('update');
    });
});
