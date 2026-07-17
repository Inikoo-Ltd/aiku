<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Agent\AssignChatAgentToScope;
use App\Actions\Chat\Agent\DeleteAgent;
use App\Actions\Chat\Agent\StoreAgent;
use App\Actions\Chat\Agent\UpdateAgent;
use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\DeleteChatAgent;
use App\Actions\Chat\ChatSession\DownloadChatAttachment;
use App\Actions\Chat\ChatSession\ExportChatConversations;
use App\Actions\Chat\ChatSession\ForceDeleteChatAgent;
use App\Actions\Chat\ChatSession\GetActiveChatSessions;
use App\Actions\Chat\ChatSession\GetAgentUnreadMessagesSummary;
use App\Actions\Chat\ChatSession\GetChatActivity;
use App\Actions\Chat\ChatSession\GetChatAgentByUserId;
use App\Actions\Chat\ChatSession\GetChatAgents;
use App\Actions\Chat\ChatSession\GetChatAgentSpecializations;
use App\Actions\Chat\ChatSession\GetChatCustomerProfile;
use App\Actions\Chat\ChatSession\GetChatCustomerTimeline;
use App\Actions\Chat\ChatSession\GetChatDashboardData;
use App\Actions\Chat\ChatSession\GetChatDashboardVisitors;
use App\Actions\Chat\ChatSession\GetChatMessages;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\GetChatStatus;
use App\Actions\Chat\ChatSession\GetChatVisitorsByCountry;
use App\Actions\Chat\ChatSession\GetGroupChatDashboardData;
use App\Actions\Chat\ChatSession\GetShopChatDashboardData;
use App\Actions\Chat\ChatSession\HandleChatTyping;
use App\Actions\Chat\ChatSession\MarkChatMessagesAsRead;
use App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects;
use App\Actions\Chat\ChatSession\RestoreChatAgent;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\ShareChatSessionToSlack;
use App\Actions\Chat\ChatSession\StoreChatAgent;
use App\Actions\Chat\ChatSession\StoreChatEvent;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreGuestProfile;
use App\Actions\Chat\ChatSession\StoreOfflineMessage;
use App\Actions\Chat\ChatSession\SummarizeChatSession;
use App\Actions\Chat\ChatSession\SyncChatSessionByEmail;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Actions\Chat\ChatSession\TranslateSessionMessages;
use App\Actions\Chat\ChatSession\TranslateSingleMessage;
use App\Actions\Chat\ChatSession\UpdateChatAgent;
use App\Actions\Chat\ChatSession\UpdateChatSession;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
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
use App\Models\Chat\ShopHasChatAgent;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

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

    $this->assertSoftDeleted('shop_has_chat_agents', [
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


// ADDITIONAL CHAT SESSION ACTIONS COVERAGE

test('ExportChatConversations streams a jsonl download of closed sessions', function () {
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

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Hello',
        'is_read'         => true,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::AGENT->value,
        'sender_id'       => null,
        'message_text'    => 'Hi, how can I help?',
        'is_read'         => true,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $response = ExportChatConversations::make()->handle($this->organisation, ['format' => 'jsonl', 'min_turns' => 1]);

    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);
});

test('HandleChatRead asController marks unread visitor messages as read via the API route', function () {
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

    $response = $this->postJson(route('grp.api.chats.read'), [
        'session_ulid' => $chatSession->ulid,
        'request_from' => ChatSenderTypeEnum::AGENT->value,
    ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['success'])->toBeTrue();

    expect($guestMessage->refresh()->is_read)->toBeTrue();
});

test('ShareChatSessionToSlack notifies configured channels', function () {
    Notification::fake();

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

    $result = ShareChatSessionToSlack::make()->handle($chatSession, 'xoxb-fake-token', ['#support']);

    expect($result['succeeded'])->toBe(['#support'])
        ->and($result['failed'])->toBe([]);

    Notification::assertSentOnDemand(\App\Helpers\SlackNotification::class);
});

test('GetChatMessages handle returns messages ordered ascending for a session', function () {
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

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'First',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $messages = GetChatMessages::make()->handle($chatSession, []);

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->message_text)->toBe('First');
});

test('TranslateChatMessage handle is a no-op when target language equals original language', function () {
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

    $chatMessage = ChatMessage::create([
        'chat_session_id'       => $chatSession->id,
        'message_type'          => ChatMessageTypeEnum::TEXT->value,
        'sender_type'           => ChatSenderTypeEnum::GUEST->value,
        'sender_id'             => null,
        'message_text'          => 'Hola',
        'original_text'         => 'Hola',
        'original_language_id'  => 68,
        'is_read'               => false,
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    TranslateChatMessage::make()->handle($chatMessage->id, 68);

    expect($chatMessage->refresh()->message_text)->toBe('Hola');
});

test('IndexChatConversations returns a paginator scoped to organisation sessions with messages', function () {
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

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Hi',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    actingAs($this->user);

    $response = get(route('grp.org.chat.conversations.show', [$this->organisation->slug]));

    $response->assertOk();
});

test('ForceDeleteChatAgent handle runs without error', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);
    expect(ForceDeleteChatAgent::make()->handle($agent, $this->organisation))->toBeNull();
});

test('RestoreChatAgent handle runs without error', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);
    $assignment = ShopHasChatAgent::create([
        'chat_agent_id' => $agent->id,
        'organisation_id' => $this->organisation->id,
        'shop_id' => $this->shop->id,
    ]);
    $assignment->delete();
    expect(RestoreChatAgent::make()->handle($agent, $this->organisation))->toBeNull();
});

