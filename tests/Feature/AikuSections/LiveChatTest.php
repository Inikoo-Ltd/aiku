<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use Illuminate\Support\Str;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Actions\CRM\Agent\StoreAgent;
use App\Actions\CRM\Agent\UpdateAgent;
use App\Actions\CRM\Agent\DeleteAgent;
use App\Actions\CRM\ChatSession\StoreChatEvent;
use App\Actions\CRM\ChatSession\StoreChatAgent;
use App\Actions\CRM\ChatSession\UpdateChatAgent;
use App\Actions\CRM\ChatSession\UpdateChatSession;
use App\Actions\CRM\ChatSession\StoreOfflineMessage;
use App\Actions\CRM\ChatSession\StoreGuestProfile;
use App\Actions\CRM\ChatSession\MarkChatMessagesAsRead;
use App\Actions\CRM\ChatSession\ProcessChatMessageSideEffects;
use App\Actions\CRM\ChatSession\GetChatStatus;
use App\Actions\CRM\ChatSession\GetAgentUnreadMessagesSummary;
use App\Actions\CRM\ChatSession\SyncChatSessionByEmail;
use App\Models\SysAdmin\Organisation;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatEvent;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\CRM\Livechat\ChatAssignment;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Actions\CRM\ChatSession\SendChatMessage;
use App\Actions\CRM\Agent\AssignChatAgentToScope;
use App\Actions\CRM\ChatSession\CloseChatSession;
use App\Actions\CRM\ChatSession\StoreChatSession;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Actions\CRM\ChatSession\AssignChatToAgent;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;

use function Pest\Laravel\actingAs;

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

    $customer = Customer::first();

    if (!$customer) {
        $customer = createCustomer($this->shop);
    }

    $this->customer = $customer;

    $this->action               = new StoreChatSession();
    $this->sendMessageAction    = new SendChatMessage();
    $this->assignmentChatAction = new AssignChatToAgent();
    $this->closeChatAction      = new CloseChatSession();
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
    $organisation = Organisation::first() ?? Organisation::factory()->create();
    $website      = Website::first() ?? Website::factory()->create();
    $customer     = Customer::first() ?? Customer::factory()->create();
    $group        = Group::first() ?? Group::factory()->create();

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
        ->and($rules['web_user_id'])->toEqual(['nullable', 'exists:web_users,id'])
        ->and($rules['language_id'])->toEqual(['required', 'exists:languages,id'])
        ->and($rules['priority'])->toEqual(['required', Rule::enum(ChatPriorityEnum::class)])
        ->and($rules['guest_identifier'])->toEqual(['nullable', 'string', 'max:255'])
        ->and($rules['ai_model_version'])->toEqual(['nullable', 'string', 'max:50'])
        ->and($rules['ulid'])->toEqual(['sometimes', 'string', 'size:26', 'unique:chat_sessions,ulid'])
        ->and($rules['shop_id'])->toEqual(['required', 'exists:shops,id']);
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
    $organisation = Organisation::first() ?? Organisation::factory()->create();
    $website      = Website::first() ?? Website::factory()->create();
    $customer     = Customer::first() ?? Customer::factory()->create();
    $group        = Group::first() ?? Group::factory()->create();

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


