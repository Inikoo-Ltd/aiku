<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use Illuminate\Support\Arr;
use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\GetAgentUnreadMessagesSummary;
use App\Actions\Chat\ChatSession\CloseEmptyChatSessions;
use App\Actions\Chat\ChatSession\GetChatReports;
use App\Actions\Chat\ChatSession\GetAgentChatNotifications;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatAgent;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreOfflineMessage;
use Illuminate\Support\Facades\RateLimiter;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Actions\Chat\GetCustomerChatHistory;
use App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Actions\Chat\ChatSession\MarkChatSessionAsRubbish;
use App\Enums\CRM\Livechat\ChatIgnoreReasonEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\get;

require_once __DIR__.'/../Support/ChatHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $web = Website::has('shop')->first();
    if (!$web) {
        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createShop();
        $web = createWebsite($this->shop);
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
        $this->shop         = $web->shop;
    }
    $web->refresh();
    $this->web       = $web;
    $this->warehouse = createWarehouse();

    $this->customer = createCustomer($this->shop);

    $this->action               = new StoreChatSession();
    $this->sendMessageAction    = new SendChatMessage();
    $this->assignmentChatAction = new AssignChatToAgent();
    $this->closeChatAction      = new CloseChatSession();

    \Illuminate\Support\Facades\Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
});

test('an offline message cannot borrow another customers name and email', function () {
    $victim = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());
    \Illuminate\Support\Facades\Auth::guard('retina')->logout();

    $session = StoreOfflineMessage::make()->handle($this->shop, [
        'web_user_id' => $victim->id,
        'message'     => 'Send my replies here',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::USER->value,
    ]);

    expect($session->web_user_id)->toBeNull()
        ->and(\Illuminate\Support\Arr::get($session->metadata, 'email_from'))->not->toBe($victim->email);
});

test('a chat session cannot be bound to a web user the caller is not logged in as', function () {
    $victim = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());

    $modelData = [
        'web_user_id' => $victim->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ];

    \Illuminate\Support\Facades\Auth::guard('retina')->logout();

    $hijacked = $this->action->handle($modelData);

    expect($hijacked->web_user_id)->toBeNull()
        ->and($hijacked->guest_identifier)->not->toBeNull();

    \Illuminate\Support\Facades\Auth::guard('retina')->login($victim);

    $legitimate = $this->action->handle($modelData);

    expect($legitimate->web_user_id)->toBe($victim->id);
});

test('an agent queue can only be read by the agent it belongs to', function () {
    actingAs($this->user);

    $someoneElse = \App\Models\SysAdmin\User::where('id', '!=', $this->user->id)->firstOr(function () {
        return \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->user->group_id]);
    });

    getJson('/app/api/chats/users/'.$someoneElse->id.'/unread-messages')->assertForbidden();
    getJson('/app/api/chats/users/'.$someoneElse->id.'/agent-notifications')->assertForbidden();

    getJson('/app/api/chats/users/'.$this->user->id.'/unread-messages')->assertOk();
    getJson('/app/api/chats/users/'.$this->user->id.'/agent-notifications')
        ->assertOk()
        ->assertJsonStructure(['data' => ['team_unread']])
        ->assertJsonMissingPath('data.waiting')
        ->assertJsonMissingPath('data.whatsapp');
});

test('customer chat history merges website and whatsapp sessions', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $webUser  = StoreWebUser::make()->action($customer, WebUser::factory()->definition());

    $websiteSession = ChatSession::create([
        'ulid'        => (string)Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'web_user_id' => $webUser->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
    ]);

    // GetChatSessions only surfaces sessions that actually have messages.
    SendChatMessage::make()->handle($websiteSession, [
        'message_text' => 'Website enquiry',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::GUEST->value,
        'sender_id'    => null,
    ]);

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $whatsappSession = MetaChatSession::create([
        'ulid'            => (string)Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $customer->id,
        'phone_number'    => '+628123456789',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $result = GetCustomerChatHistory::make()->handle(['customer_id' => $customer->id]);

    $byUlid = $result['rows']->keyBy(fn (array $row) => $row['session']->ulid);

    expect($result['rows'])->toHaveCount(2)
        ->and($byUlid[$websiteSession->ulid]['channel'])->toBe('website')
        ->and($byUlid[$whatsappSession->ulid]['channel'])->toBe('whatsapp');

    $websiteSession->update(['channel' => ChatChannelEnum::EMAIL]);

    $emailRow = GetCustomerChatHistory::make()->handle(['customer_id' => $customer->id])['rows']
        ->firstWhere(fn (array $row) => $row['session']->ulid === $websiteSession->ulid);

    expect($emailRow['channel'])->toBe('email');
});

test('customer chat history resolves the customer from a web user id', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $webUser  = StoreWebUser::make()->action($customer, WebUser::factory()->definition());

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $whatsappSession = MetaChatSession::create([
        'ulid'            => (string)Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $customer->id,
        'phone_number'    => '+628987654321',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    // Only the web user id is known; the WhatsApp thread is keyed by customer.
    $result = GetCustomerChatHistory::make()->handle(['web_user_id' => $webUser->id]);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['channel'])->toBe('whatsapp')
        ->and($result['rows'][0]['session']->ulid)->toBe($whatsappSession->ulid);
});

test('customer chat history is empty when no identity is given', function () {
    $result = GetCustomerChatHistory::make()->handle([]);

    expect($result['rows'])->toHaveCount(0)
        ->and($result['has_more'])->toBeFalse();
});

test('empty widget sessions are not counted open and the sweep closes them quietly', function () {
    $this->freezeTime();
    [, , $emptyShop] = createOwnShop(__FILE__.':chat-empty');

    $session = fn () => ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $emptyShop->id,
        'channel'          => ChatChannelEnum::WEBSITE,
        'created_at'       => now()->subDays(3),
        'updated_at'       => now(),
    ]);

    $widgetOnlyOpened = $session();
    $waitingForReply  = $session();
    ChatMessage::create([
        'chat_session_id' => $waitingForReply->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'hello',
        'is_read'         => false,
    ]);

    $open = fn () => collect(GetChatReports::make()->handle(collect([$emptyShop->id]), '1d')['daily'])->last()['open'];

    expect($open())->toBe(1);

    CloseEmptyChatSessions::make()->handle();

    expect($widgetOnlyOpened->fresh()->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($widgetOnlyOpened->messages()->count())->toBe(0)
        ->and($waitingForReply->fresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($open())->toBe(1);
});

test('ignoring a conversation records why, and undoing it puts the conversation back', function () {
    setPermissionsTeamId($this->user->group_id);

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agentProfile = ChatAgent::create(['user_id' => $agent->id, 'max_concurrent_chats' => 5, 'language_id' => 68]);

    $session = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::EMAIL,
        'status'  => ChatSessionStatusEnum::WAITING,
    ]);

    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_text'    => 'I am out of the office',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);

    $waiting = fn () => GetChatSessions::make()->handle([
        'shop_id'  => $this->shop->id,
        'statuses' => ['waiting'],
    ])->total();

    $rubbish = fn () => GetChatSessions::make()->handle([
        'shop_id'    => $this->shop->id,
        'is_rubbish' => 1,
    ])->total();

    $before        = $waiting();
    $rubbishBefore = $rubbish();

    MarkChatSessionAsRubbish::make()->handle($session, $agentProfile, true, ChatIgnoreReasonEnum::OUT_OF_OFFICE);
    $session->refresh();

    // The reason is countable, which is the point: the noise can be named and dealt with at
    // its source instead of one conversation at a time.
    expect($session->rubbish_reason)->toBe('out_of_office')
        // Hidden from the queue, but its status is untouched, and the sender is never blocked.
        ->and($session->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($waiting())->toBe($before - 1)
        ->and(Arr::get($this->shop->fresh()->settings, 'gmail.blocked_senders', []))->toBe([])
        ->and($rubbish())->toBe($rubbishBefore + 1);

    MarkChatSessionAsRubbish::make()->handle($session, $agentProfile, false);

    expect($session->fresh()->rubbish_reason)->toBeNull()
        ->and($waiting())->toBe($before);

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('a chat another agent is holding can only be disposed of by them or a supervisor', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    setPermissionsTeamId($this->user->group_id);

    $holder = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $holder->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $holdingAgent = ChatAgent::create([
        'user_id'              => $holder->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $session->assignments()->create([
        'chat_agent_id' => $holdingAgent->id,
        'status'        => \App\Enums\CRM\Livechat\ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'   => \App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'   => now(),
    ]);

    $colleague = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $colleague->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_SUPERVISOR->value, $this->shop));

    expect(\App\Actions\Chat\CanDisposeOfChat::run($holder, $session->fresh()))->toBeTrue()
        ->and(\App\Actions\Chat\CanDisposeOfChat::run($colleague, $session->fresh()))->toBeFalse()
        ->and(\App\Actions\Chat\CanDisposeOfChat::run($supervisor, $session->fresh()))->toBeTrue();

    // and an unassigned conversation stays anybody's to clear
    $waiting = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    expect(\App\Actions\Chat\CanDisposeOfChat::run($colleague, $waiting))->toBeTrue();

    $this->actingAs($colleague);

    $response = $this->patchJson(route('grp.org.chat.agents.sessions.spam', [$this->organisation->slug, $session->ulid]));

    $response->assertStatus(403);
    expect($response->json('message'))->toContain($holder->contact_name);
});

test('a website guest who gave a customer\'s email is suggested, never linked, until an agent confirms', function () {
    StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());
    $this->customer->update(['email' => 'buyer@janesgifts.test']);

    $agentProfile = ChatAgent::firstOrCreate(['user_id' => $this->user->id], ['max_concurrent_chats' => 5, 'language_id' => 68]);

    $guest = fn (array $metadata) => ChatSession::create([
        'ulid'     => (string) Str::ulid(),
        'status'   => ChatSessionStatusEnum::WAITING,
        'channel'  => ChatChannelEnum::WEBSITE,
        'shop_id'  => $this->shop->id,
        'metadata' => $metadata,
    ]);

    $suggest = fn ($chatSession) => \App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::make()->handle($chatSession)->refresh();

    $byEmail = $suggest($guest(['email' => 'Buyer@JanesGifts.test']));

    expect($byEmail->web_user_id)->toBeNull()
        ->and($byEmail->suggested_customer_id)->toBe($this->customer->id)
        ->and($byEmail->suggestion_basis)->toBe('email')
        ->and(\App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::forList($byEmail)['customer']['name'])->toBe($this->customer->name);

    $colleague = $suggest($guest(['email' => 'accounts@janesgifts.test']));

    expect($colleague->suggested_customer_id)->toBeNull()
        ->and($colleague->suggestion_hint)->toContain($this->customer->name)
        ->and(\App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::forList($colleague)['customer'])->toBeNull();

    $stranger = $suggest($guest(['email' => 'someone@gmail.com']));
    expect($stranger->suggested_customer_id)->toBeNull()->and($stranger->suggestion_hint)->toBeNull();

    $confirmed = \App\Actions\Chat\ChatSession\ConfirmSuggestedChatCustomer::make()->handle($byEmail, $agentProfile, true);
    expect($confirmed->id)->toBe($this->customer->id)
        ->and($byEmail->refresh()->webUser->customer_id)->toBe($this->customer->id);

    $other = $suggest($guest(['email' => 'buyer@janesgifts.test']));
    \App\Actions\Chat\ChatSession\ConfirmSuggestedChatCustomer::make()->handle($other, $agentProfile, false);
    $other = $suggest($other->refresh());

    expect($other->web_user_id)->toBeNull()
        ->and($other->suggestion_rejected_at)->not->toBeNull()
        ->and(\App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::forList($other))->toBeNull();

    $outcomes = collect(GetChatReports::make()->handle(collect([$this->shop->id]), 'all')['customer_suggestions'])->firstWhere('basis', 'email');
    expect($outcomes['confirmed'])->toBeGreaterThanOrEqual(1)->and($outcomes['rejected'])->toBeGreaterThanOrEqual(1);
});

