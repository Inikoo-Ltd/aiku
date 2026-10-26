<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Support\Str;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreTicketFromChatSession;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Helpers\Ticket\UpdateTicketPullRequest;
use App\Actions\Helpers\Ticket\Json\GetTicketPullRequest;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use Illuminate\Support\Facades\DB;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Mcp\Servers\AikuServer;
use App\Models\SysAdmin\User;
use App\Mcp\Tools\TicketsTool;
use App\Mcp\Tools\TicketWriteTool;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketProject;
use App\Actions\Helpers\TicketProject\StoreTicketProject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;

beforeAll(function () {
    loadDB();
});

/* Laravel writes datetimes as naive UTC strings, so a Postgres session on any other zone
   stores them shifted and hands them back shifted: a waiting_at written a second ago reads
   hours old, and a fixture written at 11:48 comes back at another time entirely. Pinned for
   this file only, and put back afterwards so no other suite inherits it. */
beforeEach(function () {
    DB::statement("set time zone 'UTC'");

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

afterEach(function () {
    DB::statement('set time zone default');
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
        ->and($ticket->source_type)->toBe('ChatSession')
        ->and($ticket->source_id)->toBe($chatSession->id)
        ->and(App\Actions\Chat\GetChatSessionTickets::run($chatSession)->collection->pluck('id')->all())->toContain($ticket->id)
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

test('assistant raises an engineer ticket with the INI prefix through MCP', function () {
    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['subject' => 'Bump Shopify API version', 'type' => 'engineer', 'kind' => 'task'])->assertOk();
    $ticket = Ticket::where('subject', 'Bump Shopify API version')->firstOrFail();
    expect($ticket->type)->toBe(TicketTypeEnum::ENGINEER)
        ->and($ticket->reference)->toStartWith('INI-');
});

test('assistant asks a QA user to check a ticket through MCP, with the comment as the note', function () {
    Notification::fake();
    $qa = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $qa->assignRole('qa');
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Pay button missing', 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id]);
    $otherQa = User::factory()->create(['group_id' => $this->group->id]);
    $otherQa->assignRole('qa');

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'ask_qa' => $qa->username])->assertHasErrors();
    expect($ticket->refresh()->qa_status)->toBeNull();
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    $notQa = User::factory()->create(['group_id' => $this->group->id]);
    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'ask_qa' => $notQa->username])->assertHasErrors();

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'ask_qa' => $qa->username.', '.$otherQa->username, 'comment' => 'Pay an order in warehouse'])->assertOk();
    $ticket->refresh();
    expect($ticket->qa_status)->toBe(TicketQaStatusEnum::REQUESTED)
        ->and($ticket->qa_user_id)->toBeNull()
        ->and($ticket->qa_user_ids)->toEqualCanonicalizing([$qa->id, $otherQa->id])
        ->and($ticket->comments()->where('body', 'like', '%Pay an order in warehouse')->count())->toBe(1);

    UpdateTicket::make()->action($ticket, ['qa_status' => null]);
});

test('any staff user raises and comments on their own help ticket through MCP but cannot change tickets', function () {
    $staff = User::factory()->create(['group_id' => $this->group->id]);

    AikuServer::actingAs($staff)->tool(TicketWriteTool::class, ['subject' => 'Label printer offline'])->assertOk();
    $ticket = Ticket::where('subject', 'Label printer offline')->firstOrFail();

    expect($ticket->reporter_id)->toBe($staff->id);
    AikuServer::actingAs($staff)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'resolved'])->assertHasErrors();
    AikuServer::actingAs($staff)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'comment' => 'It is the one in bay 3'])->assertOk();
    expect($ticket->comments()->where('body', 'like', '%bay 3%')->count())->toBe(1);

    $otherStaff = User::factory()->create(['group_id' => $this->group->id]);
    AikuServer::actingAs($otherStaff)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'comment' => 'Me too'])->assertHasErrors();
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

    AikuServer::actingAs($this->user)->tool(TicketsTool::class, ['module' => 'dispatching'])->assertDontSee($reference);
    AikuServer::actingAs($this->user)->tool(TicketsTool::class, ['search' => 'Picking screen'])->assertSee($reference);
    AikuServer::actingAs($this->user)->tool(TicketsTool::class, ['search' => 'Picking screen', 'status' => 'open'])->assertDontSee($reference);

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

    $png = base64_encode(UploadedFile::fake()->image('proof.png', 20, 20)->getContent());
    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'comment' => 'Screenshot as proof', 'attachments' => [['name' => 'proof.png', 'base64' => $png]]])->assertOk();
    $proofComment = $ticket->comments()->where('body', 'Screenshot as proof')->firstOrFail();
    expect($proofComment->getMedia('ticket_images'))->toHaveCount(1)
        ->and($proofComment->getFirstMedia('ticket_images')->name)->toBe('proof.png');

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'attachments' => [['name' => 'hack.exe', 'base64' => $png]]])->assertHasErrors();
    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'attachments' => [['name' => 'proof.png', 'base64' => '***']]])->assertHasErrors();

    $commentsBefore = $ticket->comments()->count();
    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'comment_id' => $proofComment->id, 'comment' => 'Screenshot as proof, dispatched count corrected'])->assertOk();
    expect($proofComment->refresh()->body)->toBe('Screenshot as proof, dispatched count corrected')
        ->and($ticket->comments()->count())->toBe($commentsBefore);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'comment_id' => $proofComment->id])->assertHasErrors();
    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $reference, 'comment_id' => 999999, 'comment' => 'Nope'])->assertHasErrors();

    $otherEngineer = User::factory()->create(['group_id' => $this->group->id]);
    $otherEngineer->assignRole('help-desk-supervisor');
    $otherEngineer->forgetWildcardPermissionIndex();
    AikuServer::actingAs($otherEngineer)->tool(TicketWriteTool::class, ['reference' => $reference, 'comment_id' => $proofComment->id, 'comment' => 'Not mine to edit'])->assertHasErrors();
    expect($proofComment->refresh()->body)->toBe('Screenshot as proof, dispatched count corrected');
});

