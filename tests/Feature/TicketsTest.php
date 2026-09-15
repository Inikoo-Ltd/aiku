<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Ticket\SyncTicketCollaborators;
use Illuminate\Support\Facades\Process;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreTicketFromChatSession;
use App\Actions\Helpers\Ticket\CancelStaleTickets;
use App\Actions\Helpers\Ticket\CloseTicketsAfterDeployment;
use App\Actions\Helpers\Ticket\LinkTicketsToAppDeployment;
use App\Models\DevOps\AppDeployment;
use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\Helpers\Ticket\RepairSlackTicketReporters;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\StoreTicketFromSlack;
use App\Actions\Helpers\Ticket\UI\ShowTicketsReports;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Search\SearchTickets;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Mcp\Servers\AikuServer;
use App\Models\SysAdmin\User;
use App\Mcp\Tools\TicketsTool;
use App\Mcp\Tools\TicketWriteTool;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\Guest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketNotification;
use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use App\Events\BroadcastTicketBadgeUpdate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use App\Actions\Helpers\Ticket\NotifyTicketUsers;
use App\Actions\SysAdmin\User\SendUserPushNotification;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user, $this->shop) = createShop();
    $this->group    = $this->organisation->group;
    $this->website  = createWebsite($this->shop);
    $this->website->update(['status' => true]);
    $this->customer = createCustomer($this->shop);
    $this->webUser  = createWebUser($this->customer);

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
    $this->user->assignRole('help-desk-supervisor');
    $this->user->forgetWildcardPermissionIndex();
    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

test('help ticket gets a HELP reference and defaults', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Printer on fire']);

    expect($ticket->reference)->toBe('HELP-'.$ticket->number)
        ->and($ticket->type)->toBe(TicketTypeEnum::HELP)
        ->and($ticket->status)->toBe(TicketStatusEnum::OPEN)
        ->and($ticket->priority)->toBe(ChatPriorityEnum::NORMAL);

    $next = StoreTicket::make()->action($this->group, ['subject' => 'Second']);
    expect($next->number)->toBe($ticket->number + 1);

    return $ticket;
});

test('customer ticket from retina gets an AD reference and the customer attached', function () {
    $ticket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'API returns 500', 'priority' => 'high']);

    expect($ticket->reference)->toStartWith('AD-')
        ->and($ticket->type)->toBe(TicketTypeEnum::CUSTOMER)
        ->and($ticket->customer_id)->toBe($this->customer->id)
        ->and($ticket->shop_id)->toBe($this->shop->id)
        ->and($ticket->reporter)->toBeInstanceOf($this->webUser::class)
        ->and($ticket->priority)->toBe(ChatPriorityEnum::HIGH);

    return $ticket;
});

test('status changes stamp resolved and closed dates', function (Ticket $ticket) {
    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    expect($ticket->resolved_at)->not->toBeNull()->and($ticket->closed_at)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::CANCELLED->value]);
    expect($ticket->closed_at)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::OPEN->value, 'assignee_id' => $this->user->id]);
    expect($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->assignee_id)->toBe($this->user->id);
})->depends('help ticket gets a HELP reference and defaults');

test('a ticket waiting longer than the grace period is cancelled, a fresh one is left alone', function () {
    $stale = StoreTicket::make()->action($this->group, ['subject' => 'Waited forever']);
    $fresh = StoreTicket::make()->action($this->group, ['subject' => 'Just asked']);
    $answered = StoreTicket::make()->action($this->group, ['subject' => 'Old but answered']);
    Ticket::whereIn('id', [$stale->id, $fresh->id, $answered->id])->update(['status' => TicketStatusEnum::WAITING]);
    Ticket::whereIn('id', [$stale->id, $answered->id])->update(['created_at' => now()->subDays(20), 'updated_at' => now()]);
    $answered->comments()->create(['body' => 'asked 20 days ago', 'created_at' => now()->subDays(20)]);
    $answered->comments()->create(['body' => 'here is the info', 'created_at' => now()->subDays(2)]);
    $stale->comments()->create(['body' => 'tagged it', 'created_at' => now()->subDays(20)]);

    expect(CancelStaleTickets::run(14))->toBe(1)
        ->and($stale->fresh()->status)->toBe(TicketStatusEnum::CANCELLED)
        ->and($stale->fresh()->closed_at)->not->toBeNull()
        ->and($stale->comments()->count())->toBe(2)
        ->and($fresh->fresh()->status)->toBe(TicketStatusEnum::WAITING)
        ->and($answered->fresh()->status)->toBe(TicketStatusEnum::WAITING);

    $stale = $stale->fresh();
    StoreTicketComment::make()->action($stale, $this->webUser, ['body' => 'sorry, was on holiday']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::OPEN)
        ->and($stale->fresh()->closed_at)->toBeNull();

    UpdateTicket::make()->action($stale, ['status' => TicketStatusEnum::RESOLVED->value]);
    StoreTicketComment::make()->action($stale, $this->webUser, ['body' => 'thanks!']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::RESOLVED);

    UpdateTicket::make()->action($stale, ['status' => TicketStatusEnum::WAITING->value]);
    StoreTicketComment::make()->action($stale, $this->user, ['body' => 'any news?']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::WAITING);

    StoreTicketComment::make()->action($stale, $this->webUser, ['body' => 'here you go']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::ANSWERED)
        ->and($stale->fresh()->waiting_until)->toBeNull()
        ->and($stale->fresh()->waiting_at->isAfter(now()->subMinute()))->toBeTrue();

    $board = get(route('grp.tickets.board'))->assertOk()->inertiaProps();
    $waiting = collect($board['columns'])->firstWhere('key', 'waiting');
    expect($waiting['label'])->toBe('Waiting')
        ->and(collect($waiting['statuses'])->pluck('count', 'status')->all())->toBe(['waiting' => 2, 'answered' => 1]);
});

test('staff and customers comment on the same public thread', function (Ticket $ticket) {
    $staffNote = StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Looking into it']);
    $customerNote = StoreTicketComment::make()->action($ticket, $this->webUser, ['body' => 'Any news?']);

    expect($staffNote->author_type)->toBe('User')
        ->and($customerNote->author_type)->toBe('WebUser')
        ->and($ticket->comments()->count())->toBe(2);
})->depends('customer ticket from retina gets an AD reference and the customer attached');

test('grp ticket pages render', function (Ticket $ticket) {
    get(route('grp.tickets.index'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketsDashboard')->where('can_manage', true)->has('queue')->has('stats.open'));
    get(route('grp.tickets.list'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/Tickets')->has('data.data', Ticket::count()));
    get(route('grp.tickets.board'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketsBoard')->has('columns', 5)->where('me', $this->user->username)->has('formerAssignees')->has('assignees'));
    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    get(route('grp.tickets.create'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/CreateTicket'));
    actingAs($this->user);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/Ticket')->where('ticket.reference', $ticket->reference)->has('comments', 2)->where('pageHead.wrapped_actions.0.key', 'delete')
            ->where('options.assignees', fn ($assignees) => collect($assignees)->pluck('value')->all() === GetTicketBadgeData::engineers($this->group->id)->sortBy(fn (User $user) => strtok((string) ($user->contact_name ?: $user->username), ' '))->pluck('id')->values()->all()
                && collect($assignees)->firstWhere('value', $this->user->id)['is_me'] === true)
            ->where('options.mentionable', fn ($mentionable) => collect($mentionable)->pluck('username')->contains($this->user->username))
    );
})->depends('customer ticket from retina gets an AD reference and the customer attached');

test('grp form endpoints create, update and comment', function () {
    $countBefore = Ticket::count();
    $reporter    = User::factory()->create(['group_id' => $this->group->id]);

    actingAs($reporter);
    post(route('grp.models.ticket.store'), ['subject' => 'From the form', 'priority' => 'urgent'])->assertRedirect();
    actingAs($this->user);
    $ticket = Ticket::latest('id')->first();
    expect(Ticket::count())->toBe($countBefore + 1)
        ->and($ticket->reporter_id)->toBe($reporter->id)
        ->and($ticket->priority)->toBe(ChatPriorityEnum::URGENT);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'in_progress'])->assertRedirect();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'On it'])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS)
        ->and($ticket->comments()->count())->toBe(1);
});

test('comment author edits and deletes their own comment', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Comment edit']);
    $comment = StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Typo'], false);

    patch(route('grp.models.ticket.comment.update', $comment->id), ['body' => 'Fixed'])->assertRedirect();
    expect($comment->refresh()->body)->toBe('Fixed');

    $admin = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    actingAs($admin);
    patch(route('grp.models.ticket.comment.update', $comment->id), ['body' => 'Hijacked'])->assertForbidden();
    delete(route('grp.models.ticket.comment.delete', $comment->id))->assertForbidden();
    actingAs($this->user);

    delete(route('grp.models.ticket.comment.delete', $comment->id))->assertRedirect();
    expect(TicketComment::find($comment->id))->toBeNull();
});

test('asking the reporter posts the question and cancels the ticket when its own deadline passes', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Need info']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'waiting', 'question' => 'Which order?', 'waiting_hours' => 2])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::WAITING)
        ->and($ticket->waiting_until->between(now()->addMinutes(119), now()->addMinutes(121)))->toBeTrue()
        ->and($ticket->comments()->where('is_internal', false)->where('body', 'Which order?')->exists())->toBeTrue();

    expect(CancelStaleTickets::run())->toBe(0);

    $this->travel(3)->hours();
    expect(CancelStaleTickets::run())->toBe(1)
        ->and($ticket->fresh()->status)->toBe(TicketStatusEnum::CANCELLED)
        ->and($ticket->fresh()->waiting_until)->toBeNull();
});

test('staff reporter is told of the question by email and slack as their profile prefers', function () {
    Notification::fake();
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake(['slack.com/*' => Http::response(['ok' => true])]);

    $reporter = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $reporter->update(['slack_user_id' => 'U123', 'settings' => ['notifications' => ['ticket_needs_reply' => ['email', 'slack']]]]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ask me']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'waiting', 'question' => 'Which order?', 'waiting_hours' => 24])->assertRedirect();

    Notification::assertSentTo($reporter, TicketNotification::class);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['channel'] === 'U123');

    $reporter->update(['settings' => ['notifications' => ['ticket_needs_reply' => []]]]);
    Notification::fake();
    UpdateTicket::make()->action($ticket->fresh(), ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'waiting', 'question' => 'Still?'])->assertRedirect();
    Notification::assertSentTo($reporter, TicketNotification::class, fn ($notification, $channels) => $channels === ['database']);

    $reporter->update(['settings' => ['notifications' => ['ticket_resolved' => ['email']]]]);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved', 'question' => 'Fixed the voucher total'])->assertRedirect();
    Notification::assertSentTo($reporter, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'is done'));
    expect($ticket->comments()->where('body', 'Fixed the voucher total')->count())->toBe(1);
});

