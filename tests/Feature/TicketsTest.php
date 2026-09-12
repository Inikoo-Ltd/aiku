<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreTicketFromChatSession;
use App\Actions\Helpers\Ticket\ImportJiraTickets;
use App\Actions\Helpers\Ticket\LinkTicketsToAppDeployment;
use App\Models\DevOps\AppDeployment;
use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\Helpers\Ticket\RepairSlackTicketReporters;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\UI\ShowTicketsDashboard;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\TicketsTool;
use App\Mcp\Tools\TicketWriteTool;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Guest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Auth;
use App\Helpers\SlackNotification;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;
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
    expect($ticket->resolved_at)->not->toBeNull()->and($ticket->closed_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::CLOSED->value]);
    expect($ticket->closed_at)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::OPEN->value, 'assignee_id' => $this->user->id]);
    expect($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->assignee_id)->toBe($this->user->id);
})->depends('help ticket gets a HELP reference and defaults');

test('staff can leave internal notes but customers never can', function (Ticket $ticket) {
    $staffNote = StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Looking into it', 'is_internal' => true]);
    $customerNote = StoreTicketComment::make()->action($ticket, $this->webUser, ['body' => 'Any news?', 'is_internal' => true]);

    expect($staffNote->is_internal)->toBeTrue()
        ->and($staffNote->author_type)->toBe('User')
        ->and($customerNote->is_internal)->toBeFalse()
        ->and($customerNote->author_type)->toBe('WebUser')
        ->and($ticket->comments()->count())->toBe(2);
})->depends('customer ticket from retina gets an AD reference and the customer attached');

test('grp ticket pages render', function (Ticket $ticket) {
    get(route('grp.tickets.index'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/Tickets')->has('data.data', Ticket::count()));
    get(route('grp.tickets.board'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketsBoard')->has('columns', count(TicketStatusEnum::cases())));
    get(route('grp.tickets.create'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/CreateTicket'));
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/Ticket')->where('ticket.reference', $ticket->reference)->has('comments', 2)
    );
})->depends('customer ticket from retina gets an AD reference and the customer attached');

test('grp form endpoints create, update and comment', function () {
    $countBefore = Ticket::count();

    post(route('grp.models.ticket.store'), ['subject' => 'From the form', 'priority' => 'urgent'])->assertRedirect();
    $ticket = Ticket::latest('id')->first();
    expect(Ticket::count())->toBe($countBefore + 1)
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($ticket->priority)->toBe(ChatPriorityEnum::URGENT);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'in_progress'])->assertRedirect();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'On it'])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS)
        ->and($ticket->comments()->count())->toBe(1);
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

test('tickets dashboard counts created, done, status and assignees', function () {
    $before = ShowTicketsDashboard::make()->handle($this->group, 7);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Report me', 'assignee_id' => $this->user->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    StoreTicket::make()->action($this->group, ['subject' => 'Still open', 'assignee_id' => $this->user->id]);

    $stats = ShowTicketsDashboard::make()->handle($this->group, 7);
    $today = collect($stats['daily'])->firstWhere('date', now()->toDateString());
    $me    = collect($stats['assignees'])->firstWhere('name', $this->user->contact_name ?: $this->user->username);

    expect($stats['created'])->toBe($before['created'] + 2)
        ->and($stats['done'])->toBe($before['done'] + 1)
        ->and($stats['open'])->toBe($before['open'] + 1)
        ->and(count($stats['daily']))->toBe(7)
        ->and($today['created'])->toBeGreaterThanOrEqual(2)
        ->and(collect($stats['by_status'])->firstWhere('status', 'resolved')['total'])->toBeGreaterThanOrEqual(1)
        ->and($me['done'])->toBeGreaterThanOrEqual(1)
        ->and($me['open'])->toBeGreaterThanOrEqual(1)
        ->and($me['median_hours'])->not->toBeNull();

    get(route('grp.tickets.dashboard', ['days' => 30]))->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/TicketsDashboard')->where('stats.days', 30)->has('stats.daily', 30)
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

    $stats = ShowTicketsDashboard::make()->handle($this->group, 7);
    expect($stats['csat'])->toBeGreaterThan(0)
        ->and(count($stats['csat_by_month']))->toBe(12)
        ->and(collect($stats['csat_by_month'])->last()['total'])->toBeGreaterThanOrEqual(1);
});

test('staff reporter rates their own resolved help ticket from grp', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Rate from grp', 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::CLOSED->value]);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_rate', true));
    post(route('grp.models.ticket.rate', $ticket->id), ['rating' => 5])->assertRedirect();

    expect($ticket->fresh()->rating)->toBe(5);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_rate', false)->where('ticket.rating', 5));
});