test('a ticket raised from a chat carries that conversation, read only', function () {
    $session = \App\Models\Chat\ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $session->messages()->create([
        'message_text' => 'My order never arrived',
        'message_type' => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => \App\Enums\CRM\Livechat\ChatSenderTypeEnum::GUEST->value,
    ]);

    $withdrawn = $session->messages()->create([
        'message_text' => 'ignore that, wrong chat',
        'message_type' => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => \App\Enums\CRM\Livechat\ChatSenderTypeEnum::GUEST->value,
    ]);
    $withdrawn->delete();

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'         => 'Order never arrived',
        'type'            => TicketTypeEnum::CUSTOMER->value,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $this->customer->id,
        'source_type'     => 'ChatSession',
        'source_id'       => $session->id,
    ]);

    $payload = get(route('grp.json.ticket.chat', $ticket->id))->assertOk()->json();

    expect($payload['session']['ulid'])->toBe($session->ulid)
        ->and($payload['messages'])->toHaveCount(2)
        ->and($payload['messages'][0]['text'])->toBe('My order never arrived')
        ->and($payload['messages'][0]['sender_type'])->toBe('guest')
        // a withdrawn message keeps its place without its words
        ->and($payload['messages'][1]['is_redacted'])->toBeTrue()
        ->and($payload['messages'][1]['text'])->toBeNull()
        ->and($payload['truncated'])->toBeFalse();

    // the resource tells the page there is something to read, and the customer is one click away
    expect(TicketResource::make($ticket->refresh())->resolve()['source']['has_conversation'])->toBeTrue()
        ->and($payload['customer']['name'])->toBe($this->customer->name)
        ->and($payload['customer']['url'])->toBe(route('grp.org.shops.show.crm.customers.show', [
            $this->organisation->slug,
            $this->shop->slug,
            $this->customer->slug,
        ]));

    // a ticket with no conversation behind it says so, and does not offer one
    $plain = StoreTicket::make()->action($this->group, ['subject' => 'Raised by hand']);

    expect(TicketResource::make($plain)->resolve()['source'])->toBeNull()
        ->and(get(route('grp.json.ticket.chat', $plain->id))->assertOk()->json('session'))->toBeNull();
});

test('somebody who cannot see a ticket cannot read its conversation', function () {
    $session = \App\Models\Chat\ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'         => 'Confidential one',
        'type'            => TicketTypeEnum::CUSTOMER->value,
        'is_confidential' => true,
        'source_type'     => 'ChatSession',
        'source_id'       => $session->id,
    ]);

    actingAs(User::factory()->create(['group_id' => $this->group->id]));

    get(route('grp.json.ticket.chat', $ticket->id))->assertForbidden();
});

test('settling a ticket tells the customer and ends the conversation, when that was agreed', function () {
    $session = \App\Models\Chat\ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'Refund never arrived',
        'type'          => TicketTypeEnum::CUSTOMER->value,
        'blocks_source' => true,
        'closes_source' => true,
        'source_type'   => 'ChatSession',
        'source_id'     => $session->id,
        'assignee_id'   => $this->user->id,
    ]);

    patch(route('grp.models.ticket.update', $ticket->id), [
        'status'         => 'resolved',
        'status_comment' => 'Refunded this morning, it will show in a day or two',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $session->refresh();

    // the customer was told, in the conversation, with the note signed off
    $lastMessage = $session->messages()->latest('id')->skip(1)->first();

    expect($lastMessage->message_text)->toContain('Refunded this morning')
        ->and($lastMessage->message_text)->toContain('- Developer')
        ->and($lastMessage->sender_type)->toBe(\App\Enums\CRM\Livechat\ChatSenderTypeEnum::AGENT)
        ->and($lastMessage->sender_id)->toBeNull()
        ->and($session->status)->toBe(\App\Enums\CRM\Livechat\ChatSessionStatusEnum::CLOSED);

    // and the ticket says what happened
    expect($ticket->refresh()->comments()->where('is_internal', true)->latest('id')->value('body'))
        ->toContain('sent to the customer');
});