test('browser channel queues a web push to the reporter devices and prunes expired endpoints', function () {
    Notification::fake();
    Config::set('services.webpush.public_key', 'public');
    Config::set('services.webpush.private_key', 'private');

    post(route('grp.profile.push-subscriptions.store'), ['endpoint' => 'https://push.example.com/live', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk();
    post(route('grp.profile.push-subscriptions.store'), ['endpoint' => 'https://push.example.com/gone', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk();
    expect($this->user->pushSubscriptions()->count())->toBe(2);

    $this->user->update(['settings' => ['notifications' => ['ticket_resolved' => ['browser']]]]);
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Push me']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $this->user->id]);

    Queue::fake();
    NotifyTicketUsers::make()->done($ticket->fresh(), null);
    SendUserPushNotification::assertPushed(1);

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->twice();
    $webPush->shouldReceive('flush')->andReturn((function () {
        yield new MessageSentReport(new GuzzleRequest('POST', 'https://push.example.com/live'), new GuzzleResponse(201), true);
        yield new MessageSentReport(new GuzzleRequest('POST', 'https://push.example.com/gone'), new GuzzleResponse(410), false);
    })());
    app()->bind(WebPush::class, fn () => $webPush);

    expect(SendUserPushNotification::run($this->user, ['title' => 'T', 'body' => 'B', 'url' => '/']))->toBe(1)
        ->and($this->user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://push.example.com/live'])
        ->and($this->user->pushSubscriptions()->first()->last_used_at)->not->toBeNull();

    delete(route('grp.profile.push-subscriptions.delete'), ['endpoint' => 'https://push.example.com/live'])->assertOk();
    expect($this->user->pushSubscriptions()->count())->toBe(0);
});

test('ticket page shows a history from opened to its status changes, newest first', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Timeline']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('timeline.0.text', 'Status: Todo → In progress')->where('timeline.1.text', 'Ticket opened')
    );
});

test('chat agent raises a ticket linked to the session', function () {
    $agent = ChatAgent::firstOrCreate(
        ['user_id' => $this->user->id],
        ['is_online' => true, 'max_concurrent_chats' => 100, 'current_chat_count' => 0]
    );
    $chatSession = StoreChatSession::make()->handle([
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ]);

    $ticket = StoreTicketFromChatSession::make()->handle($chatSession, $agent, [
        'summary'       => 'Visitor cannot checkout',
        'description'   => 'Card declined twice',
        'priority'      => 'high',
        'reference_url' => 'https://app.aiku.test/chat',
    ]);

    expect($ticket->type)->toBe(TicketTypeEnum::CUSTOMER)
        ->and($ticket->model_type)->toBe('ChatSession')
        ->and($ticket->model_id)->toBe($chatSession->id)
        ->and($ticket->shop_id)->toBe($this->shop->id)
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($ticket->description)->toContain('Reference: https://app.aiku.test/chat')
        ->and($chatSession->chatEvents()->where('event_type', ChatEventTypeEnum::TICKET)->count())->toBe(1);
});

test('retina support pages render for the customer', function () {
    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Retina')]);

    $ticket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Portfolio sync stuck']);

    $this->actingAs($this->webUser, 'retina')
        ->get('http://'.$this->website->domain.'/app/dropshipping/support')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dropshipping/RetinaTickets'));

    $this->actingAs($this->webUser, 'retina')
        ->get('http://'.$this->website->domain.'/app/dropshipping/support/'.$ticket->reference)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dropshipping/RetinaTicket')->where('ticket.reference', $ticket->reference));
});

test('screenshots can be attached to tickets and comments', function () {
    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Broken layout, see screenshot',
        'images'  => [UploadedFile::fake()->image('shot.png', 400, 300)],
    ]);
    $comment = StoreTicketComment::make()->action($ticket, $this->user, [
        'images' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.jpg')],
    ]);

    expect($ticket->getMedia('ticket_images'))->toHaveCount(1)
        ->and($comment->body)->toBe('')
        ->and($comment->getMedia('ticket_images'))->toHaveCount(2)
        ->and($ticket->ticketImageSources()[0])->toHaveKey('original');

    post(route('grp.models.ticket.comment.store', $ticket->id), [])->assertSessionHasErrors('body');
});

test('pdf, word, excel, csv and zip files can be attached to tickets and comments', function () {
    $directory = sys_get_temp_dir().'/ticket_files_'.uniqid();
    mkdir($directory);

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'qty');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save("$directory/stock.xlsx");
    (new PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet))->save("$directory/legacy.xls");

    $docx = new ZipArchive();
    $docx->open("$directory/notes.docx", ZipArchive::CREATE);
    $docx->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $docx->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
    $docx->close();

    $bundle = new ZipArchive();
    $bundle->open("$directory/bundle.zip", ZipArchive::CREATE);
    $bundle->addFromString('logs/error.log', "boom\n");
    $bundle->close();

    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Invoice looks wrong',
        'images'  => [UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n%%EOF\n")],
    ]);
    $comment = StoreTicketComment::make()->action($ticket, $this->user, [
        'images' => [
            new UploadedFile("$directory/stock.xlsx", 'stock.xlsx', null, null, true),
            new UploadedFile("$directory/legacy.xls", 'legacy.xls', null, null, true),
            new UploadedFile("$directory/notes.docx", 'notes.docx', null, null, true),
            UploadedFile::fake()->createWithContent('orders.csv', "reference,qty\nA-1,2\n"),
            UploadedFile::fake()->image('shot.png'),
        ],
    ]);
    $zipComment = StoreTicketComment::make()->action($ticket, $this->user, [
        'images' => [new UploadedFile("$directory/bundle.zip", 'bundle.zip', null, null, true)],
    ]);

    expect($ticket->getMedia('ticket_attachments'))->toHaveCount(1)
        ->and($ticket->ticketAttachments()[0])->toMatchArray(['name' => 'invoice.pdf', 'mime' => 'application/pdf'])
        ->and(collect($comment->ticketAttachments())->pluck('name')->sort()->values()->all())->toBe(['legacy.xls', 'notes.docx', 'orders.csv', 'stock.xlsx'])
        ->and($comment->getMedia('ticket_images'))->toHaveCount(1)
        ->and($zipComment->ticketAttachments()[0]['name'])->toBe('bundle.zip');

    foreach ([UploadedFile::fake()->createWithContent('notes.txt', "plain text\n")] as $rejectedFile) {
        expect(fn () => StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$rejectedFile]]))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    }
});

test('videos up to 50 MB can be attached, other files stay capped at 10 MB, and videos stream with range requests', function () {
    $mp4Header       = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";
    $movHeader       = "\x00\x00\x00\x14ftypqt  \x00\x00\x00\x00qt  ";
    $webmHeader      = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02";
    $elevenMegabytes = 11 * 1024 * 1024;

    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Screen recording of the bug',
        'images'  => [
            UploadedFile::fake()->createWithContent('recording.mp4', str_pad($mp4Header, $elevenMegabytes, "\0")),
            UploadedFile::fake()->createWithContent('iphone.mov', str_pad($movHeader, 2048, "\0")),
            UploadedFile::fake()->createWithContent('capture.webm', str_pad($webmHeader, 2048, "\0")),
        ],
    ]);

    expect(collect($ticket->ticketAttachments())->pluck('name')->sort()->values()->all())->toBe(['capture.webm', 'iphone.mov', 'recording.mp4']);

    expect(fn () => StoreTicketComment::make()->action($ticket, $this->user, [
        'images' => [UploadedFile::fake()->createWithContent('huge.pdf', str_pad("%PDF-1.4\n", $elevenMegabytes, ' ')."\n%%EOF\n")],
    ]))->toThrow(Illuminate\Validation\ValidationException::class);

    $recording = $ticket->getMedia('ticket_attachments')->firstWhere('name', 'recording.mp4');

    get(route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $recording->ulid]), ['Range' => 'bytes=0-99'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-99/'.$elevenMegabytes)
        ->assertHeader('Content-Type', 'video/mp4');
});

test('ticket attachments are served inline through the ticket, only to people who can see them', function () {
    $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n");

    $ticket          = StoreTicket::make()->action($this->group, ['subject' => 'Attachment route', 'images' => [$pdf('ticket.pdf')]]);
    $internalComment = StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$pdf('internal.pdf')]]);
    $internalComment->update(['is_internal' => true]);
    $otherTicket = StoreTicket::make()->action($this->group, ['subject' => 'Someone else', 'images' => [$pdf('other.pdf')]]);

    $ticketPdf   = $ticket->getMedia('ticket_attachments')->first();
    $internalPdf = $internalComment->getMedia('ticket_attachments')->first();
    $otherPdf    = $otherTicket->getMedia('ticket_attachments')->first();
    $urlFor      = fn ($media) => route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $media->ulid]);

    expect($ticket->ticketAttachments()[0]['url'])->toBe($urlFor($ticketPdf));

    $response = get($urlFor($ticketPdf))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
    get($urlFor($internalPdf))->assertOk();
    get($urlFor($otherPdf))->assertNotFound();

    $ticket->update(['is_confidential' => true]);
    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    get($urlFor($ticketPdf))->assertForbidden();
    actingAs($this->user);

    Storage::disk($ticketPdf->disk)->delete($ticketPdf->getPathRelativeToRoot());
    get($urlFor($ticketPdf))->assertNotFound();
});

test('customers get attachments of their ticket through retina, never those of internal comments', function () {
    $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n");

    $ticket          = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Invoice attached']);
    $customerComment = StoreTicketComment::make()->action($ticket, $this->webUser, ['images' => [$pdf('invoice.pdf')]]);
    $internalComment = StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$pdf('internal.pdf')]]);
    $internalComment->update(['is_internal' => true]);

    $attachmentUrl = fn ($comment) => 'http://'.$this->website->domain.'/app/dropshipping/support/'.$ticket->reference.'/attachments/'.$comment->getMedia('ticket_attachments')->first()->ulid;

    $this->actingAs($this->webUser, 'retina')->get($attachmentUrl($customerComment))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->webUser, 'retina')->get($attachmentUrl($internalComment))->assertNotFound();
});

test('tickets dashboard counts created, done, status and assignees', function () {
    $before = ShowTicketsReports::make()->handle($this->group, '1w');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Report me', 'assignee_id' => $this->user->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    StoreTicket::make()->action($this->group, ['subject' => 'Still open', 'assignee_id' => $this->user->id]);

    $stats = ShowTicketsReports::make()->handle($this->group, '1w');
    $today = collect($stats['daily'])->firstWhere('date', now()->toDateString());
    $me    = collect($stats['assignees'])->firstWhere('name', $this->user->contact_name ?: $this->user->username);

    expect($stats['created'])->toBe($before['created'] + 2)
        ->and($stats['done'])->toBe($before['done'] + 1)
        ->and($stats['open'])->toBe($before['open'] + 1)
        ->and(count($stats['daily']))->toBe(8)
        ->and(collect($stats['daily'])->last()['open'])->toBe($stats['open'])
        ->and($stats['bucket'])->toBe('day')
        ->and(ShowTicketsReports::make()->handle($this->group, '1y')['bucket'])->toBe('week')
        ->and($today['created'])->toBeGreaterThanOrEqual(2)
        ->and(collect($stats['by_status'])->firstWhere('status', 'resolved')['total'])->toBeGreaterThanOrEqual(1)
        ->and($me['done'])->toBeGreaterThanOrEqual(1)
        ->and($me['open'])->toBeGreaterThanOrEqual(1)
        ->and($me['median_hours'])->not->toBeNull()
        ->and($me)->toHaveKeys(['longest_wait_days', 'rating', 'assigned', 'in_progress', 'resolved', 'cancelled'])
        ->and($stats['reporters'])->toBeArray()
        ->and($stats['assignees_total']['created'])->toBe($stats['created'])
        ->and(collect($stats['by_status'])->sum('total'))->toBe($stats['created'])
        ->and($stats['resolvers_total']['done'])->toBe($before['resolvers_total']['done']);

    $oldTicket = StoreTicket::make()->action($this->group, ['subject' => 'Old but resolved now', 'assignee_id' => $this->user->id]);
    $oldTicket->update(['created_at' => now()->subMonths(3)]);
    UpdateTicket::make()->action($oldTicket, ['status' => TicketStatusEnum::RESOLVED->value]);

    $after = ShowTicketsReports::make()->handle($this->group, '1w');

    expect($after['assignees_total']['done'])->toBe($stats['assignees_total']['done'])
        ->and($after['resolvers_total']['done'])->toBe($stats['resolvers_total']['done'] + 1);

    get(route('grp.tickets.reports', ['created' => 'lm']))->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/TicketsReports')->where('stats.interval', 'lm')->has('stats.daily', now()->subMonth()->daysInMonth)
    );
});

test('reporter rates a resolved ticket once and CSAT shows on the dashboard', function () {
    $ticket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Rate me']);

    expect(RateTicket::canRate($ticket, $this->webUser))->toBeFalse();

    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    $ticket->refresh();

    expect(RateTicket::canRate($ticket, $this->webUser))->toBeTrue()
        ->and(RateTicket::canRate($ticket, $this->user))->toBeFalse();

    $this->actingAs($this->webUser, 'retina')
        ->post('http://'.$this->website->domain.'/app/models/ticket/'.$ticket->id.'/rate', ['rating' => 4, 'comment' => 'Quick fix'])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->rating)->toBe(4)
        ->and($ticket->rating_comment)->toBe('Quick fix')
        ->and($ticket->rated_at)->not->toBeNull()
        ->and(RateTicket::canRate($ticket, $this->webUser))->toBeFalse();

    $this->actingAs($this->webUser, 'retina')
        ->post('http://'.$this->website->domain.'/app/models/ticket/'.$ticket->id.'/rate', ['rating' => 1])
        ->assertForbidden();

    $stats = ShowTicketsReports::make()->handle($this->group, '1w');
    expect($stats['csat'])->toBeGreaterThan(0)
        ->and(count($stats['csat_by_month']))->toBe(12)
        ->and(collect($stats['csat_by_month'])->last()['total'])->toBeGreaterThanOrEqual(1);
});

test('staff reporter rates their own resolved help ticket from grp', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Rate from grp', 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::CANCELLED->value]);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_rate', true));
    post(route('grp.models.ticket.rate', $ticket->id), ['rating' => 5])->assertRedirect();

    expect($ticket->fresh()->rating)->toBe(5);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_rate', false)->where('ticket.rating', 5));
});