test('an agent links a customer by hand to one conversation, and the next email from that address is only suggested', function () {
    actingAs($this->user);
    StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());
    $this->customer->update(['email' => 'buyer@janesgifts.test', 'searchable_text' => 'jane buyer janesgifts']);

    $guest = fn () => ChatSession::create([
        'ulid'     => (string) Str::ulid(),
        'status'   => ChatSessionStatusEnum::WAITING,
        'channel'  => ChatChannelEnum::WEBSITE,
        'shop_id'  => $this->shop->id,
        'metadata' => ['email' => 'jane.personal@gmail.com'],
    ]);

    $first = $guest();
    $base  = '/app/api/chats/sessions/'.$first->ulid;

    expect($this->getJson($base.'/customer-candidates?q=janesgifts')->assertOk()->json('0.id'))->toBe($this->customer->id);
    $this->putJson($base.'/customer', ['customer_id' => 999999999])->assertStatus(422);
    $this->putJson($base.'/customer', ['customer_id' => $this->customer->id])->assertOk();

    expect($first->refresh()->webUser->customer_id)->toBe($this->customer->id)
        ->and($first->suggestion_basis)->toBe('manual')
        ->and($this->customer->refresh()->email)->toBe('buyer@janesgifts.test')
        ->and($first->audits()->where('event', 'updated')->latest('id')->first()->new_values['web_user_id'])->toBe($first->web_user_id)
        ->and($first->audits()->latest('id')->first()->user_id)->toBe($this->user->id)
        ->and($this->customer->audits()->where('event', 'chat_linked')->latest('id')->first()->new_values['chat_session'])->toBe($first->ulid);

    $mistake = $guest();
    $this->putJson('/app/api/chats/sessions/'.$mistake->ulid.'/customer', ['customer_id' => $this->customer->id])->assertOk();
    $this->deleteJson('/app/api/chats/sessions/'.$mistake->ulid.'/customer')->assertOk();
    $this->deleteJson('/app/api/chats/sessions/'.$mistake->ulid.'/customer')->assertStatus(422);

    expect($mistake->refresh()->web_user_id)->toBeNull()
        ->and($this->customer->audits()->where('event', 'chat_unlinked')->latest('id')->first()->user_id)->toBe($this->user->id)
        ->and(\App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::make()->handle($mistake)->refresh()->suggested_customer_id)->toBeNull();

    $second = \App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::make()->handle($guest())->refresh();

    expect($second->web_user_id)->toBeNull()
        ->and($second->suggested_customer_id)->toBe($this->customer->id)
        ->and($second->suggestion_basis)->toBe('previous_link');

    $stranger = StoreCustomer::make()->action($this->shop, array_merge(Customer::factory()->definition(), ['email' => 'jane.personal@gmail.com']));
    StoreWebUser::make()->action($stranger, WebUser::factory()->definition());
    $third = \App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::make()->handle($guest())->refresh();

    expect($third->suggested_customer_id)->toBe($stranger->id)->and($third->suggestion_basis)->toBe('email');
});

test('a WhatsApp guest is suggested by the number they write from, whatever way it was stored', function () {
    config(['chat.noise.greet_bare_hello' => false]);
    $this->customer->update(['phone' => '07500 111222']);

    $metaChatSession = noiseTestWhatsappSession($this->shop, '+447500111222', 'Hello');
    $metaChatSession = \App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::make()->handle($metaChatSession)->refresh();

    expect($metaChatSession->customer_id)->toBeNull()
        ->and($metaChatSession->suggested_customer_id)->toBe($this->customer->id)
        ->and($metaChatSession->suggestion_basis)->toBe('phone');
});

test('a quoted reference suggests a customer only when it can mean nobody else', function () {
    StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());
    $newcomer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    StoreWebUser::make()->action($newcomer, WebUser::factory()->definition());

    $collidingReference = 'AWD'.random_int(100000, 999999);
    $orderReference     = 'AWD'.random_int(100000, 999999);
    $newcomerReference  = 'AWD'.random_int(100000, 999999);
    $numericReference   = (string) random_int(10000000, 99999999);
    $collider           = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $collider->update(['reference' => $collidingReference]);
    $newcomer->update(['reference' => $newcomerReference]);

    foreach ([$collidingReference, $orderReference, $numericReference] as $reference) {
        \App\Actions\Ordering\Order\StoreOrder::make()->action($this->customer, [
            'reference'        => $reference,
            'date'             => now()->subYears(6)->toDateString(),
            'customer_id'      => $this->customer->id,
            'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
            'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        ]);
    }

    $guest = fn (string $subject) => \App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::make()->handle(ChatSession::create([
        'ulid'     => (string) Str::ulid(),
        'status'   => ChatSessionStatusEnum::WAITING,
        'channel'  => ChatChannelEnum::WEBSITE,
        'shop_id'  => $this->shop->id,
        'metadata' => ['email_subject' => $subject],
    ]))->refresh();

    $byCustomerReference = $guest('Customer reference: #'.$newcomerReference);

    expect($byCustomerReference->suggested_customer_id)->toBe($newcomer->id)
        ->and($byCustomerReference->suggestion_basis)->toBe('customer_reference')
        ->and(\App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::forList($byCustomerReference)['label'])->toBe('Quoted this customer\'s reference');

    $byOrder = $guest('Where is my order '.$orderReference.'?');

    expect($byOrder->suggested_customer_id)->toBe($this->customer->id)
        ->and($byOrder->suggestion_basis)->toBe('order')
        ->and($guest('Customer reference: #'.$collidingReference)->suggested_customer_id)->toBeNull()
        ->and($guest('Order '.$orderReference.', my account '.$newcomerReference)->suggested_customer_id)->toBeNull();

    $newcomer->update(['reference' => $numericReference]);
    $collider->update(['reference' => $numericReference.'0']);

    expect($guest('Where is order '.$numericReference.'?')->suggested_customer_id)->toBeNull()
        ->and($guest('Delivery to postcode '.$numericReference.'0')->suggested_customer_id)->toBeNull();
});

test('a guest who writes like a customer and cannot be identified is asked once, and never by email', function () {
    config(['chat.noise.greet_bare_hello' => false]);
    config([
        'askbot-laravel.openai_api_key' => 'test-key',
        'chat.noise.test_answer'        => json_encode(['verdict' => 'genuine', 'confidence' => 95, 'reason' => 'Asks about an order.', 'existing_customer' => true]),
    ]);
    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => fn () => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => config('chat.noise.test_answer')]]]]),
    ]);

    $website = ChatSession::create([
        'ulid'     => (string) Str::ulid(),
        'status'   => ChatSessionStatusEnum::WAITING,
        'channel'  => ChatChannelEnum::WEBSITE,
        'shop_id'  => $this->shop->id,
        'metadata' => ['email' => 'nobody-we-know@gmail.com'],
    ]);
    ChatMessage::create([
        'chat_session_id' => $website->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => 'Where is my order? It was due on Friday.',
    ]);

    $website = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($website)->refresh();

    $asked = $website->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->get();

    expect($asked)->toHaveCount(1)
        ->and($asked->first()->message_text)->toContain('order number')
        ->and($website->customer_asked_at)->not->toBeNull()
        ->and($website->is_spam)->toBeFalse()
        ->and(\App\Actions\Chat\ChatSession\AskGuestIfCustomer::make()->handle($website))->toBeFalse();

    $email = noiseTestEmailSession($this->shop, 'someone@example.com', 'My order', 'Where is my order?');
    $email = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($email)->refresh();

    expect($email->customer_asked_at)->toBeNull()
        ->and($email->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->count())->toBe(0);
});

test('a phone call takes the agent out of the rota, is filed only against their own shops, and is read only by them', function () {
    setPermissionsTeamId($this->user->group_id);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create([
        'user_id'              => $clerk->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
    ]);

    expect(ChatAgent::available()->whereKey($agent->id)->exists())->toBeTrue();

    $this->actingAs($clerk);

    $first  = $this->postJson(route('grp.chat.phone_calls.start'), ['shop_id' => $this->shop->id])->assertOk()->json('call.id');
    $second = $this->postJson(route('grp.chat.phone_calls.start'), ['shop_id' => $this->shop->id])->assertOk()->json('call.id');

    expect($second)->toBe($first)
        ->and($agent->fresh()->isOnPhoneCall())->toBeTrue()
        ->and(ChatAgent::available()->whereKey($agent->id)->exists())->toBeFalse();

    [, , $otherShop] = createOwnShop('phone-calls-other-shop');
    $foreignSession  = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $otherShop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $this->postJson(route('grp.chat.phone_calls.end'), [
        'notes'           => 'Asked about a delivery',
        'contact_type'    => 'guest',
        'chat_session_id' => $foreignSession->id,
    ])->assertStatus(422);

    expect($foreignSession->chatEvents()->where('event_type', ChatEventTypeEnum::PHONE_CALL)->exists())->toBeFalse();

    $ownSession = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $this->postJson(route('grp.chat.phone_calls.end'), [
        'notes'           => 'Asked about a delivery',
        'contact_type'    => 'guest',
        'chat_session_id' => $ownSession->id,
    ])->assertOk();

    expect($ownSession->chatEvents()->where('event_type', ChatEventTypeEnum::PHONE_CALL)->count())->toBe(1)
        ->and(ChatAgent::available()->whereKey($agent->id)->exists())->toBeTrue();

    $stale = \App\Models\Chat\ChatPhoneCall::create([
        'group_id'      => $clerk->group_id,
        'shop_id'       => $this->shop->id,
        'chat_agent_id' => $agent->id,
        'user_id'       => $clerk->id,
        'status'        => \App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum::IN_PROGRESS,
        'started_at'    => now()->subMinutes(config('chat.phone_call.max_minutes') + 1),
    ]);

    expect(\App\Actions\Chat\PhoneCall\AutoCloseStaleChatPhoneCalls::run())->toBe(1)
        ->and($stale->fresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum::AUTO_CLOSED)
        ->and($stale->fresh()->duration_seconds)->toBeNull()
        ->and((int) \App\Models\Chat\ChatPhoneCall::where('chat_agent_id', $agent->id)->sum('duration_seconds'))
        ->toBe((int) \App\Models\Chat\ChatPhoneCall::where('chat_agent_id', $agent->id)->where('status', \App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum::COMPLETED)->sum('duration_seconds'));

    $this->get(route('grp.chat.phone_calls.index'))->assertForbidden();

    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $outsider->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $otherShop));

    $seen = \App\Actions\Chat\PhoneCall\UI\IndexChatPhoneCalls::make()->handle($this->organisation->group, 'phone_calls', $outsider);
    $own  = \App\Actions\Chat\PhoneCall\UI\IndexChatPhoneCalls::make()->handle($this->organisation->group, 'phone_calls', $clerk);

    expect($seen->total())->toBe(0)
        ->and($own->total())->toBe(2);
});