test('a ticket that was not agreed to close its conversation leaves it alone', function () {
    $session = \App\Models\Chat\ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'     => 'Nothing to tell them',
        'type'        => TicketTypeEnum::CUSTOMER->value,
        'source_type' => 'ChatSession',
        'source_id'   => $session->id,
        'assignee_id' => $this->user->id,
    ]);

    patch(route('grp.models.ticket.update', $ticket->id), [
        'status'         => 'resolved',
        'status_comment' => 'Fixed, nothing the customer needs to hear',
    ])->assertRedirect();

    expect($session->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatSessionStatusEnum::ACTIVE)
        ->and($session->messages()->count())->toBe(0);
});

test('a ticket settled on WhatsApp answers inside the day, and closes in silence after it', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', null);

    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], [
        'whatsapp' => ['phone_number_id' => '111', 'waba_id' => '222'],
    ])]);
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], [
        'meta' => ['access_key' => 'token'],
    ])]);

    $channel = \App\Models\Chat\MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $sessionFor = function (\Illuminate\Support\Carbon $lastInbound) use ($channel) {
        $session = \App\Models\Chat\MetaChatSession::create([
            'ulid'            => (string) \Illuminate\Support\Str::ulid(),
            'meta_channel_id' => $channel->id,
            'shop_id'         => $this->shop->id,
            'phone_number'    => '+628123456789',
            'status'          => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::ACTIVE->value,
            'language_id'     => 68,
            'priority'        => ChatPriorityEnum::NORMAL->value,
        ]);

        // what Meta's window is measured from: the customer's last message
        $session->messages()->create([
            'meta_channel_id' => $channel->id,
            'message_type'    => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => \App\Enums\CRM\Livechat\ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'my order is late',
            'created_at'      => $lastInbound,
        ]);

        return $session;
    };

    $ticketFor = function ($session) {
        return StoreTicket::make()->action($this->group, [
            'subject'       => 'Late order '.uniqid(),
            'type'          => TicketTypeEnum::CUSTOMER->value,
            'blocks_source' => true,
            'closes_source' => true,
            'source_type'   => 'MetaChatSession',
            'source_id'     => $session->id,
            'assignee_id'   => $this->user->id,
        ]);
    };

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST']]])]);

    // inside the window: the customer is answered on WhatsApp and the conversation closes
    $fresh = $sessionFor(now()->subHours(2));
    patch(route('grp.models.ticket.update', $ticketFor($fresh)->id), [
        'status'         => 'resolved',
        'status_comment' => 'Reshipped this morning',
    ])->assertRedirect();

    $sent = $fresh->refresh()->messages()->where('sender_type', \App\Enums\CRM\Livechat\ChatSenderTypeEnum::AGENT->value)->latest('id')->first();

    expect($sent)->not->toBeNull()
        ->and($sent->message_text)->toContain('Reshipped this morning')
        ->and($sent->message_text)->toContain('- Developer')
        ->and($fresh->status)->toBe(\App\Enums\CRM\Livechat\ChatSessionStatusEnum::CLOSED);

    // past it: Meta would refuse the message, so the chat is closed without one
    $stale = $sessionFor(now()->subDays(3));
    $staleTicket = $ticketFor($stale);

    patch(route('grp.models.ticket.update', $staleTicket->id), [
        'status'         => 'resolved',
        'status_comment' => 'Reshipped, sorry for the wait',
    ])->assertRedirect();

    expect($stale->refresh()->messages()->where('sender_type', \App\Enums\CRM\Livechat\ChatSenderTypeEnum::AGENT->value)->count())->toBe(0)
        ->and($stale->status)->toBe(\App\Enums\CRM\Livechat\ChatSessionStatusEnum::CLOSED)
        ->and($staleTicket->refresh()->comments()->where('is_internal', true)->latest('id')->value('body'))
        ->toContain('WhatsApp');
});