test('customer ticket escalates to a help ticket that keeps the customer and points back', function () {
    $customerTicket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Feed is empty', 'priority' => 'high']);

    $response = post(route('grp.models.ticket.escalate', $customerTicket->id), ['description' => 'Feed generator crashed']);
    $helpTicket = Ticket::where('model_type', 'Ticket')->where('model_id', $customerTicket->id)->first();
    $response->assertRedirect(route('grp.tickets.show', $helpTicket->reference));

    expect($helpTicket->type)->toBe(TicketTypeEnum::HELP)
        ->and($helpTicket->kind)->toBe(TicketKindEnum::ESCALATION)
        ->and($helpTicket->subject)->toBe('Feed is empty')
        ->and($helpTicket->description)->toBe('Feed generator crashed')
        ->and($helpTicket->priority)->toBe(ChatPriorityEnum::HIGH)
        ->and($helpTicket->customer_id)->toBe($this->customer->id)
        ->and($helpTicket->reporter_id)->toBe($this->user->id)
        ->and($customerTicket->escalations()->pluck('reference')->all())->toBe([$helpTicket->reference]);

    post(route('grp.models.ticket.escalate', $helpTicket->id))->assertStatus(422);
});

test('staff file a bug from anywhere without leaving the page', function () {
    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    $response = post(route('grp.models.ticket.store'), [
        'subject' => 'Button dead',
        'kind'    => 'bug',
        'stay'    => true,
        'reference_url' => 'https://app.aiku.test/org/awa/shops',
    ], ['referer' => 'https://app.aiku.test/org/awa/shops']);

    $ticket = Ticket::where('subject', 'Button dead')->first();
    $response->assertRedirect('https://app.aiku.test/org/awa/shops')->assertSessionHas('notification.title', $ticket->reference);
    expect($ticket->kind)->toBe(TicketKindEnum::BUG)
        ->and($ticket->type)->toBe(TicketTypeEnum::HELP)
        ->and($ticket->data['reference_url'])->toBe('https://app.aiku.test/org/awa/shops');

    actingAs($this->user);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('ticket.reference_url', 'https://app.aiku.test/org/awa/shops')
    );
});

test('tickets take free tags and the known list grows with them', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Tagged', 'tags' => ['not a bug'], 'module' => 'dispatching']);
    UpdateTicket::make()->action($ticket, ['tags' => ['not a bug', 'printer voodoo']]);

    expect($ticket->fresh()->tags)->toBe(['not a bug', 'printer voodoo'])
        ->and($ticket->module)->toBe(TicketModuleEnum::DISPATCHING)
        ->and(Ticket::knownTags($this->group->id))->toContain('printer voodoo', 'lack of training');
});

test('confidential tickets are only visible to reporter, assignee and lead engineers', function () {
    $outsider = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $outsider->removeRole('group-admin');
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'HR matter', 'is_confidential' => true, 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);

    expect(Ticket::visibleTo($this->user)->whereKey($ticket->id)->exists())->toBeTrue()
        ->and(Ticket::visibleTo($outsider)->whereKey($ticket->id)->exists())->toBeFalse();

    actingAs($outsider);
    get(route('grp.tickets.show', $ticket->reference))->assertForbidden();
    get(route('grp.tickets.list', ['elements' => ['mine' => 'reported']]))->assertOk();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'peek'])->assertForbidden();
    get(route('grp.tickets.reports'))->assertInertia(fn (AssertableInertia $page) => $page->where('stats.open', Ticket::visibleTo($outsider)->whereNotIn('status', ['resolved', 'cancelled'])->count()));

    UpdateTicket::make()->action($ticket, ['assignee_id' => $outsider->id]);
    expect(Ticket::visibleTo($outsider)->whereKey($ticket->id)->exists())->toBeTrue();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'on it'])->assertRedirect();
});

test('assistant raises, lists, works and closes a ticket through MCP', function () {
    $created = AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['subject' => 'Picking screen freezes', 'module' => 'dispatching', 'priority' => 'high']);
    $created->assertOk();
    $ticket    = Ticket::where('subject', 'Picking screen freezes')->firstOrFail();
    $reference = $ticket->reference;
    expect($ticket->kind)->toBe(TicketKindEnum::BUG)
        ->and($ticket->module)->toBe(TicketModuleEnum::DISPATCHING)
        ->and($ticket->reporter_id)->toBe($this->user->id);

    $listed = AikuServer::actingAs($this->user)->tool(TicketsTool::class, ['priority' => 'high,urgent', 'module' => 'dispatching']);
    $listed->assertOk()->assertSee($reference);

    $updated = AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, [
        'reference' => $reference,
        'status'    => 'resolved',
        'assignee'  => $this->user->username,
        'tags'      => ['data fix'],
        'comment'   => 'Fixed by clearing the stale lock',
    ]);
    $updated->assertOk();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->assignee_id)->toBe($this->user->id)
        ->and($ticket->tags)->toBe(['data fix'])
        ->and($ticket->comments()->where('body', 'Fixed by clearing the stale lock')->value('author_id'))->toBe($this->user->id);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'comment' => 'Merged stock 41882 into 40115', 'internal' => true])->assertOk();
    expect($ticket->comments()->where('body', 'Merged stock 41882 into 40115')->value('is_internal'))->toBeTrue();

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'status' => 'waiting', 'waiting_hours' => 24])->assertOk();
    expect($ticket->refresh()->waiting_until->diffInHours(now(), true))->toBeGreaterThan(23)->toBeLessThan(25);

    $shown = AikuServer::actingAs($this->user)->tool(TicketsTool::class, ['reference' => strtolower($reference)]);
    $shown->assertOk()->assertSee('Fixed by clearing the stale lock');

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'subject' => 'Stale lock on picking', 'description' => 'Rewritten'])->assertOk();
    expect($ticket->fresh()->subject)->toBe('Stale lock on picking')
        ->and($ticket->fresh()->description)->toBe('Rewritten');

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'assignee' => 'nobody-here'])->assertHasErrors();
});

test('new tickets post one alert in the Slack tickets channel and edit it as status and assignee change', function () {
    Config::set('services.slack.notifications.tickets_channel', '#tickets');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'channel' => 'C9', 'ts' => '42.1']), 'slack.com/api/chat.update' => Http::response(['ok' => true])]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ping me']);
    expect($ticket->fresh()->data['slack_alert'])->toEqualCanonicalizing(['channel' => 'C9', 'ts' => '42.1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['channel'] === '#tickets' && str_contains(json_encode($request['blocks']), 'Status:* Todo'));

    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.update') && $request['ts'] === '42.1' && $request['channel'] === 'C9' && str_contains(json_encode($request['blocks']), 'Status:* In progress') && str_contains(json_encode($request['blocks']), $this->user->username));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '42.1' && str_ends_with($request['text'], 'In progress'));

    UpdateTicket::make()->action($ticket, ['priority' => 'urgent']);
    Http::assertSentCount(3);

    Config::set('services.slack.notifications.tickets_channel', null);
    StoreTicket::make()->action($this->group, ['subject' => 'Silent']);
    Http::assertSentCount(3);
});


test('slack slash command raises a bug ticket for the matching aiku user', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $this->user->update(['email' => 'raul@example.com']);
    Http::fake(['slack.com/api/users.info*' => Http::response(['ok' => true, 'user' => ['profile' => ['email' => 'RAUL@example.com']]])]);
    Auth::logout();

    $params    = ['text' => "Labels print blank\nOnly on the SK printer", 'user_id' => 'U1', 'user_name' => 'raul', 'channel_name' => 'bugs'];
    $body      = http_build_query($params);
    $timestamp = (string) time();
    $headers   = [
        'X-Slack-Request-Timestamp' => $timestamp,
        'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
        'Content-Type'              => 'application/x-www-form-urlencoded',
    ];

    $response = $this->call('POST', route('webhooks.slack_ticket'), $params, [], [], $this->transformHeadersToServerVars($headers), $body);
    $response->assertOk();

    $ticket = Ticket::where('subject', 'Labels print blank')->firstOrFail();
    expect($ticket->kind)->toBe(TicketKindEnum::BUG)
        ->and($ticket->description)->toBe('Only on the SK printer')
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($ticket->data['slack']['channel'])->toBe('bugs')
        ->and($response->json('text'))->toContain($ticket->reference);

    actingAs($this->user);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('ticket.is_from_slack', true)->has('ticket.reporter_avatar')
    );

    $this->call('POST', route('webhooks.slack_ticket'), $params, [], [], $this->transformHeadersToServerVars(['X-Slack-Request-Timestamp' => $timestamp, 'X-Slack-Signature' => 'v0=bad']), $body)->assertStatus(401);
});

test('deployed commits that name a ticket are recorded on it once', function () {
    $ticket     = StoreTicket::make()->action($this->group, ['subject' => 'Labels blank']);
    $deployment = AppDeployment::create(['commit_hash' => 'abc123abc123', 'semantic_version' => 'v2.360.0']);
    $commits    = [
        ['hash' => 'deadbeef0001', 'subject' => "🐛 dispatching: labels print blank, fixes {$ticket->reference}", 'name' => 'x', 'email' => 'x@x'],
        ['hash' => 'deadbeef0002', 'subject' => 'chore: unrelated', 'name' => 'x', 'email' => 'x@x'],
        ['hash' => 'deadbeef0003', 'subject' => 'HELP-999999 does not exist', 'name' => 'x', 'email' => 'x@x'],
    ];

    $linked = LinkTicketsToAppDeployment::run($deployment, $commits);
    LinkTicketsToAppDeployment::run($deployment, $commits);

    $ticket->refresh();
    expect($linked)->toBe([$ticket->reference => 1])
        ->and($ticket->data['commits'])->toHaveCount(1)
        ->and($ticket->data['commits'][0]['version'])->toBe('v2.360.0')
        ->and($ticket->comments()->count())->toBe(1)
        ->and($ticket->comments()->first()->body)->toContain('v2.360.0')
        ->and(TicketResource::make($ticket)->resolve()['commits'][0]['hash'])->toBe('deadbeef0001');
});

test('slack ticket reaction raises a ticket from the message and mirrors replies into its thread', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $this->user->update(['email' => 'raul@example.com']);
    Http::fake([
        'slack.com/api/users.info*'            => Http::response(['ok' => true, 'user' => ['name' => $this->user->username, 'real_name' => 'Somebody Else', 'profile' => ['email' => 'raul-on-slack@example.com']]]),
        'slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [[
            'user'  => 'U1',
            'text'  => "Picking shows 1 instead of 3\nFaire FPGB",
            'files' => [['id' => 'F1', 'name' => 'shot.png', 'mimetype' => 'image/png', 'size' => 100, 'url_private_download' => 'https://files.slack.com/shot.png']],
        ]]]),
        'files.slack.com/*'                    => Http::response(UploadedFile::fake()->image('shot.png', 10, 10)->getContent(), 200, ['Content-Type' => 'image/png']),
        'slack.com/api/chat.postMessage'       => Http::response(['ok' => true]),
    ]);
    Auth::logout();

    $post = function (array $payload, string $signature = null) {
        $body      = json_encode($payload);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => $signature ?? 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    $post(['type' => 'url_verification', 'challenge' => 'abc'])->assertOk()->assertJson(['challenge' => 'abc']);
    $post(['type' => 'event_callback', 'event' => []], 'v0=bad')->assertStatus(401);

    $event = ['type' => 'event_callback', 'event' => ['type' => 'reaction_added', 'reaction' => 'ticket', 'user' => 'U2', 'item' => ['type' => 'message', 'channel' => 'C1', 'ts' => '1789138198.657369']]];
    $post($event)->assertOk();
    $post($event)->assertOk();
    $post(['type' => 'event_callback', 'event' => ['type' => 'reaction_added', 'reaction' => 'eyes', 'item' => ['type' => 'message', 'channel' => 'C1', 'ts' => '2']]])->assertOk();

    $ticket = Ticket::where('subject', 'Picking shows 1 instead of 3')->sole();
    expect($ticket->description)->toBe('Faire FPGB')
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($this->user->fresh()->slack_user_id)->toBe('U1')
        ->and($ticket->data['slack']['ts'])->toBe('1789138198.657369')
        ->and($ticket->getMedia('ticket_images')->count())->toBe(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'conversations.history'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'files.slack.com'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '1789138198.657369' && str_contains($request['text'], $ticket->reference));

    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Fixed, please check']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::WAITING->value]);
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'ts' => '1789138199.1', 'thread_ts' => '1789138198.657369', 'text' => 'It is FPGB-123']])->assertOk();
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'bot_id' => 'B1', 'ts' => '1789138199.2', 'thread_ts' => '1789138198.657369', 'text' => 'HELP-1 is now Waiting']])->assertOk();
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'ts' => '1789138199.3', 'text' => 'unrelated top level message']])->assertOk();
    $fromModal = StoreTicket::make()->action($this->group, ['subject' => 'Born in aiku', 'data' => ['slack_alert' => ['channel' => 'C9', 'ts' => '55.1']]]);
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C9', 'user' => 'U1', 'ts' => '55.2', 'thread_ts' => '55.1', 'text' => 'reply under the alert card']])->assertOk();
    expect($fromModal->comments()->pluck('body')->all())->toBe(['reply under the alert card']);
    StoreTicketComment::make()->action($fromModal, $this->user, ['body' => 'answer from aiku']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '55.1' && str_contains($request['text'], 'answer from aiku'));
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::ANSWERED)
        ->and($ticket->comments()->pluck('body')->all())->toBe(['Fixed, please check', 'It is FPGB-123']);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'It is FPGB-123'));
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'Fixed, please check'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_ends_with($request['text'], 'Done'));

    $bare = StoreTicketFromSlack::run($this->group, ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '7', 'subject' => 'No clue where <https://app.aiku.io/org/aw/shops/uk|here>']);
    expect($bare->data['reference_url'])->toBe('https://app.aiku.io/org/aw/shops/uk');
    $bare = StoreTicketFromSlack::run($this->group, ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '8', 'subject' => 'Nothing to go on']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '8' && str_contains($request['text'], 'screenshot'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '7' && str_contains($request['text'], 'screenshot'));

    $orphan = StoreTicket::make()->action($this->group, ['subject' => 'Unknown reporter', 'data' => ['slack' => ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '9']]]);
    expect($orphan->reporter_id)->toBeNull()
        ->and(RepairSlackTicketReporters::run())->toBe(1)
        ->and($orphan->fresh()->reporter_id)->toBe($this->user->id);
});