test('a conversation nobody has taken past its channel time joins the group queue, and a held or fresh one does not', function () {
    \Illuminate\Support\Facades\Http::fake();

    config([
        'chat.unclaimed.after_seconds.email'    => 7200,
        'chat.unclaimed.after_seconds.website'  => 120,
        'chat.unclaimed.after_seconds.whatsapp' => 1800,
    ]);

    $stale = noiseTestEmailSession($this->shop, 'waited@example.com', 'Nobody answered', 'Where is my order');
    $stale->update(['last_visitor_message_at' => now()->subHours(3)]);

    $fresh = noiseTestEmailSession($this->shop, 'justnow@example.com', 'Just arrived', 'Where is my order');
    $fresh->update(['last_visitor_message_at' => now()->subMinutes(5)]);

    $held = noiseTestEmailSession($this->shop, 'taken@example.com', 'Being answered', 'Where is my order');
    $held->update(['last_visitor_message_at' => now()->subHours(3)]);

    $agent = ChatAgent::firstOrCreate(
        ['user_id' => $this->user->id],
        [
            'max_concurrent_chats' => 5,
            'language_id'          => 68,
            'is_online'            => true,
            'is_available'         => true,
            'current_chat_count'   => 0,
        ]
    );

    $held->assignments()->create([
        'chat_agent_id' => $agent->id,
        'status'        => ChatAssignmentStatusEnum::ACTIVE,
        'assigned_by'   => ChatAssignmentAssignedByEnum::SYSTEM,
        'assigned_at'   => now(),
    ]);

    // A widget opened and abandoned without a word: 4,115 of these sit in `waiting` on
    // production, and counting them would make every number here nonsense.
    $empty = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::WAITING,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'guest_identifier'        => 'guest_'.Str::random(5),
        'last_visitor_message_at' => now()->subDays(2),
    ]);

    $queue = collect(GetChatSessions::make()->handle(['unclaimed' => true, 'limit' => 1000])->items())->pluck('id')->all();

    expect($queue)->toContain($stale->id)
        ->and($queue)->not->toContain($fresh->id)
        ->and($queue)->not->toContain($held->id)
        ->and($queue)->not->toContain($empty->id);
});

test('the unclaimed queue is the whole group\'s, not the shops the person asking works', function () {
    \Illuminate\Support\Facades\Http::fake();

    config(['chat.unclaimed.after_seconds.email' => 7200]);

    [, , $otherShop] = createOwnShop('unclaimed-other-shop');

    $foreign = noiseTestEmailSession($otherShop, 'bulgaria@example.com', 'Nobody watching', 'Where is my order');
    $foreign->update(['last_visitor_message_at' => now()->subHours(3)]);

    $action = GetChatSessions::make();
    $user   = $this->user;

    $scopeFor = fn (array $filters) => (function (array $filters) use ($user) {
        return $this->chatFiltersScopedTo($user, $filters);
    })->call($action, $filters);

    expect($scopeFor(['unclaimed' => true]))->not->toHaveKey('allowed_shop_ids')
        ->and($scopeFor([]))->toHaveKey('allowed_shop_ids');

    $queue = collect($action->handle($scopeFor(['unclaimed' => true]) + ['limit' => 1000])->items())->pluck('id')->all();

    expect($queue)->toContain($foreign->id);
});

test('the unclaimed count follows the shops picked on the rail, and every shop when none is', function () {
    \Illuminate\Support\Facades\Http::fake();

    config(['chat.unclaimed.after_seconds.email' => 7200]);

    [, , $otherShop] = createOwnShop('unclaimed-count-other-shop');

    $here = noiseTestEmailSession($this->shop, 'here@example.com', 'Nobody answered', 'Where is my order');
    $here->update(['last_visitor_message_at' => now()->subHours(3)]);

    $there = noiseTestEmailSession($otherShop, 'there@example.com', 'Nobody answered', 'Where is my order');
    $there->update(['last_visitor_message_at' => now()->subHours(3)]);

    $agent = ChatAgent::firstOrCreate(
        ['user_id' => $this->user->id],
        ['max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]
    );

    $everyShop = GetAgentChatNotifications::make()->handle($agent)['unclaimed'];
    $thisShop  = GetAgentChatNotifications::make()->handle($agent, [$this->shop->id])['unclaimed'];
    $bothShops = GetAgentChatNotifications::make()->handle($agent, [$this->shop->id, $otherShop->id])['unclaimed'];

    expect($thisShop)->toBeLessThan($everyShop)
        ->and($bothShops)->toBe($thisShop + 1)
        ->and(GetAgentChatNotifications::make()->handle($agent, [$otherShop->id])['unclaimed'])->toBe(1);
});

test('the unclaimed alert reports the backlog once and stays quiet until it changes', function () {
    \Illuminate\Support\Facades\Http::fake();

    config([
        'chat.unclaimed.after_seconds.email' => 7200,
        'chat.unclaimed.slack_channel'       => null,
    ]);

    \Illuminate\Support\Facades\Cache::forget('chat:unclaimed:last-alerted');

    $stale = noiseTestEmailSession($this->shop, 'unanswered@example.com', 'Nobody answered', 'Where is my order');
    $stale->update(['last_visitor_message_at' => now()->subHours(3)]);

    $first = \App\Actions\Chat\AlertUnclaimedChatSessions::make()->handle();

    expect($first['total'])->toBeGreaterThanOrEqual(1)
        ->and($first['by_shop'])->toHaveKey($this->shop->name)
        ->and($first['oldest_minutes'])->toBeGreaterThanOrEqual(180);

    $signature = \Illuminate\Support\Facades\Cache::get('chat:unclaimed:last-alerted');

    \App\Actions\Chat\AlertUnclaimedChatSessions::make()->handle();

    expect(\Illuminate\Support\Facades\Cache::get('chat:unclaimed:last-alerted'))->toBe($signature);

    $another = noiseTestEmailSession($this->shop, 'also@example.com', 'Also nobody', 'Where is my order');
    $another->update(['last_visitor_message_at' => now()->subHours(3)]);

    \App\Actions\Chat\AlertUnclaimedChatSessions::make()->handle();

    expect(\Illuminate\Support\Facades\Cache::get('chat:unclaimed:last-alerted'))->not->toBe($signature);

    $answeredInGmail = noiseTestEmailSession($this->shop, 'gmail.answered@example.com', 'Answered from Gmail', 'Where is my order');
    $answeredInGmail->update(['last_visitor_message_at' => now()->subHours(3), 'last_agent_message_at' => now()->subHour()]);

    expect(collect(GetChatSessions::make()->handle(['unclaimed' => true, 'limit' => 1000])->items())->pluck('id'))
        ->toContain($another->id)
        ->not->toContain($answeredInGmail->id);
});

test('a shop may set its own unclaimed time, and the rest follow the group default', function () {
    \Illuminate\Support\Facades\Http::fake();

    config(['chat.unclaimed.after_seconds.email' => 7200]);

    \Illuminate\Support\Facades\Cache::forget('chat:unclaimed:overrides:email');

    $patient = noiseTestEmailSession($this->shop, 'patient@example.com', 'Shop waits longer', 'Where is my order');
    $patient->update(['last_visitor_message_at' => now()->subHours(3)]);

    $queue = fn () => collect(GetChatSessions::make()->handle(['unclaimed' => true, 'limit' => 1000])->items())->pluck('id')->all();

    expect($queue())->toContain($patient->id);

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, ['chat_unclaimed_email_seconds' => 86400]);

    \Illuminate\Support\Facades\Cache::forget('chat:unclaimed:overrides:email');

    expect(Arr::get($this->shop->refresh()->settings, 'chat.unclaimed_after_seconds.email'))->toBe(86400)
        ->and($queue())->not->toContain($patient->id);

    // Nought is not a time, it is "no opinion": stored as one it would drag every conversation
    // on the shop into the queue the moment it arrived.
    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, ['chat_unclaimed_email_seconds' => 0]);

    \Illuminate\Support\Facades\Cache::forget('chat:unclaimed:overrides:email');

    expect(Arr::get($this->shop->refresh()->settings, 'chat.unclaimed_after_seconds.email'))->toBeNull()
        ->and($queue())->toContain($patient->id);
});

test('the bin opens for an agent when no status is asked for', function () {
    \Illuminate\Support\Facades\Http::fake();
    setPermissionsTeamId($this->user->group_id);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    ChatAgent::create(['user_id' => $clerk->id, 'max_concurrent_chats' => 10]);

    $binned = noiseTestEmailSession($this->shop, 'binned@example.com', 'Out of office', 'I am away');
    $binned->update(['is_rubbish' => true, 'rubbish_at' => now()]);

    $bin = collect(GetChatSessions::make()->handle([
        'is_rubbish'     => true,
        'assigned_to_me' => $clerk->id,
    ])->items())->pluck('id')->all();

    expect($bin)->toContain($binned->id);
});

test('an offline message becomes an email conversation only when the shop asks for it', function () {
    \Illuminate\Support\Facades\Http::fake();

    $offlineMessage = fn (string $email = 'jane@example.com') => StoreOfflineMessage::make()->handle($this->shop->refresh(), [
        'name'        => 'Jane Doe',
        'email'       => $email,
        'message'     => 'Nobody was on, please write back',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'web_user_id' => null,
    ]);

    $settings = $this->shop->settings ?? [];
    data_set($settings, 'gmail.email', 'help@example.com');
    data_set($settings, 'chat.email_offline_replies', false);
    $this->shop->updateQuietly(['settings' => $settings]);

    expect($offlineMessage('jane.first@example.com')->channel)->toBe(ChatChannelEnum::WEBSITE);

    data_set($settings, 'chat.email_offline_replies', true);
    $this->shop->updateQuietly(['settings' => $settings]);

    $emailed = $offlineMessage();

    expect($emailed->channel)->toBe(ChatChannelEnum::EMAIL)
        ->and($emailed->metadata['email_from'])->toBe('jane@example.com')
        ->and($emailed->metadata['email_from_name'])->toBe('Jane Doe')
        ->and($emailed->metadata['email_subject'])->toContain($this->shop->name);

    // No mailbox to send from means the answer would go nowhere at all, which is worse than
    // leaving it in the widget.
    data_set($settings, 'gmail.email', null);
    $this->shop->updateQuietly(['settings' => $settings]);

    expect($offlineMessage('jane.third@example.com')->channel)->toBe(ChatChannelEnum::WEBSITE);

    // The toggle is a shop setting, so it has to survive the form it is saved from without
    // taking the rest of the shop's settings with it.
    data_set($settings, 'gmail.email', 'help@example.com');
    $this->shop->updateQuietly(['settings' => $settings]);

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, ['chat_email_offline_replies' => true]);

    expect(Arr::get($this->shop->refresh()->settings, 'chat.email_offline_replies'))->toBeTrue()
        ->and(Arr::get($this->shop->settings, 'gmail.email'))->toBe('help@example.com');

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, ['chat_email_offline_replies' => false]);

    expect(Arr::get($this->shop->refresh()->settings, 'chat.email_offline_replies'))->toBeFalse();
});

