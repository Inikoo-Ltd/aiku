<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreTicketFromChatSession;
use App\Actions\Helpers\Ticket\CancelStaleTickets;
use App\Actions\Helpers\Ticket\LinkTicketsToAppDeployment;
use App\Models\DevOps\AppDeployment;
use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\Helpers\Ticket\RepairSlackTicketReporters;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\StoreTicketFromSlack;
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
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketReporterNotification;
use Illuminate\Support\Facades\Auth;
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
    $answered->comments()->create(['body' => 'asked 20 days ago', 'is_internal' => false, 'created_at' => now()->subDays(20)]);
    $answered->comments()->create(['body' => 'here is the info', 'is_internal' => false, 'created_at' => now()->subDays(2)]);
    $stale->comments()->create(['body' => 'tagged it', 'is_internal' => true]);

    expect(CancelStaleTickets::run(14))->toBe(1)
        ->and($stale->fresh()->status)->toBe(TicketStatusEnum::CANCELLED)
        ->and($stale->fresh()->closed_at)->not->toBeNull()
        ->and($stale->comments()->where('is_internal', true)->count())->toBe(2)
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
});

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
    get(route('grp.tickets.board'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketsBoard')->has('columns', 5));
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
    $reporter->update(['slack_user_id' => 'U123', 'settings' => ['ticket_notifications' => 'both']]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ask me']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'waiting', 'question' => 'Which order?', 'waiting_hours' => 24])->assertRedirect();

    Notification::assertSentTo($reporter, TicketReporterNotification::class);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['channel'] === 'U123');

    $reporter->update(['settings' => ['ticket_notifications' => 'none']]);
    Notification::fake();
    UpdateTicket::make()->action($ticket->fresh(), ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'waiting', 'question' => 'Still?'])->assertRedirect();
    Notification::assertNothingSent();

    $reporter->update(['settings' => ['ticket_notifications' => 'email']]);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertRedirect();
    Notification::assertSentTo($reporter, TicketReporterNotification::class, fn ($notification) => str_contains($notification->subject, 'is done'));
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
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::CANCELLED->value]);

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
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['thread_ts'] === '1789138198.657369' && str_contains($request['text'], $ticket->reference));

    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Fixed, please check']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'private', 'is_internal' => true]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::WAITING->value]);
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'ts' => '1789138199.1', 'thread_ts' => '1789138198.657369', 'text' => 'It is FPGB-123']])->assertOk();
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'bot_id' => 'B1', 'ts' => '1789138199.2', 'thread_ts' => '1789138198.657369', 'text' => 'HELP-1 is now Waiting']])->assertOk();
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'ts' => '1789138199.3', 'text' => 'unrelated top level message']])->assertOk();
    $fromModal = StoreTicket::make()->action($this->group, ['subject' => 'Born in aiku', 'data' => ['slack_alert' => ['channel' => 'C9', 'ts' => '55.1']]]);
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C9', 'user' => 'U1', 'ts' => '55.2', 'thread_ts' => '55.1', 'text' => 'reply under the alert card']])->assertOk();
    expect($fromModal->comments()->pluck('body')->all())->toBe(['reply under the alert card']);
    StoreTicketComment::make()->action($fromModal, $this->user, ['body' => 'answer from aiku']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['thread_ts'] === '55.1' && str_contains($request['text'], 'answer from aiku'));
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::OPEN)
        ->and($ticket->comments()->where('is_internal', false)->pluck('body')->all())->toBe(['Fixed, please check', 'It is FPGB-123']);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'It is FPGB-123'));
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'Fixed, please check'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'private'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_ends_with($request['text'], 'Done'));

    $bare = StoreTicketFromSlack::run($this->group, ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '7', 'subject' => 'No clue where <https://app.aiku.io/org/aw/shops/uk|here>']);
    expect($bare->data['reference_url'])->toBe('https://app.aiku.io/org/aw/shops/uk');
    $bare = StoreTicketFromSlack::run($this->group, ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '8', 'subject' => 'Nothing to go on']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['thread_ts'] === '8' && str_contains($request['text'], 'screenshot'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['thread_ts'] === '7' && str_contains($request['text'], 'screenshot'));

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
            && $view['blocks'][2]['optional'] === false
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
    get(route('grp.tickets.dashboard'))->assertForbidden();
    get(route('grp.tickets.board'))->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['status' => 'resolved'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['priority' => 'urgent'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertRedirect();
    post(route('grp.models.ticket.comment.store', $other->id), ['body' => 'secret?', 'is_internal' => true])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($other->comments()->sole()->is_internal)->toBeFalse();

    actingAs($helper);
    get(route('grp.tickets.show', $other->reference))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true));
    get(route('grp.tickets.dashboard'))->assertForbidden();
    get(route('grp.tickets.board'))->assertOk();
    patch(route('grp.models.ticket.update', $other->id), ['is_confidential' => true])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $helper->id])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['priority' => 'urgent'])->assertRedirect();
    UpdateTicket::make()->action($other, ['assignee_id' => $helper->id]);
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => null])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $boss->id])->assertRedirect();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $helper->id])->assertForbidden();
    UpdateTicket::make()->action($other->refresh(), ['assignee_id' => $helper->id]);
    post(route('grp.models.ticket.comment.store', $other->id), ['body' => 'internal', 'is_internal' => true])->assertRedirect();
    expect($other->fresh()->priority)->toBe(ChatPriorityEnum::URGENT)
        ->and($other->fresh()->assignee_id)->toBe($helper->id)
        ->and($other->comments()->where('is_internal', true)->where('body', 'internal')->count())->toBe(1)
        ->and($other->comments()->where('body', 'like', 'Passed from%')->count())->toBeGreaterThanOrEqual(1);

    actingAs($boss);
    get(route('grp.tickets.dashboard'))->assertOk();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $boss->id])->assertRedirect();
    expect($other->fresh()->assignee_id)->toBe($boss->id);

    AikuServer::actingAs($reporter)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertHasErrors();
    AikuServer::actingAs($reporter)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'cancelled'])->assertOk();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertOk();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $reporter->username])->assertHasErrors();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $helper->username])->assertOk();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $boss->username])->assertOk();

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
        $references = collect($page->toArray()['props']['columns'])
            ->firstWhere('key', 'closed')['tickets'];
        $references = collect($references)->pluck('reference');

        expect($references)->toContain($fresh->reference)
            ->and($references)->not->toContain($stale->reference);
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