test('a pull request link is checked on GitHub before it is saved, and read from GitHub when the ticket opens', function () {
    Cache::flush();
    Http::fake([
        'https://api.github.com/repos/acme/app/pulls/42/commits*' => Http::response([
            [
                'sha'      => 'abcdef1234567890',
                'html_url' => 'https://github.com/acme/app/commit/abcdef1234567890',
                'commit'   => ['message' => "Round each invoice line\n\nThe total now adds rounded lines", 'author' => ['name' => 'Louis', 'date' => '2026-09-30T10:00:00Z']],
                'author'   => ['login' => 'louis', 'avatar_url' => 'https://avatars.example/louis'],
            ],
        ]),
        'https://api.github.com/repos/acme/app/pulls/42' => Http::response([
            'number'   => 42,
            'title'    => 'Fix invoice rounding',
            'html_url' => 'https://github.com/acme/app/pull/42',
            'state'    => 'closed',
            'merged'   => true,
            'draft'    => false,
            'body'     => 'Rounds each line before adding them up',
            'user'     => ['login' => 'louis', 'avatar_url' => 'https://avatars.example/louis', 'html_url' => 'https://github.com/louis'],
        ]),
        'https://api.github.com/repos/acme/app/pulls/*' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Invoice totals are off by a cent']);

    expect(fn () => UpdateTicketPullRequest::make()->action($ticket, ['pull_request_url' => 'https://gitlab.com/acme/app/merge_requests/1']))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and(fn () => UpdateTicketPullRequest::make()->action($ticket, ['pull_request_url' => 'https://github.com/acme/app/pull/7']))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and($ticket->fresh()->pull_request_url)->toBeNull();

    UpdateTicketPullRequest::make()->action($ticket, ['pull_request_url' => ' https://github.com/acme/app/pull/42/files#diff ']);
    expect($ticket->fresh()->pull_request_url)->toBe('https://github.com/acme/app/pull/42');

    $read = GetTicketPullRequest::make()->handle($ticket->fresh());
    expect($read['error'])->toBeNull()
        ->and($read['commits'])->toBeNull()
        ->and($read['pull_request']['title'])->toBe('Fix invoice rounding')
        ->and($read['pull_request']['state'])->toBe('merged')
        ->and($read['pull_request']['author']['login'])->toBe('louis');

    $commits = GetTicketPullRequest::make()->handle($ticket->fresh(), true)['commits'];
    expect($commits)->toHaveCount(1)
        ->and($commits[0]['short_sha'])->toBe('abcdef1')
        ->and($commits[0]['subject'])->toBe('Round each invoice line')
        ->and($commits[0]['author'])->toBe('louis')
        ->and($commits[0]['date'])->toBe('2026-09-30T10:00:00Z');

    UpdateTicketPullRequest::make()->action($ticket->fresh(), ['pull_request_url' => null]);
    expect($ticket->fresh()->pull_request_url)->toBeNull()
        ->and(GetTicketPullRequest::make()->handle($ticket->fresh()))->toBe(['pull_request' => null, 'commits' => null, 'error' => null]);
});

test('a project gathers tickets, tasks, milestones, commits and progress updates its team can edit', function () {
    $member   = User::factory()->create(['group_id' => $this->group->id]);
    $outsider = User::factory()->create(['group_id' => $this->group->id]);
    $first    = StoreTicket::make()->action($this->group, ['subject' => 'Move moulds to the new unit']);
    $second   = StoreTicket::make()->action($this->group, ['subject' => 'Train the new packers']);
    $task     = \App\Actions\Tasks\StoreStaffTask::run($member, ['subject' => 'Label the new shelves', 'department' => 'warehouse']);
    $first->update(['data' => [...$first->data, 'commits' => [['hash' => 'abc1234def', 'subject' => 'Moulds page', 'version' => 'v9.1.0', 'deployed_at' => now()->toIso8601String()]]]]);

    actingAs($member);
    post(route('grp.models.ticket_project.store'), [
        'name'        => 'Warehouse move',
        'description' => 'Move all production and reorganise the organisation',
        'start_date'  => now()->subWeek()->toDateString(),
        'target_date' => now()->subWeek()->addMonths(3)->toDateString(),
        'member_ids'  => [$member->id, $this->user->id],
    ])->assertRedirect(route('grp.projects.show', 'warehouse-move'));

    $project = TicketProject::where('slug', 'warehouse-move')->firstOrFail();

    patch(route('grp.models.ticket_project.update', $project->id), ['milestones' => [['name' => 'Moulds moved', 'due_date' => now()->toDateString(), 'done' => true], ['name' => 'Packers trained']]])->assertRedirect()->assertSessionHasNoErrors();
    [$moved, $trained] = $project->milestones()->get()->all();

    post(route('grp.models.ticket_project.work.attach', $project->id), ['references' => 'HELP-0'])->assertSessionHasErrors('references');
    post(route('grp.models.ticket_project.work.attach', $project->id), ['references' => strtolower($first->reference).", {$second->reference} {$task->reference}", 'ticket_project_milestone_id' => $moved->id])->assertRedirect()->assertSessionHasNoErrors();
    patch(route('grp.models.ticket.project.update', $second->id), ['ticket_project_milestone_id' => $trained->id])->assertRedirect()->assertSessionHasNoErrors();
    Notification::fake();
    post(route('grp.models.ticket_project.update.store', $project->id), ['body' => 'Moulds are in, packers next week', 'health' => 'at_risk'])->assertRedirect()->assertSessionHasNoErrors();
    Notification::assertSentTo($this->user, \App\Notifications\TicketProjectNotification::class, fn ($notification) => $notification->title === 'Warehouse move: At risk' && str_contains($notification->body, 'packers next week') && str_contains((string) $notification->toMail($this->user)->render(), 'Open the project'));
    Notification::assertNotSentTo($member, \App\Notifications\TicketProjectNotification::class);
    Notification::assertNotSentTo($outsider, \App\Notifications\TicketProjectNotification::class);
    UpdateTicket::make()->action($first->fresh(), ['status' => TicketStatusEnum::RESOLVED->value]);

    patch(route('grp.models.ticket_project.update', $project->id), ['milestones' => [['id' => $moved->id, 'name' => 'Moulds moved', 'done' => true], ['id' => $trained->id, 'name' => 'Packers trained']]])->assertRedirect()->assertSessionHasNoErrors();
    expect($moved->fresh()->done_at)->not->toBeNull()
        ->and($task->fresh()->ticket_project_milestone_id)->toBe($moved->id)
        ->and($second->fresh()->ticket_project_milestone_id)->toBe($trained->id);

    get(route('grp.projects.show', $project->slug))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Tickets/TicketProject')
        ->where('can_edit', true)
        ->where('project.health', 'at_risk')
        ->where('progress.total', 3)
        ->where('progress.done', 1)
        ->where('progress.percent', 33)
        ->where('progress.week', 2)
        ->has('work', 3)
        ->where('milestones.0.total', 2)
        ->where('milestones.0.done', 1)
        ->where('milestones.1.total', 1)
        ->where('commits.0.hash', 'abc1234def')
        ->where('commits.0.reference', $first->reference)
        ->where('updates.0.health', 'at_risk')
        ->has('burn_up', 2)
        ->has('workload')
        ->has('activity'));
    get(route('grp.projects.index'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketProjects')->where('projects.0.progress.done', 1)->where('projects.0.health', 'at_risk'));
    get(str_replace('/projects', '/tickets/projects', route('grp.projects.show', $project->slug)))->assertRedirect(route('grp.projects.show', $project->slug))->assertStatus(301);
    expect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($member)['projects']['route']['name'])->toBe('grp.projects.index')
        ->and(collect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($member)['tickets']['topMenu']['subSections'])->pluck('label'))->not->toContain('Projects');
    get(route('grp.tickets.show', $first->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('ticket.project.slug', $project->slug)->where('can_change_project', true)->has('options.milestones', 2));

    $otherProject = StoreTicketProject::make()->action($this->group, ['name' => 'Other', 'start_date' => now()->toDateString()]);
    $otherMilestone = $otherProject->milestones()->create(['name' => 'Elsewhere']);
    patch(route('grp.models.ticket.project.update', $second->id), ['ticket_project_milestone_id' => $otherMilestone->id])->assertSessionHasErrors('ticket_project_milestone_id');
    expect($second->fresh()->ticket_project_milestone_id)->toBe($trained->id);
    patch(route('grp.models.ticket.project.update', $second->id), ['ticket_project_id' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect($second->fresh()->ticket_project_id)->toBeNull();

    patch(route('grp.models.ticket_project.update', $project->id), ['milestones' => [['id' => $trained->id, 'name' => 'Packers trained']]])->assertRedirect()->assertSessionHasNoErrors();
    expect($task->fresh()->ticket_project_id)->toBe($project->id)
        ->and($task->fresh()->ticket_project_milestone_id)->toBeNull();

    patchJson(route('grp.tasks.project.update', $task->reference), ['ticket_project_milestone_id' => $trained->id])
        ->assertOk()
        ->assertJson(['ticket_project_id' => $project->id, 'ticket_project_milestone_id' => $trained->id]);

    actingAs($outsider);
    $otherProject->update(['owner_id' => $outsider->id]);
    post(route('grp.models.ticket_project.work.attach', $otherProject->id), ['references' => $first->reference])->assertSessionHasErrors('references');
    expect($first->fresh()->ticket_project_id)->toBe($project->id);
    post(route('grp.models.ticket_project.update.store', $project->id), ['body' => 'hello'])->assertForbidden();
    patch(route('grp.models.ticket.project.update', $first->id), ['ticket_project_id' => null])->assertForbidden();
});

test('the project tools let a team member run a project through the AI assistant', function () {
    $member   = User::factory()->create(['group_id' => $this->group->id, 'username' => 'projmember'.Str::random(4)]);
    $outsider = User::factory()->create(['group_id' => $this->group->id]);
    $ticket   = StoreTicket::make()->action($this->group, ['subject' => 'Move the filling line']);
    $task     = \App\Actions\Tasks\StoreStaffTask::run($member, ['subject' => 'Book the movers', 'department' => 'warehouse']);

    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'create', 'name' => 'Factory move', 'goal' => 'Move production', 'start_date' => now()->subWeek()->toDateString(), 'target_date' => now()->addWeeks(11)->toDateString(), 'team' => $member->username])->assertOk()->assertSee('factory-move');
    $project = TicketProject::where('slug', 'factory-move')->firstOrFail();
    expect($project->owner_id)->toBe($member->id);

    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'Line moved', 'due_date' => now()->addWeeks(4)->toDateString()])->assertOk();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'Factory move', 'milestone' => 'Staff trained'])->assertOk();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'staff trained', 'position' => 1, 'rename' => 'Staff ready'])->assertOk();
    expect($project->milestones()->pluck('name')->all())->toBe(['Staff ready', 'Line moved']);

    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'add_work', 'project' => 'factory-move', 'references' => "{$ticket->reference} {$task->reference}", 'milestone' => 'Line moved'])->assertOk();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'move_work', 'project' => 'factory-move', 'references' => $task->reference, 'milestone' => 'Staff ready'])->assertOk();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'move_work', 'project' => 'factory-move', 'references' => $task->reference, 'milestone' => 'Nope'])->assertHasErrors();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'move_work', 'project' => 'factory-move', 'references' => $task->reference])->assertHasErrors();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'none'])->assertHasErrors();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'Ghost', 'rename' => 'Real'])->assertHasErrors();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'Line moved', 'start_date' => now()->addWeeks(5)->toDateString()])->assertHasErrors();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'Line moved', 'due_date' => 'next friday'])->assertHasErrors();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'Line moved'])->assertHasErrors();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'update', 'project' => 'factory-move', 'target_date' => now()->subMonth()->toDateString()])->assertHasErrors();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'add_work', 'project' => 'factory-move', 'references' => ' '])->assertHasErrors();
    expect($project->milestones()->pluck('name')->all())->toBe(['Staff ready', 'Line moved'])
        ->and($task->fresh()->milestone->name)->toBe('Staff ready');
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'Line moved', 'due_date' => 'none'])->assertOk();
    expect($project->milestones()->where('name', 'Line moved')->first()->due_date)->toBeNull();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'post_update', 'project' => 'factory-move', 'body' => 'Movers booked', 'health' => 'on_track'])->assertOk();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'Staff ready', 'done' => true])->assertOk();

    expect($ticket->fresh()->milestone->name)->toBe('Line moved')
        ->and($task->fresh()->milestone->name)->toBe('Staff ready')
        ->and($project->milestones()->where('name', 'Staff ready')->first()->done_at)->not->toBeNull();

    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectsTool::class, ['project' => 'factory-move'])->assertOk()->assertSee(['Movers booked', 'on_track', $ticket->reference, 'Staff ready']);
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectsTool::class, [])->assertOk()->assertSee('factory-move');

    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'milestone', 'project' => 'factory-move', 'milestone' => 'Line moved', 'delete' => true])->assertOk();
    expect($ticket->fresh()->ticket_project_id)->toBe($project->id)->and($ticket->fresh()->ticket_project_milestone_id)->toBeNull();
    AikuServer::actingAs($member)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'move_work', 'project' => 'factory-move', 'references' => $ticket->reference, 'remove' => true])->assertOk();
    expect($ticket->fresh()->ticket_project_id)->toBeNull();

    AikuServer::actingAs($outsider)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'post_update', 'project' => 'factory-move', 'body' => 'hi'])->assertHasErrors();
    AikuServer::actingAs($outsider)->tool(\App\Mcp\Tools\ProjectWriteTool::class, ['action' => 'move_work', 'project' => 'factory-move', 'references' => $task->reference, 'remove' => true])->assertHasErrors();
    expect($task->fresh()->ticket_project_id)->toBe($project->id);
});

