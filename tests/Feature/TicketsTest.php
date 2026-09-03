<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreTicketFromChatSession;
use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\UI\ShowTicketsDashboard;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Helpers\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;

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

    expect($ticket->model_type)->toBe('ChatSession')
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
