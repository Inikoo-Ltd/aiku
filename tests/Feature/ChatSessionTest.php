<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Agent\DeleteAgent;
use App\Actions\Chat\Agent\StoreAgent;
use App\Actions\Chat\Agent\UpdateAgent;
use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatAgent;
use App\Actions\Chat\ChatSession\StoreChatEvent;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\UpdateChatAgent;
use App\Actions\Chat\ChatSession\UpdateChatSession;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatEvent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Pest\Laravel\actingAs;

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

    \Illuminate\Support\Facades\Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
});

test('can create chat session for guest with minimal data', function () {
    $modelData = [
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ];

    $chatSession = $this->action->handle($modelData);

    expect($chatSession)->toBeInstanceOf(ChatSession::class)
        ->and($chatSession->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($chatSession->web_user_id)->toBeNull()
        ->and($chatSession->language_id)->toBe(68)
        ->and($chatSession->priority)->toBe(ChatPriorityEnum::NORMAL)
        ->and($chatSession->guest_identifier)->toMatch('/^guest_\d{5}$/')
        ->and($chatSession->ai_model_version)->toBe('default')
        ->and($chatSession->ulid)->not->toBeNull();
});

test('can create chat session for guest with custom guest identifier', function () {
    $guestIdentifier = 'guest_custom_123';

    $modelData = [
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => $guestIdentifier,
        'shop_id'          => $this->shop->id,
    ];

    $chatSession = $this->action->handle($modelData);

    expect($chatSession->guest_identifier)->toBe($guestIdentifier);
});

test('can create chat session for authenticated web user', function () {
    $webUser = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());

    $modelData = [
        'web_user_id'      => $webUser->id,
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::HIGH->value,
        'ai_model_version' => 'gpt-4-turbo',
        'shop_id'          => $this->shop->id,
    ];

    \Illuminate\Support\Facades\Auth::guard('retina')->login($webUser);
    $chatSession = $this->action->handle($modelData);

    expect($chatSession->web_user_id)->toBe($webUser->id)
        ->and($chatSession->guest_identifier)->toBeNull()
        ->and($chatSession->priority)->toBe(ChatPriorityEnum::HIGH)
        ->and($chatSession->ai_model_version)->toBe('gpt-4-turbo');
});

test('create chat event for guest session', function () {
    $modelData = [
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ];

    $chatSession = $this->action->handle($modelData);

    $chatEvent = ChatEvent::where('chat_session_id', $chatSession->id)->first();

    expect($chatEvent)->toBeInstanceOf(ChatEvent::class)
        ->and($chatEvent->event_type)->toBe(ChatEventTypeEnum::OPEN)
        ->and($chatEvent->actor_type)->toBe(ChatActorTypeEnum::GUEST)
        ->and($chatEvent->actor_id)->toBeNull()
        ->and($chatEvent->payload)->toHaveKeys([
            'ip_address',
            'user_agent',
            'guest_identifier',
            'language_id',
            'priority',
            'is_guest'
        ])
        ->and($chatEvent->payload['is_guest'])->toBeTrue()
        ->and($chatEvent->payload['language_id'])->toBe(68)
        ->and($chatEvent->payload['priority'])->toBe(ChatPriorityEnum::NORMAL->value);
});

test('creates chat event for authenticated user session', function () {
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
    ]);

    $modelData = [
        'web_user_id' => $webUser->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ];

    \Illuminate\Support\Facades\Auth::guard('retina')->login($webUser);
    $chatSession = $this->action->handle($modelData);

    $chatEvent = ChatEvent::where('chat_session_id', $chatSession->id)->first();

    expect($chatEvent->actor_type)->toBe(ChatActorTypeEnum::USER)
        ->and($chatEvent->actor_id)->toBe($webUser->id)
        ->and($chatEvent->payload['is_guest'])->toBeFalse();
});