test('slack shortcut opens the ticket modal and only its submit creates the ticket', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake([
        'slack.com/api/users.info*'      => Http::response(['ok' => true, 'user' => ['name' => $this->user->username, 'profile' => ['email' => 'nobody@example.com']]]),
        'slack.com/api/views.open'       => Http::response(['ok' => true]),
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        'files.slack.com/*'              => Http::response(UploadedFile::fake()->image('modal.png', 10, 10)->getContent(), 200, ['Content-Type' => 'image/png']),
    ]);
    Auth::logout();

    $post = function (array $payload) {
        $body      = http_build_query(['payload' => json_encode($payload)]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/x-www-form-urlencoded',
        ];

        return $this->call('POST', route('webhooks.slack_interactivity'), ['payload' => json_encode($payload)], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    $post(['type' => 'message_action', 'callback_id' => 'raise_ticket', 'trigger_id' => 'T1', 'user' => ['id' => 'U1'], 'channel' => ['id' => 'C1'], 'message' => ['ts' => '55.1', 'text' => "Shortcut labels blank\nSK printer only"]])->assertOk();
    Http::assertSent(function ($request) {
        $view = $request['view'] ?? null;

        return str_contains($request->url(), 'views.open')
            && $request['trigger_id'] === 'T1'
            && $view['blocks'][0]['element']['initial_value'] === 'Shortcut labels blank'
            && $view['blocks'][1]['element']['initial_value'] === 'SK printer only'
            && $view['blocks'][2]['optional'] === false
            && json_decode($view['private_metadata'], true) === ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '55.1'];
    });
    expect(Ticket::where('subject', 'Shortcut labels blank')->exists())->toBeFalse();

    $post([
        'type' => 'view_submission',
        'user' => ['id' => 'U1'],
        'view' => [
            'callback_id'      => 'raise_ticket',
            'private_metadata' => json_encode(['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '55.1']),
            'state'            => ['values' => [
                'subject'       => ['value' => ['value' => 'Shortcut labels blank']],
                'description'   => ['value' => ['value' => 'SK printer only']],
                'reference_url' => ['value' => ['value' => 'https://app.aiku.io/org/aw/warehouses/ac']],
                'files'         => ['value' => ['files' => [['id' => 'F9', 'name' => 'modal.png', 'mimetype' => 'image/png', 'size' => 100, 'url_private_download' => 'https://files.slack.com/modal.png']]]],
            ]],
        ],
    ])->assertOk();

    $ticket = Ticket::where('subject', 'Shortcut labels blank')->sole();
    expect($ticket->description)->toBe('SK printer only')
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($ticket->data['reference_url'])->toBe('https://app.aiku.io/org/aw/warehouses/ac')
        ->and($ticket->data['slack']['ts'])->toBe('55.1')
        ->and($ticket->getMedia('ticket_images')->count())->toBe(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '55.1' && str_contains($request['text'], $ticket->reference));
});

test('only the help desk manages tickets, everyone else reports, comments and closes their own', function () {
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $helper   = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $helper->assignRole('help-desk-clerk');
    $boss = User::factory()->create(['group_id' => $this->group->id]);
    $boss->assignRole('help-desk-supervisor');

    actingAs($reporter);
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Mine', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    $other  = StoreTicket::make()->action($this->group, ['subject' => 'Not mine']);

    get(route('grp.tickets.show', $ticket->reference))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false)->where('is_reporter', true));
    get(route('grp.tickets.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketsDashboard')->where('can_manage', false)->has('mine', 1)->missing('queue'));
    get(route('grp.tickets.reports'))->assertOk();
    get(route('grp.tickets.board'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false));
    patch(route('grp.models.ticket.update', $other->id), ['status' => 'resolved'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['priority' => 'urgent'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertForbidden();
    post(route('grp.models.ticket.comment.store', $other->id), ['body' => 'secret?'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::OPEN)
        ->and($other->comments()->sole()->body)->toBe('secret?');

    $manager = User::factory()->create(['group_id' => $this->group->id]);
    $manager->assignRole('group-admin');
    actingAs($manager);
    get(route('grp.tickets.show', $other->reference))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false));
    get(route('grp.tickets.board'))->assertOk();
    patch(route('grp.models.ticket.update', $other->id), ['status' => 'resolved'])->assertForbidden();

    actingAs($helper);
    get(route('grp.tickets.create'))->assertOk();
    post(route('grp.models.ticket.store'), ['subject' => 'Engineers report too'])->assertRedirect();
    get(route('grp.tickets.show', $other->reference))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true));
    get(route('grp.tickets.reports'))->assertOk();
    get(route('grp.tickets.board'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true));
    patch(route('grp.models.ticket.update', $other->id), ['is_confidential' => true])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $helper->id])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['priority' => 'urgent'])->assertForbidden();
    UpdateTicket::make()->action($other, ['assignee_id' => $helper->id]);
    patch(route('grp.models.ticket.update', $other->id), ['priority' => 'urgent'])->assertRedirect();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => null])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $boss->id])->assertRedirect();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $helper->id])->assertForbidden();
    UpdateTicket::make()->action($other->refresh(), ['assignee_id' => $helper->id]);
    post(route('grp.models.ticket.comment.store', $other->id), ['body' => 'from the page'])->assertRedirect();
    expect($other->fresh()->priority)->toBe(ChatPriorityEnum::URGENT)
        ->and($other->fresh()->assignee_id)->toBe($helper->id)
        ->and($other->comments()->where('body', 'from the page')->count())->toBe(1)
        ->and($other->comments()->where('body', 'like', 'Passed from%')->count())->toBe(0);

    actingAs($boss);
    get(route('grp.tickets.reports'))->assertOk();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $boss->id])->assertRedirect();
    expect($other->fresh()->assignee_id)->toBe($boss->id);

    AikuServer::actingAs($reporter)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertHasErrors();
    AikuServer::actingAs($reporter)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'cancelled'])->assertHasErrors();
    AikuServer::actingAs($reporter)->tool(TicketsTool::class, ['reference' => $ticket->reference])->assertHasErrors();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertHasErrors();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertOk();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $reporter->username])->assertHasErrors();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $helper->username])->assertOk();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $boss->username])->assertOk();
    AikuServer::actingAs($reporter)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'comment' => 'reporters use the page'])->assertHasErrors();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'comment' => 'not my ticket any more'])->assertOk();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'comment' => 'on it'])->assertOk();
    $unassigned = StoreTicket::make()->action($this->group, ['subject' => 'Nobody owns me']);
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $unassigned->reference, 'comment' => 'too early'])->assertHasErrors();
    expect($other->comments()->where('body', 'on it')->value('author_id'))->toBe($boss->id)
        ->and($other->comments()->where('body', 'not my ticket any more')->value('author_id'))->toBe($helper->id)
        ->and($other->comments()->where('body', 'reporters use the page')->exists())->toBeFalse()
        ->and($unassigned->comments()->exists())->toBeFalse();

    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'acting_as' => $helper->username, 'comment' => 'said at the desk'])->assertOk();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'acting_as' => 'nobody-here', 'comment' => 'ghost'])->assertHasErrors();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'acting_as' => $boss->username, 'comment' => 'impersonating the boss'])->assertHasErrors();
    expect($other->comments()->where('body', 'said at the desk')->value('author_id'))->toBe($helper->id)
        ->and($other->comments()->whereIn('body', ['ghost', 'impersonating the boss'])->exists())->toBeFalse();

    $oldNote = $other->comments()->create(['body' => 'old internal note', 'is_internal' => true]);
    expect($other->commentsVisibleTo($helper)->whereKey($oldNote->id)->exists())->toBeTrue()
        ->and($other->commentsVisibleTo($reporter)->whereKey($oldNote->id)->exists())->toBeTrue()
        ->and($other->commentsVisibleTo($boss)->whereKey($oldNote->id)->exists())->toBeTrue();
    actingAs($helper);
    patch(route('grp.models.ticket.comment.toggle_visibility', $oldNote->id))->assertForbidden();
    actingAs($boss);
    patch(route('grp.models.ticket.comment.toggle_visibility', $oldNote->id))->assertRedirect();
    expect($oldNote->fresh()->is_lead_only)->toBeTrue()
        ->and($other->commentsVisibleTo($helper)->whereKey($oldNote->id)->exists())->toBeFalse()
        ->and($other->commentsVisibleTo($boss)->whereKey($oldNote->id)->exists())->toBeTrue();
    patch(route('grp.models.ticket.comment.toggle_visibility', $oldNote->id))->assertRedirect();
    expect($oldNote->fresh()->is_lead_only)->toBeFalse();

    actingAs($helper);
    delete(route('grp.models.ticket.delete', $other->id))->assertForbidden();
    actingAs($boss);
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $boss->id, 'is_confidential' => true])->assertRedirect();
    expect($other->fresh()->is_confidential)->toBeTrue();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertHasErrors();

    actingAs($boss);
    delete(route('grp.models.ticket.delete', $other->id))->assertRedirect(route('grp.tickets.index'));
    expect(Ticket::find($other->id))->toBeNull()
        ->and(Ticket::withTrashed()->find($other->id))->not->toBeNull();
});

test('board only shows tickets closed in the last 24 hours', function () {
    $fresh = StoreTicket::make()->action($this->group, ['subject' => 'Closed today']);
    $stale = StoreTicket::make()->action($this->group, ['subject' => 'Closed last week']);
    UpdateTicket::make()->action($fresh, ['status' => TicketStatusEnum::RESOLVED->value]);
    UpdateTicket::make()->action($stale, ['status' => TicketStatusEnum::RESOLVED->value]);
    $stale->update(['closed_at' => now()->subDays(7)]);

    get(route('grp.tickets.board', ['periods' => ['closed' => '24h']]))->assertInertia(function (AssertableInertia $page) use ($fresh, $stale) {
        $columns = collect($page->toArray()['props']['columns']);
        expect($columns->firstWhere('key', 'open')['period'])->toBeNull();
        $references = $columns->firstWhere('key', 'closed')['tickets'];
        $references = collect($references)->pluck('reference');

        expect($references)->toContain($fresh->reference)
            ->and($references)->not->toContain($stale->reference);
    });
});