test('ticket page suggests similar tickets the viewer can see', function () {
    setPermissionsTeamId($this->group->id);
    $outsider = User::factory()->create(['group_id' => $this->group->id]);

    $ticket  = StoreTicket::make()->action($this->group, ['subject' => 'Shopify orders stuck in processing', 'description' => 'Orders from the Shopify sales channel never leave processing']);
    $similar = StoreTicket::make()->action($this->group, ['subject' => 'Shopify order processing delayed', 'description' => 'Shopify sales channel orders stay in processing']);
    $other   = StoreTicket::make()->action($this->group, ['subject' => 'Printer out of toner']);
    $hidden  = StoreTicket::make()->action($this->group, ['subject' => 'Shopify processing confidential', 'is_confidential' => true]);

    $response = get(route('grp.json.ticket.similar', $ticket->id))->assertOk();
    $found    = collect($response->json())->pluck('reference');

    expect($found->all())->toContain($similar->reference)
        ->and($found->all())->not->toContain($ticket->reference)
        ->and($found->all())->not->toContain($other->reference)
        ->and($response->json('0'))->toHaveKeys(['id', 'reference', 'subject', 'status_label', 'status_icon', 'type_icon']);

    actingAs($outsider);
    expect(collect(get(route('grp.json.ticket.similar', $ticket->id))->assertOk()->json())->pluck('reference')->all())
        ->not->toContain($hidden->reference);
    get(route('grp.json.ticket.similar', $hidden->id))->assertForbidden();
});