test('a logged in customer is never asked for the name and email we already hold', function () {
    \Illuminate\Support\Facades\Http::fake();

    $organisation = $this->organisation;
    $website      = $this->web;
    $customer     = $this->customer;
    $group        = $this->organisation->group;

    /** @var \App\Models\CRM\WebUser $webUser */
    $webUser = WebUser::factory()->create([
        'organisation_id' => $organisation->id,
        'group_id'        => $group->id,
        'website_id'      => $website->id,
        'customer_id'     => $customer->id,
        'type'            => WebUserTypeEnum::WEB->value,
        'contact_name'    => 'Known Customer',
        'email'           => 'known@example.com',
    ]);

    $settings = $this->shop->settings ?? [];
    data_set($settings, 'gmail.email', 'help@example.com');
    data_set($settings, 'chat.email_offline_replies', true);
    $this->shop->updateQuietly(['settings' => $settings]);

    \Illuminate\Support\Facades\Auth::guard('retina')->login($webUser);

    $session = StoreOfflineMessage::make()->handle($this->shop->refresh(), [
        'message'     => 'Nobody was on, please write back',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::USER->value,
        'web_user_id' => $webUser->id,
    ]);

    expect($session->metadata['name'])->toBe('Known Customer')
        ->and($session->metadata['email'])->toBe('known@example.com')
        ->and($session->channel)->toBe(ChatChannelEnum::EMAIL)
        ->and($session->metadata['email_from'])->toBe('known@example.com');
});

test('a customer replying to a closed email conversation puts it back in the waiting queue', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $gmailMessage = fn (string $id, string $text) => [
        'id'       => $id,
        'threadId' => 'th-reopen',
        'labelIds' => ['INBOX'],
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => [
                ['name' => 'From', 'value' => 'Returning Writer <returning.writer@example.com>'],
                ['name' => 'Subject', 'value' => 'Missing lid'],
                ['name' => 'Message-ID', 'value' => "<$id@example.com>"],
            ],
            'body'     => ['data' => rtrim(strtr(base64_encode($text), '+/', '-_'), '=')],
        ],
    ];

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/r1?*'  => \Illuminate\Support\Facades\Http::response($gmailMessage('r1', 'The lid is missing')),
        'gmail.googleapis.com/gmail/v1/users/me/messages/r2?*'  => \Illuminate\Support\Facades\Http::response($gmailMessage('r2', 'It still has not arrived')),
        'gmail.googleapis.com/gmail/v1/users/me/labels'         => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L9', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'r1')->chatSession;

    $agent = ChatAgent::firstOrCreate(
        ['user_id' => $this->user->id],
        ['is_online' => true, 'max_concurrent_chats' => 100, 'current_chat_count' => 0]
    );

    $assignment = $session->assignments()->create([
        'chat_agent_id' => $agent->id,
        'status'        => \App\Enums\CRM\Livechat\ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'   => \App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'   => now(),
    ]);

    $session->update(['status' => ChatSessionStatusEnum::CLOSED, 'closed_at' => now()]);

    \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'r2');

    expect($session->refresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($session->closed_at)->toBeNull()
        ->and($assignment->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAssignmentStatusEnum::RESOLVED);
});

test('a colleague holding a whatsapp chat keeps it in the team list after they stop covering the shop', function () {
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $mine  = ChatAgent::where('user_id', $this->user->id)->first()
        ?? StoreChatAgent::make()->handle(['user_id' => $this->user->id]);
    $other = StoreChatAgent::make()->handle(['user_id' => User::factory()->create(['group_id' => $this->organisation->group_id])->id]);

    makeChatWorker($this->user, $this->shop);

    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628444555888',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $metaChatSession->assignments()->create([
        'meta_channel_id' => $channel->id,
        'chat_agent_id'   => $other->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    $team = collect(GetMetaChatSessions::make()->handle([
        'assigned_to_me' => $this->user->id,
        'view_team'      => true,
        'statuses'       => [ChatSessionStatusEnum::ACTIVE->value],
        'shop_id'        => $this->shop->id,
    ])->items())->pluck('ulid');

    expect($mine->id)->not->toBe($other->id)
        ->and($team)->toContain($metaChatSession->ulid);
});

test('a closed whatsapp chat nobody ever picked up is in the team list, not in mine', function () {
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    ChatAgent::where('user_id', $this->user->id)->first()
        ?? StoreChatAgent::make()->handle(['user_id' => $this->user->id]);

    makeChatWorker($this->user, $this->shop);

    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628444555999',
        'status'          => ChatSessionStatusEnum::CLOSED,
        'closed_at'       => now(),
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $filters = [
        'assigned_to_me' => $this->user->id,
        'statuses'       => [ChatSessionStatusEnum::CLOSED->value],
        'shop_id'        => $this->shop->id,
    ];

    $mineUlids = collect(GetMetaChatSessions::make()->handle($filters)->items())->pluck('ulid');
    $teamUlids = collect(GetMetaChatSessions::make()->handle($filters + ['view_team' => true])->items())->pluck('ulid');

    expect($mineUlids)->not->toContain($metaChatSession->ulid)
        ->and($teamUlids)->toContain($metaChatSession->ulid);
});

test('replying to a waiting conversation nobody holds claims it instead of refusing the message', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);

    $session = StoreChatSession::make()->handle([
        'shop_id'     => $this->shop->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'channel'     => ChatChannelEnum::WEBSITE,
    ]);

    $session->update(['status' => ChatSessionStatusEnum::WAITING, 'closed_at' => now(), 'closed_by' => 'agent']);

    $agent = ChatAgent::firstOrCreate(
        ['user_id' => $this->user->id],
        ['is_online' => true, 'max_concurrent_chats' => 100, 'current_chat_count' => 0]
    );

    actingAs($this->user);

    $result = SendChatMessage::make()->asController(Request::create(
        '/',
        'POST',
        ['message_text' => 'Back to you', 'message_type' => ChatMessageTypeEnum::TEXT->value, 'sender_type' => ChatSenderTypeEnum::AGENT->value],
    )->setRouteResolver(fn () => new class ($session) {
        public function __construct(private $session)
        {
        }

        public function route($name)
        {
            return (string) $this->session->ulid;
        }

        public function parameter($name, $default = null)
        {
            return (string) $this->session->ulid;
        }

        public function named(...$patterns): bool
        {
            return false;
        }
    }));

    expect($result['ok'])->toBeTrue()
        ->and($session->refresh()->status)->toBe(ChatSessionStatusEnum::ACTIVE)
        ->and($session->closed_at)->toBeNull()
        ->and($session->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->where('chat_agent_id', $agent->id)->count())->toBe(1);
});

test('replying to a conversation another agent holds is still refused', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);

    $session = StoreChatSession::make()->handle([
        'shop_id'     => $this->shop->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'channel'     => ChatChannelEnum::WEBSITE,
    ]);

    $other = StoreChatAgent::make()->handle(['user_id' => User::factory()->create(['group_id' => $this->organisation->group_id])->id]);

    $session->assignments()->create([
        'chat_agent_id' => $other->id,
        'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'   => now(),
    ]);

    ChatAgent::firstOrCreate(
        ['user_id' => $this->user->id],
        ['is_online' => true, 'max_concurrent_chats' => 100, 'current_chat_count' => 0]
    );

    actingAs($this->user);

    $result = SendChatMessage::make()->asController(Request::create(
        '/',
        'POST',
        ['message_text' => 'Mine now', 'message_type' => ChatMessageTypeEnum::TEXT->value, 'sender_type' => ChatSenderTypeEnum::AGENT->value],
    )->setRouteResolver(fn () => new class ($session) {
        public function __construct(private $session)
        {
        }

        public function route($name)
        {
            return (string) $this->session->ulid;
        }

        public function parameter($name, $default = null)
        {
            return (string) $this->session->ulid;
        }

        public function named(...$patterns): bool
        {
            return false;
        }
    }));

    expect($result['ok'])->toBeFalse()
        ->and($result['code'])->toBe(403)
        ->and($session->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->count())->toBe(1);
});

test('assigning a conversation nobody holds creates the assignment instead of leaving it active and unheld', function () {
    $session = StoreChatSession::make()->handle([
        'shop_id'     => $this->shop->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'channel'     => ChatChannelEnum::EMAIL,
    ]);

    $session->update(['status' => ChatSessionStatusEnum::WAITING, 'closed_at' => now(), 'closed_by' => 'agent']);

    $mine  = ChatAgent::where('user_id', $this->user->id)->first()
        ?? StoreChatAgent::make()->handle(['user_id' => $this->user->id]);
    $other = StoreChatAgent::make()->handle(['user_id' => User::factory()->create(['group_id' => $this->organisation->group_id])->id]);
    $other->update([
        'is_online'         => true,
        'is_available'      => true,
        'presence_status'   => \App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at' => now(),
        'max_concurrent_chats' => 100,
        'current_chat_count'   => 0,
    ]);

    $assignment = \App\Actions\Chat\ChatSession\AssignChatToAgent::make()->handle($session, $other->id, $mine->id);

    expect($assignment->chat_agent_id)->toBe($other->id)
        ->and($assignment->status)->toBe(ChatAssignmentStatusEnum::ACTIVE)
        ->and($session->refresh()->status)->toBe(ChatSessionStatusEnum::ACTIVE)
        ->and($session->closed_at)->toBeNull()
        ->and($session->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->count())->toBe(1);
});