test('list and board show only tickets created in the chosen interval', function () {
    $fresh = StoreTicket::make()->action($this->group, ['subject' => 'Raised today']);
    $old   = StoreTicket::make()->action($this->group, ['subject' => 'Raised last year']);
    $old->update(['created_at' => now()->subYear()->subMonth()]);

    get(route('grp.tickets.list', ['created' => 'tdy']))->assertInertia(function (AssertableInertia $page) use ($fresh, $old) {
        $references = collect($page->toArray()['props']['data']['data'])->pluck('reference');
        expect($references)->toContain($fresh->reference)->and($references)->not->toContain($old->reference)
            ->and($page->toArray()['props']['createdInterval'])->toBe('tdy');
    });

    $old->update(['created_at' => now(), 'priority' => 'urgent']);
    get(route('grp.tickets.list', ['elements' => ['priority' => 'urgent']]))->assertInertia(function (AssertableInertia $page) use ($fresh, $old) {
        $references = collect($page->toArray()['props']['data']['data'])->pluck('reference');
        expect($references)->toContain($old->reference)->and($references)->not->toContain($fresh->reference);
    });
    $old->update(['created_at' => now()->subYear()->subMonth()]);

    $fresh->update(['created_at' => now()->subHours(2)]);
    get(route('grp.tickets.list', ['created' => '1h']))->assertInertia(fn (AssertableInertia $page) => expect(collect($page->toArray()['props']['data']['data'])->pluck('reference'))->not->toContain($fresh->reference));
    get(route('grp.tickets.list', ['created' => '3h']))->assertInertia(fn (AssertableInertia $page) => expect(collect($page->toArray()['props']['data']['data'])->pluck('reference'))->toContain($fresh->reference));

    get(route('grp.tickets.board', ['created' => '24h']))->assertInertia(function (AssertableInertia $page) use ($fresh, $old) {
        $references = collect($page->toArray()['props']['columns'])->flatMap(fn ($column) => $column['tickets'])->pluck('reference');
        expect($references)->toContain($fresh->reference)->and($references)->not->toContain($old->reference);
    });
});

test('assigning and starting a ticket stamps its lifecycle dates', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Lifecycle']);
    expect($ticket->status)->toBe(TicketStatusEnum::OPEN)
        ->and($ticket->assigned_at)->toBeNull()
        ->and($ticket->started_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id]);
    expect($ticket->status)->toBe(TicketStatusEnum::ASSIGNED)
        ->and($ticket->assigned_at)->not->toBeNull()
        ->and($ticket->started_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    $startedAt = $ticket->started_at;
    expect($startedAt)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::WAITING->value]);
    expect($ticket->started_at->eq($startedAt))->toBeTrue()
        ->and($ticket->closed_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    expect($ticket->resolved_at)->not->toBeNull()->and($ticket->closed_at)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::OPEN->value]);
    expect($ticket->status)->toBe(TicketStatusEnum::ASSIGNED)
        ->and($ticket->started_at)->toBeNull()
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['assignee_id' => null]);
    expect($ticket->status)->toBe(TicketStatusEnum::OPEN)
        ->and($ticket->assigned_at)->toBeNull();
});

test('an engineer asks QA to check, QA answers with a verdict and the engineer still decides when it is done', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Totals wrong', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);

    actingAs($qa);
    get(route('grp.tickets.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false)->where('can_qa', true)->has('qa_queue', 0));
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertForbidden();
    get(route('grp.tickets.create'))->assertOk();

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'passed'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::REQUESTED)
        ->and($ticket->qa_requested_at)->not->toBeNull()
        ->and($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS);

    actingAs($qa);
    get(route('grp.tickets.index'))->assertInertia(fn (AssertableInertia $page) => $page->has('qa_queue', 1));
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_qa', true)->where('ticket.qa_status', 'requested'));
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'failed', 'qa_note' => 'Still wrong with a voucher'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::FAILED)
        ->and($ticket->qa_user_id)->toBe($qa->id)
        ->and($ticket->qa_checked_at)->not->toBeNull()
        ->and($ticket->comments()->latest('id')->value('body'))->toBe('QA failed: Still wrong with a voucher');

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested', 'qa_user_id' => $reporter->id])->assertSessionHasErrors('qa_user_id');
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested', 'qa_user_id' => $qa->id])->assertRedirect();
    expect($ticket->refresh()->qa_user_id)->toBe($qa->id);
    actingAs($qa);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'passed'])->assertRedirect();
    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::PASSED)
        ->and($ticket->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->where('body', 'QA passed')->exists())->toBeTrue();
});

test('ticket badges count my tickets and the engineer queue, and engineers hear of new tickets in-app', function () {
    Notification::fake();
    Event::fake([BroadcastTicketBadgeUpdate::class]);

    $count    = fn (User $user, string $slot, string $key) => GetTicketBadgeData::run($user)[$slot][$key]['count'];
    $baseline = GetTicketBadgeData::run($this->user)['queue'];

    $reporter = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $ticket   = StoreTicket::make()->action($this->group, ['subject' => 'Badge me', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    Notification::assertSentTo($this->user, TicketNotification::class, fn ($notification, $channels) => $channels === ['database'] && str_contains($notification->subject, $ticket->reference));
    Notification::assertNotSentTo($reporter, TicketNotification::class);
    Event::assertDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $this->user->id && $event->notification !== null);

    expect(GetTicketBadgeData::run($reporter)['queue'])->toBeNull()
        ->and($count($reporter, 'mine', 'to_do'))->toBe(1)
        ->and($count($reporter, 'mine', 'in_progress'))->toBe(0)
        ->and($count($reporter, 'mine', 'waiting'))->toBe(0)
        ->and($count($this->user, 'queue', 'new_unassigned'))->toBe($baseline['new_unassigned']['count'] + 1)
        ->and($count($this->user, 'queue', 'todo_week'))->toBe($baseline['todo_week']['count'] + 1)
        ->and($count($this->user, 'queue', 'overdue'))->toBe($baseline['overdue']['count'])
        ->and($count($this->user, 'queue', 'assigned_to_me'))->toBe($baseline['assigned_to_me']['count']);

    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id, 'status' => TicketStatusEnum::WAITING->value, 'question' => 'Which printer?']);
    $ticket->update(['created_at' => now()->subDays(2)]);

    expect($count($reporter, 'mine', 'waiting'))->toBe(1)
        ->and($count($this->user, 'queue', 'assigned_to_me'))->toBe($baseline['assigned_to_me']['count']);

    StoreTicketComment::make()->action($ticket, $reporter, ['body' => 'The red one']);
    Notification::assertSentTo($this->user, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'new comment'));

    expect($count($this->user, 'queue', 'assigned_to_me'))->toBe($baseline['assigned_to_me']['count'] + 1)
        ->and($count($this->user, 'queue', 'overdue'))->toBe($baseline['overdue']['count'] + 1);

    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    expect($count($reporter, 'mine', 'to_do') + $count($reporter, 'mine', 'in_progress') + $count($reporter, 'mine', 'waiting'))->toBe(0)
        ->and($count($this->user, 'queue', 'overdue'))->toBe($baseline['overdue']['count']);
});

test('tickets reports link to filtered lists by assignee and dates', function () {
    $mine = StoreTicket::make()->action($this->group, ['subject' => 'Assigned to me', 'assignee_id' => $this->user->id]);
    UpdateTicket::make()->action($mine, ['status' => TicketStatusEnum::RESOLVED->value]);
    StoreTicket::make()->action($this->group, ['subject' => 'Not assigned to me']);

    $from = $mine->fresh()->created_at->toDateString();

    get(route('grp.tickets.list', ['filter' => ['assignee' => $this->user->username]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('data.data', Ticket::where('assignee_id', $this->user->id)->count()));

    get(route('grp.tickets.list', ['filter' => ['created_since' => $from]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('data.data', Ticket::where('created_at', '>=', $from)->count()));

    get(route('grp.tickets.list', ['filter' => ['resolved_since' => $from]]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('data.data', Ticket::where('resolved_at', '>=', $from)->count()));

    $stats = ShowTicketsReports::make()->handle($this->group, '1w');
    expect($stats)->toHaveKey('from');
    foreach ($stats['assignees'] as $row) {
        expect($row)->toHaveKeys(['username', 'short_name']);
    }
});

test('engineers raise task and qa tickets, staff cannot, and internal tickets stay out of the to-do rail count', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $staff    = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');

    expect(collect(TicketKindEnum::raisableBy($engineer))->pluck('value')->all())->toBe(['bug', 'feature', 'task', 'qa'])
        ->and(collect(TicketKindEnum::raisableBy($staff))->pluck('value')->all())->toBe(['bug', 'feature']);

    $todoBefore = GetTicketBadgeData::run($engineer)['queue']['todo_week']['count'];

    actingAs($staff);
    post(route('grp.models.ticket.store'), ['subject' => 'Do it for me', 'kind' => 'task'])->assertSessionHasErrors('kind');

    actingAs($engineer);
    post(route('grp.models.ticket.store'), ['subject' => 'Please test totals', 'kind' => 'qa'])->assertSessionHasNoErrors();
    post(route('grp.models.ticket.store'), ['subject' => 'Printer blank', 'kind' => 'bug'])->assertSessionHasNoErrors();

    $qaTicket = Ticket::where('subject', 'Please test totals')->first();
    expect($qaTicket->kind)->toBe(TicketKindEnum::QA)
        ->and($qaTicket->defaultWaitingHours())->toBe(14 * 24)
        ->and(GetTicketBadgeData::run($engineer)['queue']['todo_week']['count'])->toBe($todoBefore + 1);
});

test('ticket search ranks subject over description over comments, understands key:value tokens and jumps to a reference', function () {
    $bySubject     = StoreTicket::make()->action($this->group, ['subject' => 'Email marketing broken', 'description' => 'nothing here', 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);
    $byDescription = StoreTicket::make()->action($this->group, ['subject' => 'Something else', 'description' => 'The email marketing tool times out']);
    $byComment     = StoreTicket::make()->action($this->group, ['subject' => 'Unrelated', 'description' => 'unrelated']);
    StoreTicketComment::make()->handle($byComment, $this->user, ['body' => 'Same as the email marketing bug'], mirrorToSlack: false, notifyUsers: false);
    $byInternal    = StoreTicket::make()->action($this->group, ['subject' => 'Quiet', 'description' => 'quiet']);
    StoreTicketComment::make()->handle($byInternal, $this->user, ['body' => 'email marketing note'], mirrorToSlack: false, notifyUsers: false)->update(['is_internal' => true]);
    $other         = StoreTicket::make()->action($this->group, ['subject' => 'Invoice PDF export', 'description' => 'nothing']);

    $search = function (string $q) {
        $hits = collect();
        get(route('grp.tickets.list', ['filter' => ['global' => $q]]))->assertInertia(function (AssertableInertia $page) use (&$hits) {
            $hits = collect($page->toArray()['props']['data']['data']);
        });

        return $hits;
    };

    $hits = $search('email marke');
    expect($hits->pluck('reference')->take(2)->all())->toBe([$bySubject->reference, $byDescription->reference])
        ->and($hits->pluck('reference')->slice(2)->sort()->values()->all())->toBe(collect([$byComment->reference, $byInternal->reference])->sort()->values()->all())
        ->and($hits->first()['search_snippet'])->toContain('<mark>Email</mark>')
        ->and($search('email -tool')->pluck('reference'))->not->toContain($byDescription->reference)
        ->and($search('"marketing tool"')->pluck('reference')->all())->toBe([$byDescription->reference])
        ->and($search('email status:open reporter:me')->pluck('reference'))->toContain($bySubject->reference)
        ->and($search('email status:resolved'))->toBeEmpty()
        ->and($search('email is:unassigned after:'.now()->toDateString())->count())->toBe(4)
        ->and($search('email before:'.now()->toDateString()))->toBeEmpty()
        ->and($search('email assignee:'.$this->user->username))->toBeEmpty();

    foreach ([(string) $other->number, 'help-'.$other->number, 'HELP'.$other->number, 'https://app.aiku.io/tickets/'.$other->reference.'?tab=comments'] as $reference) {
        expect($search($reference)->pluck('reference')->all())->toBe([$other->reference], $reference);
    }

    $other->update(['subject' => 'Renamed to email digest']);
    expect($search('digest')->pluck('reference')->all())->toBe([$other->reference])
        ->and($search('emial digest')->pluck('reference')->all())->toBe([$other->reference])
        ->and($search('email marketting')->pluck('reference')->first())->toBe($bySubject->reference);

    $staff = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $staff->removeRole('group-admin');
    actingAs($staff);
    expect($search('email')->pluck('reference'))->not->toContain($byInternal->reference)
        ->and(get(route('grp.search.index', ['q' => 'email marke', 'route_src' => 'grp.tickets.board']))->assertOk()->json('results.tickets.*.code'))->toContain($bySubject->reference)
        ->and(SearchTickets::run((string) $other->number)['results']['tickets'][0]['href'])->toBe(route('grp.tickets.show', $other->reference))
        ->and(get(route('grp.search.index', ['q' => strtolower($other->reference), 'route_src' => 'grp.dashboard.show']))->assertOk()->json('results.tickets.*.code'))->toBe([$other->reference]);
});

test('only the assignee and supervisors change kind and module, and no ticket is turned into or out of an escalation', function () {
    setPermissionsTeamId($this->group->id);
    $clerk    = User::factory()->create(['group_id' => $this->group->id]);
    $assignee = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');
    $assignee->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Kind rules', 'kind' => 'bug', 'assignee_id' => $assignee->id]);

    actingAs($clerk);
    patch(route('grp.models.ticket.update', $ticket->id), ['kind' => 'feature'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['module' => 'dispatching'])->assertForbidden();
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonPath('can_change_kind_module', false);

    actingAs($assignee);
    patch(route('grp.models.ticket.update', $ticket->id), ['kind' => 'feature', 'module' => 'dispatching'])->assertRedirect()->assertSessionHasNoErrors();
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()
        ->assertJsonPath('can_change_kind_module', true)
        ->assertJsonPath('ticket.reference', $ticket->reference)
        ->assertJsonStructure(['ticket', 'options' => ['kinds', 'modules', 'assignees'], 'can_manage', 'can_assign', 'routes' => ['update', 'escalate']]);

    actingAs($this->user);
    patch(route('grp.models.ticket.update', $ticket->id), ['kind' => 'bug'])->assertRedirect()->assertSessionHasNoErrors();
    patch(route('grp.models.ticket.update', $ticket->id), ['kind' => 'escalation'])->assertSessionHasErrors('kind');
    expect($ticket->fresh()->kind?->value)->toBe('bug')
        ->and($ticket->fresh()->module?->value)->toBe('dispatching');

    $escalated = StoreTicket::make()->action($this->group, ['subject' => 'From a customer', 'kind' => 'escalation']);
    patch(route('grp.models.ticket.update', $escalated->id), ['kind' => 'bug'])->assertSessionHasErrors('kind');
    patch(route('grp.models.ticket.update', $escalated->id), ['kind' => 'escalation'])->assertSessionHasNoErrors();
    expect(fn () => UpdateTicket::make()->action($escalated->fresh(), ['kind' => 'feature']))->toThrow(Illuminate\Validation\ValidationException::class)
        ->and($escalated->fresh()->kind?->value)->toBe('escalation');

    $ticket->update(['is_confidential' => true]);
    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    get(route('grp.json.ticket.controls', $ticket->id))->assertForbidden();
});

test('ticket comment and history order are saved per user and come back on the ticket page', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Order settings']);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments_newest_first', true)->where('history_newest_first', true)
    );

    patch(route('grp.models.profile.update'), ['ticket_comments_newest_first' => false])->assertSessionHasNoErrors();
    patch(route('grp.models.profile.update'), ['ticket_history_newest_first' => false])->assertSessionHasNoErrors();

    expect($this->user->fresh()->settings)->toMatchArray(['ticket_comments_newest_first' => false, 'ticket_history_newest_first' => false]);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments_newest_first', false)->where('history_newest_first', false)
    );
});

test('the attachment gallery lists ticket and visible comment files newest first, with thumbnails for images', function () {
    $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n");

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Gallery', 'images' => [UploadedFile::fake()->image('screen.png', 200, 100), $pdf('spec.pdf')]]);
    StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$pdf('reply.pdf')]]);
    StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$pdf('internal.pdf')]])->update(['is_internal' => true]);

    $gallery = collect($ticket->attachmentGalleryFor($this->user));
    $specPdf = $ticket->getMedia('ticket_attachments')->first();

    expect($gallery->pluck('name')->all())->toBe(['internal.pdf', 'reply.pdf', 'spec.pdf', 'screen.png'])
        ->and($gallery->firstWhere('name', 'screen.png')['thumbnail'])->toHaveKey('original')
        ->and($gallery->firstWhere('name', 'spec.pdf')['thumbnail'])->toBeNull()
        ->and($gallery->firstWhere('name', 'spec.pdf')['url'])->toBe(route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $specPdf->ulid]));

    setPermissionsTeamId($this->group->id);
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');
    expect(collect($ticket->attachmentGalleryFor($clerk))->pluck('name')->all())->toBe(['internal.pdf', 'reply.pdf', 'spec.pdf', 'screen.png']);

    $ticket->comments()->where('is_internal', true)->update(['is_lead_only' => true]);
    expect(collect($ticket->attachmentGalleryFor($clerk))->pluck('name')->all())->toBe(['reply.pdf', 'spec.pdf', 'screen.png'])
        ->and(collect($ticket->attachmentGalleryFor($this->user))->pluck('name')->all())->toContain('internal.pdf');

    get(route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $ticket->getMedia('ticket_images')->first()->ulid]))->assertOk();
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonCount(4, 'attachment_gallery');
});