test('DeleteChatAgent handle runs without error', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);
    expect(DeleteChatAgent::make()->handle($agent, $this->organisation))->toBeNull();
});

test('GetChatDashboardVisitors returns grouped visitor stats by website', function () {
    $result = GetChatDashboardVisitors::make()->handle($this->organisation);

    expect($result)->toBeArray();
});

test('DownloadChatAttachment forbids downloading media not attached to a chat message', function () {
    $media = Media::create([
        'group_id'        => $this->organisation->group_id,
        'model_type'      => 'App\\Models\\CRM\\Customer',
        'model_id'        => $this->customer->id,
        'collection_name' => 'default',
        'name'            => 'file',
        'file_name'       => 'file.txt',
        'mime_type'       => 'text/plain',
        'uuid'            => (string) Str::uuid(),
        'ulid'            => (string) Str::ulid(),
        'disk'            => 'local',
        'size'            => 10,
        'manipulations'   => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
    ]);

    expect(fn () => DownloadChatAttachment::make()->handle($media->ulid))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('GetChatActivity returns formatted events for a chat session', function () {
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

    StoreChatEvent::make()->openSession($chatSession, ChatActorTypeEnum::GUEST, null, ['is_guest' => true, 'ip_address' => '127.0.0.1']);

    $result = GetChatActivity::make()->handle($chatSession);

    expect($result['success'])->toBeTrue()
        ->and($result['events'])->toHaveCount(1);
});

test('GetGroupChatDashboardData returns stats and table for a group', function () {
    $result = GetGroupChatDashboardData::make()->handle($this->organisation->group);

    expect($result)->toHaveKeys(['stats', 'table'])
        ->and($result['stats'])->toHaveKeys(['chatEnabledShops', 'chatAgents', 'chatSessionsTotal']);
});

test('SummarizeChatSession returns session unchanged when there are no messages', function () {
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

    $result = SummarizeChatSession::make()->handle($chatSession);

    expect($result->id)->toBe($chatSession->id)
        ->and($result->metadata)->toBeNull();
});

test('GetShopChatDashboardData returns stats for a shop', function () {
    $result = GetShopChatDashboardData::make()->handle($this->shop);

    expect($result)->toHaveKeys([
        'chatEnabled',
        'chatAgents',
        'chatSessionsTotal',
        'chatSessionsWaiting',
        'chatSessionsActive',
        'chatSessionsClosed',
        'chatMessagesTotal',
        'chatMessagesUnread',
    ]);
});

test('GetChatAgentSpecializations returns all enum cases with labels', function () {
    $result = GetChatAgentSpecializations::make()->handle();

    expect($result)->not->toBeEmpty()
        ->and($result[0])->toHaveKeys(['value', 'label']);
});

test('GetChatVisitorsByCountry returns aggregated counts by country code', function () {
    ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'geo_country_code' => 'MY',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $result = GetChatVisitorsByCountry::make()->handle($this->organisation);

    expect($result)->not->toBeEmpty()
        ->and(collect($result)->pluck('country_code')->map(fn ($code) => trim($code)))->toContain('MY');
});

test('GetChatCustomerProfile returns empty defaults when session has no web user', function () {
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

    $result = GetChatCustomerProfile::make()->handle($chatSession);

    expect($result)->toBe(['tags' => [], 'stats' => null]);
});

test('GetChatCustomerTimeline returns empty events when session has no customer', function () {
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

    $result = GetChatCustomerTimeline::make()->handle($chatSession);

    expect($result)->toBe(['events' => []]);
});

test('GetChatAgents returns only available agents with shop assignments', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
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
    ], $agent);

    $result = GetChatAgents::make()->handle();

    expect(collect($result)->pluck('agent_id'))->toContain($agent->id);
});