test('staff can flag a closed-now reply as wrong, but not an ordinary message', function () {
    config(['chat.out_of_hours_reply' => true]);
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class);
    $schedule = outOfHoursTestSchedule($this->shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 15:30', 'Europe/London'));

    $session = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::WAITING,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'last_visitor_message_at' => now(),
    ]);

    $reply = \App\Actions\Chat\ChatSession\SendOutOfHoursReply::make();
    expect($reply->handle($session))->toBeTrue();

    $automated = $session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->sole();

    $agentMessage = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::AGENT,
        'message_text'    => 'Just checking in',
    ]);

    actingAs($this->user);

    $this->post(route('grp.chat.ai.sent.flag', ['chat', $agentMessage->id]), ['reason' => 'Wrong'])->assertNotFound();

    $this->post(route('grp.chat.ai.sent.flag', ['chat', $automated->id]))->assertSessionHasErrors('reason');
    expect(Arr::get($automated->refresh()->metadata, 'flagged_wrong_at'))->toBeNull();

    $this->post(route('grp.chat.ai.sent.flag', ['chat', $automated->id]), ['reason' => 'Customer already told us the products'])->assertRedirect();

    expect(Arr::get($automated->refresh()->metadata, 'flagged_wrong_at'))->not->toBeNull()
        ->and(Arr::get($automated->metadata, 'flagged_by_user_id'))->toBe($this->user->id)
        ->and(Arr::get($automated->metadata, 'flagged_reason'))->toBe('Customer already told us the products')
        ->and(\App\Http\Resources\CRM\Livechat\ChatMessageResource::make($automated)->resolve()['metadata'])->not->toHaveKey('flagged_reason');

    $sentRow = collect(get(route('grp.chat.ai.sent'))->assertOk()->viewData('page')['props']['data']['data'])
        ->firstWhere('message_id', $automated->id);
    expect($sentRow)->not->toBeNull()->and($sentRow['reversed'])->toBeTrue()
        ->and($sentRow['flagged_reason'])->toBe('Customer already told us the products');

    $dashboard = get(route('grp.chat.ai.dashboard'))->assertOk()->viewData('page')['props']['dashboard'];
    expect(collect($dashboard['by_kind'])->firstWhere('kind', 'out_of_hours')['wrong'])->toBeGreaterThanOrEqual(1);

    outOfHoursTestCleanUp($schedule, [$session]);
});

test('chat availability answers offline for a shop without a website, works it out once per minute and needs a shop', function () {
    $shop = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());

    $shopLoads = 0;
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$shopLoads) {
        if (str_starts_with($query->sql, 'select * from "shops"')) {
            $shopLoads++;
        }
    });

    foreach ([1, 2] as $poll) {
        $this->getJson(route('grp.api.chats.availability', ['shop_id' => $shop->id]))
            ->assertOk()
            ->assertExactJson(['is_online' => false]);
    }

    expect($shopLoads)->toBe(1);

    $this->getJson(route('grp.api.chats.availability'))->assertUnprocessable();
});

test('guest chat endpoints reject junk ids and throttle one address', function () {
    RateLimiter::clear('chat-guest-minute:127.0.0.1');
    RateLimiter::clear('chat-guest-hour:127.0.0.1');

    $junk = [
        'shop_id'     => '../../etc/shells',
        'language_id' => '${@print(md5(31337))}',
        'name'        => 'e',
        'email'       => 'sample@email.tst',
        'message'     => 'e',
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
    ];

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('grp.api.chats.offline-message.store'), $junk)->assertStatus(422);
    }

    $this->postJson(route('grp.api.chats.sessions.store'), $junk)->assertStatus(429);
});

test('StoreOfflineMessage from the same guest email joins the conversation still open', function () {
    $modelData = [
        'name'        => 'e',
        'email'       => 'Repeat.Guest@example.com',
        'message'     => 'e',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'web_user_id' => null,
    ];

    $first  = StoreOfflineMessage::make()->handle($this->shop, $modelData);
    $second = StoreOfflineMessage::make()->handle($this->shop, array_merge($modelData, ['email' => 'repeat.guest@example.com', 'message' => 'e again']));

    expect($second->id)->toBe($first->id)
        ->and(ChatMessage::where('chat_session_id', $first->id)->count())->toBe(2);
});

test('chat:spam_scanner_flood marks only the scanner guest sessions as spam', function () {
    Bus::fake();
    ChatSession::whereRaw("metadata->>'email' = ?", ['sample@email.tst'])->update(['status' => ChatSessionStatusEnum::CLOSED]);

    $scanner = StoreOfflineMessage::make()->handle($this->shop, [
        'name'        => 'e',
        'email'       => 'sample@email.tst',
        'message'     => 'e',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'web_user_id' => null,
    ]);
    $genuine = StoreOfflineMessage::make()->handle($this->shop, [
        'name'        => 'Jane',
        'email'       => 'jane.flood.check@example.com',
        'message'     => 'Where is my order',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'web_user_id' => null,
    ]);

    $this->artisan('chat:spam_scanner_flood')->assertSuccessful();
    expect($scanner->fresh()->is_spam)->toBeFalse();

    $this->artisan('chat:spam_scanner_flood --apply')->assertSuccessful();
    expect($scanner->fresh()->is_spam)->toBeTrue()
        ->and($genuine->fresh()->is_spam)->toBeFalse();
});

test('a website guest on a test email domain is put in spam by rule', function () {
    Bus::fake();
    ChatSession::whereRaw("metadata->>'email' = ?", ['sample@email.tst'])->update(['status' => ChatSessionStatusEnum::CLOSED]);

    $offline = fn (string $email) => StoreOfflineMessage::make()->handle($this->shop, [
        'name'        => 'e',
        'email'       => $email,
        'message'     => 'e',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'web_user_id' => null,
    ]);

    $scanner = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($offline('sample@email.tst'))->refresh();
    $rule    = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->verdictByRules($offline('buyer.rule.check@gmail.com'));

    expect($scanner->is_spam)->toBeTrue()
        ->and($scanner->noise_source)->toBe('rule')
        ->and($rule)->toBeNull();
});

test('the noise model is told a buyer calling it a partnership is genuine, and only selling us their own goods is spam', function () {
    $definitions = \App\Enums\CRM\Livechat\ChatNoiseVerdictEnum::definitions();

    expect($definitions['genuine'])->toContain('call it a partnership or a collaboration')
        ->and($definitions['spam'])->toContain('their own products')
        ->and($definitions['spam'])->not->toContain('collaborations');
});

test('customer uploads are capped at 10MB a file and 100MB a day per IP', function () {
    $ip = '203.0.113.'.random_int(1, 254);
    \Illuminate\Support\Facades\RateLimiter::clear('chat-customer-upload-bytes:'.$ip);
    $send = fn (int $kilobytes) => SendChatMessage::make()->customerUploadRefusal(
        \Illuminate\Http\Request::create('/', 'POST', [], [], ['attachments' => [\Illuminate\Http\UploadedFile::fake()->create('a.pdf', $kilobytes)]], ['REMOTE_ADDR' => $ip])
    );

    expect($send(11 * 1024)['code'])->toBe(422);

    foreach (range(1, 10) as $ignored) {
        expect($send(10 * 1024))->toBeNull();
    }

    expect($send(1)['code'])->toBe(429);

    \Illuminate\Support\Facades\RateLimiter::clear('chat-customer-upload-bytes:'.$ip);
});

test('a customer chasing an unanswered email keeps their place in the queue', function () {
    [, , $queueShop] = createOwnShop(__FILE__.':chase-keeps-place');

    $emailSession = fn (array $messages) => tap(ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'channel'          => ChatChannelEnum::EMAIL,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $queueShop->id,
        'ai_model_version' => 'default',
    ]), function (ChatSession $session) use ($messages) {
        foreach ($messages as [$senderType, $writtenAt]) {
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'message_type'    => ChatMessageTypeEnum::TEXT->value,
                'sender_type'     => $senderType->value,
                'message_text'    => 'Any update?',
                'is_read'         => false,
                'created_at'      => $writtenAt,
                'updated_at'      => $writtenAt,
            ]);
        }
    });

    $chaser = $emailSession([
        [ChatSenderTypeEnum::GUEST, now()->subHours(5)],
        [ChatSenderTypeEnum::AGENT, now()->subHours(4)],
        [ChatSenderTypeEnum::GUEST, now()->subHours(3)],
        [ChatSenderTypeEnum::SYSTEM, now()->subHours(3)->addSecond()],
        [ChatSenderTypeEnum::GUEST, now()->subMinutes(5)],
    ]);
    $patient = $emailSession([
        [ChatSenderTypeEnum::GUEST, now()->subHours(2)],
    ]);

    $queue = collect(GetChatSessions::make()->handle([
        'shop_id'  => $queueShop->id,
        'statuses' => [ChatSessionStatusEnum::ACTIVE->value],
    ])->items())->pluck('id')->intersect([$chaser->id, $patient->id])->values()->all();

    expect($queue)->toBe([$chaser->id, $patient->id]);
});

test('an agent marks a website conversation as unread again and only the customer messages still waiting come back', function () {
    $agentUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $chatAgent = ChatAgent::create(['user_id' => $agentUser->id, 'max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]);
    makeChatWorker($agentUser, $this->shop);

    $readAt      = now()->subMinutes(2)->startOfSecond();
    $makeSession = fn () => ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    $write = fn (ChatSession $session, ChatSenderTypeEnum $sender, int $minutesAgo) => ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => $sender->value,
        'message_text'    => 'Message from '.$sender->value,
        'is_read'         => true,
        'read_at'         => $readAt,
        'created_at'      => now()->subMinutes($minutesAgo),
        'updated_at'      => now()->subMinutes($minutesAgo),
    ]);

    $waitingOnUs    = $makeSession();
    $answeredBefore = $write($waitingOnUs, ChatSenderTypeEnum::GUEST, 40);
    $ourReply       = $write($waitingOnUs, ChatSenderTypeEnum::AGENT, 30);
    $stillWaiting   = [$write($waitingOnUs, ChatSenderTypeEnum::GUEST, 20), $write($waitingOnUs, ChatSenderTypeEnum::GUEST, 10)];
    $systemNote     = $write($waitingOnUs, ChatSenderTypeEnum::SYSTEM, 5);

    $answered        = $makeSession();
    $earlierQuestion = $write($answered, ChatSenderTypeEnum::GUEST, 40);
    $lastQuestion    = $write($answered, ChatSenderTypeEnum::GUEST, 30);
    $lastWordIsOurs  = $write($answered, ChatSenderTypeEnum::AGENT, 20);

    $before = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    Event::fake([\App\Events\BroadcastChatListEvent::class, \App\Events\BroadcastMessagesRead::class]);
    actingAs($agentUser);

    $this->patchJson(route('grp.org.chat.agents.sessions.unread', [$this->organisation->slug, $waitingOnUs->ulid]))
        ->assertOk()
        ->assertJsonPath('data.unread_count', 2);

    $afterFirst = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    expect($afterFirst['unassigned_unread_count'])->toBe($before['unassigned_unread_count'] + 2)
        ->and($afterFirst['total_unread_count'])->toBe($before['total_unread_count'] + 2)
        ->and($afterFirst['waiting']['chat']['sessions'])->toBe($before['waiting']['chat']['sessions'] + 1)
        ->and($stillWaiting[0]->refresh()->is_read)->toBeFalse()
        ->and($stillWaiting[0]->read_at)->toBeNull()
        ->and($stillWaiting[1]->refresh()->is_read)->toBeFalse()
        ->and($answeredBefore->refresh()->is_read)->toBeTrue()
        ->and($ourReply->refresh()->is_read)->toBeTrue()
        ->and($ourReply->read_at?->equalTo($readAt))->toBeTrue()
        ->and($systemNote->refresh()->is_read)->toBeTrue();

    $listed = collect(GetChatSessions::make()->handle(['shop_id' => $this->shop->id, 'limit' => 100])->items())
        ->firstWhere('id', $waitingOnUs->id);
    expect((int) $listed?->unread_count)->toBe(2);

    $this->patchJson(route('grp.org.chat.agents.sessions.unread', [$this->organisation->slug, $answered->ulid]))
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);

    expect($lastQuestion->refresh()->is_read)->toBeFalse()
        ->and($earlierQuestion->refresh()->is_read)->toBeTrue()
        ->and($lastWordIsOurs->refresh()->is_read)->toBeTrue()
        ->and(GetAgentUnreadMessagesSummary::make()->handle($chatAgent)['unassigned_unread_count'])->toBe($before['unassigned_unread_count'] + 3);

    Event::assertDispatched(\App\Events\BroadcastChatListEvent::class, 2);
    Event::assertNotDispatched(\App\Events\BroadcastMessagesRead::class);
});