test('validation rules are correct', function () {
    $rules = $this->action->rules();

    expect($rules)->toHaveKeys([
        'web_user_id',
        'language_id',
        'guest_identifier',
        'ai_model_version',
        'priority',
        'ulid',
        'shop_id'
    ])
        ->and($rules['web_user_id'])->toEqual(['nullable', 'integer', 'exists:web_users,id'])
        ->and($rules['language_id'])->toEqual(['required', 'integer', 'exists:languages,id'])
        ->and($rules['priority'])->toEqual(['required', Rule::enum(ChatPriorityEnum::class)])
        ->and($rules['guest_identifier'])->toEqual(['nullable', 'string', 'max:255'])
        ->and($rules['ai_model_version'])->toEqual(['nullable', 'string', 'max:50'])
        ->and($rules['ulid'])->toEqual(['sometimes', 'string', 'size:26', 'unique:chat_sessions,ulid'])
        ->and($rules['shop_id'])->toEqual(['required', 'integer', 'exists:shops,id']);
});

test('json response structure is correct', function () {
    $modelData = [
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ];

    $chatSession = $this->action->handle($modelData);
    $response    = $this->action->jsonResponse($chatSession);

    expect($response)->toBeInstanceOf(ChatSessionResource::class);

    $responseData = $response->response()->getData(true);

    expect($responseData)->toHaveKeys(['success', 'message', 'data'])
        ->and($responseData['success'])->toBeTrue()
        ->and($responseData['message'])->toBe('Chat session started successfully');
});

test('handles different priority levels correctly', function () {
    $priorities = [
        ChatPriorityEnum::LOW->value,
        ChatPriorityEnum::NORMAL->value,
        ChatPriorityEnum::HIGH->value,
        ChatPriorityEnum::URGENT->value,
    ];

    foreach ($priorities as $priority) {
        $modelData = [
            'language_id' => 68,
            'priority'    => $priority,
            'shop_id'     => $this->shop->id,
        ];

        $chatSession = $this->action->handle($modelData);

        expect($chatSession->priority->value)->toBe($priority);
    }
});

test('uses default values when not provided', function () {
    $modelData = [
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ];

    $chatSession = $this->action->handle($modelData);

    expect($chatSession->ai_model_version)->toBe('default')
        ->and($chatSession->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($chatSession->language_id)->toBe(68);
});

test('guest identifier is generated when not provided for guest', function () {
    $modelData = [
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ];

    $chatSession1 = $this->action->handle($modelData);
    $chatSession2 = $this->action->handle($modelData);


    expect($chatSession1->guest_identifier)->not->toBeNull()
        ->and($chatSession2->guest_identifier)->not->toBeNull()
        ->and($chatSession1->guest_identifier)->not->toBe($chatSession2->guest_identifier);
});

// SEND MESSAGE ACTION TESTS

test('validation rules are correct for SendChatMessage', function () {
    $rules = $this->sendMessageAction->rules();

    expect($rules)->toHaveKeys([
        'message_text',
        'message_type',
        'sender_id',
    ])
        ->and($rules['message_type'])->toEqual(['required', Rule::enum(ChatMessageTypeEnum::class)])
        ->and($rules['sender_id'])->toEqual(['nullable', 'integer', Rule::exists('web_users', 'id')]);
});

test('can send text message from guest', function () {
    $this->chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_test_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $modelData = [
        'message_text' => 'Hello, I need help!',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::GUEST->value,
        'sender_id'    => null,
    ];

    $chatMessage = $this->sendMessageAction->handle($this->chatSession, $modelData);

    expect($chatMessage)->toBeInstanceOf(ChatMessage::class)
        ->and($chatMessage->message_text)->toBe('Hello, I need help!')
        ->and($chatMessage->message_type)->toBe(ChatMessageTypeEnum::TEXT)
        ->and($chatMessage->sender_type)->toBe(ChatSenderTypeEnum::GUEST)
        ->and($chatMessage->sender_id)->toBeNull()
        ->and($chatMessage->is_read)->toBeFalse()
        ->and($chatMessage->chat_session_id)->toBe($this->chatSession->id);

    $this->chatSession->refresh();
    expect($this->chatSession->last_visitor_message_at)->not->toBeNull();

    $chatEvent = ChatEvent::where('chat_session_id', $this->chatSession->id)
        ->where('event_type', ChatEventTypeEnum::SEND)
        ->first();

    expect($chatEvent)->toBeInstanceOf(ChatEvent::class)
        ->and($chatEvent->event_type)->toBe(ChatEventTypeEnum::SEND)
        ->and($chatEvent->actor_type)->toBe(ChatActorTypeEnum::GUEST)
        ->and($chatEvent->actor_id)->toBeNull()
        ->and($chatEvent->payload['chat_message_id'])->toBe($chatMessage->id)
        ->and($chatEvent->payload['chat_message_type'])->toBe(ChatMessageTypeEnum::TEXT->value)
        ->and($chatEvent->payload['is_guest_message'])->toBeTrue();
});

test('can send text message from web user', function () {
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
    ]);

    $this->chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => null,
        'language_id'      => 68,
        'shop_id'          => $this->shop->id,
        'priority'         => ChatPriorityEnum::NORMAL,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
        'web_user_id'      => $webUser->id,
    ]);

    $modelData = [
        'message_text' => 'Hello from web user!',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::USER->value,
        'sender_id'    => $webUser->id,
    ];

    $chatMessage = $this->sendMessageAction->handle($this->chatSession, $modelData);

    expect($chatMessage->sender_type)->toBe(ChatSenderTypeEnum::USER)
        ->and($chatMessage->sender_id)->toBe($webUser->id)
        ->and($chatMessage->message_text)->toBe('Hello from web user!');

    $this->chatSession->refresh();
    expect($this->chatSession->last_visitor_message_at)->not->toBeNull();
});