test('help ticket raised by staff opens a staff conversation that follows the assignee', function () {
    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'Board loads slowly',
        'kind'          => TicketKindEnum::BUG->value,
        'reporter_type' => 'User',
        'reporter_id'   => $this->user->id,
    ]);

    $conversation = $ticket->staffConversation;
    expect($ticket->kind)->toBe(TicketKindEnum::BUG)
        ->and($conversation)->not->toBeNull()
        ->and($conversation->name)->toBe($ticket->reference.' · Board loads slowly')
        ->and($conversation->participants->pluck('id')->all())->toBe([$this->user->id]);

    $assignee = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    UpdateTicket::make()->action($ticket, ['assignee_id' => $assignee->id]);

    expect($conversation->fresh()->participants->pluck('id')->sort()->values()->all())->toBe(collect([$this->user->id, $assignee->id])->sort()->values()->all());
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
        ->and($helpTicket->staffConversation)->not->toBeNull()
        ->and($customerTicket->escalations()->pluck('reference')->all())->toBe([$helpTicket->reference]);

    post(route('grp.models.ticket.escalate', $helpTicket->id))->assertStatus(422);
});

test('jira import keeps keys, maps fields, resolves customer by email and bumps the sequence', function () {
    $this->webUser->update(['email' => 'shopper@example.com']);
    $this->shop->update(['slug' => 'awd']);
    $this->website->update(['domain' => 'aw-dropship.com']);

    $adf = fn (string $text) => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];

    Http::fake([
        'jira.test/rest/api/3/attachment/content/77' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf']),
        'jira.test/rest/api/3/attachment/content/78' => Http::response(UploadedFile::fake()->image('shot.png', 10, 10)->getContent(), 200, ['Content-Type' => 'image/png']),
        'jira.test/rest/api/3/search/jql' => Http::response(['issues' => [
            [
                'id'     => '1',
                'key'    => 'AD-500',
                'fields' => [
                    'summary'           => 'eBay listing missing',
                    'description'       => $adf('Line one'),
                    'status'            => ['name' => 'Waiting for customer'],
                    'issuetype'         => ['name' => 'Ebay Channel'],
                    'priority'          => ['name' => 'High'],
                    'reporter'          => ['displayName' => 'Shopper', 'emailAddress' => 'SHOPPER@example.com'],
                    'assignee'          => ['displayName' => $this->user->contact_name, 'emailAddress' => null],
                    'created'           => '2026-01-02T10:00:00.000+0100',
                    'updated'           => '2026-01-03T10:00:00.000+0100',
                    'resolutiondate'    => null,
                    'customfield_10227' => ['value' => 'Dropship UK'],
                    'customfield_10294' => 'Shopper Ltd',
                    'attachment'        => [
                        ['id' => '77', 'filename' => 'invoice.pdf', 'mimeType' => 'application/pdf', 'size' => 4, 'content' => 'https://jira.test/rest/api/3/attachment/content/77'],
                        ['id' => '78', 'filename' => 'shot.png', 'mimeType' => 'image/png', 'size' => 100, 'content' => 'https://jira.test/rest/api/3/attachment/content/78'],
                    ],
                    'comment'           => ['comments' => [
                        ['author' => ['displayName' => $this->user->contact_name], 'body' => $adf('Looking into it'), 'jsdPublic' => false, 'created' => '2026-01-02T11:00:00.000+0100'],
                    ]],
                ],
            ],
            [
                'id'     => '2',
                'key'    => 'AD-7',
                'fields' => [
                    'summary'           => 'Old one',
                    'description'       => null,
                    'status'            => ['name' => 'Done'],
                    'issuetype'         => ['name' => 'Shopify Channel'],
                    'priority'          => ['name' => 'Medium'],
                    'reporter'          => ['displayName' => 'Ghost', 'emailAddress' => 'nobody@example.com'],
                    'created'           => '2025-01-02T10:00:00.000+0100',
                    'updated'           => '2025-01-03T10:00:00.000+0100',
                    'resolutiondate'    => '2025-01-03T10:00:00.000+0100',
                    'customfield_10227' => ['value' => 'Dropship UK'],
                ],
            ],
        ]]),
    ]);

    $imported = ImportJiraTickets::make()->setJiraCredentials(['base_url' => 'https://jira.test', 'email' => 'x', 'api_token' => 'y'])->handle($this->group, 'AD');

    $ticket = Ticket::where('reference', 'AD-500')->first();
    expect($imported)->toBe(2)
        ->and($ticket->type)->toBe(TicketTypeEnum::CUSTOMER)
        ->and($ticket->number)->toBe(500)
        ->and($ticket->status)->toBe(TicketStatusEnum::WAITING)
        ->and($ticket->priority)->toBe(ChatPriorityEnum::HIGH)
        ->and($ticket->description)->toBe("Line one\n")
        ->and($ticket->shop_id)->toBe($this->shop->id)
        ->and($ticket->customer_id)->toBe($this->customer->id)
        ->and($ticket->reporter_type)->toBe('WebUser')
        ->and($ticket->assignee_id)->toBe($this->user->id)
        ->and($ticket->created_at->year)->toBe(2026)
        ->and($ticket->comments()->count())->toBe(1)
        ->and($ticket->comments()->first()->is_internal)->toBeTrue()
        ->and(Ticket::where('reference', 'AD-7')->first()->resolved_at)->not->toBeNull()
        ->and($ticket->getMedia('ticket_attachments')->pluck('name')->all())->toBe(['invoice.pdf'])
        ->and($ticket->getMedia('ticket_images'))->toHaveCount(1)
        ->and($ticket->ticketAttachments()[0]['url'])->toBeString();

    ImportJiraTickets::make()->setJiraCredentials(['base_url' => 'https://jira.test', 'email' => 'x', 'api_token' => 'y'])->handle($this->group, 'AD');
    expect($ticket->fresh()->media()->count())->toBe(2);

    $next = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'After import']);
    expect($next->number)->toBe(501);
});