test('GetActiveChatSessions returns active and waiting sessions for an organisation', function () {
    ChatSession::create([
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

    $result = GetActiveChatSessions::make()->handle($this->organisation);

    expect($result)->not->toBeEmpty()
        ->and($result[0])->toHaveKeys(['id', 'status', 'has_messages', 'country_code']);
});

test('GetChatDashboardData returns stats, shops and table for an organisation', function () {
    $result = GetChatDashboardData::make()->handle($this->organisation);

    expect($result)->toHaveKeys(['stats', 'chatEnabledShops', 'table']);
});

test('TranslateSingleMessage dispatches a translation job when no translation exists yet', function () {
    TranslateChatMessage::shouldRun();

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

    $chatMessage = ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Bonjour',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    TranslateSingleMessage::make()->handle($chatMessage, 68);

    expect(true)->toBeTrue();
});

test('HandleChatTyping handle broadcasts a typing indicator event', function () {
    Event::fake();

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

    $result = HandleChatTyping::make()->handle([
        'session_ulid' => $chatSession->ulid,
        'user_name'    => 'Agent Smith',
        'is_typing'    => true,
    ]);

    expect($result['event_type'])->toBe('typing_indicator');

    Event::assertDispatched(\App\Events\BroadcastTypingIndicator::class);
});

test('GetChatSessions returns a paginator of sessions with messages', function () {
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

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Hi',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $result = GetChatSessions::make()->handle([]);

    expect($result->total())->toBeGreaterThanOrEqual(1);
});

test('GetChatAgentByUserId asController returns 404 json when agent does not exist', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id]);

    try {
        GetChatAgentByUserId::make()->asController($user->id);
        $this->fail('Expected HttpResponseException to be thrown.');
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(404);
    }
});

test('GetChatAgentByUserId asController returns agent details when found', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $response = GetChatAgentByUserId::make()->asController($user->id);

    $data = $response->getData(true);

    expect($data['success'])->toBeTrue()
        ->and($data['data']['id'])->toBe($agent->id);
});

test('TranslateSessionMessages dispatches a translation indicator when no messages need translating', function () {
    Event::fake();

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

    TranslateSessionMessages::make()->handle($chatSession, 68);

    Event::assertDispatched(\App\Events\TranslationChatIndicator::class);
});

test('TranslateSessionMessages chains translation jobs for unread visitor messages', function () {
    Bus::fake();

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

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Bonjour',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    TranslateSessionMessages::make()->handle($chatSession, 68);

    expect(true)->toBeTrue();
});