test('can send message from agent after assignment', function () {
    $user = $this->user;

    actingAs($user);

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
    $chatSession = ChatSession::find(1)
        ?? ChatSession::inRandomOrder()->first();


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
    $chatSession = ChatSession::find(1)
        ?? ChatSession::inRandomOrder()->first();

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


// AGENT ACTIONS TESTS

test('can store a new agent', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id]);

    $modelData = [
        'organisation_id'      => $this->organisation->id,
        'user_id'              => $user->id,
        'language_id'          => 68,
        'max_concurrent_chats' => 5,
        'shop_id'              => [$this->shop->id],
    ];

    $agent = StoreAgent::make()->handle($modelData);

    expect($agent)->toBeInstanceOf(ChatAgent::class)
        ->and($agent->user_id)->toBe($user->id)
        ->and($agent->max_concurrent_chats)->toBe(5)
        ->and($agent->language_id)->toBe(68)
        ->and($agent->is_online)->toBeFalse()
        ->and($agent->current_chat_count)->toBe(0);

    $this->assertDatabaseHas('shop_has_chat_agents', [
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'chat_agent_id'   => $agent->id,
    ]);
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
        'shop_id',
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
        'shop_id'              => [$this->shop->id],
    ]);

    expect($updatedAgent->max_concurrent_chats)->toBe(20);

    $this->assertDatabaseHas('shop_has_chat_agents', [
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'chat_agent_id'   => $agent->id,
    ]);
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

test('can assign chat agent to scope for multiple shops', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    AssignChatAgentToScope::make()->handle([
        'organisation_id' => $this->organisation->id,
        'shop_id'         => [$this->shop->id],
    ], $agent);

    $this->assertDatabaseHas('shop_has_chat_agents', [
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'chat_agent_id'   => $agent->id,
    ]);
});

test('cannot assign chat agent to a scope it is already assigned to', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    $data = [
        'organisation_id' => $this->organisation->id,
        'shop_id'         => [$this->shop->id],
    ];

    AssignChatAgentToScope::make()->handle($data, $agent);

    expect(fn () => AssignChatAgentToScope::make()->handle($data, $agent))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('updating chat agent scope removes stale shop assignments and adds new ones', function () {
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $otherShop = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());
    $agent     = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    AssignChatAgentToScope::make()->handle([
        'organisation_id' => $this->organisation->id,
        'shop_id'         => [$this->shop->id],
    ], $agent);

    AssignChatAgentToScope::make()->update([
        'organisation_id' => $this->organisation->id,
        'shop_id'         => [$otherShop->id],
    ], $agent);

    $this->assertDatabaseMissing('shop_has_chat_agents', [
        'chat_agent_id' => $agent->id,
        'shop_id'       => $this->shop->id,
    ]);

    $this->assertDatabaseHas('shop_has_chat_agents', [
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $otherShop->id,
        'chat_agent_id'   => $agent->id,
    ]);
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

test('StoreOfflineMessage creates a new session with the offline message', function () {
    $modelData = [
        'name'         => 'John Doe',
        'email'        => 'john@example.com',
        'message'      => 'I need help offline',
        'language_id'  => 68,
        'sender_type'  => ChatSenderTypeEnum::GUEST->value,
        'web_user_id'  => null,
    ];

    $chatSession = StoreOfflineMessage::make()->handle($this->shop, $modelData);

    expect($chatSession)->toBeInstanceOf(ChatSession::class)
        ->and($chatSession->metadata['name'])->toBe('John Doe')
        ->and($chatSession->metadata['email'])->toBe('john@example.com');

    $chatMessage = ChatMessage::where('chat_session_id', $chatSession->id)->first();

    expect($chatMessage->message_text)->toBe('I need help offline')
        ->and($chatMessage->metadata['is_offline_message'])->toBeTrue();
});

test('StoreOfflineMessage reopens a closed session when ulid matches', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::CLOSED,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'closed_at'        => now(),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $modelData = [
        'session_ulid' => $chatSession->ulid,
        'name'         => 'Jane Doe',
        'email'        => 'jane@example.com',
        'message'      => 'Following up',
        'language_id'  => 68,
        'sender_type'  => ChatSenderTypeEnum::GUEST->value,
        'web_user_id'  => null,
    ];

    $reopenedSession = StoreOfflineMessage::make()->handle($this->shop, $modelData);

    expect($reopenedSession->id)->toBe($chatSession->id)
        ->and($reopenedSession->status)->toBe(ChatSessionStatusEnum::ACTIVE)
        ->and($reopenedSession->closed_at)->toBeNull();
});

test('StoreGuestProfile stores guest contact metadata and creates a message', function () {
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

    $result = StoreGuestProfile::make()->handle($chatSession, [
        'name'  => 'Guest Name',
        'email' => 'guest@example.com',
        'phone' => '555-1234',
    ]);

    expect($result['message'])->toBeInstanceOf(ChatMessage::class)
        ->and($result['event_payload']['name'])->toBe('Guest Name')
        ->and($result['event_payload']['email'])->toBe('guest@example.com');

    $chatSession->refresh();
    expect($chatSession->metadata['name'])->toBe('Guest Name')
        ->and($chatSession->metadata['phone'])->toBe('555-1234');

    $chatEvent = ChatEvent::where('chat_session_id', $chatSession->id)
        ->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)
        ->first();

    expect($chatEvent)->toBeInstanceOf(ChatEvent::class);
});