test('internal notes are visible to any staff member including the reporter, lead only notes stay with leads and customers see neither', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Visibility']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'public reply']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'internal note'])->update(['is_internal' => true]);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'lead note'])->update(['is_internal' => true, 'is_lead_only' => true]);

    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    expect($ticket->commentsVisibleTo($reporter)->pluck('body')->sort()->values()->all())->toBe(['internal note', 'public reply'])
        ->and($ticket->commentsVisibleTo($this->user)->count())->toBe(3)
        ->and($ticket->commentsVisibleTo($this->webUser)->pluck('body')->all())->toBe(['public reply']);
});

test('done and cancel publish the closing comment together with the status change', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Close with a note']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved', 'status_comment' => 'Fixed the rounding in the totals'])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->where('body', 'Fixed the rounding in the totals')->where('is_internal', false)->value('author_id'))->toBe($this->user->id);

    $duplicate = StoreTicket::make()->action($this->group, ['subject' => 'Cancel with a note']);
    patch(route('grp.models.ticket.update', $duplicate->id), ['status' => 'cancelled', 'status_comment' => 'Duplicate of another ticket'])->assertRedirect()->assertSessionHasNoErrors();
    expect($duplicate->fresh()->status)->toBe(TicketStatusEnum::CANCELLED)
        ->and($duplicate->comments()->where('body', 'Duplicate of another ticket')->exists())->toBeTrue();

    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    actingAs($reporter);
    $own = StoreTicket::make()->action($this->group, ['subject' => 'Mine to close', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    patch(route('grp.models.ticket.update', $own->id), ['priority' => 'urgent', 'status_comment' => 'sneaky'])->assertForbidden();
    patch(route('grp.models.ticket.update', $own->id), ['status' => 'cancelled', 'status_comment' => 'Not needed any more'])->assertForbidden();
    expect($own->fresh()->status)->toBe(TicketStatusEnum::OPEN)
        ->and($own->comments()->where('body', 'Not needed any more')->exists())->toBeFalse();
});

test('the ticket write tool closes after next deployment and holds the comment until then', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ship it by MCP']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'assignee_id' => $this->user->id]);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'pending_deploy', 'comment' => 'Fixed, live after the deploy'])->assertOk();

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::PENDING_DEPLOY)
        ->and($ticket->comments()->count())->toBe(0)
        ->and(data_get($ticket->fresh()->data, 'deploy_comment.user_id'))->toBe($this->user->id);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('ticket.deploy_comment', 'Fixed, live after the deploy'));

    CloseTicketsAfterDeployment::run();

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->where('body', 'Fixed, live after the deploy')->sole()->author_id)->toBe($this->user->id);
});

test('a mentioned user is notified on the channels they chose and the plain comment notice is not doubled', function () {
    Notification::fake();
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake(['slack.com/*' => Http::response(['ok' => true])]);

    $reporter = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $reporter->update(['nickname' => 'Mentionee', 'slack_user_id' => 'U999', 'settings' => ['notifications' => ['ticket_mention' => ['slack'], 'ticket_comment' => ['email']]]]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Mention me']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Can you check this @mentionee?']);

    Notification::assertSentToTimes($reporter, TicketNotification::class, 1);
    Notification::assertSentTo($reporter, TicketNotification::class, fn ($notification, $channels) => str_contains($notification->subject, 'mentioned you') && $channels === ['database']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['channel'] === 'U999');
});

test('slack ticket direct message logs a refused delivery without failing and retries when slack is rate limited', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $user = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $user->update(['slack_user_id' => 'U404']);

    Http::fake(['slack.com/*' => Http::sequence()->push(['ok' => false, 'error' => 'user_not_found'])->push(['ok' => false, 'error' => 'ratelimited'], 429)]);
    \Illuminate\Support\Facades\Log::spy();
    \App\Actions\Helpers\Ticket\SendTicketSlackDirectMessage::run($user, 'Hello');
    \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context['error'] === 'user_not_found' && $context['permanent'] === true)->once();

    expect(fn () => \App\Actions\Helpers\Ticket\SendTicketSlackDirectMessage::run($user, 'Hello'))->toThrow(RuntimeException::class);
});

test('tickets sidebar link opens the board for lead engineers and the dashboard for everyone else', function () {
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');

    expect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($this->user)['tickets']['route']['name'])->toBe('grp.tickets.board')
        ->and(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($clerk)['tickets']['route']['name'])->toBe('grp.tickets.index');
});

test('jira ticket attachments missing from the ticket and comment media are copied once, tagged with their jira id', function () {
    User::factory()->create(['group_id' => $this->group->id, 'settings' => ['jira' => ['base_url' => 'https://jira.test/', 'email' => 'bot@test', 'api_token' => 'token']]]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Imported from Jira']);
    $ticket->update(['data' => ['jira_key' => 'HELP-9001']]);
    $comment = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'screenshot', 'is_internal' => false]);
    $existingFile = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($existingFile, "%PDF-1.4\n%%EOF\n");
    $comment->attachTicketFile($existingFile, 'already.pdf', 'application/pdf', ['jira_attachment_id' => '500']);

    $resolvedLongAgo = StoreTicket::make()->action($this->group, ['subject' => 'Resolved three weeks ago']);
    $resolvedLongAgo->update(['data' => ['jira_key' => 'HELP-9003'], 'status' => TicketStatusEnum::RESOLVED, 'resolved_at' => now()->subWeeks(3)]);

    Config::set('media-library.max_file_size', 10);

    Http::fake([
        'jira.test/rest/api/3/issue/HELP-9001*' => Http::response(['fields' => ['attachment' => [
            ['id' => '500', 'filename' => 'already.pdf', 'mimeType' => 'application/pdf', 'size' => 15, 'content' => 'https://jira.test/rest/api/3/attachment/content/500'],
            ['id' => 501, 'filename' => 'invoice.pdf', 'mimeType' => 'application/pdf', 'size' => 15, 'content' => 'https://jira.test/rest/api/3/attachment/content/501'],
        ]]]),
        'jira.test/rest/api/3/issue/HELP-9003*' => Http::response(['fields' => ['attachment' => [
            ['id' => '601', 'filename' => 'recording.mp4', 'mimeType' => 'video/mp4', 'size' => 15, 'content' => 'https://jira.test/rest/api/3/attachment/content/601'],
        ]]]),
        'jira.test/rest/api/3/attachment/content/501' => Http::response("%PDF-1.4\n%%EOF\n"),
    ]);

    expect(\App\Actions\Helpers\Ticket\ImportJiraTicketAttachments::make()->handle($resolvedLongAgo->fresh()))->toBe(0)
        ->and($resolvedLongAgo->fresh()->getMedia('ticket_attachments'))->toHaveCount(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'content/601'));

    $action = \App\Actions\Helpers\Ticket\ImportJiraTicketAttachments::make();

    expect($action->handle($ticket))->toBe(1)
        ->and(\App\Actions\Helpers\Ticket\ImportJiraTicketAttachments::make()->handle($ticket->fresh()))->toBe(0);

    $media = $ticket->fresh()->getMedia('ticket_attachments');

    expect($media)->toHaveCount(1)
        ->and($media->first()->name)->toBe('invoice.pdf')
        ->and($media->first()->getCustomProperty('source'))->toBe(['jira_attachment_id' => '501'])
        ->and($comment->fresh()->getMedia('ticket_attachments'))->toHaveCount(1)
        ->and(config('media-library.max_file_size'))->toBe(10);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'content/500'));
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('bot@test:token')));
});