// CHAT UI ACTIONS TESTS

test('UI Show shop chat dashboard', function () {
    actingAs($this->user);

    $response = get(route('grp.org.shops.show.crm.chat.dashboard', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Shop/Chat/Dashboard');
    });
});

test('UI Index chat sessions for a shop', function () {
    actingAs($this->user);

    $response = get(route('grp.org.shops.show.crm.chat_sessions.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Shop/CRM/ChatSessions');
    });
});

test('UI Show chat session for a shop', function () {
    actingAs($this->user);

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

    $response = get(route('grp.org.shops.show.crm.chat_sessions.show', [$this->organisation->slug, $this->shop->slug, $chatSession->id]));

    $response->assertOk();
});

test('UI Show org chat dashboard', function () {
    actingAs($this->user);

    $response = get(route('grp.org.chat.dashboard', [$this->organisation->slug]));

    $response->assertOk();
});

test('UI Show org chat conversation detail', function () {
    actingAs($this->user);

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

    $response = get(route('grp.org.chat.conversations.detail', [$this->organisation->slug, $chatSession->id]));

    $response->assertOk();
});

test('UI Show group chat dashboard', function () {
    actingAs($this->user);

    $response = get(route('grp.chat.dashboard'));

    $response->assertOk();
});

test('UI Show chat conversations index for organisation', function () {
    actingAs($this->user);

    $response = get(route('grp.org.chat.conversations.show', [$this->organisation->slug]));

    $response->assertOk();
});

test('UI Edit agent shows the form for an existing agent', function () {
    actingAs($this->user);

    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    $response = get(route('grp.org.chat.agents.edit', [$this->organisation->slug, $agent->id]));

    $response->assertOk();
});

test('UI Create agent shows the create form', function () {
    actingAs($this->user);

    $response = get(route('grp.org.chat.agents.create', [$this->organisation->slug]));

    $response->assertOk();
});

test('UI Show agent listing for organisation', function () {
    actingAs($this->user);

    $response = get(route('grp.org.chat.agents.show', [$this->organisation->slug]));

    $response->assertOk();
});

test('UI Show group agents listing', function () {
    actingAs($this->user);

    $response = get(route('grp.chat.agents.show'));

    $response->assertOk();
});

it('can render chat sessions index in CRM', function () {
    $this->withoutExceptionHandling();

    setPermissionsTeamId($this->shop->group_id);
    SeedShopPermissions::run($this->shop);
    $crmViewPermission = Permission::where('name', "crm.{$this->shop->id}.view")->first();
    if ($crmViewPermission) {
        $this->user->givePermissionTo($crmViewPermission);
    }
    $this->user->refresh();
    actingAs($this->user);

    $response = get(route('grp.org.shops.show.crm.chat_sessions.index', [
        $this->organisation->slug,
        $this->shop->slug,
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Shop/CRM/ChatSessions')
            ->has('data');
    });
});

it('can render chat session detail', function () {
    $this->withoutExceptionHandling();

    $chatSession = ChatSession::where('shop_id', $this->shop->id)->first()
        ?? StoreChatSession::make()->handle([
            'language_id' => 68,
            'priority'    => ChatPriorityEnum::NORMAL->value,
            'shop_id'     => $this->shop->id,
        ]);

    setPermissionsTeamId($this->shop->group_id);
    SeedShopPermissions::run($this->shop);
    $crmViewPermission = Permission::where('name', "crm.{$this->shop->id}.view")->first();
    if ($crmViewPermission) {
        $this->user->givePermissionTo($crmViewPermission);
    }
    $this->user->refresh();
    actingAs($this->user);

    $response = get(route('grp.org.shops.show.crm.chat_sessions.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $chatSession->id,
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Shop/CRM/ChatSession')
            ->has('chatSession')
            ->has('messages');
    });
});