test('marking a conversation as unread is refused to staff who do not work chat on its shop', function () {
    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    $question = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Where is my parcel?',
        'is_read'         => true,
        'read_at'         => now(),
    ]);

    $channel         = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $whatsappSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+4219'.random_int(10000000, 99999999),
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);
    $whatsappQuestion = $whatsappSession->messages()->create([
        'meta_channel_id' => $channel->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Where is my parcel?',
        'is_read'         => true,
        'read_at'         => now(),
    ]);

    actingAs($outsider);

    $this->patchJson(route('grp.org.chat.agents.sessions.unread', [$this->organisation->slug, $session->ulid]))->assertForbidden();
    $this->patchJson(route('grp.org.chat.agents.whatsapp.sessions.unread', [$this->organisation->slug, $whatsappSession->ulid]))->assertForbidden();

    expect($question->refresh()->is_read)->toBeTrue()
        ->and($whatsappQuestion->refresh()->is_read)->toBeTrue();
});

test('marking as unread a conversation the customer never wrote in changes nothing', function () {
    $agentUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    makeChatWorker($agentUser, $this->shop);

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    $ourOpening = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::AGENT->value,
        'message_text'    => 'Hello, how can we help?',
        'is_read'         => true,
        'read_at'         => now(),
    ]);

    Event::fake([\App\Events\BroadcastChatListEvent::class]);
    actingAs($agentUser);

    $this->patchJson(route('grp.org.chat.agents.sessions.unread', [$this->organisation->slug, $session->ulid]))
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);

    expect($ourOpening->refresh()->is_read)->toBeTrue();
    Event::assertNotDispatched(\App\Events\BroadcastChatListEvent::class);
});

test('an offline message keeps the photos the customer attached (HELP-4071)', function () {
    RateLimiter::clear('chat-guest-minute:127.0.0.1');
    RateLimiter::clear('chat-guest-hour:127.0.0.1');
    RateLimiter::clear('chat-customer-upload-bytes:127.0.0.1');

    $response = $this->post(route('grp.api.chats.offline-message.store'), [
        'shop_id'     => $this->shop->id,
        'name'        => 'Smashed Box',
        'email'       => 'smashed-'.Str::random(6).'@example.com',
        'message'     => 'It arrived smashed, see pics',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'attachments' => [
            \Illuminate\Http\UploadedFile::fake()->image('box.jpg', 400, 300),
            \Illuminate\Http\UploadedFile::fake()->image('items.png', 400, 300),
        ],
    ], ['Accept' => 'application/json'])->assertOk();

    $message = ChatMessage::where('chat_session_id', ChatSession::where('ulid', $response->json('session_ulid'))->value('id'))->latest('id')->first();

    expect($message->message_text)->toBe('It arrived smashed, see pics')
        ->and($message->message_type)->toBe(ChatMessageTypeEnum::IMAGE)
        ->and($message->getMedia('chat_images'))->toHaveCount(2)
        ->and($message->metadata['is_offline_message'])->toBeTrue();

    $this->post(route('grp.api.chats.offline-message.store'), [
        'shop_id'     => $this->shop->id,
        'name'        => 'Script',
        'email'       => 'script@example.com',
        'message'     => 'x',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('evil.php', 10, 'application/x-php')],
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

test('an agent working several shops is only alerted for the unassigned chats of the shops they pick', function () {
    [, , $otherShop] = createOwnShop('alert-shops-other-shop');
    $agentUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $chatAgent = ChatAgent::create(['user_id' => $agentUser->id, 'max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]);
    makeChatWorker($agentUser, $this->shop);
    makeChatWorker($agentUser, $otherShop);

    $waitingIn = function (\App\Models\Catalogue\Shop $shop) {
        $session = ChatSession::create([
            'ulid'             => (string) Str::ulid(),
            'status'           => ChatSessionStatusEnum::WAITING,
            'guest_identifier' => 'guest_'.Str::random(5),
            'language_id'      => 68,
            'priority'         => ChatPriorityEnum::NORMAL,
            'shop_id'          => $shop->id,
            'ai_model_version' => 'default',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'Hello',
            'is_read'         => false,
        ]);
    };

    expect(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($agentUser->fresh())['chat_alert_shops'])->toEqualCanonicalizing([$this->shop->id, $otherShop->id]);

    actingAs($agentUser)
        ->patchJson(route('grp.models.profile.update'), ['chat_alert_shops' => [
            ['key' => $this->shop->id, 'value' => false],
            ['key' => $otherShop->id, 'value' => true],
            ['key' => 999999, 'value' => true],
        ]])
        ->assertOk();

    $alertShopsField = collect(\App\Actions\UI\Profile\EditProfileSettings::make()->generateBlueprint($agentUser->fresh())['formData']['blueprint'])
        ->firstWhere('label', 'Alerts')['fields']['chat_alert_shops'];

    expect(Arr::get($agentUser->fresh()->settings, 'chat_alert_shops'))->toBe([$otherShop->id])
        ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($agentUser->fresh())['chat_alert_shops'])->toBe([$otherShop->id])
        ->and(collect($alertShopsField['value'])->pluck('value', 'key')->all())->toEqual([$this->shop->id => false, $otherShop->id => true]);

    $before = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);
    $waitingIn($this->shop);
    $notPicked = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);
    $waitingIn($otherShop);
    $picked = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    expect($notPicked['unassigned_unread_count'])->toBe($before['unassigned_unread_count'])
        ->and($notPicked['waiting']['chat']['sessions'])->toBe($before['waiting']['chat']['sessions'])
        ->and($picked['unassigned_unread_count'])->toBe($before['unassigned_unread_count'] + 1)
        ->and($picked['waiting']['chat']['sessions'])->toBe($before['waiting']['chat']['sessions'] + 1);

    actingAs($agentUser)
        ->patchJson(route('grp.models.profile.update'), ['chat_alert_shops' => [
            ['key' => $this->shop->id, 'value' => false],
            ['key' => $otherShop->id, 'value' => false],
        ]])
        ->assertOk();

    expect(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($agentUser->fresh())['chat_alert_shops'])->toBe([])
        ->and(GetAgentUnreadMessagesSummary::make()->handle($chatAgent)['unassigned_unread_count'])->toBe($before['unassigned_unread_count']);
});

test('opening a whatsapp conversation marks it read without waiting for Meta', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Http::fake();

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628444555999',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $write = fn (string $text, string $at) => \App\Models\Chat\MetaChatMessage::create([
        'meta_chat_session_id' => $metaChatSession->id,
        'meta_channel_id'      => $channel->id,
        'message_type'         => ChatMessageTypeEnum::TEXT,
        'sender_type'          => ChatSenderTypeEnum::GUEST,
        'message_text'         => $text,
        'meta_message_id'      => 'wamid.read-'.Str::random(8),
        'created_at'           => $at,
    ]);
    $first  = $write('Hello', '2026-10-29 10:00:00');
    $newest = $write('Anyone there?', '2026-10-29 10:05:00');

    expect(\App\Actions\Chat\MetaChatSession\MarkMetaChatMessagesAsRead::make()->handle($metaChatSession))->toBe(2)
        ->and($first->fresh()->is_read)->toBeTrue()
        ->and($newest->fresh()->is_read)->toBeTrue();

    \App\Actions\Chat\Whatsapp\SendWhatsappReadReceipt::assertPushed(1, fn ($action, $params) => $params[0]->is($newest));
    \Illuminate\Support\Facades\Http::assertNothingSent();
});

describe('chat spike alert', function () {
    beforeEach(function () {
        config()->set('services.discord.bot_token', 'bot-token');
        $this->user->update(['settings' => array_merge($this->user->settings ?? [], ['discord_user_id' => '4242'])]);
        \Illuminate\Support\Facades\Cache::forget('chat:spike-alerted:'.$this->shop->id);
        \Illuminate\Support\Facades\Http::fake([
            'discord.com/api/v10/users/@me/channels'    => \Illuminate\Support\Facades\Http::response(['id' => '777']),
            'discord.com/api/v10/channels/777/messages' => \Illuminate\Support\Facades\Http::response(['id' => '1']),
        ]);
        $this->spikeFor = fn (array $spikes) => collect($spikes)->firstWhere('shop_id', $this->shop->id);
    });

    test('many customers writing in at once DMs the engineer once per cooldown', function () {
        foreach (range(1, \App\Actions\Chat\AlertChatMessageSpikes::MIN_CHATS - 1) as $i) {
            noiseTestEmailSession($this->shop, "spike$i@example.com", "Checkout broken $i", 'I cannot pay');
        }
        $this->travel(1)->seconds();
        noiseTestWhatsappSession($this->shop, '+628111222'.rand(100, 999), 'Payment page is down');

        $spike = ($this->spikeFor)(\App\Actions\Chat\AlertChatMessageSpikes::make()->handle($this->user->username));

        expect($spike['chats'])->toBeGreaterThanOrEqual(\App\Actions\Chat\AlertChatMessageSpikes::MIN_CHATS)
            ->and($spike['sent'])->toBeTrue();

        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), '/channels/777/messages')
            && str_contains($request['content'], $this->shop->name)
            && str_contains($request['content'], 'email: Checkout broken')
            && str_contains($request['content'], 'whatsapp: Payment page is down'));

        $again = ($this->spikeFor)(\App\Actions\Chat\AlertChatMessageSpikes::make()->handle($this->user->username));

        expect($again['sent'])->toBeFalse();
        \Illuminate\Support\Facades\Http::assertSentCount(2);
    });

    test('a busy half hour that is usual for the shop at that time does not alert', function () {
        foreach (range(1, \App\Actions\Chat\AlertChatMessageSpikes::BASELINE_WEEKS) as $week) {
            foreach (range(1, 20) as $i) {
                $session = noiseTestEmailSession($this->shop, "usual$week-$i@example.com", 'Usual question', 'Where is my order');
                ChatMessage::where('chat_session_id', $session->id)->update(['created_at' => now()->subWeeks($week)->subMinutes(10)]);
            }
        }
        foreach (range(1, \App\Actions\Chat\AlertChatMessageSpikes::MIN_CHATS) as $i) {
            noiseTestEmailSession($this->shop, "busy$i@example.com", 'Busy as usual', 'Where is my order');
        }

        expect(($this->spikeFor)(\App\Actions\Chat\AlertChatMessageSpikes::make()->handle($this->user->username)))->toBeNull();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    });
});