test('jira comment authors are repaired from the jira reporter or the jira author email, leaving unknown authors empty', function () {
    User::factory()->create(['group_id' => $this->group->id, 'settings' => ['jira' => ['base_url' => 'https://jira.test', 'email' => 'bot@test', 'api_token' => 'token']]]);

    $reporter  = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id, 'email' => 'colleague-'.uniqid().'@test.com']);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Imported comments']);
    $ticket->update(['data' => ['jira_key' => 'HELP-9002'], 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    $byReporter  = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from reporter', 'is_internal' => false, 'created_at' => '2026-09-14 11:48:58']);
    $byColleague = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from colleague', 'is_internal' => false, 'created_at' => '2026-09-14 12:00:00']);
    $byStranger  = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from stranger', 'is_internal' => false, 'created_at' => '2026-09-14 13:00:00']);

    $developer         = User::factory()->create(['group_id' => $this->group->id, 'username' => 'dev'.strtolower(\Illuminate\Support\Str::random(8)), 'status' => true]);
    $byDeveloper       = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from developer', 'is_internal' => false, 'created_at' => '2026-09-14 14:00:00']);
    $byCustomerNamed   = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from customer account named like a user', 'is_internal' => false, 'created_at' => '2026-09-14 15:00:00']);
    $byJiraAutomation  = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'No reply for 14 days', 'is_internal' => false, 'created_at' => '2026-09-14 16:00:00']);

    Http::fake(['jira.test/rest/api/3/issue/HELP-9002*' => Http::response(['fields' => [
        'reporter' => ['accountId' => 'acc-reporter'],
        'comment'  => ['comments' => [
            ['created' => '2026-09-14T11:48:58.135+0200', 'author' => ['accountId' => 'acc-reporter', 'emailAddress' => 'shared@inbox.test']],
            ['created' => '2026-09-14T12:00:00.000+0200', 'author' => ['accountId' => 'acc-colleague', 'emailAddress' => strtoupper($colleague->email)]],
            ['created' => '2026-09-14T13:00:00.000+0200', 'author' => ['accountId' => 'acc-stranger', 'emailAddress' => 'nobody@nowhere.test']],
            ['created' => '2026-09-14T14:00:00.000+0200', 'author' => ['accountId' => 'acc-developer', 'accountType' => 'atlassian', 'displayName' => ucfirst($developer->username).' Surname']],
            ['created' => '2026-09-14T15:00:00.000+0200', 'author' => ['accountId' => 'acc-customer', 'accountType' => 'customer', 'displayName' => $developer->username]],
            ['created' => '2026-09-14T16:00:00.000+0200', 'author' => ['accountId' => 'acc-automation', 'accountType' => 'app', 'displayName' => 'Automation for Jira']],
        ]],
    ]])]);

    expect(\App\Actions\Helpers\Ticket\RepairJiraTicketCommentAuthors::make()->handle($ticket->fresh()))->toBe(3)
        ->and($byReporter->fresh()->author_id)->toBe($reporter->id)
        ->and($byColleague->fresh()->author_id)->toBe($colleague->id)
        ->and($byStranger->fresh()->author_id)->toBeNull()
        ->and($byDeveloper->fresh()->author_id)->toBe($developer->id)
        ->and($byCustomerNamed->fresh()->author_id)->toBeNull()
        ->and($byJiraAutomation->fresh()->author_id)->toBeNull();
});

test('assigning a ticket pushes fresh badge counts to every engineer and QA user and the previous assignee', function () {
    setPermissionsTeamId($this->group->id);
    $otherEngineer = User::factory()->create(['group_id' => $this->group->id]);
    $otherEngineer->assignRole('help-desk-clerk');
    $qaUser = User::factory()->create(['group_id' => $this->group->id]);
    $qaUser->assignRole(\App\Enums\SysAdmin\Authorisation\RolesEnum::QA->value);
    $previousAssignee = User::factory()->create(['group_id' => $this->group->id]);
    $previousAssignee->assignRole('help-desk-clerk');
    $outsider = User::factory()->create(['group_id' => $this->group->id]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Queue push', 'assignee_id' => $previousAssignee->id]);

    Event::fake([BroadcastTicketBadgeUpdate::class]);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id]);

    foreach ([$this->user, $otherEngineer, $qaUser, $previousAssignee] as $expectedUser) {
        Event::assertDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $expectedUser->id);
    }
    Event::assertNotDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $outsider->id);

    Event::fake([BroadcastTicketBadgeUpdate::class]);
    StoreTicketComment::make()->action($ticket->fresh(), $this->user, ['body' => 'on it']);
    Event::assertNotDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $otherEngineer->id);
});

test('the assignee edits status, priority, assignee, kind and module of their ticket from the list', function () {
    setPermissionsTeamId($this->group->id);
    $assignee  = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id]);
    $assignee->assignRole('help-desk-clerk');
    $colleague->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Edit from the list', 'kind' => 'bug', 'assignee_id' => $assignee->id]);

    actingAs($assignee);
    get(route('grp.tickets.list'))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/Tickets')
            ->where('updateRoute', 'grp.models.ticket.update')
            ->has('options.priorities')
            ->has('options.kinds')
            ->has('options.modules')
            ->where('options.assignees', fn ($assignees) => collect($assignees)->pluck('value')->contains($colleague->id))
    );

    $update = fn (array $data) => patch(route('grp.models.ticket.update', $ticket->id), $data)->assertRedirect()->assertSessionHasNoErrors();

    $update(['status' => TicketStatusEnum::IN_PROGRESS->value]);
    $update(['priority' => 'urgent']);
    $update(['kind' => 'feature']);
    $update(['module' => 'dispatching']);
    $update(['status' => TicketStatusEnum::WAITING->value, 'question' => 'Which order?', 'waiting_hours' => 24]);
    $update(['status' => TicketStatusEnum::IN_PROGRESS->value]);
    $update(['assignee_id' => $colleague->id]);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS)
        ->and($ticket->priority->value)->toBe('urgent')
        ->and($ticket->kind?->value)->toBe('feature')
        ->and($ticket->module?->value)->toBe('dispatching')
        ->and($ticket->assignee_id)->toBe($colleague->id)
        ->and($ticket->comments()->where('body', 'Which order?')->exists())->toBeTrue();
});

test('ticket list sorts by creation, remembers the Mine filter and lets lead engineers edit any row', function () {
    $olderAssignedToMe = StoreTicket::make()->action($this->group, ['subject' => 'Created first, updated later', 'assignee_id' => $this->user->id]);
    $olderAssignedToMe->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    $newest = StoreTicket::make()->action($this->group, ['subject' => 'Created last']);
    UpdateTicket::make()->action($olderAssignedToMe->fresh(), ['priority' => 'urgent']);

    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/Tickets')
            ->where('data.data.0.reference', $newest->reference)
            ->where('can_assign', true)
            ->where('mineFilter', null)
    );

    patch(route('grp.models.profile.update'), ['tickets_list_mine' => 'assigned'])->assertSessionHasNoErrors();
    expect($this->user->fresh()->settings['tickets_list_mine'] ?? null)->toBe('assigned');

    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->where('mineFilter', 'assigned')
            ->where('data.data', fn ($rows) => collect($rows)->isNotEmpty() && collect($rows)->every(fn ($row) => $row['assignee_id'] === $this->user->id))
    );

    get(route('grp.tickets.list', ['elements' => ['mine' => 'reported,assigned,collaborating']]))->assertInertia(
        fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => collect($rows)->pluck('reference')->contains($newest->reference))
    );

    patch(route('grp.models.profile.update'), ['tickets_list_mine' => ''])->assertSessionHasNoErrors();

    setPermissionsTeamId($this->group->id);
    $someoneElse = User::factory()->create(['group_id' => $this->group->id]);
    $someoneElse->assignRole('help-desk-clerk');
    UpdateTicket::make()->action($newest->fresh(), ['assignee_id' => $someoneElse->id]);

    patch(route('grp.models.ticket.update', $newest->id), ['priority' => 'high'])->assertRedirect()->assertSessionHasNoErrors();
    patch(route('grp.models.ticket.update', $newest->id), ['assignee_id' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect($newest->fresh()->priority->value)->toBe('high')
        ->and($newest->fresh()->assignee_id)->toBeNull();
});

test('the quick look controls include the comments the viewer can see', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Quick look comments']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'public note']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'internal note'])->update(['is_internal' => true]);

    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()
        ->assertJsonCount(2, 'comments')
        ->assertJsonPath('comments.0.body', 'internal note')
        ->assertJsonPath('comments_newest_first', (bool) data_get($this->user->fresh()->settings, 'ticket_comments_newest_first', true));

    setPermissionsTeamId($this->group->id);
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');
    actingAs($clerk);
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonCount(2, 'comments');

    $ticket->comments()->where('body', 'internal note')->update(['is_lead_only' => true]);
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonCount(1, 'comments')->assertJsonPath('comments.0.body', 'public note');
});

test('the board tells lead engineers apart so only they can drag any ticket', function () {
    get(route('grp.tickets.board'))->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true)->where('can_assign', true));

    setPermissionsTeamId($this->group->id);
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');
    actingAs($clerk);

    get(route('grp.tickets.board'))->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true)->where('can_assign', false));
});

test('the assignee adds collaborators who can see the ticket, tag it and ask QA, while only the assignee moves it', function () {
    Mail::fake();
    Notification::fake();
    setPermissionsTeamId($this->group->id);
    $assignee  = User::factory()->create(['group_id' => $this->group->id]);
    $helper    = User::factory()->create(['group_id' => $this->group->id]);
    $qa        = User::factory()->create(['group_id' => $this->group->id]);
    $bystander = User::factory()->create(['group_id' => $this->group->id]);
    $outsider  = User::factory()->create(['group_id' => $this->group->id]);
    $assignee->assignRole('help-desk-clerk');
    $helper->assignRole('help-desk-clerk');
    $bystander->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Needs three people', 'assignee_id' => $assignee->id, 'is_confidential' => true]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    $collaborators = route('grp.models.ticket.collaborators.update', $ticket->id);
    $update        = route('grp.models.ticket.update', $ticket->id);

    actingAs($bystander);
    expect($ticket->fresh()->isVisibleTo($bystander))->toBeFalse();
    patch($collaborators, ['collaborator_ids' => [$bystander->id]])->assertForbidden();
    patch($update, ['tags' => ['sneaky']])->assertForbidden();

    actingAs($assignee);
    patch($collaborators, ['collaborator_ids' => [$helper->id, $qa->id, $outsider->id]])->assertSessionHasErrors('collaborator_ids.2');
    patch($collaborators, ['collaborator_ids' => [$helper->id, $qa->id]])->assertRedirect()->assertSessionHasNoErrors();

    expect($ticket->collaborators()->pluck('users.id')->sort()->values()->all())->toBe(collect([$helper->id, $qa->id])->sort()->values()->all())
        ->and($ticket->collaborators()->whereKey($helper->id)->first()->pivot->added_by_id)->toBe($assignee->id);
    Notification::assertSentTo($helper, TicketNotification::class, fn ($notification) => str_contains($notification->subject, $ticket->reference));
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_manage_collaborators', true)
            ->where('timeline', fn ($timeline) => collect($timeline)->contains(fn ($event) => str_starts_with($event['text'], 'Collaborators:')))
    );

    actingAs($helper);
    expect($ticket->fresh()->isVisibleTo($helper))->toBeTrue();
    get(route('grp.tickets.show', $ticket->reference))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_update', false)->where('can_contribute', true)->where('can_manage_collaborators', false)
    );
    patch($update, ['tags' => ['data fix']])->assertRedirect()->assertSessionHasNoErrors();
    patch($update, ['qa_status' => 'requested'])->assertRedirect()->assertSessionHasNoErrors();
    patch($update, ['status' => 'resolved'])->assertForbidden();
    patch($update, ['priority' => 'urgent'])->assertForbidden();
    patch($collaborators, ['collaborator_ids' => []])->assertForbidden();
    expect($ticket->fresh()->tags)->toContain('data fix')
        ->and(GetTicketBadgeData::run($helper)['queue']['collaborating']['count'])->toBeGreaterThanOrEqual(1);
    get(route('grp.tickets.list', ['elements' => ['mine' => 'collaborating']]))->assertInertia(
        fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => collect($rows)->pluck('reference')->contains($ticket->reference))
    );

    actingAs($assignee);
    patch($update, ['assignee_id' => $helper->id])->assertRedirect();
    expect($ticket->fresh()->assignee_id)->toBe($helper->id)
        ->and($ticket->collaborators()->pluck('users.id')->all())->toBe([$qa->id]);
});