test('authenticated agent can assign chat session to self', function () {
    $user = $this->user;

    actingAs($user);

    makeChatWorker($user, $this->shop);

    $agent = ChatAgent::firstOrCreate(
        ['user_id' => $user->id],
        [
            'is_online'            => true,
            'max_concurrent_chats' => 100,
            'current_chat_count'   => 0,
        ]
    );

    $chatSession = ChatSession::create([
        'ulid'             => Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING->value,
        'guest_identifier' => 'guest_001',
        'language_id'      => 68,
        'shop_id'          => $this->shop->id,
        'priority'         => ChatPriorityEnum::NORMAL,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $response = app(AssignChatToAgent::class)
        ->assignToSelf($this->organisation, $chatSession->ulid);

    expect($response)->toBeInstanceOf(JsonResponse::class);

    $data = $response->getData(true);

    expect($data['success'])->toBeTrue()
        ->and($data['message'])->toBe('Chat session assigned to you successfully')
        ->and($data['data']['assigned_agent_id'])->toBe($agent->id)
        ->and($data['data']['action_type'])->toBe('self_assign');

    $this->assertDatabaseHas('chat_assignments', [
        'chat_session_id' => $chatSession->id,
        'chat_agent_id'   => $agent->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
    ]);
});

test('assign to me is refused to somebody who may not work chat on the shop', function () {
    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    actingAs($outsider);

    $chatSession = ChatSession::create([
        'ulid'     => Str::ulid(),
        'status'   => ChatSessionStatusEnum::WAITING->value,
        'shop_id'  => $this->shop->id,
        'priority' => ChatPriorityEnum::NORMAL,
    ]);

    $response = app(AssignChatToAgent::class)->assignToSelf($this->organisation, $chatSession->ulid);

    expect($response->getStatusCode())->toBe(403)
        ->and(ChatAssignment::where('chat_session_id', $chatSession->id)->exists())->toBeFalse();

    $chatSession->forceDelete();
});

test('an agent replying to a waiting chat picks it up, and a chat can only have one active agent', function () {
    $user = $this->user;

    actingAs($user);
    makeChatWorker($user, $this->shop);

    $agent = ChatAgent::firstOrCreate(
        ['user_id' => $user->id],
        [
            'is_online'            => true,
            'max_concurrent_chats' => 100,
            'current_chat_count'   => 0,
        ]
    );

    $chatSession = ChatSession::create([
        'ulid'             => Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING->value,
        'guest_identifier' => 'guest_reply_claims',
        'language_id'      => 68,
        'shop_id'          => $this->shop->id,
        'priority'         => ChatPriorityEnum::NORMAL,
        'ai_model_version' => 'default',
    ]);

    $this->postJson(route('grp.org.chat.agents.messages.send', [$this->organisation->slug, $chatSession->ulid]), [
        'message_text' => 'Hello, how can I help?',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::AGENT->value,
    ])->assertSuccessful();

    expect($chatSession->refresh()->status)->toBe(ChatSessionStatusEnum::ACTIVE)
        ->and($chatSession->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->pluck('chat_agent_id')->all())->toBe([$agent->id]);

    expect(fn () => $chatSession->assignments()->create([
        'chat_agent_id' => $agent->id,
        'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'   => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('an agent given a conversation by somebody else is alerted, and taking one yourself is not an alert', function () {
    Event::fake([\App\Events\BroadcastPersonalNotification::class]);

    $agentFor = function (): ChatAgent {
        $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

        return ChatAgent::create([
            'user_id'              => $user->id,
            'max_concurrent_chats' => 10,
            'is_available'         => true,
            'presence_status'      => \App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum::ONLINE,
            'last_heartbeat_at'    => now(),
        ]);
    };

    $supervisor = $agentFor();
    $agent      = $agentFor();

    $chatSession = ChatSession::create([
        'ulid'     => Str::ulid(),
        'status'   => ChatSessionStatusEnum::WAITING->value,
        'shop_id'  => $this->shop->id,
        'priority' => ChatPriorityEnum::NORMAL,
        'metadata' => ['name' => 'Anna Customer'],
    ]);

    AssignChatToAgent::make()->handle($chatSession, $agent->id, $supervisor->id);

    Event::assertDispatched(\App\Events\BroadcastPersonalNotification::class, fn ($event) => $event->userId === $agent->user_id
        && $event->notification['kind'] === 'chat_assigned'
        && str_contains($event->notification['body'], 'Anna Customer')
        && str_ends_with($event->notification['route'], $chatSession->ulid));

    $mine = ChatSession::create([
        'ulid'     => Str::ulid(),
        'status'   => ChatSessionStatusEnum::WAITING->value,
        'shop_id'  => $this->shop->id,
        'priority' => ChatPriorityEnum::NORMAL,
    ]);

    AssignChatToAgent::make()->handle($mine, $supervisor->id, $supervisor->id);

    Event::assertNotDispatched(\App\Events\BroadcastPersonalNotification::class, fn ($event) => $event->userId === $supervisor->user_id);

    foreach ([$chatSession, $mine] as $session) {
        $session->assignments()->delete();
        $session->forceDelete();
    }
});

test('can send message from agent after assignment', function () {
    $user = $this->user;

    actingAs($user);
    makeChatWorker($user, $this->shop);

    $agent = ChatAgent::where('user_id', $user->id)
        ->whereNull('deleted_at')
        ->first();

    if (!$agent) {
        $agent = ChatAgent::create([
            'user_id'              => $user->id,
            'is_online'            => true,
            'max_concurrent_chats' => 10,
            'current_chat_count'   => 0,
        ]);
    }

    $chatSession = ChatSession::whereHas('assignments', function ($query) use ($agent) {
        $query->where('chat_agent_id', $agent->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE);
    })
        ->where('status', ChatSessionStatusEnum::ACTIVE)
        ->first();

    if (!$chatSession) {
        $chatSession = ChatSession::whereIn('status', [
            ChatSessionStatusEnum::WAITING->value
        ])->first();

        $this->assignmentChatAction->handle($chatSession, $agent->id, $agent->id);
    }

    $modelData = [
        'message_text' => 'How can I help you? from agent after assignment',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::AGENT->value,
        'sender_id'    => $agent->id,
    ];

    $chatMessage = $this->sendMessageAction->handle($chatSession, $modelData);

    expect($chatMessage->sender_type)->toBe(ChatSenderTypeEnum::AGENT)
        ->and($chatMessage->sender_id)->toBe($agent->id)
        ->and($chatMessage->message_text)->toBe('How can I help you? from agent after assignment');

    $chatSession->refresh();
    expect($chatSession->last_agent_message_at)->not->toBeNull();
});

test('can send message media', function () {
    $chatSession = $this->action->handle(['language_id' => 68, 'priority' => ChatPriorityEnum::NORMAL->value, 'shop_id' => $this->shop->id]);


    $modelData = [
        'message_type' => ChatMessageTypeEnum::IMAGE->value,
        'sender_type'  => ChatSenderTypeEnum::GUEST->value,
        'media_id'     => 1,
        'message_text' => 'this image',
    ];

    $chatMessage = $this->sendMessageAction->handle($chatSession, $modelData);

    expect($chatMessage->message_text)->toBe('this image')
        ->and($chatMessage->media_id)->toBe(1)
        ->and($chatMessage->message_type)->toBe(ChatMessageTypeEnum::IMAGE);
});

test('can send system message', function () {
    $chatSession = $this->action->handle(['language_id' => 68, 'priority' => ChatPriorityEnum::NORMAL->value, 'shop_id' => $this->shop->id]);

    $modelData = [
        'message_text' => 'System notification',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
        'sender_id'    => null,
    ];

    $chatMessage = $this->sendMessageAction->handle($chatSession, $modelData);

    expect($chatMessage->sender_type)->toBe(ChatSenderTypeEnum::SYSTEM)
        ->and($chatMessage->sender_id)->toBeNull();

    $chatSession->refresh();
});

test('can close chat session by agent from active assignment', function (): void {
    $group = createGroup();

    $guest = createAdminGuest($group);
    $user  = $guest->getUser();

    $agent = ChatAgent::updateOrCreate(
        ['user_id' => $user->id],
        [
            'is_online'            => true,
            'max_concurrent_chats' => 100,
            'current_chat_count'   => 0,
            'deleted_at'           => null,
        ]
    );

    $chatSession = ChatSession::create([
        'ulid'             => Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'guest_identifier' => 'guest_close_test',
        'language_id'      => 68,
        'shop_id'          => $this->shop->id,
        'priority'         => ChatPriorityEnum::NORMAL,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $assignment = ChatAssignment::create([
        'chat_session_id' => $chatSession->id,
        'chat_agent_id'   => $agent->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    actingAs($user);

    $closedSession = $this->closeChatAction->handle($chatSession, $agent->id);

    expect($closedSession->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($closedSession->closed_at)->not->toBeNull();

    $assignment->refresh();

    expect($assignment->status)->toBe(ChatAssignmentStatusEnum::RESOLVED)
        ->and($assignment->resolved_at)->not->toBeNull();
});

test('closing an already-closed chat session is a no-op, not a second close', function (): void {
    $group = createGroup();

    $guest = createAdminGuest($group);
    $user  = $guest->getUser();

    $agent = ChatAgent::updateOrCreate(
        ['user_id' => $user->id],
        [
            'is_online'            => true,
            'max_concurrent_chats' => 100,
            'current_chat_count'   => 0,
            'deleted_at'           => null,
        ]
    );

    $chatSession = ChatSession::create([
        'ulid'             => Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'guest_identifier' => 'guest_double_close_test',
        'language_id'      => 68,
        'shop_id'          => $this->shop->id,
        'priority'         => ChatPriorityEnum::NORMAL,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    ChatAssignment::create([
        'chat_session_id' => $chatSession->id,
        'chat_agent_id'   => $agent->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    actingAs($user);

    $firstClose = $this->closeChatAction->handle($chatSession, $agent->id);

    expect($firstClose->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($firstClose->closed_at)->not->toBeNull();

    $closedAt = $firstClose->closed_at;

    // Whatever triggers it a second time — a second click, a stale suggestion, anything —
    // closing a session that is already closed must do nothing: no second system message,
    // no second close event, no re-resolved assignment with a fresh timestamp.
    $secondClose = $this->closeChatAction->handle($firstClose->fresh(), $agent->id);

    expect($secondClose->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($secondClose->closed_at->eq($closedAt))->toBeTrue();

    expect(ChatMessage::where('chat_session_id', $chatSession->id)
        ->where('message_text', 'Chat session has been closed by agent')
        ->count())->toBe(1);

    expect(ChatEvent::where('chat_session_id', $chatSession->id)
        ->where('event_type', \App\Enums\CRM\Livechat\ChatEventTypeEnum::CLOSE)
        ->count())->toBe(1);
});

// AGENT ACTIONS TESTS

test('can store a new agent', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id]);

    $modelData = [
        'organisation_id'      => $this->organisation->id,
        'user_id'              => $user->id,
        'language_id'          => 68,
        'max_concurrent_chats' => 5,
    ];

    $agent = StoreAgent::make()->handle($modelData);

    expect($agent)->toBeInstanceOf(ChatAgent::class)
        ->and($agent->user_id)->toBe($user->id)
        ->and($agent->max_concurrent_chats)->toBe(5)
        ->and($agent->language_id)->toBe(68)
        ->and($agent->is_online)->toBeFalse()
        ->and($agent->current_chat_count)->toBe(0);
});

test('chat translations follow the agent chat language, not the app language', function () {
    $spanishId = \App\Models\Helpers\Language::where('code', 'es')->value('id');
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id, 'language_id' => 68]);

    expect(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($user)['chat_language_id'])->toBe(68);

    StoreAgent::make()->handle([
        'organisation_id'      => $this->organisation->id,
        'user_id'              => $user->id,
        'language_id'          => $spanishId,
        'max_concurrent_chats' => 5,
    ]);

    expect(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($user->fresh())['chat_language_id'])->toBe($spanishId);
});

test('cannot store an agent for a user that is already active', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id]);

    $modelData = [
        'organisation_id'      => $this->organisation->id,
        'user_id'              => $user->id,
        'language_id'          => 68,
        'max_concurrent_chats' => 5,
    ];

    StoreAgent::make()->handle($modelData);

    expect(fn () => StoreAgent::make()->handle($modelData))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('storing an agent restores a soft deleted agent for the same user', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id]);

    $modelData = [
        'organisation_id'      => $this->organisation->id,
        'user_id'              => $user->id,
        'language_id'          => 68,
        'max_concurrent_chats' => 5,
    ];

    $agent = StoreAgent::make()->handle($modelData);
    $agent->delete();

    $modelData['max_concurrent_chats'] = 10;

    $restoredAgent = StoreAgent::make()->handle($modelData);

    expect($restoredAgent->id)->toBe($agent->id)
        ->and($restoredAgent->trashed())->toBeFalse()
        ->and($restoredAgent->max_concurrent_chats)->toBe(10);
});

test('StoreAgent validation rules are correct', function () {
    $rules = StoreAgent::make()->rules();

    expect($rules)->toHaveKeys([
        'organisation_id',
        'user_id',
        'language_id',
        'max_concurrent_chats',
    ])
        ->and($rules['user_id'])->toEqual(['required', 'integer', 'exists:users,id'])
        ->and($rules['max_concurrent_chats'])->toEqual(['required', 'integer', 'min:1', 'max:100']);
});

test('can update an agent', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    $updatedAgent = UpdateAgent::make()->handle($this->organisation, $agent, [
        'max_concurrent_chats' => 20,
    ]);

    expect($updatedAgent->max_concurrent_chats)->toBe(20);
});

test('cannot update agent to a user_id already used by another active agent', function () {
    $userOne = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $userTwo = User::factory()->create(['group_id' => $this->organisation->group_id]);

    ChatAgent::create([
        'user_id'              => $userOne->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    $agentTwo = ChatAgent::create([
        'user_id'              => $userTwo->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    expect(fn () => UpdateAgent::make()->handle($this->organisation, $agentTwo, [
        'user_id' => $userOne->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('setOnline marks the agent online and available', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    $onlineAgent = UpdateAgent::make()->setOnline($user->id);

    expect($onlineAgent->id)->toBe($agent->id)
        ->and($onlineAgent->is_online)->toBeTrue();
});

test('setOffline marks the agent offline and unavailable', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $offlineAgent = UpdateAgent::make()->setOffline($user->id);

    expect($offlineAgent->id)->toBe($agent->id)
        ->and($offlineAgent->is_online)->toBeFalse()
        ->and($offlineAgent->is_available)->toBe(0);
});

test('setOnline returns null when no agent exists for the user', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id]);

    expect(UpdateAgent::make()->setOnline($user->id))->toBeNull();
});

test('can delete an agent', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    $result = DeleteAgent::make()->handle($agent);

    expect($result['success'])->toBeTrue();

    $this->assertSoftDeleted('chat_agents', ['id' => $agent->id]);
});

test('cannot delete an agent that is still handling active chats', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 1,
    ]);

    $result = DeleteAgent::make()->handle($agent);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('This agent is still handling active chats.');

    $this->assertDatabaseHas('chat_agents', ['id' => $agent->id, 'deleted_at' => null]);
});

test('cannot delete an agent that is still online', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $result = DeleteAgent::make()->handle($agent);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('This agent is still online.');

    $this->assertDatabaseHas('chat_agents', ['id' => $agent->id, 'deleted_at' => null]);
});

// CHAT SESSION ACTIONS TESTS

test('StoreChatEvent handle creates a chat event', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $chatEvent = StoreChatEvent::make()->handle(
        $chatSession,
        ChatEventTypeEnum::NOTE,
        ChatActorTypeEnum::SYSTEM,
        null,
        ['note' => 'test']
    );

    expect($chatEvent)->toBeInstanceOf(ChatEvent::class)
        ->and($chatEvent->event_type)->toBe(ChatEventTypeEnum::NOTE)
        ->and($chatEvent->actor_type)->toBe(ChatActorTypeEnum::SYSTEM)
        ->and($chatEvent->payload['note'])->toBe('test');
});

test('StoreChatEvent closeSession builds the correct payload', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $chatEvent = StoreChatEvent::make()->closeSession(
        $chatSession,
        ChatActorTypeEnum::AGENT,
        99
    );

    expect($chatEvent->event_type)->toBe(ChatEventTypeEnum::CLOSE)
        ->and($chatEvent->payload['closed_by_agent_id'])->toBe(99)
        ->and($chatEvent->payload['user_type'])->toBe('guest')
        ->and($chatEvent->payload['guest_identifier'])->toBe($chatSession->guest_identifier);
});

test('StoreChatAgent creates a chat agent profile', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id]);

    $chatAgent = StoreChatAgent::make()->handle([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 7,
    ]);

    expect($chatAgent)->toBeInstanceOf(ChatAgent::class)
        ->and($chatAgent->user_id)->toBe($user->id)
        ->and($chatAgent->max_concurrent_chats)->toBe(7)
        ->and($chatAgent->is_available)->toBe(1)
        ->and($chatAgent->current_chat_count)->toBe(0);
});

test('UpdateChatAgent handle updates fields and resets chat count when made unavailable', function () {
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $chatAgent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 3,
    ]);

    $updatedAgent = UpdateChatAgent::make()->handle($chatAgent, [
        'is_available' => false,
    ]);

    expect($updatedAgent->is_available)->toBe(0)
        ->and($updatedAgent->current_chat_count)->toBe(0);
});