test('jev decides which shortlisted tickets are really similar', function () {
    config(['services.openrouter.api_key' => 'test-key']);

    $ticket   = StoreTicket::make()->action($this->group, ['subject' => 'Shopify orders stuck in processing', 'description' => 'Orders from the Shopify sales channel never leave processing']);
    $wordy    = StoreTicket::make()->action($this->group, ['subject' => 'Shopify orders processing stuck again', 'description' => 'Shopify sales channel orders processing']);
    $sameIdea = StoreTicket::make()->action($this->group, ['subject' => 'Shopify sync delayed', 'description' => 'Orders do not come through from the store']);

    \Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/alpha/decisions' => \Illuminate\Support\Facades\Http::response(['answers' => [
            'ticket_'.$wordy->id    => ['noul' => 0.2],
            'ticket_'.$sameIdea->id => ['noul' => 0.9],
        ]]),
    ]);

    $found = collect(get(route('grp.json.ticket.similar', $ticket->id))->assertOk()->json())->pluck('reference')->all();

    expect($found)->toBe([$sameIdea->reference]);
    \Illuminate\Support\Facades\Http::assertSentCount(1);

    get(route('grp.json.ticket.similar', $ticket->id))->assertOk();
    \Illuminate\Support\Facades\Http::assertSentCount(1);

    get(route('grp.json.ticket.similar', ['ticket' => $ticket->id, 'refresh' => 1]))->assertOk();
    \Illuminate\Support\Facades\Http::assertSentCount(2);
});