test('reports stay unfiltered by default and narrow to one assignee when picked', function () {
    setPermissionsTeamId($this->group->id);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    StoreTicket::make()->action($this->group, ['subject' => 'Theirs', 'assignee_id' => $engineer->id]);
    StoreTicket::make()->action($this->group, ['subject' => 'Nobody on it']);

    $everyone = ShowTicketsReports::make()->handle($this->group, 'all');
    $theirs   = ShowTicketsReports::make()->handle($this->group, 'all', null, $engineer);

    expect($everyone['assignee'])->toBeNull()
        ->and($theirs['assignee'])->toBe($engineer->username)
        ->and($theirs['created'])->toBe(Ticket::where('group_id', $this->group->id)->where('assignee_id', $engineer->id)->count())
        ->and($everyone['created'])->toBeGreaterThan($theirs['created']);

    get(route('grp.tickets.reports', ['created' => 'all']))->assertInertia(
        fn (AssertableInertia $page) => $page->where('stats.assignee', null)
            ->where('assigneeOptions', fn ($options) => collect($options)->pluck('value')->contains($engineer->username))
    );
    get(route('grp.tickets.reports', ['created' => 'all', 'assignee' => $engineer->username]))->assertInertia(
        fn (AssertableInertia $page) => $page->where('stats.assignee', $engineer->username)->where('stats.created', $theirs['created'])
    );
    get(route('grp.tickets.reports', ['created' => 'all', 'assignee' => 'not-an-engineer']))->assertInertia(
        fn (AssertableInertia $page) => $page->where('stats.assignee', null)
    );
});

test('zip attachments list their contents for the preview and still download on a plain open', function () {
    $directory = sys_get_temp_dir().'/ticket_zip_'.uniqid();
    mkdir($directory);
    $zip = new ZipArchive();
    $zip->open("$directory/logs.zip", ZipArchive::CREATE);
    $zip->addEmptyDir('logs');
    $zip->addFromString('logs/error.log', "boom\n");
    $zip->addFromString('readme.txt', 'hi');
    $zip->close();

    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Logs attached',
        'images'  => [new UploadedFile("$directory/logs.zip", 'logs.zip', null, null, true)],
    ]);
    $url = $ticket->ticketAttachments('grp.tickets.attachments.show')[0]['url'];

    get($url.'?contents=1')->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonFragment(['name' => 'logs/', 'is_directory' => true])
        ->assertJsonFragment(['name' => 'logs/error.log', 'size' => 5, 'is_directory' => false]);

    expect(get($url)->assertOk()->headers->get('content-disposition'))->toStartWith('attachment');
});

test('rejected ticket files get readable messages that name the file', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Readable upload errors']);

    $messageFor = function (array $files) use ($ticket): string {
        try {
            StoreTicketComment::make()->action($ticket, $this->user, ['images' => $files]);
        } catch (Illuminate\Validation\ValidationException $exception) {
            return collect($exception->errors())->flatten()->implode(' ');
        }

        return '';
    };

    expect($messageFor([UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf')]))
        ->toContain('"huge.pdf" is too big')
        ->not->toContain('images.0')
        ->and($messageFor([UploadedFile::fake()->createWithContent('notes.txt', "plain text\n")]))
        ->toContain('"notes.txt" cannot be attached')
        ->and($messageFor(array_map(fn (int $number) => UploadedFile::fake()->image("shot$number.png"), range(1, 6))))
        ->toContain('You can attach up to 5 files at a time.');
});

test('attachment previews are open to engineers, QA, lead engineers and the reporter only', function () {
    setPermissionsTeamId($this->group->id);
    $reporter  = User::factory()->create(['group_id' => $this->group->id]);
    $engineer  = User::factory()->create(['group_id' => $this->group->id]);
    $qa        = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'Screenshots inside',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
        'images'        => [UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n%%EOF\n")],
    ]);
    $url = route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $ticket->getMedia('ticket_attachments')->first()->ulid]);

    foreach ([$reporter, $engineer, $qa, $this->user] as $allowedViewer) {
        actingAs($allowedViewer);
        get($url)->assertOk();
        get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonPath('can_preview_attachments', true);
    }

    actingAs($colleague);
    get(route('grp.tickets.show', $ticket->reference))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_preview_attachments', false)
    );
    get($url)->assertForbidden();
});

test('rar and 7z attachments are accepted and list their contents through bsdtar', function () {
    Process::fake([
        'bsdtar --version' => Process::result('bsdtar 3.7.2 - libarchive 3.7.2'),
        'bsdtar -tvf *'    => Process::result("drwxr-xr-x  0 1000   1000        0 Sep 15 10:00 logs/\n-rw-r--r--  0 1000   1000        5 Sep 15 10:00 logs/error log.txt\n"),
    ]);

    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Archives attached',
        'images'  => [
            UploadedFile::fake()->createWithContent('logs.rar', "Rar!\x1A\x07\x01\x00".str_repeat("\0", 64)),
            UploadedFile::fake()->createWithContent('logs.7z', "7z\xBC\xAF\x27\x1C\x00\x04".str_repeat("\0", 64)),
        ],
    ]);
    $urls = collect($ticket->ticketAttachments('grp.tickets.attachments.show'))->pluck('url', 'name');

    foreach (['logs.rar', 'logs.7z'] as $name) {
        get($urls[$name].'?contents=1')->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['name' => 'logs/', 'size' => 0, 'is_directory' => true])
            ->assertJsonFragment(['name' => 'logs/error log.txt', 'size' => 5, 'is_directory' => false]);
        expect(get($urls[$name])->assertOk()->headers->get('content-disposition'))->toStartWith('attachment');
    }

    Process::fake(['bsdtar --version' => Process::result(exitCode: 127)]);

    get($urls['logs.rar'].'?contents=1')->assertStatus(422)
        ->assertJsonPath('message', 'This server cannot read RAR files yet. Ask an administrator to install libarchive-tools.');
});

test('reporters follow progress from their badge, cannot move their ticket, and internal notes stay with the people working on it', function () {
    Mail::fake();
    setPermissionsTeamId($this->group->id);
    $reporter  = User::factory()->create(['group_id' => $this->group->id]);
    $engineer  = User::factory()->create(['group_id' => $this->group->id]);
    $bystander = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $bystander->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'My printer',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
        'assignee_id'   => $engineer->id,
    ]);
    $update  = route('grp.models.ticket.update', $ticket->id);
    $comment = route('grp.models.ticket.comment.store', $ticket->id);

    expect(GetTicketBadgeData::run($reporter)['mine']['to_do']['count'])->toBe(1);

    actingAs($reporter);
    patch($update, ['status' => 'cancelled', 'status_comment' => 'never mind'])->assertForbidden();
    patch($update, ['status' => 'in_progress'])->assertForbidden();
    post($comment, ['body' => 'secret from the reporter', 'is_internal' => true])->assertSessionHasErrors('is_internal');
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_update', false)->where('can_comment_internally', false)
    );

    actingAs($bystander);
    post($comment, ['body' => 'Engineering note from another engineer', 'is_internal' => true])->assertRedirect()->assertSessionHasNoErrors();

    actingAs($engineer);
    patch($update, ['status' => 'in_progress'])->assertRedirect()->assertSessionHasNoErrors();
    post($comment, ['body' => 'Looking into it'])->assertRedirect()->assertSessionHasNoErrors();
    post($comment, ['body' => 'Driver is broken, not telling yet', 'is_internal' => true])->assertRedirect()->assertSessionHasNoErrors();
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_comment_internally', true)
            ->where('comments', fn ($comments) => collect($comments)->pluck('body')->contains('Driver is broken, not telling yet'))
    );

    $badges = GetTicketBadgeData::run($reporter->fresh());
    $titles = collect($badges['recent'])->pluck('title');
    expect($badges['mine']['in_progress']['count'])->toBe(1)
        ->and($badges['mine']['to_do']['count'])->toBe(0)
        ->and($titles->all())->toContain(__(':reference is now :status', ['reference' => $ticket->reference, 'status' => TicketStatusEnum::labels()['in_progress']]))
        ->and($titles->all())->toContain(__(':reference has a new comment', ['reference' => $ticket->reference]))
        ->and(collect($badges['recent'])->where('read', false)->count())->toBeGreaterThanOrEqual(2)
        ->and($ticket->comments()->where('body', 'like', 'secret%')->count())->toBe(0);

    actingAs($reporter);
    get(route('grp.tickets.show', $ticket->reference))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments', fn ($comments) => collect($comments)->pluck('body')->contains('Driver is broken, not telling yet'))
    );
    expect(collect(GetTicketBadgeData::run($reporter->fresh())['recent'])->where('read', false)->count())->toBe(0);

    $note      = $ticket->comments()->where('body', 'Driver is broken, not telling yet')->first();
    $showsNote = fn () => get(route('grp.tickets.show', $ticket->reference))->assertOk()->viewData('page')['props']['comments'];

    actingAs($bystander);
    expect(collect($showsNote())->pluck('body')->all())->toContain('Driver is broken, not telling yet');

    actingAs($this->user);
    patch(route('grp.models.ticket.comment.toggle_visibility', $note->id))->assertRedirect();
    expect($note->fresh()->is_lead_only)->toBeTrue()
        ->and(collect($showsNote())->pluck('body')->all())->toContain('Driver is broken, not telling yet');

    actingAs($engineer);
    expect(collect($showsNote())->pluck('body')->all())->not->toContain('Driver is broken, not telling yet');
});

test('report totals, ratings and reporters link to matching ticket lists', function () {
    setPermissionsTeamId($this->group->id);
    $reporter = User::factory()->create(['group_id' => $this->group->id]);

    $rated    = StoreTicket::make()->action($this->group, ['subject' => 'Rated one', 'reporter_type' => 'User', 'reporter_id' => $reporter->id, 'assignee_id' => $this->user->id]);
    $unrated  = StoreTicket::make()->action($this->group, ['subject' => 'Not rated', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    $someone  = StoreTicket::make()->action($this->group, ['subject' => 'Someone else', 'assignee_id' => $this->user->id]);
    $rated->forceFill(['rating' => 4, 'rated_at' => now()])->saveQuietly();

    $references = fn (array $filter) => collect(get(route('grp.tickets.list', ['filter' => $filter]))->assertOk()->viewData('page')['props']['data']['data'])->pluck('reference');

    expect($references(['reporter' => 'User-'.$reporter->id])->sort()->values()->all())->toBe(collect([$rated->reference, $unrated->reference])->sort()->values()->all())
        ->and($references(['reporter' => 'User-'.$reporter->id, 'rated' => 1])->all())->toBe([$rated->reference])
        ->and($references(['has_assignee' => 1])->all())->toContain($rated->reference, $someone->reference)
        ->and($references(['has_assignee' => 1])->all())->not->toContain($unrated->reference)
        ->and($references(['reporter' => 'WebUser-'.$reporter->id])->all())->toBe([]);

    $reporterRow = collect(ShowTicketsReports::make()->handle($this->group, 'all')['reporters'])->firstWhere('key', 'User-'.$reporter->id);
    expect($reporterRow)->toHaveKey('avatar')
        ->and($reporterRow['created'])->toBe(2);
});

test('engineer report rows split assigned and collaborating tickets and link to matching lists', function () {
    setPermissionsTeamId($this->group->id);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');

    $own     = StoreTicket::make()->action($this->group, ['subject' => 'Own work', 'assignee_id' => $engineer->id]);
    $helping = StoreTicket::make()->action($this->group, ['subject' => 'Helping out', 'assignee_id' => $this->user->id]);
    SyncTicketCollaborators::make()->action($helping, [$engineer->id]);

    $row = collect(ShowTicketsReports::make()->handle($this->group, 'all')['assignees'])->firstWhere('username', $engineer->username);
    expect($row['open'])->toBe(1)
        ->and($row['collaborating']['open'])->toBe(1)
        ->and($row['collaborating']['done'])->toBe(0);

    $references = fn (array $filter) => collect(get(route('grp.tickets.list', ['filter' => $filter]))->assertOk()->viewData('page')['props']['data']['data'])->pluck('reference')->sort()->values()->all();

    expect($references(['assignee' => $engineer->username]))->toBe([$own->reference])
        ->and($references(['collaborator' => $engineer->username]))->toBe([$helping->reference])
        ->and($references(['involved' => $engineer->username]))->toBe(collect([$own->reference, $helping->reference])->sort()->values()->all())
        ->and(ShowTicketsReports::make()->handle($this->group, 'all', null, $engineer)['created'])->toBe(2);
});

test('the ticket list can be narrowed to the tickets someone collaborates on', function () {
    setPermissionsTeamId($this->group->id);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');

    $helping = StoreTicket::make()->action($this->group, ['subject' => 'Helping out', 'assignee_id' => $this->user->id]);
    StoreTicket::make()->action($this->group, ['subject' => 'Not involved', 'assignee_id' => $this->user->id]);
    SyncTicketCollaborators::make()->action($helping, [$engineer->id]);

    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => count($rows) >= 2)
    );
    $response = get(route('grp.tickets.list', ['elements' => ['collaborator' => $engineer->username]]))->assertOk();
    expect(collect($response->viewData('page')['props']['data']['data'])->pluck('reference')->all())->toBe([$helping->reference]);
});
