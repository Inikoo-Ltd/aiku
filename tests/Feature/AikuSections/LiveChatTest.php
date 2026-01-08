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
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
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
    $web = Website::first();
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
        'ulid'
    ])
        ->and($rules['web_user_id'])->toEqual(['nullable', 'exists:web_users,id'])
        ->and($rules['language_id'])->toEqual(['required', 'exists:languages,id'])
        ->and($rules['priority'])->toEqual(['required', Rule::enum(ChatPriorityEnum::class)])
        ->and($rules['guest_identifier'])->toEqual(['nullable', 'string', 'max:255'])
        ->and($rules['ai_model_version'])->toEqual(['nullable', 'string', 'max:50'])
        ->and($rules['ulid'])->toEqual(['sometimes', 'string', 'size:26', 'unique:chat_sessions,ulid']);
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
            'max_concurrent_chats' => 5,
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
        ->assignToSelf('aw', $chatSession->ulid);

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
            'max_concurrent_chats' => 5,
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


test('can send message without text but with media', function () {
    $chatSession = ChatSession::find(1)
        ?? ChatSession::inRandomOrder()->first();


    $modelData = [
        'message_type' => ChatMessageTypeEnum::IMAGE->value,
        'sender_type'  => ChatSenderTypeEnum::GUEST->value,
        'media_id'     => 1,
        'message_text' => null,
    ];

    $chatMessage = $this->sendMessageAction->handle($chatSession, $modelData);

    expect($chatMessage->message_text)->toBeNull()
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
            'max_concurrent_chats' => 5,
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