test('a ticket being written is shown related tickets judged by jev', function () {
    config(['services.openrouter.api_key' => 'test-key']);

    $related = StoreTicket::make()->action($this->group, ['subject' => 'Shopify orders stuck in processing', 'description' => 'Orders from the Shopify sales channel never leave processing']);
    $other   = StoreTicket::make()->action($this->group, ['subject' => 'Shopify invoice logo missing', 'description' => 'Logo missing on the Shopify invoice']);

    \Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/alpha/decisions' => \Illuminate\Support\Facades\Http::response(['answers' => [
            'ticket_'.$related->id => ['noul' => 0.8],
            'ticket_'.$other->id   => ['noul' => 0.1],
        ]]),
    ]);

    $draft = ['subject' => 'Shopify orders not leaving processing', 'description' => 'Since this morning Shopify orders stay in processing'];

    expect(collect(\Pest\Laravel\post(route('grp.json.ticket.similar_draft'), $draft)->assertOk()->json())->pluck('reference')->all())->toBe([$related->reference]);
    \Illuminate\Support\Facades\Http::assertSentCount(1);

    \Pest\Laravel\post(route('grp.json.ticket.similar_draft'), $draft)->assertOk();
    \Illuminate\Support\Facades\Http::assertSentCount(1);

    \Pest\Laravel\post(route('grp.json.ticket.similar_draft'), [...$draft, 'refresh' => true])->assertOk();
    \Illuminate\Support\Facades\Http::assertSentCount(2);

    expect(\Pest\Laravel\post(route('grp.json.ticket.similar_draft'), ['subject' => 'hi'])->assertOk()->json())->toBe([]);
});

