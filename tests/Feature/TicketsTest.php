<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreTicketFromChatSession;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Helpers\Ticket;
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
    get(route('grp.tickets.create'))->assertInertia(fn (AssertableInertia $page) => $page->component('CreateModel'));
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