test('staff file a bug from anywhere without leaving the page', function () {
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
        ->and($ticket->data['reference_url'])->toBe('https://app.aiku.test/org/awa/shops')
        ->and($ticket->staffConversation)->not->toBeNull();
});

test('tickets take free tags and the known list grows with them', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Tagged', 'tags' => ['not a bug'], 'module' => 'dispatching']);
    UpdateTicket::make()->action($ticket, ['tags' => ['not a bug', 'printer voodoo']]);

    expect($ticket->fresh()->tags)->toBe(['not a bug', 'printer voodoo'])
        ->and($ticket->module)->toBe(TicketModuleEnum::DISPATCHING)
        ->and(Ticket::knownTags($this->group->id))->toContain('printer voodoo', 'lack of training');
});

test('confidential tickets are only visible to reporter, assignee and admins', function () {
    $outsider = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $outsider->removeRole('group-admin');
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'HR matter', 'is_confidential' => true, 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);

    expect(Ticket::visibleTo($this->user)->whereKey($ticket->id)->exists())->toBeTrue()
        ->and(Ticket::visibleTo($outsider)->whereKey($ticket->id)->exists())->toBeFalse();

    actingAs($outsider);
    get(route('grp.tickets.show', $ticket->reference))->assertForbidden();
    get(route('grp.tickets.index', ['elements' => ['mine' => 'reported']]))->assertOk();

    UpdateTicket::make()->action($ticket, ['assignee_id' => $outsider->id]);
    expect(Ticket::visibleTo($outsider)->whereKey($ticket->id)->exists())->toBeTrue();
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
        ->and($ticket->comments()->first()->is_internal)->toBeTrue();

    $shown = AikuServer::actingAs($this->user)->tool(TicketsTool::class, ['reference' => strtolower($reference)]);
    $shown->assertOk()->assertSee('Fixed by clearing the stale lock');

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'assignee' => 'nobody-here'])->assertHasErrors();
});