test('agents tick several inbox conversations and mark them read or close them, without opening them', function () {
    $agentUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    ChatAgent::create(['user_id' => $agentUser->id, 'max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]);
    makeChatWorker($agentUser, $this->shop);
    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    $question = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Thank you, all arrived',
        'is_read'         => false,
    ]);

    $channel         = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $whatsappSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+4219'.random_int(10000000, 99999999),
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);
    $whatsappQuestion = $whatsappSession->messages()->create([
        'meta_channel_id' => $channel->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Thanks!',
        'is_read'         => false,
    ]);

    Event::fake([\App\Events\BroadcastChatListEvent::class, \App\Events\BroadcastMessagesRead::class, \App\Events\BroadcastMetaChatListEvent::class, \App\Events\BroadcastRealtimeChat::class]);
    Bus::fake([\App\Actions\Chat\Whatsapp\SendWhatsappReadReceipt::class]);

    actingAs($outsider);
    $this->patchJson(route('grp.org.chat.agents.sessions.read', [$this->organisation->slug, $session->ulid]))->assertForbidden();
    $this->patchJson(route('grp.org.chat.agents.whatsapp.sessions.read', [$this->organisation->slug, $whatsappSession->ulid]))->assertForbidden();
    expect($question->refresh()->is_read)->toBeFalse()
        ->and($whatsappQuestion->refresh()->is_read)->toBeFalse();

    actingAs($agentUser);
    $this->patchJson(route('grp.org.chat.agents.sessions.read', [$this->organisation->slug, $session->ulid]))->assertOk();
    $this->patchJson(route('grp.org.chat.agents.whatsapp.sessions.read', [$this->organisation->slug, $whatsappSession->ulid]))->assertOk();

    expect($question->refresh()->is_read)->toBeTrue()
        ->and($whatsappQuestion->refresh()->is_read)->toBeTrue();
    Event::assertDispatched(\App\Events\BroadcastChatListEvent::class);

    $this->patchJson(route('grp.org.chat.agents.sessions.close', [$this->organisation->slug, $session->ulid]))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($session->refresh()->status)->toBe(ChatSessionStatusEnum::CLOSED);
});

function handOverTestStorefront(bool $chatOn, bool $mailbox, ?string $email = null): array
{
    [, , $shop] = createShop();
    $website    = createWebsite($shop);
    $website->update(['status' => true]);
    \App\Actions\Web\Website\UpdateWebsite::make()->action($website, ['shopping_assistant_enabled' => true, 'shopping_assistant_name' => 'Aura', 'shopping_assistant_monthly_budget' => 1000]);
    \App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantBudget::forget($website);

    $websiteSettings = $website->refresh()->settings ?? [];
    data_set($websiteSettings, 'enable_chat', $chatOn);
    $website->update(['settings' => $websiteSettings]);

    $shopSettings = $shop->refresh()->settings ?? [];
    if ($mailbox) {
        $shopSettings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    } else {
        unset($shopSettings['gmail']);
    }
    $shop->update(['settings' => $shopSettings]);

    \App\Actions\Web\Website\UI\DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($website->domain);

    $customer = StoreCustomer::make()->action($shop->refresh(), array_merge(Customer::factory()->definition(), ['email' => $email ?? 'handover.'.uniqid().'@example.com', 'contact_name' => 'Hanna Handover']));
    $webUser  = StoreWebUser::make()->action($customer, ['username' => 'ho-'.uniqid(), 'email' => $customer->email, 'password' => 'test'], sendRegistrationEmail: false);

    return [$website->refresh(), $customer, $webUser, $shop->refresh()];
}

function handOverTestAvailableAgent(\App\Models\Catalogue\Shop $shop): ChatAgent
{
    $user = User::factory()->create(['group_id' => $shop->group_id]);
    makeChatWorker($user, $shop);

    return ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
    ]);
}

function handOverTestOffer(Customer $customer, \App\Models\Web\Website $website, WebUser $webUser, string $question = 'Do you deliver to Jersey?', ?\App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation $conversation = null): \App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage
{
    $conversation ??= \App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation::run($customer, \App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum::SEARCH, ['web_user_id' => $webUser->id]);
    \App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant::make()->storeCustomerMessage($conversation, $question);

    return $conversation->messages()->create([
        'role'            => \App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum::ASSISTANT,
        'body'            => 'I am not sure about that one.',
        'hand_over_offer' => \App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum::NOT_SURE,
    ]);
}

function handOverTestUrl(\App\Models\Web\Website $website, string $path = 'hand-over'): string
{
    return 'http://'.$website->domain.'/json/shopping-assistant/'.$path;
}

test('tapping the offer in chat hours with an agent free opens a website chat from the assistant conversation, waiting and unassigned', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 11:00', 'Europe/London'));
    handOverTestAvailableAgent($shop);
    $offer = handOverTestOffer($customer, $website, $webUser);
    $conversation = $offer->conversation;

    $response = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $conversation->ulid, 'offer_message_id' => $offer->id])->assertOk();

    $session = ChatSession::where('ulid', $response->json('chat_session'))->firstOrFail();
    $handOver = $conversation->handOvers()->sole();
    $told     = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
    expect($response->json('route'))->toBe('live_chat')
        ->and($response->json('conversation'))->toBe($conversation->ulid)
        ->and($response->json('message_id'))->toBe($told->id)
        ->and($session->channel)->toBe(ChatChannelEnum::WEBSITE)
        ->and($session->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($session->shopping_assistant_conversation_id)->toBe($conversation->id)
        ->and($session->web_user_id)->toBe($webUser->id)
        ->and($session->assignments()->count())->toBe(0)
        ->and($session->messages()->pluck('message_text')->all())->toBe(['Do you deliver to Jersey?'])
        ->and($session->messages()->first()->sender_type)->toBe(ChatSenderTypeEnum::USER)
        ->and($handOver->reason->value)->toBe('not_sure')
        ->and($handOver->route->value)->toBe('live_chat')
        ->and($handOver->in_hours)->toBeTrue()
        ->and($handOver->agent_available)->toBeTrue()
        ->and($handOver->question)->toBe('Do you deliver to Jersey?')
        ->and($handOver->offer_message_id)->toBe($offer->id)
        ->and($handOver->chat_session_id)->toBe($session->id)
        ->and($conversation->refresh()->status->value)->toBe('open')
        ->and($conversation->handed_over_at)->not->toBeNull()
        ->and($conversation->chat_session_id)->toBe($session->id)
        ->and($told->body)->toBe('I am passing you to our team. Your chat with them is opening now.')
        ->and($told->flags)->toBe(['handed_over']);

    outOfHoursTestCleanUp($schedule, [$session]);
});

test('outside chat hours with a usable mailbox the hand-over opens an email chat and says when the team is back', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, true);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 15:30', 'Europe/London'));
    $offer = handOverTestOffer($customer, $website, $webUser);

    $response = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $offer->conversation->ulid, 'offer_message_id' => $offer->id])->assertOk();

    $handOver = $offer->conversation->handOvers()->sole();
    $session  = $handOver->chatSession;
    $told     = $offer->conversation->messages()->where('role', 'assistant')->latest('id')->first();
    expect($response->json('route'))->toBe('email_chat')
        ->and($response->json('chat_session'))->toBeNull()
        ->and($handOver->in_hours)->toBeFalse()
        ->and($handOver->agent_available)->toBeFalse()
        ->and($session->channel)->toBe(ChatChannelEnum::EMAIL)
        ->and($session->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($session->assignments()->count())->toBe(0)
        ->and(Arr::get($session->metadata, 'email_subject'))->toBe('Your question: Do you deliver to Jersey?')
        ->and(Arr::get($session->metadata, 'email_from'))->toBe($customer->email)
        ->and(Arr::get($session->metadata, 'email_from_name'))->toBe('Hanna Handover')
        ->and($session->messages()->count())->toBe(1)
        ->and($told->body)->toStartWith('Our team is back on Friday at ')
        ->and($told->body)->toEndWith(' and will reply by email, to the address on your account.');
    Bus::assertNotDispatched(fn (\Lorisleiva\Actions\Decorators\JobDecorator $job) => $job->decorates(\App\Actions\Comms\Mailbox\SendChatMessageByGmail::class));

    outOfHoursTestCleanUp($schedule, [$session]);
});

test('with no mailbox the hand-over is a website chat answered later, and with neither chat nor mailbox nothing is offered', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 15:30', 'Europe/London'));
    $offer = handOverTestOffer($customer, $website, $webUser);

    $response = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $offer->conversation->ulid, 'offer_message_id' => $offer->id])->assertOk();

    $session = ChatSession::where('ulid', $response->json('chat_session'))->firstOrFail();
    $told    = $offer->conversation->messages()->where('role', 'assistant')->latest('id')->first();
    expect($response->json('route'))->toBe('web_chat')
        ->and($session->channel)->toBe(ChatChannelEnum::WEBSITE)
        ->and($told->body)->toStartWith('Our team is back on Friday at ')
        ->and($told->body)->toEndWith(' and will reply in the chat on this website.');

    [$bareWebsite, $bareCustomer, $bareWebUser] = handOverTestStorefront(false, false);
    $bareOffer = handOverTestOffer($bareCustomer, $bareWebsite, $bareWebUser);

    expect(\App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings::run($bareWebsite, $bareCustomer))->toBe(['name' => 'Aura', 'hand_over' => false]);
    $this->actingAs($bareWebUser, 'retina')->postJson(handOverTestUrl($bareWebsite), ['conversation' => $bareOffer->conversation->ulid, 'offer_message_id' => $bareOffer->id])->assertNotFound();
    expect($bareOffer->conversation->handOvers()->count())->toBe(0);

    outOfHoursTestCleanUp($schedule, [$session]);
});

test('tapping twice gives one hand-over and one chat, and a second question joins the open chat', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 11:00', 'Europe/London'));
    handOverTestAvailableAgent($shop);
    $offer        = handOverTestOffer($customer, $website, $webUser);
    $conversation = $offer->conversation;
    $body         = ['conversation' => $conversation->ulid, 'offer_message_id' => $offer->id];

    $first  = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), $body)->assertOk();
    $second = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), $body)->assertOk();

    expect($second->json())->toBe($first->json())
        ->and($conversation->handOvers()->count())->toBe(1)
        ->and(ChatSession::where('shopping_assistant_conversation_id', $conversation->id)->count())->toBe(1)
        ->and($conversation->messages()->where('model', 'hand-over')->count())->toBe(1);

    $secondOffer = handOverTestOffer($customer, $website, $webUser, 'Can I pay by cheque?', $conversation);
    $third       = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $conversation->ulid, 'offer_message_id' => $secondOffer->id])->assertOk();

    $session = ChatSession::where('ulid', $first->json('chat_session'))->firstOrFail();
    expect($third->json('chat_session'))->toBe($first->json('chat_session'))
        ->and($conversation->handOvers()->count())->toBe(2)
        ->and($conversation->handOvers()->pluck('route')->map->value->unique()->all())->toBe(['live_chat'])
        ->and($session->messages()->orderBy('id')->pluck('message_text')->all())->toBe(['Do you deliver to Jersey?', 'Can I pay by cheque?'])
        ->and(ChatSession::where('shopping_assistant_conversation_id', $conversation->id)->count())->toBe(1);

    outOfHoursTestCleanUp($schedule, [$session]);
});