test('tickets are linked jira style and read from both sides', function () {
    $blocker = StoreTicket::make()->action($this->group, ['subject' => 'Stock sync fails']);
    $blocked = StoreTicket::make()->action($this->group, ['subject' => 'Shop shows wrong stock']);
    $customer = StoreTicket::make()->action($this->group, ['subject' => 'Customer cannot see stock', 'type' => 'customer']);

    post(route('grp.models.ticket.link.store', $blocked->id), ['linked_ticket_id' => $blocker->id, 'type' => 'blocked_by'])->assertRedirect()->assertSessionHasNoErrors();
    post(route('grp.models.ticket.link.store', $blocked->id), ['linked_ticket_id' => $customer->id, 'type' => 'relates'])->assertSessionHasNoErrors();
    post(route('grp.models.ticket.link.store', $blocker->id), ['linked_ticket_id' => $blocked->id, 'type' => 'relates'])->assertSessionHasErrors('linked_ticket_id');
    post(route('grp.models.ticket.link.store', $blocked->id), ['linked_ticket_id' => $blocked->id, 'type' => 'relates'])->assertSessionHasErrors('linked_ticket_id');

    $link = \App\Models\Helpers\TicketLink::where('ticket_id', $blocker->id)->firstOrFail();
    expect($link->linked_ticket_id)->toBe($blocked->id)
        ->and($link->type)->toBe(\App\Enums\Helpers\Ticket\TicketLinkTypeEnum::BLOCKS);

    $linksOf = fn ($ticket) => collect(\App\Actions\Helpers\Ticket\UI\ShowTicket::make()->linksFor($ticket->fresh(), $this->user))->map(fn ($row) => $row['label'].' '.$row['reference'])->all();
    expect($linksOf($blocker))->toBe(['Is blocking '.$blocked->reference])
        ->and($linksOf($blocked))->toContain('Is blocked by '.$blocker->reference, 'Relates to '.$customer->reference);

    $props = get(route('grp.tickets.show', $blocked->reference))->inertiaProps();
    expect($props['can_link'])->toBeTrue()
        ->and($props['links'])->toHaveCount(2)
        ->and(collect($props['timeline'])->pluck('text')->all())->toContain('Linked: Is blocked by '.$blocker->reference);

    $plain = User::factory()->create(['group_id' => $this->group->id]);
    actingAs($plain);
    delete(route('grp.models.ticket.link.delete', $link->id))->assertForbidden();

    $qa = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $qa->assignRole('qa');
    actingAs($qa);
    delete(route('grp.models.ticket.link.delete', $link->id))->assertRedirect();
    expect(\App\Models\Helpers\TicketLink::find($link->id))->toBeNull()
        ->and(collect(\App\Actions\Helpers\Ticket\UI\ShowTicket::make()->linksFor($blocker->fresh(), $this->user))->all())->toBe([]);
});

test('a linked ticket is created as INI by default, HELP allowed, never a customer ticket', function () {
    $source = StoreTicket::make()->action($this->group, ['subject' => 'Packing list totals wrong']);

    get(route('grp.tickets.create', ['link_ticket' => $source->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('linkFrom.reference', $source->reference)
        ->where('types', fn ($types) => collect($types)->pluck('value')->all() === ['engineer', 'help']));

    post(route('grp.models.ticket.store'), ['subject' => 'Fix packing totals', 'link_ticket_id' => $source->id, 'link_type' => 'blocks'])->assertRedirect();
    $created = \App\Models\Helpers\Ticket::where('subject', 'Fix packing totals')->firstOrFail();
    expect($created->type)->toBe(TicketTypeEnum::ENGINEER)
        ->and(\App\Models\Helpers\TicketLink::where('ticket_id', $source->id)->where('linked_ticket_id', $created->id)->value('type'))->toBe(\App\Enums\Helpers\Ticket\TicketLinkTypeEnum::BLOCKS);

    post(route('grp.models.ticket.store'), ['subject' => 'Ask the customer', 'type' => 'customer', 'link_ticket_id' => $source->id])->assertSessionHasErrors('type');
    post(route('grp.models.ticket.store'), ['subject' => 'Help side', 'type' => 'help', 'link_ticket_id' => $source->id])->assertSessionHasNoErrors();

    $plain = User::factory()->create(['group_id' => $this->group->id]);
    actingAs($plain);
    get(route('grp.tickets.create', ['link_ticket' => $source->id]))->assertForbidden();
});

test('the ticket page only reads cached similar tickets until asked', function () {
    config(['services.openrouter.api_key' => 'test-key']);
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Shopify orders stuck in processing']);
    StoreTicket::make()->action($this->group, ['subject' => 'Shopify orders processing delayed']);
    \Illuminate\Support\Facades\Http::fake(['openrouter.ai/*' => \Illuminate\Support\Facades\Http::response(['answers' => []])]);

    expect(\Pest\Laravel\getJson(route('grp.json.ticket.similar', ['ticket' => $ticket->id, 'cached_only' => 1]))->assertOk()->json('cached'))->toBeFalse();
    \Illuminate\Support\Facades\Http::assertNothingSent();

    \Pest\Laravel\getJson(route('grp.json.ticket.similar', $ticket->id))->assertOk();
    expect(\Pest\Laravel\getJson(route('grp.json.ticket.similar', ['ticket' => $ticket->id, 'cached_only' => 1]))->assertOk()->json())->toBe([]);
    \Illuminate\Support\Facades\Http::assertSentCount(1);
});