test('UpdateChatAgent handle caps current_chat_count to new max_concurrent_chats', function () {
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $chatAgent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 10,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 8,
    ]);

    $updatedAgent = UpdateChatAgent::make()->handle($chatAgent, [
        'max_concurrent_chats' => 5,
    ]);

    expect($updatedAgent->max_concurrent_chats)->toBe(5)
        ->and($updatedAgent->current_chat_count)->toBe(5);
});

test('UpdateChatSession updates priority and logs an event', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $updatedFields = UpdateChatSession::make()->handle($chatSession, [
        'priority' => ChatPriorityEnum::URGENT->value,
    ]);

    expect($updatedFields)->toBe(['priority' => ChatPriorityEnum::URGENT->value]);

    $chatSession->refresh();
    expect($chatSession->priority)->toBe(ChatPriorityEnum::URGENT);

    $chatEvent = ChatEvent::where('chat_session_id', $chatSession->id)
        ->where('event_type', ChatEventTypeEnum::PRIORITY)
        ->first();

    expect($chatEvent)->toBeInstanceOf(ChatEvent::class)
        ->and($chatEvent->payload['priority_previous'])->toBe(ChatPriorityEnum::NORMAL->value)
        ->and($chatEvent->payload['priority_current'])->toBe(ChatPriorityEnum::URGENT->value);
});

test('UpdateChatSession returns empty array and logs nothing when no fields change', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $updatedFields = UpdateChatSession::make()->handle($chatSession, []);

    expect($updatedFields)->toBe([]);

    $this->assertDatabaseMissing('chat_events', [
        'chat_session_id' => $chatSession->id,
    ]);
});