test('the Talk to a person link with a typed question starts the conversation and hands over, and without a question it is refused', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 11:00', 'Europe/London'));
    handOverTestAvailableAgent($shop);
    $before = \App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation::where('customer_id', $customer->id)->count();

    $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), [])->assertUnprocessable();
    $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['message' => '   '])->assertUnprocessable();
    expect(\App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation::where('customer_id', $customer->id)->count())->toBe($before);

    $response = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['message' => 'I need a quote for 500 candles'])->assertOk();

    $conversation = \App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation::where('ulid', $response->json('conversation'))->firstOrFail();
    $handOver     = $conversation->handOvers()->sole();
    $session      = $handOver->chatSession;
    expect($conversation->web_user_id)->toBe($webUser->id)
        ->and($conversation->messages()->where('role', 'customer')->pluck('body')->all())->toBe(['I need a quote for 500 candles'])
        ->and($handOver->reason->value)->toBe('asked_for_person')
        ->and($handOver->offer_message_id)->toBeNull()
        ->and($handOver->customer_message_id)->toBe($conversation->messages()->where('role', 'customer')->value('id'))
        ->and($session->messages()->pluck('message_text')->all())->toBe(['I need a quote for 500 candles'])
        ->and($conversation->messages()->where('role', 'assistant')->count())->toBe(1);

    $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $conversation->ulid])->assertUnprocessable();

    outOfHoursTestCleanUp($schedule, [$session]);
});

test('another customer cannot hand over a conversation that is not theirs, and a spent AI budget does not block a hand-over', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 15:30', 'Europe/London'));
    $offer = handOverTestOffer($customer, $website, $webUser);

    $otherCustomer = StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), ['email' => 'other.'.uniqid().'@example.com']));
    $otherWebUser  = StoreWebUser::make()->action($otherCustomer, ['username' => 'ho-'.uniqid(), 'email' => $otherCustomer->email, 'password' => 'test'], sendRegistrationEmail: false);

    $this->actingAs($otherWebUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $offer->conversation->ulid, 'offer_message_id' => $offer->id])->assertNotFound();
    expect($offer->conversation->handOvers()->count())->toBe(0);

    \App\Actions\Web\Website\UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => 0]);
    \App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantBudget::forget($website);
    expect(\App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings::run($website->refresh(), $customer, true))->toBeNull();

    $response = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $offer->conversation->ulid, 'offer_message_id' => $offer->id])->assertOk();

    expect($response->json('route'))->toBe('web_chat')
        ->and($offer->conversation->handOvers()->count())->toBe(1);

    outOfHoursTestCleanUp($schedule, [ChatSession::where('ulid', $response->json('chat_session'))->firstOrFail()]);
});

test('the conversation JSON carries the hand-over fields and the cited source links, and anonymising the customer wipes the hand-over question', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 15:30', 'Europe/London'));
    $offer = handOverTestOffer($customer, $website, $webUser);
    $offer->update(['sources' => [
        ['id' => 1, 'kind' => 'page', 'source_type' => 'Webpage', 'source_id' => 1, 'title' => 'Delivery · Shipping times', 'url' => 'https://shop.test/delivery'],
        ['id' => 2, 'kind' => 'page', 'source_type' => 'Webpage', 'source_id' => 1, 'title' => 'Delivery · Costs', 'url' => 'https://shop.test/delivery'],
        ['id' => 3, 'kind' => 'faq', 'source_type' => 'Faq', 'source_id' => 2, 'title' => 'No page', 'url' => null],
    ]]);
    $conversation = $offer->conversation;

    $before = $this->actingAs($webUser, 'retina')->getJson(handOverTestUrl($website, $conversation->ulid))->assertOk();
    $assistant = collect($before->json('messages'))->firstWhere('id', $offer->id);
    expect($before->json('hand_over_available'))->toBeTrue()
        ->and($assistant['hand_over_offer'])->toBe('not_sure')
        ->and($assistant['handed_over'])->toBeFalse()
        ->and($assistant['links'])->toBe([['label' => 'Delivery', 'url' => 'https://shop.test/delivery']]);

    $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $conversation->ulid, 'offer_message_id' => $offer->id])->assertOk();

    $after = $this->actingAs($webUser, 'retina')->getJson(handOverTestUrl($website, $conversation->ulid))->assertOk();
    expect(collect($after->json('messages'))->firstWhere('id', $offer->id)['handed_over'])->toBeTrue();

    $handOver = $conversation->handOvers()->sole();
    expect($handOver->question)->toBe('Do you deliver to Jersey?');
    \App\Actions\CRM\Customer\AnonymiseCustomer::make()->action($customer, 'Erasure request', false);
    expect($handOver->refresh()->question)->toBeNull();

    outOfHoursTestCleanUp($schedule, [$handOver->chatSession]);
});

test('the same typed question sent twice within a minute is one hand-over, and a different question from a new conversation goes into the customers open chat', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 11:00', 'Europe/London'));
    handOverTestAvailableAgent($shop);

    $first  = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['message' => 'I need a quote for 500 candles'])->assertOk();
    $second = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['message' => 'I need a quote for 500 candles'])->assertOk();

    $conversations = fn () => \App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation::where('customer_id', $customer->id)->where('website_id', $website->id);
    $session       = ChatSession::where('ulid', $first->json('chat_session'))->firstOrFail();
    expect($second->json())->toBe($first->json())
        ->and($conversations()->count())->toBe(1)
        ->and(\App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage::where('shopping_assistant_conversation_id', $conversations()->value('id'))->where('role', 'customer')->count())->toBe(1)
        ->and(\App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver::whereIn('shopping_assistant_conversation_id', $conversations()->pluck('id'))->count())->toBe(1)
        ->and($session->messages()->count())->toBe(1);

    $third = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['message' => 'And do you do gift wrapping?'])->assertOk();

    expect($third->json('conversation'))->not->toBe($first->json('conversation'))
        ->and($third->json('chat_session'))->toBe($first->json('chat_session'))
        ->and($third->json('route'))->toBe('live_chat')
        ->and($conversations()->count())->toBe(2)
        ->and(\App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver::whereIn('shopping_assistant_conversation_id', $conversations()->pluck('id'))->count())->toBe(2)
        ->and(ChatSession::where('web_user_id', $webUser->id)->where('shop_id', $shop->id)->count())->toBe(1)
        ->and($session->messages()->orderBy('id')->pluck('message_text')->all())->toBe(['I need a quote for 500 candles', 'And do you do gift wrapping?']);

    outOfHoursTestCleanUp($schedule, [$session]);
});

test('a customer cannot hand over more than the daily limit of questions on a website, and nothing is created by the refused one', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 15:30', 'Europe/London'));

    $conversation = \App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation::run($customer, \App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum::SEARCH, ['web_user_id' => $webUser->id]);
    $rows         = collect(range(1, \App\Actions\CRM\ShoppingAssistant\HandOverShoppingAssistantConversation::MAX_PER_DAY))->map(fn (int $number) => \App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver::create([
        'shopping_assistant_conversation_id' => $conversation->id,
        'reason'                             => 'asked_for_person',
        'route'                              => 'web_chat',
        'in_hours'                           => false,
        'agent_available'                    => false,
        'question'                           => "Earlier question $number",
        'created_at'                         => now()->subHours(2),
    ]));

    $conversationsBefore = \App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation::count();
    $handOversBefore     = \App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver::count();
    $chatsBefore         = ChatSession::count();

    $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['message' => 'One question too many'])->assertStatus(429);
    $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['conversation' => $conversation->ulid, 'message' => 'One question too many'])->assertStatus(429);

    expect(\App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation::count())->toBe($conversationsBefore)
        ->and(\App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver::count())->toBe($handOversBefore)
        ->and(ChatSession::count())->toBe($chatsBefore);

    \Illuminate\Support\Facades\DB::table('shopping_assistant_hand_overs')->where('id', $rows->first()->id)->update(['created_at' => now()->subHours(25)]);

    $response = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), ['message' => 'One question too many'])->assertOk();

    expect(\App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver::count())->toBe($handOversBefore + 1);

    outOfHoursTestCleanUp($schedule, [ChatSession::where('ulid', $response->json('chat_session'))->firstOrFail()]);
});

test('a second tap on an offer whose chat never got the question sends it then', function () {
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    [$website, $customer, $webUser, $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 11:00', 'Europe/London'));
    handOverTestAvailableAgent($shop);
    $offer = handOverTestOffer($customer, $website, $webUser);
    $body  = ['conversation' => $offer->conversation->ulid, 'offer_message_id' => $offer->id];

    $first   = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), $body)->assertOk();
    $session = ChatSession::where('ulid', $first->json('chat_session'))->firstOrFail();
    $session->messages()->withTrashed()->forceDelete();
    expect($session->messages()->count())->toBe(0);

    $second = $this->actingAs($webUser, 'retina')->postJson(handOverTestUrl($website), $body)->assertOk();

    expect($second->json())->toBe($first->json())
        ->and($offer->conversation->handOvers()->count())->toBe(1)
        ->and($session->messages()->pluck('message_text')->all())->toBe(['Do you deliver to Jersey?']);

    outOfHoursTestCleanUp($schedule, [$session]);
});

test('outside chat hours the website chat is in Merlin mode until an agent of the shop comes online, then a person is there', function () {
    [, , , $shop] = handOverTestStorefront(true, false);
    $schedule = outOfHoursTestSchedule($shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 09:00', 'Europe/London'));

    expect(\App\Actions\Chat\ChatSession\IsChatStaffed::run($shop))->toBeFalse()
        ->and(\App\Actions\HumanResources\WorkSchedule\GetChatConfig::make()->forShop($shop, true)['is_online'])->toBeFalse();

    $agent = handOverTestAvailableAgent($shop);

    expect(\App\Actions\Chat\ChatSession\IsChatStaffed::run($shop))->toBeTrue()
        ->and(\App\Actions\HumanResources\WorkSchedule\GetChatConfig::make()->forShop($shop, true)['is_online'])->toBeTrue();

    $agent->update(['is_available' => false]);

    expect(\App\Actions\Chat\ChatSession\IsChatStaffed::run($shop))->toBeFalse();

    $agent->delete();
    outOfHoursTestCleanUp($schedule);
});