test('new tickets ping the Slack tickets channel when one is configured', function () {
    Notification::fake();
    Config::set('services.slack.notifications.tickets_channel', '#tickets');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ping me']);

    Notification::assertSentOnDemand(SlackNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['slack'] === '#tickets');

    Config::set('services.slack.notifications.tickets_channel', null);
    StoreTicket::make()->action($this->group, ['subject' => 'Silent']);
    Notification::assertSentOnDemandTimes(SlackNotification::class, 1);
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

test('read-only mirror mode blocks every write but still lets everyone read', function () {
    $ticket         = StoreTicket::make()->action($this->group, ['subject' => 'Before freeze']);
    $customerTicket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Customer before freeze']);
    Config::set('tickets.read_only_types', ['help', 'customer']);

    get(route('grp.tickets.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('tickets_read_only_types', ['help', 'customer']));
    get(route('grp.tickets.show', $ticket->reference))->assertOk();

    post(route('grp.models.ticket.store'), ['subject' => 'During freeze'])->assertStatus(423);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertStatus(423);
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'nope'])->assertStatus(423);
    post(route('grp.models.ticket.escalate', $customerTicket->id))->assertStatus(423);
    expect(fn () => StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'retina during freeze']))->toThrow(HttpException::class);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'comment' => 'x'])->assertHasErrors();
    AikuServer::actingAs($this->user)->tool(TicketsTool::class, ['reference' => $ticket->reference])->assertOk();

    expect(Ticket::where('subject', 'During freeze')->exists())->toBeFalse()
        ->and($ticket->fresh()->status)->toBe(TicketStatusEnum::OPEN)
        ->and($ticket->comments()->count())->toBe(0);

    Config::set('tickets.read_only_types', ['customer']);

    post(route('grp.models.ticket.store'), ['subject' => 'Help after cut-over'])->assertRedirect();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'help is writable'])->assertRedirect();
    post(route('grp.models.ticket.escalate', $customerTicket->id))->assertRedirect();
    expect(fn () => StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'retina still frozen']))->toThrow(HttpException::class);
    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $customerTicket->reference, 'comment' => 'x'])->assertHasErrors();
    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'comment' => 'via mcp'])->assertOk();

    expect(Ticket::where('subject', 'Help after cut-over')->exists())->toBeTrue()
        ->and($ticket->comments()->count())->toBe(2);
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
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['thread_ts'] === '1789138198.657369' && str_contains($request['text'], $ticket->reference));

    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Fixed, please check']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'private', 'is_internal' => true]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'Fixed, please check'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'private'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_ends_with($request['text'], 'Resolved'));

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

    $post(['type' => 'message_action', 'callback_id' => 'raise_ticket', 'trigger_id' => 'T1', 'user' => ['id' => 'U1'], 'channel' => ['id' => 'C1'], 'message' => ['ts' => '55.1', 'text' => "Labels blank\nSK printer only"]])->assertOk();
    Http::assertSent(function ($request) {
        $view = $request['view'] ?? null;

        return str_contains($request->url(), 'views.open')
            && $request['trigger_id'] === 'T1'
            && $view['blocks'][0]['element']['initial_value'] === 'Labels blank'
            && $view['blocks'][1]['element']['initial_value'] === 'SK printer only'
            && json_decode($view['private_metadata'], true) === ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '55.1'];
    });
    expect(Ticket::where('subject', 'Labels blank')->exists())->toBeFalse();

    $post([
        'type' => 'view_submission',
        'user' => ['id' => 'U1'],
        'view' => [
            'callback_id'      => 'raise_ticket',
            'private_metadata' => json_encode(['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '55.1']),
            'state'            => ['values' => [
                'subject'       => ['value' => ['value' => 'Labels blank']],
                'description'   => ['value' => ['value' => 'SK printer only']],
                'reference_url' => ['value' => ['value' => 'https://app.aiku.io/org/aw/warehouses/ac']],
                'files'         => ['value' => ['files' => [['id' => 'F9', 'name' => 'modal.png', 'mimetype' => 'image/png', 'size' => 100, 'url_private_download' => 'https://files.slack.com/modal.png']]]],
            ]],
        ],
    ])->assertOk();

    $ticket = Ticket::where('subject', 'Labels blank')->sole();
    expect($ticket->description)->toBe('SK printer only')
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($ticket->data['reference_url'])->toBe('https://app.aiku.io/org/aw/warehouses/ac')
        ->and($ticket->data['slack']['ts'])->toBe('55.1')
        ->and($ticket->getMedia('ticket_images')->count())->toBe(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['thread_ts'] === '55.1' && str_contains($request['text'], $ticket->reference));
});