test('MarkChatMessagesAsRead marks unread visitor messages as read for an agent', function () {
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

    $guestMessage = ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Hello',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $agentMessage = ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::AGENT->value,
        'sender_id'       => null,
        'message_text'    => 'Hi there',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    MarkChatMessagesAsRead::make()->handle($chatSession, ChatSenderTypeEnum::AGENT);

    expect($guestMessage->refresh()->is_read)->toBeTrue()
        ->and($agentMessage->refresh()->is_read)->toBeFalse();
});

test('ProcessChatMessageSideEffects updates visitor timestamp and logs a message event', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'last_visitor_message_at' => null,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $chatMessage = ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Need help',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    ProcessChatMessageSideEffects::make()->handle(
        $chatSession,
        ChatSenderTypeEnum::GUEST->value,
        null,
        $chatMessage
    );

    $chatSession->refresh();
    expect($chatSession->last_visitor_message_at)->not->toBeNull();

    $chatEvent = ChatEvent::where('chat_session_id', $chatSession->id)
        ->where('event_type', ChatEventTypeEnum::SEND)
        ->first();

    expect($chatEvent->payload['chat_message_id'])->toBe($chatMessage->id)
        ->and($chatEvent->payload['is_guest_message'])->toBeTrue();
});

test('GetChatStatus returns offline defaults when shop has no website', function () {
    $shop = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());

    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $status = GetChatStatus::make()->handle($shop, $chatSession);

    expect($status['is_online'])->toBeFalse()
        ->and($status['schedule'])->toBeNull()
        ->and($status['is_user'])->toBeFalse()
        ->and($status['is_metadata'])->toBeFalse()
        ->and($status['session']->id)->toBe($chatSession->id);
});

test('GetAgentUnreadMessagesSummary returns zero counts when agent has no shops assigned', function () {
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $chatAgent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $summary = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    expect($summary)->toBe([
        'assigned_unread_count'   => 0,
        'unassigned_unread_count' => 0,
    ]);
});

test('GetAgentUnreadMessagesSummary counts unassigned unread visitor messages', function () {
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $chatAgent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    AssignChatAgentToScope::make()->handle([
        'organisation_id' => $this->organisation->id,
        'shop_id'         => [$this->shop->id],
    ], $chatAgent);

    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Anyone there?',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $summary = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    expect($summary['unassigned_unread_count'])->toBeGreaterThanOrEqual(1)
        ->and($summary['assigned_unread_count'])->toBe(0);
});

test('SyncChatSessionByEmail links the session to an existing web user', function () {
    $webUser = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());

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

    $result = SyncChatSessionByEmail::make()->handle($chatSession, $webUser->email);

    expect($result['success'])->toBeTrue();

    $chatSession->refresh();
    expect($chatSession->web_user_id)->toBe($webUser->id);
});

test('SyncChatSessionByEmail returns failure when no customer matches the email', function () {
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

    $result = SyncChatSessionByEmail::make()->handle($chatSession, 'no-such-user@example.com');

    expect($result['success'])->toBeFalse();

    $chatSession->refresh();
    expect($chatSession->web_user_id)->toBeNull();
});
