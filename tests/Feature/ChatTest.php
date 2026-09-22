<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Agent\AssignChatAgentToScope;
use Illuminate\Support\Arr;
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
use App\Actions\Helpers\Address\GetFormattedAddress;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Actions\Chat\ChatSession\GetChatCustomerTimeline;
use App\Actions\Chat\ChatSession\GetChatReports;
use App\Actions\Chat\ChatSession\GetChatDashboardVisitors;
use App\Actions\Chat\ChatSession\GetChatMessages;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\GetChatStatus;
use App\Actions\Chat\ChatSession\GetChatVisitorsByCountry;
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
use App\Actions\Chat\GetCustomerChatHistory;
use App\Actions\Chat\MetaChatSession\AssignMetaChatToAgent;
use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions;
use App\Actions\Comms\WhatsappCampaign\SendWhatsappDeliveryChannel;
use App\Actions\Chat\MetaChatSession\UpdateMetaChatSession;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;
use App\Enums\CRM\Livechat\ChatRetractionReasonEnum;
use App\Actions\Chat\ChatSession\RedactChatMessage;
use App\Models\Chat\ChatMessageTranslation;
use App\Actions\Chat\ChatSession\RetractChatMessage;
use App\Actions\Chat\ChatSession\RestoreChatSession;
use App\Actions\Chat\ChatSession\TrashChatSession;
use Illuminate\Support\Facades\Route;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Actions\Chat\ChatSession\MarkChatSessionAsRubbish;
use App\Enums\CRM\Livechat\ChatIgnoreReasonEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\HumanResources\Employee;
use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatEvent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Chat\MetaChatEvent;
use App\Models\Chat\MetaChatSession;
use App\Models\Chat\ShopHasChatAgent;
use App\Models\Catalogue\Product;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Media;
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
use function Pest\Laravel\getJson;
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
    $group        = createGroup();

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
    $group        = createGroup();

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

    ShopHasChatAgent::firstOrCreate([
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'chat_agent_id'   => $agent->id,
    ]);

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

    ShopHasChatAgent::firstOrCreate([
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'chat_agent_id'   => $agent->id,
    ]);

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
        'total_unread_count'      => 0,
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

test('chat status for a session moved to trash answers not found instead of failing', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    $chatSession->delete();

    $this->getJson(route('grp.api.chats.status', [
        'shop_id' => $this->shop->id,
        'ulid'    => $chatSession->ulid,
    ]))->assertNotFound();
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

    $result = ShareChatSessionToSlack::make()->handle($chatSession, 'xoxb-fake-token', [
        ['type' => 'channel', 'id' => 'C0SUPPORT', 'name' => '#support'],
    ]);

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

    expect($result)->toBe(['tags' => [], 'stats' => null, 'email' => null, 'profile_url' => null]);
});

test('GetChatCustomerProfile gives the customer address and leaves baskets out of the last orders', function () {
    $result = GetChatCustomerProfile::make()->contactAndLastOrders($this->customer);

    expect($result)->toHaveKeys(['company_name', 'phone', 'address', 'last_orders'])
        ->and($result['address'])->toBe(GetFormattedAddress::run($this->customer->address))
        ->and(count($result['last_orders']))->toBe(
            min(5, $this->customer->orders()->where('state', '!=', OrderStateEnum::CREATING)->count())
        );
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

test('GetChatAgents returns only available agents who may work the shop', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    setPermissionsTeamId($this->user->group_id);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
    ]);

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

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Already seen',
        'is_read'         => true,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::AGENT->value,
        'sender_id'       => null,
        'message_text'    => 'Agent reply',
        'is_read'         => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $result = GetChatSessions::make()->handle([]);

    expect($result->total())->toBeGreaterThanOrEqual(1);

    $session = collect($result->items())->firstWhere('id', $chatSession->id);
    expect($session)->not->toBeNull()
        ->and((int) $session->unread_count)->toBe(1)
        ->and(\App\Http\Resources\CRM\Livechat\ChatSessionListResource::make($session)->resolve()['unread_count'])->toBe(1);
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

    $response = get(route('grp.org.shops.show.chat.reports', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Chat/ChatReports');
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

    $response = get(route('grp.org.chat.reports', [$this->organisation->slug]));

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

    $response = get(route('grp.chat.reports'));

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

describe('staff messaging', function () {
    beforeEach(function () {
        $this->otherUser = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        if ($this->otherUser->group_id !== $this->user->group_id) {
            $this->otherUser->update(['group_id' => $this->user->group_id]);
        }
    });

    test('dm is created once and reused', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $first  = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        $second = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->otherUser, ['user_ids' => [$this->user->id]]);

        expect($first->id)->toBe($second->id)
            ->and($first->type)->toBe('dm')
            ->and($first->participants()->count())->toBe(2);
    });

    test('message is sent, counted unread for the other participant, then read', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => '<b>hello</b> there']);

        Event::assertDispatched(\App\Events\StaffMessageSent::class);
        expect($conversation->messages()->first()->body)->toBe('hello there');

        $mine   = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id);
        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->otherUser)->firstWhere('id', $conversation->id);
        expect((int) $mine->unread_count)->toBe(0)->and((int) $theirs->unread_count)->toBe(1);

        \App\Actions\Chat\Staff\MarkStaffConversationRead::run($conversation, $this->otherUser);
        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->otherUser)->firstWhere('id', $conversation->id);
        expect((int) $theirs->unread_count)->toBe(0);
    });

    test('a message sent in the same second the conversation was read still counts unread', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        $other        = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);

        \Illuminate\Support\Carbon::setTestNow('2026-09-14 10:00:00.100000');
        \App\Actions\Chat\Staff\MarkStaffConversationRead::run($conversation, $other);
        \Illuminate\Support\Carbon::setTestNow('2026-09-14 10:00:00.200000');
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'same second']);
        \Illuminate\Support\Carbon::setTestNow();

        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($other)->firstWhere('id', $conversation->id);
        expect((int) $theirs->unread_count)->toBe(1);
    });

    test('the conversations list loads avatars and contexts once, not per conversation', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'list me']);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $rows = \App\Http\Resources\Chat\StaffConversationResource::collection(
            \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)
        )->resolve();
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query');
        \Illuminate\Support\Facades\DB::disableQueryLog();

        expect(collect($rows)->pluck('ulid'))->toContain($conversation->ulid)
            ->and($queries->filter(fn (string $sql) => str_contains($sql, 'from "media"'))->count())->toBeLessThanOrEqual(1)
            ->and($queries->filter(fn (string $sql) => preg_match('/from "(delivery_notes|orders|picking_sessions)"/', $sql))->count())->toBeLessThanOrEqual(3);
    });

    test('reaction toggles on and off', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'react']);

        \App\Actions\Chat\Staff\ToggleStaffMessageReaction::run($message, $this->otherUser, '👍');
        expect($message->reactions()->count())->toBe(1);
        \App\Actions\Chat\Staff\ToggleStaffMessageReaction::run($message, $this->otherUser, '👍');
        expect($message->reactions()->count())->toBe(0);
    });

    test('image only message stores media', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        \Illuminate\Support\Facades\Storage::fake('public');
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        $response = actingAs($this->user)
            ->post(route('grp.chat.staff.conversations.messages.store', $conversation), [
                'image' => \Illuminate\Http\UploadedFile::fake()->image('screenshot.png', 40, 40),
            ]);
        $response->assertSuccessful();
        expect($response->json('data.image'))->not->toBeNull()
            ->and($response->json('data.body'))->toBe('');
    });

    test('non participant is rejected by the message endpoint', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $stranger     = User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        actingAs($stranger)
            ->postJson(route('grp.chat.staff.conversations.messages.store', $conversation), ['body' => 'intrude'])
            ->assertForbidden();
    });

    test('empty conversation is listed only for its creator until a message is sent', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        $newcomer     = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$newcomer->id]]);

        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->not->toBeNull()
            ->and(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($newcomer)->firstWhere('id', $conversation->id))->toBeNull();

        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'now you see me']);

        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($newcomer)->firstWhere('id', $conversation->id))->not->toBeNull();
    });
});

describe('staff messaging mentions', function () {
    test('mention resolves to participant and flags unread for them', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        $nickname = 'adamm'.Str::lower(Str::random(6));
        $other    = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'nickname' => $nickname]);

        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => "hey @$nickname check this"]);

        expect($message->mentions)->toBe([$other->id]);

        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($other)->firstWhere('id', $conversation->id);
        $mine   = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id);
        expect($theirs->has_mention)->toBeTruthy()
            ->and($mine->has_mention)->toBeFalsy();
    });
});

describe('staff messaging page', function () {
    beforeEach(function () {
        $this->otherUser = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
    });

    test('index renders the messaging page', function () {
        actingAs($this->user)->get(route('grp.chat.staff.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffMessaging'));
    });

    test('show renders the messaging page for a participant', function () {
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        actingAs($this->user)->get(route('grp.chat.staff.show', $conversation))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffMessaging'));
    });
});

describe('staff messaging context shortcuts', function () {
    test('ask CRM on an order opens one group conversation with the shop CRM role holders', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $product = Product::where("shop_id", $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);

        $crmUser = User::factory()->create(['group_id' => $this->user->group_id]);
        setPermissionsTeamId($this->user->group_id);
        $crmUser->assignRole(\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id'))->toContain($crmUser->id);

        $first  = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $order, 'crm');
        $second = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $order, 'crm');

        expect($first->id)->toBe($second->id)
            ->and($first->type)->toBe('group')
            ->and($first->context_type)->toBe('Order')
            ->and($first->context_id)->toBe($order->id)
            ->and($first->participants()->pluck('users.id'))->toContain($crmUser->id, $this->user->id);

        $resource = (new \App\Http\Resources\Chat\StaffConversationResource($first->load('participants')))->resolve();
        expect($resource['context_label'])->toBe($order->reference)
            ->and($resource['context_url'])->toContain($order->slug);
    });
});

describe('staff messaging audience mapping', function () {
    test('org crm list is ignored, shop crm list wins', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $other   = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);

        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$other->id, now()->timestamp);
        $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->not->toBe([$other->id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$other->id]);
    });

    test('org warehouse list is the fallback when shop has none', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $other   = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);

        $shopSettings = $this->shop->settings ?? [];
        \Illuminate\Support\Arr::set($shopSettings, 'staff_chat.warehouse_user_ids', []);
        $this->shop->update(['settings' => $shopSettings]);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$other->id, now()->timestamp);
        $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['staff_chat' => ['warehouse_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('warehouse', $order)->pluck('id')->all())->toBe([$other->id]);
    });
});

describe('staff messaging routing', function () {
    test('shop primary wins over org', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id]]])]);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$a->id, now()->timestamp);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$a->id]);
    });

    test('inactive primary falls back to active backup', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);
        $b       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id], 'crm_backup_user_ids' => [$b->id]]])]);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$a->id);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$b->id, now()->timestamp);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$b->id]);
    });

    test('nobody active returns primary and backup together', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);
        $b       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id], 'crm_backup_user_ids' => [$b->id]]])]);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$a->id);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$b->id);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
    });
});

describe('staff messaging picking session', function () {
    test('ask CRM on a picking session opens one group conversation with the shop CRM role holders', function () {
        $pickingSession = \App\Models\Inventory\PickingSession::first();
        if (!$pickingSession) {
            $this->markTestSkipped('no picking session available');
        }

        $first  = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $pickingSession, 'crm');
        $second = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $pickingSession, 'crm');

        expect($first->id)->toBe($second->id)
            ->and($first->context_type)->toBe('PickingSession');

        $resource = (new \App\Http\Resources\Chat\StaffConversationResource($first->load('participants')))->resolve();
        expect($resource['context_url'])->toContain($pickingSession->slug);
    });
});

describe('staff messaging team', function () {
    test('team membership toggles and coworkers endpoint flags it', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        expect(\App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other))->toBeTrue()
            ->and($this->user->teamMembers()->count())->toBe(1);

        $coworkers = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');
        $row = collect($coworkers)->firstWhere('id', $other->id);
        expect($row['in_team'])->toBeTrue()
            ->and($row['organisation_ids'])->toBeArray();

        expect(\App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other))->toBeFalse()
            ->and($this->user->teamMembers()->count())->toBe(0);
    });

    test('the coworkers poll shares the group list between viewers but keeps team and closeness per viewer', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        \Illuminate\Support\Facades\Cache::forget('staff-coworkers:'.$this->user->group_id);
        \App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other);

        actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk();

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $mine = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');
        $queryLog          = collect(\Illuminate\Support\Facades\DB::getQueryLog());
        $queriesOnWarmPoll = $queryLog
            ->filter(fn (array $query) => preg_match('/"(users|user_has_models|employees|media|user_has_team_members)"/', $query['query']))
            ->count();
        expect($queryLog->count())->toBeLessThanOrEqual(6);
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $theirs = actingAs($other)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');

        expect(collect($mine)->firstWhere('id', $other->id)['in_team'])->toBeTrue()
            ->and(collect($mine)->pluck('id'))->not->toContain($this->user->id)
            ->and(collect($theirs)->pluck('id'))->not->toContain($other->id)
            ->and(collect($theirs)->firstWhere('id', $this->user->id)['in_team'])->toBeFalse()
            ->and($queriesOnWarmPoll)->toBeLessThanOrEqual(2);

        $searched = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index', ['q' => $other->chatName()]))->assertOk()->json('data');
        expect(collect($searched)->pluck('id'))->toContain($other->id);

        \App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other);
    });

    test('json-only grp routes exist and skip building the layout', function () {
        $routeNames = (new ReflectionClassConstant(\App\Http\Middleware\HandleInertiaGrpRequests::class, 'JSON_ONLY_ROUTES'))->getValue();

        foreach ($routeNames as $routeName) {
            expect(\Illuminate\Support\Facades\Route::has($routeName))->toBeTrue("$routeName is not a registered route");

            $request = \Illuminate\Http\Request::create('/');
            $request->setRouteResolver(fn () => \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName));

            expect(app(\App\Http\Middleware\HandleInertiaGrpRequests::class)->share($request))->toBe([]);
        }
    });
});

describe('staff messaging archive', function () {
    test('closing a conversation hides it until a new message arrives', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $other, ['body' => 'hi']);

        \App\Actions\Chat\Staff\ArchiveStaffConversation::run($conversation, $this->user);
        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->toBeNull();

        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $other, ['body' => 'are you there?']);
        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->not->toBeNull();
    });
});

describe('staff messaging gifs', function () {
    test('search returns mapped klipy results', function () {
        \Illuminate\Support\Facades\Http::fake([
            'api.klipy.com/*' => \Illuminate\Support\Facades\Http::response([
                'result' => true,
                'data'   => [
                    'data'     => [
                        [
                            'id'    => '1',
                            'file'  => [
                                'sm' => ['gif' => ['url' => 'https://static.klipy.com/a/sm.gif', 'width' => 100, 'height' => 80]],
                                'md' => ['gif' => ['url' => 'https://static.klipy.com/a/md.gif', 'width' => 200, 'height' => 160]],
                            ],
                        ],
                    ],
                    'has_next'     => true,
                    'current_page' => 1,
                ],
            ]),
        ]);
        config(['services.klipy.key' => 'k']);

        actingAs($this->user)
            ->getJson(route('grp.chat.staff.gifs.search', ['q' => 'cat', 'page' => 1]))
            ->assertOk()
            ->assertJsonPath('data.0.url', 'https://static.klipy.com/a/md.gif')
            ->assertJsonPath('next', 2);
    });
});

describe('staff messaging nickname', function () {
    test('user sets a nickname and it appears in profile update and messages', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'Raulito'])
            ->assertOk();

        expect($this->user->fresh()->nickname)->toBe('Raulito');

        Event::fake([\App\Events\StaffMessageSent::class]);
        Bus::fake([\App\Actions\Chat\Staff\TranslateStaffMessage::class]);
        $other        = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'hi']);

        $resource = (new \App\Http\Resources\Chat\StaffMessageResource($message->load(['user', 'translations', 'reactions', 'conversation'])))->resolve();
        expect($resource['user_name'])->toBe('Raulito');
    });

    test('nickname must be unique', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'SharedNick'])
            ->assertOk();

        actingAs($other)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'SharedNick'])
            ->assertStatus(422);
    });
});

describe('staff messaging chat theme', function () {
    test('user can set chat theme', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'nord'])
            ->assertOk();

        expect(Arr::get($this->user->fresh()->settings, 'chat_theme'))->toBe('nord');
    });

    test('changing chat theme busts the cached layout props', function () {
        config()->set('ui.cache.layout', true);

        $props = new App\Actions\UI\Grp\GetFirstLoadProps()->handle($this->user);
        expect(Arr::get($props, 'layout.chat_theme'))->not->toBe('gruvbox');

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'gruvbox'])
            ->assertOk();

        $props = new App\Actions\UI\Grp\GetFirstLoadProps()->handle($this->user->fresh());
        expect(Arr::get($props, 'layout.chat_theme'))->toBe('gruvbox');
    });

    test('invalid chat theme is rejected', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'not-a-theme'])
            ->assertStatus(422);
    });
});

describe('staff chat audience seeding', function () {
    test('seed fills an empty shop list from role holders and leaves a curated one alone', function () {
        $roleHolder = User::factory()->create(['group_id' => $this->user->group_id]);
        setPermissionsTeamId($this->user->group_id);
        $roleHolder->assignRole(\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

        $settings = $this->shop->settings ?? [];
        \Illuminate\Support\Arr::set($settings, 'staff_chat.crm_user_ids', []);
        $this->shop->update(['settings' => $settings]);

        \App\Actions\Chat\Staff\SeedStaffChatAudiences::run();

        expect(\Illuminate\Support\Arr::get($this->shop->fresh()->settings, 'staff_chat.crm_user_ids'))->toContain($roleHolder->id);

        $curated  = User::factory()->create(['group_id' => $this->user->group_id]);
        $settings = $this->shop->fresh()->settings ?? [];
        \Illuminate\Support\Arr::set($settings, 'staff_chat.crm_user_ids', [$curated->id]);
        $this->shop->update(['settings' => $settings]);

        \App\Actions\Chat\Staff\SeedStaffChatAudiences::run();

        expect(\Illuminate\Support\Arr::get($this->shop->fresh()->settings, 'staff_chat.crm_user_ids'))->toBe([$curated->id]);
    });
});

test('a chat session cannot be bound to a web user the caller is not logged in as', function () {
    $victim = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());

    $modelData = [
        'web_user_id' => $victim->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ];

    config()->set('app.enforce_chat_identity', true);
    \Illuminate\Support\Facades\Auth::guard('retina')->logout();

    $hijacked = $this->action->handle($modelData);

    expect($hijacked->web_user_id)->toBeNull()
        ->and($hijacked->guest_identifier)->not->toBeNull();

    \Illuminate\Support\Facades\Auth::guard('retina')->login($victim);

    $legitimate = $this->action->handle($modelData);

    expect($legitimate->web_user_id)->toBe($victim->id);
});

test('a claimed chat web user is recorded but honoured while enforcement is off', function () {
    $victim = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());

    config()->set('app.enforce_chat_identity', false);
    \Illuminate\Support\Facades\Auth::guard('retina')->logout();
    \Illuminate\Support\Facades\Log::spy();

    $chatSession = $this->action->handle([
        'web_user_id' => $victim->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ]);

    expect($chatSession->web_user_id)->toBe($victim->id);
    \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => $message === 'Chat web user claimed without a matching login');
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

test('can update rating on a meta chat session', function () {
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string)Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628111222333',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $updated = UpdateMetaChatSession::make()->handle($metaChatSession, ['rating' => 4]);

    expect($updated)->toBe(['rating' => 4])
        ->and($metaChatSession->fresh()->rating)->toBe(4);

    $event = MetaChatEvent::where('meta_chat_session_id', $metaChatSession->id)
        ->where('event_type', ChatEventTypeEnum::RATING)
        ->first();

    expect($event)->toBeInstanceOf(MetaChatEvent::class)
        ->and($event->payload['values']['rating'])->toBe(4);
});

test('new meta chat session response carries the assigned agent', function () {
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string)Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628555666777',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $agent = ChatAgent::where('user_id', $this->user->id)->first()
        ?? StoreChatAgent::make()->handle(['user_id' => $this->user->id]);

    AssignMetaChatToAgent::make()->handle($metaChatSession, $agent, 'Assigned to agent who started the chat');

    $payload = StoreMetaChatSession::make()->jsonResponse($metaChatSession->fresh());

    // Without this the agent panel treats the brand new chat as unassigned and
    // blocks the composer behind "Assign to me".
    expect($payload['assigned_agent'])->not->toBeNull()
        ->and($payload['assigned_agent']['id'])->toBe($agent->id)
        ->and($payload['assigned_agent']['user_id'])->toBe($this->user->id);
});

test('my chats excludes a whatsapp thread now held by another agent', function () {
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $mine  = ChatAgent::where('user_id', $this->user->id)->first()
        ?? StoreChatAgent::make()->handle(['user_id' => $this->user->id]);
    $other = StoreChatAgent::make()->handle(['user_id' => User::factory()->create(['group_id' => $this->organisation->group_id])->id]);

    foreach ([$mine, $other] as $agent) {
        if ($agent->isAssignedToShop($this->shop->id, $this->organisation->id)) {
            continue;
        }

        AssignChatAgentToScope::make()->handle([
            'organisation_id' => $this->organisation->id,
            'shop_id'         => [$this->shop->id],
        ], $agent);
    }

    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string)Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628444555666',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    // I handled it first, then it was handed over: my row goes stale, theirs is active.
    $metaChatSession->assignments()->create([
        'meta_channel_id' => $channel->id,
        'chat_agent_id'   => $mine->id,
        'status'          => ChatAssignmentStatusEnum::RESOLVED->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now()->subDay(),
    ]);
    $metaChatSession->assignments()->create([
        'meta_channel_id' => $channel->id,
        'chat_agent_id'   => $other->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    $filters = [
        'assigned_to_me' => $this->user->id,
        'statuses'       => [ChatSessionStatusEnum::ACTIVE->value],
        'shop_id'        => $this->shop->id,
    ];

    $mineUlids = collect(GetMetaChatSessions::make()->handle($filters)->items())->pluck('ulid');
    $teamUlids = collect(GetMetaChatSessions::make()->handle($filters + ['view_team' => true])->items())->pluck('ulid');

    expect($mineUlids)->not->toContain($metaChatSession->ulid)
        ->and($teamUlids)->toContain($metaChatSession->ulid);
});

test('the whatsapp list follows the position, like the website one, not the retired shop table', function () {
    setPermissionsTeamId($this->user->group_id);

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    ChatAgent::create(['user_id' => $clerk->id, 'max_concurrent_chats' => 10]);

    $unclaimed = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628444555777',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $waiting = collect(GetMetaChatSessions::make()->handle([
        'assigned_to_me' => $clerk->id,
        'statuses'       => [ChatSessionStatusEnum::WAITING->value],
        'shop_id'        => $this->shop->id,
    ])->items())->pluck('ulid');

    expect($waiting)->toContain($unclaimed->ulid);
});

test('a whatsapp number says which country it is from', function (?string $phone, ?string $countryCode) {
    expect(\App\Actions\Helpers\Country\GetCountryCodeFromPhone::run($phone))->toBe($countryCode);
})->with([
    ['+447926412326', 'GB'],
    ['+918979080122', 'IN'],
    ['+12125550100', 'US'],
    ['+421905123456', 'SK'],
    ['+18765550100', 'JM'],
    ['07926412326', null],
    [null, null],
]);


test('a template status webhook is verified by the WhatsApp Business Account it names', function () {
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $appSecret = 'test-meta-app-secret';
    $wabaId    = '102290129340398';

    $this->organisation->update([
        'settings' => array_merge($this->organisation->settings ?? [], [
            'meta' => ['app_secret' => $appSecret],
        ]),
    ]);

    $this->shop->update([
        'settings' => array_merge($this->shop->settings ?? [], [
            'whatsapp' => ['waba_id' => $wabaId],
        ]),
    ]);

    $template = MetaMessageTemplate::create([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'meta_channel_id' => $channel->id,
        'template_id'     => '1234567890'.$this->shop->id,
        'name'            => 'webhook_status_template',
        'status'          => 'PENDING',
    ]);

    /* The payload Meta sends for a template verdict: it names the account in entry.id and
       carries no metadata, because a template belongs to the account rather than to any one
       of the numbers under it. */
    $body = json_encode([
        'object' => 'whatsapp_business_account',
        'entry'  => [
            [
                'id'      => $wabaId,
                'changes' => [
                    [
                        'field' => 'message_template_status_update',
                        'value' => [
                            'event'                 => 'APPROVED',
                            'message_template_id'   => $template->template_id,
                            'message_template_name' => $template->name,
                            'reason'                => 'NONE',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->call(
        'POST',
        route('webhooks.whatsapp.handle'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256'  => 'sha256='.hash_hmac('sha256', $body, $appSecret),
        ],
        $body
    );

    $response->assertOk();

    expect($template->refresh()->status)->toBe('APPROVED');
});

test('a template status webhook signed with the wrong secret is rejected', function () {
    $wabaId = '102290129340399';

    $this->organisation->update([
        'settings' => array_merge($this->organisation->settings ?? [], [
            'meta' => ['app_secret' => 'test-meta-app-secret'],
        ]),
    ]);

    $this->shop->update([
        'settings' => array_merge($this->shop->settings ?? [], [
            'whatsapp' => ['waba_id' => $wabaId],
        ]),
    ]);

    $body = json_encode([
        'object' => 'whatsapp_business_account',
        'entry'  => [
            [
                'id'      => $wabaId,
                'changes' => [
                    [
                        'field' => 'message_template_status_update',
                        'value' => ['event' => 'APPROVED', 'message_template_id' => '404'],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->call(
        'POST',
        route('webhooks.whatsapp.handle'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'not-the-app-secret'),
        ],
        $body
    );

    $response->assertStatus(401);
});

test('staff task to a department is queued with a thread and the claimer joins it', function () {
    $requester = $this->user;
    $worker    = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Check the product looks like the picture', 'department' => 'warehouse']);

    expect($task->reference)->toStartWith('TASK-')
        ->and($task->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::TODO)
        ->and($task->assignee_id)->toBeNull()
        ->and($task->conversation->context_type)->toBe('StaffTask')
        ->and($task->conversation->participants()->count())->toBe(1)
        ->and($task->conversation->messages()->count())->toBe(1);

    expect(\App\Actions\Tasks\Json\GetStaffTasks::run($requester, 'requested')->pluck('id')->all())->toBe([$task->id])
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine'))->toBeEmpty();

    $task = \App\Actions\Tasks\UpdateStaffTask::run($task, $worker, ['status' => 'in_progress']);

    expect($task->assignee_id)->toBe($worker->id)
        ->and($task->started_at)->not->toBeNull()
        ->and($task->conversation->hasParticipant($worker))->toBeTrue()
        ->and($task->conversation->messages()->count())->toBe(2)
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine')->pluck('id')->all())->toBe([$task->id]);

    $task = \App\Actions\Tasks\UpdateStaffTask::run($task, $worker, ['status' => 'done']);

    expect($task->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::DONE)
        ->and($task->closed_at)->not->toBeNull()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine'))->toBeEmpty()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine', true)->pluck('id')->all())->toBe([$task->id]);
});

test('staff task raised from a chat message links back to the source thread', function () {
    $requester = $this->user;
    $colleague = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($requester, ['user_ids' => [$colleague->id]]);
    $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $colleague, ['body' => 'please update the homepage banners']);

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => $message->body, 'assignee_id' => $colleague->id, 'source_message_id' => $message->id]);

    expect($task->assignee_id)->toBe($colleague->id)
        ->and($task->assigned_at)->not->toBeNull()
        ->and($task->staff_conversation_id)->not->toBe($conversation->id)
        ->and($task->conversation->participants()->count())->toBe(2)
        ->and($conversation->messages()->count())->toBe(2)
        ->and($conversation->messages()->latest('id')->first()->body)->toContain($task->reference);

    $cancelled = \App\Actions\Tasks\UpdateStaffTask::run($task, $colleague, ['status' => 'cancelled', 'note' => 'banner already updated']);

    expect($cancelled->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::CANCELLED)
        ->and($cancelled->conversation->messages()->latest('id')->first()->body)->toContain('banner already updated');
});

test('staff tasks page and options respond', function () {
    actingAs($this->user);

    get(route('grp.tasks.index'))->assertOk();
    get(route('grp.tasks.list_all'))->assertOk();
    get(route('grp.tasks.board'))->assertOk();
    get(route('grp.tasks.reports'))->assertOk();
    get(route('grp.tasks.reports', ['created' => '1w']))->assertOk();
    getJson(route('grp.tasks.options'))->assertOk()->assertJsonStructure(['departments', 'my_departments', 'priorities', 'statuses']);
    getJson(route('grp.tasks.list', ['view' => 'department']))->assertOk();
});

test('stale staff task nudges its assignee once per window', function () {
    $requester = $this->user;
    $assignee  = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Quiet task', 'assignee_id' => $assignee->id]);

    expect(\App\Actions\Tasks\NudgeStaleStaffTasks::run(48))->toBe(0);

    $task->conversation->update(['last_message_at' => now()->subHours(50)]);

    expect(\App\Actions\Tasks\NudgeStaleStaffTasks::run(48))->toBe(1)
        ->and(\App\Actions\Tasks\NudgeStaleStaffTasks::run(48))->toBe(0)
        ->and($assignee->notifications()->count())->toBe(1)
        ->and($task->fresh()->data['nudged_at'])->not->toBeNull();
});

test('engineers and qa see staff tasks but cannot be assigned one', function () {
    $engineer = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'gp-hd', 'scopes' => []]]]))->getUser();

    expect(\App\Models\Tasks\StaffTask::canBeAssigned($engineer))->toBeFalse()
        ->and(\App\Models\Tasks\StaffTask::canBeAssigned($this->user))->toBeTrue()
        ->and(collect(\App\Models\Tasks\StaffTask::departments($this->organisation->group_id))->pluck('value'))->not->toContain('help-desk');

    actingAs($engineer);
    get(route('grp.tasks.index'))->assertOk();
    get(route('grp.tasks.board'))->assertOk();

    actingAs($this->user);
    \Pest\Laravel\postJson(route('grp.tasks.store'), ['subject' => 'Fix the bug', 'assignee_id' => $engineer->id])->assertUnprocessable();
});

test('inbound gmail message becomes an email chat session and the agent reply goes back through gmail', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $webUser = StoreWebUser::make()->action($this->customer, array_merge(WebUser::factory()->definition(), ['email' => 'buyer@example.com']));

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                           => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/m1*'   => \Illuminate\Support\Facades\Http::response([
            'id'       => 'm1',
            'threadId' => 't1',
            'payload'  => [
                'mimeType' => 'text/plain',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Buyer Person <buyer@example.com>'],
                    ['name' => 'Subject', 'value' => 'Where is my order?'],
                    ['name' => 'Message-ID', 'value' => '<abc@example.com>'],
                ],
                'body'     => ['data' => rtrim(strtr(base64_encode("Hello, any news?\n\nOn Mon, Bob wrote:\n> old stuff"), '+/', '-_'), '=')],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'         => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/imported']]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send'  => \Illuminate\Support\Facades\Http::response(['id' => 'sent1']),
        'gmail.googleapis.com/*'                                => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'm1');

    expect($message)->toBeInstanceOf(ChatMessage::class)
        ->and($message->message_text)->toBe('Hello, any news?')
        ->and($message->sender_type)->toBe(ChatSenderTypeEnum::USER)
        ->and($message->sender_id)->toBe($webUser->id)
        ->and(Arr::get($message->metadata, 'gmail_message_id'))->toBe('m1');

    $session = $message->chatSession->fresh();
    expect($session->channel)->toBe(\App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL)
        ->and($session->web_user_id)->toBe($webUser->id)
        ->and(Arr::get($session->metadata, 'gmail_thread_id'))->toBe('t1')
        ->and(Arr::get($session->metadata, 'gmail_last_header_message_id'))->toBe('<abc@example.com>');

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'm1'))->toBeNull();

    $agentUser = createAdminGuest($this->organisation->group)->getUser();
    $agent     = ChatAgent::updateOrCreate(['user_id' => $agentUser->id], ['max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => false, 'is_available' => false, 'current_chat_count' => 0, 'signature' => "Kind regards,\nSig Agent"]);
    $reply = $session->messages()->create([
        'message_text' => 'Shipped today',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
        'sender_id'    => $agent->id,
    ]);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply);

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return $request['threadId'] === 't1'
            && str_contains($raw, 'To: Buyer Person <buyer@example.com>')
            && str_contains($raw, 'In-Reply-To: <abc@example.com>')
            && str_contains($raw, 'Subject: Re: Where is my order?')
            && str_contains(base64_decode(substr($raw, strpos($raw, "\r\n\r\n") + 4)), "Shipped today\n\nKind regards,\nSig Agent");
    });
    expect(Arr::get($reply->fresh()->metadata, 'gmail_message_id'))->toBe('sent1');

    $fileReply = $session->messages()->create([
        'message_text' => 'Invoice attached',
        'message_type' => ChatMessageTypeEnum::FILE,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
        'sender_id'    => $agent->id,
    ]);
    $invoicePath = tempnam(sys_get_temp_dir(), 'chat').'.txt';
    file_put_contents($invoicePath, 'invoice body');
    $media = \App\Actions\Helpers\Media\StoreMediaFromFile::run($fileReply, [
        'path'         => $invoicePath,
        'originalName' => 'invoice.txt',
        'extension'    => 'txt',
        'checksum'     => md5_file($invoicePath),
    ], 'chat_attachments', 'file');
    $fileReply->update(['media_id' => $media->id]);
    $secondPath = tempnam(sys_get_temp_dir(), 'chat').'.txt';
    file_put_contents($secondPath, 'packing list');
    \App\Actions\Helpers\Media\StoreMediaFromFile::run($fileReply, [
        'path'         => $secondPath,
        'originalName' => 'packing.txt',
        'extension'    => 'txt',
        'checksum'     => md5_file($secondPath),
    ], 'chat_attachments', 'file');

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($fileReply->fresh());

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return str_contains($raw, 'Content-Type: multipart/mixed')
            && str_contains($raw, 'Content-Disposition: attachment; filename="invoice.txt"')
            && str_contains($raw, trim(chunk_split(base64_encode('invoice body'))))
            && str_contains($raw, 'Content-Disposition: attachment; filename="packing.txt"');
    });
});

test('inbound gmail from an unknown sender becomes a guest email session and a spammed sender is skipped next time', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class]);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '1'];
    $this->shop->update(['settings' => $settings]);

    $gmailMessage = fn (string $id) => \Illuminate\Support\Facades\Http::response([
        'id' => $id, 'threadId' => 't-'.$id,
        'payload' => ['mimeType' => 'text/plain', 'headers' => [['name' => 'From', 'value' => 'Stranger <stranger@example.com>'], ['name' => 'Subject', 'value' => 'How do I register?'], ['name' => 'Message-ID', 'value' => '<'.$id.'@example.com>']], 'body' => ['data' => rtrim(strtr(base64_encode('I would like to open an account'), '+/', '-_'), '=')]],
    ]);
    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/g1*'  => $gmailMessage('g1'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/g2*'  => $gmailMessage('g2'),
        'gmail.googleapis.com/gmail/v1/users/me/labels'        => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/unmatched'], ['id' => 'L2', 'name' => 'aiku/spam']]]),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'g1');
    $session = $message->chatSession->fresh();

    expect($message->sender_type)->toBe(ChatSenderTypeEnum::GUEST)
        ->and($session->web_user_id)->toBeNull()
        ->and($session->channel)->toBe(\App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL)
        ->and(Arr::get($session->metadata, 'email'))->toBe('stranger@example.com')
        ->and(Arr::get($session->metadata, 'name'))->toBe('Stranger');

    $agentUser = createAdminGuest($this->organisation->group)->getUser();
    $agent     = ChatAgent::firstOrCreate(['user_id' => $agentUser->id], ['max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => false, 'is_available' => false, 'current_chat_count' => 0]);
    \App\Actions\Chat\ChatSession\MarkChatSessionAsSpam::run($session, $agent);

    expect(Arr::get($this->shop->fresh()->settings, 'gmail.blocked_senders'))->toBe(['stranger@example.com'])
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop->fresh(), 'g2'))->toBeNull();
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), 'messages/g2/modify') && $request['addLabelIds'] === ['L2']);
});

test('a campaign send closes the promo-only session but leaves one an agent is handling open', function () {
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $agent   = ChatAgent::where('user_id', $this->user->id)->first()
        ?? StoreChatAgent::make()->handle(['user_id' => $this->user->id]);

    $makeSession = fn (string $phone) => MetaChatSession::create([
        'ulid'            => (string)Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => $phone,
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $promoOnly = $makeSession('+628444555777');
    $handled   = $makeSession('+628444555778');
    $handled->assignments()->create([
        'meta_channel_id' => $channel->id,
        'chat_agent_id'   => $agent->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    $sender = SendWhatsappDeliveryChannel::make();
    $sender->parkSession($promoOnly);
    $sender->parkSession($handled);

    expect($promoOnly->fresh()->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($promoOnly->fresh()->closed_by)->toBe(ChatSessionClosedByTypeEnum::SYSTEM)
        ->and($handled->fresh()->status)->toBe(ChatSessionStatusEnum::ACTIVE);
});

test('supervisor writes in any staff task thread without joining and can subscribe', function () {
    $requester  = $this->user;
    $colleague  = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $supervisor = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $supervisorPosition = \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $supervisor->group_id)->where('code', 'like', '%-m')->where('department', '!=', \App\Models\Tasks\StaffTask::EXCLUDED_DEPARTMENT)->value('id');
    \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert(['user_id' => $supervisor->id, 'job_position_id' => $supervisorPosition, 'group_id' => $supervisor->group_id, 'scopes' => '{}']);

    $task         = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Supervised task', 'assignee_id' => $requester->id]);
    $conversation = $task->conversation;

    actingAs($colleague);
    \Pest\Laravel\postJson(route('grp.chat.staff.conversations.messages.store', $conversation->ulid), ['body' => 'hi'])->assertForbidden();
    getJson(route('grp.tasks.conversation', $task->reference))->assertForbidden();

    actingAs($supervisor);
    getJson(route('grp.tasks.conversation', $task->reference))->assertOk()->assertJsonPath('data.ulid', $conversation->ulid);
    getJson(route('grp.chat.staff.conversations.messages.index', $conversation->ulid))->assertOk();
    \Pest\Laravel\postJson(route('grp.chat.staff.conversations.messages.store', $conversation->ulid), ['body' => 'please prioritise'])->assertCreated();

    expect($conversation->hasParticipant($supervisor))->toBeFalse();

    \Pest\Laravel\postJson(route('grp.tasks.subscription.toggle', $task->reference))->assertOk()->assertJsonPath('data.is_subscribed', true);
    expect($conversation->hasParticipant($supervisor))->toBeTrue();

    \Pest\Laravel\postJson(route('grp.tasks.subscription.toggle', $task->reference))->assertOk()->assertJsonPath('data.is_subscribed', false);
    expect($conversation->hasParticipant($supervisor))->toBeFalse();

    actingAs($requester);
    \Pest\Laravel\postJson(route('grp.tasks.subscription.toggle', $task->reference))->assertOk();
    expect($conversation->hasParticipant($requester))->toBeTrue();
});

test('staff task collaborators join the thread and see the task as theirs', function () {
    $requester = $this->user;
    $owner     = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $helper    = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Two person job', 'assignee_id' => $owner->id, 'collaborator_ids' => [$helper->id, $owner->id]]);

    expect($task->collaborators()->pluck('users.id')->all())->toBe([$helper->id])
        ->and($task->conversation->hasParticipant($helper))->toBeTrue()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($helper, 'mine')->pluck('id')->all())->toBe([$task->id]);

    actingAs($requester);
    \Pest\Laravel\patchJson(route('grp.tasks.collaborators.update', $task->reference), ['collaborator_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.collaborators', []);

    expect($task->conversation->hasParticipant($helper))->toBeFalse()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($helper, 'mine'))->toBeEmpty();

    \App\Actions\Tasks\SyncStaffTaskCollaborators::run($task, [$helper->id], $requester);
    \App\Actions\Tasks\UpdateStaffTask::run($task->fresh(), $requester, ['assignee_id' => $helper->id]);

    expect($task->collaborators()->count())->toBe(0);
});

test('staff task reports share a task between its assignee and collaborators', function () {
    $requester = $this->user;
    $people    = collect(range(1, 3))->map(fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser());

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Three person job', 'assignee_id' => $people[0]->id, 'collaborator_ids' => [$people[1]->id, $people[2]->id]]);

    $rows = collect(\App\Actions\Tasks\UI\ShowStaffTasksReports::make()->handle($this->organisation->group, '1w')['by_assignee'])->keyBy('name');

    foreach ($people as $person) {
        expect($rows[$person->contact_name ?: $person->username]['created'])->toBe(0.33);
    }
});

test('inbound guest gmail attachments wait in gmail until an agent replies, then are all saved on the email chat message', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                                         => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/a1/attachments/att1' => \Illuminate\Support\Facades\Http::response(['data' => $encode('%PDF-1.4 invoice')]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/a1*'                 => \Illuminate\Support\Facades\Http::response([
            'id'       => 'a1',
            'threadId' => 'ta1',
            'payload'  => [
                'mimeType' => 'multipart/mixed',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Stranger <stranger@example.com>'],
                    ['name' => 'Subject', 'value' => 'Damaged goods'],
                ],
                'parts'    => [
                    ['mimeType' => 'text/plain', 'filename' => '', 'body' => ['data' => $encode('See attached')]],
                    ['mimeType' => 'application/pdf', 'filename' => 'invoice.pdf', 'body' => ['attachmentId' => 'att1']],
                    ['mimeType' => 'image/png', 'filename' => 'photo.png', 'body' => ['data' => $encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))]],
                    ['mimeType' => 'image/png', 'filename' => 'logo.png', 'headers' => [['name' => 'Content-Disposition', 'value' => 'inline; filename="logo.png"']], 'body' => ['data' => $encode('x')]],
                ],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'                       => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                                              => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'a1');

    // A stranger's pictures come in at once: an email whose images are missing reads as broken,
    // and staff compare this screen against Gmail. Their other files still wait for a reply,
    // and the signature logo is never imported at all.
    expect($message->attachedFiles()->pluck('name')->all())->toBe(['photo.png'])
        ->and(Arr::get($message->metadata, 'gmail_pending_attachments'))->toBe(1);

    \App\Actions\Chat\ChatSession\SendChatMessage::run($message->chatSession, [
        'message_text' => 'Sorry to hear that',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::AGENT->value,
    ]);

    $files = $message->fresh()->attachedFiles();

    expect($message->chatSession->messages()->where('sender_type', ChatSenderTypeEnum::GUEST)->count())->toBe(1)
        ->and(Arr::get($message->fresh()->metadata, 'gmail_pending_attachments'))->toBeNull()
        ->and($message->fresh()->message_text)->toBe('See attached')
        ->and($message->fresh()->message_type)->toBe(ChatMessageTypeEnum::FILE)
        // The photograph is not fetched twice: the reply only brings what was waiting.
        ->and($files->pluck('name')->all())->toBe(['photo.png', 'invoice.pdf'])
        ->and($files->pluck('collection_name')->all())->toBe(['chat_images', 'chat_attachments'])
        ->and(stream_get_contents($files[1]->stream()))->toBe('%PDF-1.4 invoice');

    $resource = \App\Http\Resources\CRM\Livechat\ChatMessageResource::make($message->fresh())->resolve();
    expect($resource['attachments'])->toHaveCount(2)
        ->and($resource['attachments'][0]['is_image'])->toBeTrue()
        ->and($resource['attachments'][1]['file_name'])->toBe('invoice.pdf');
});

test('chat media older than the retention window moves to the archive database and is still downloadable', function () {
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => 'archive'])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists archive');

    $session = ChatSession::first();
    $message = $session->messages()->create([
        'message_text' => 'Old invoice',
        'message_type' => ChatMessageTypeEnum::FILE,
        'sender_type'  => ChatSenderTypeEnum::GUEST,
    ]);

    $storeFile = function (string $name, string $contents) use ($message) {
        $path = tempnam(sys_get_temp_dir(), 'chat').'.txt';
        file_put_contents($path, $contents);

        return \App\Actions\Helpers\Media\StoreMediaFromFile::run($message, [
            'path'         => $path,
            'originalName' => $name,
            'extension'    => 'txt',
            'checksum'     => md5_file($path),
        ], 'chat_attachments', 'file');
    };

    $old   = $storeFile('old.txt', 'archived invoice body');
    $fresh = $storeFile('fresh.txt', 'fresh invoice body');
    DB::table('media')->where('id', $old->id)->update(['created_at' => now()->subDays(config('archive.chat_media_retention_days') + 1)]);

    expect(\App\Actions\Chat\ChatSession\ArchiveChatMedia::run())->toBe(1);

    $old   = $old->fresh();
    $fresh = $fresh->fresh();

    expect($old->getCustomProperty('archived_at'))->not->toBeNull()
        ->and(is_file($old->getPath()))->toBeFalse()
        ->and($fresh->getCustomProperty('archived_at'))->toBeNull()
        ->and(is_file($fresh->getPath()))->toBeTrue()
        ->and(\App\Actions\Chat\ChatSession\GetChatMediaContents::run($old))->toBe('archived invoice body')
        ->and(DownloadChatAttachment::make()->handle($old->ulid)->getContent())->toBe('archived invoice body')
        ->and(\App\Actions\Chat\ChatSession\ArchiveChatMedia::run())->toBe(0);

    $resource = \App\Http\Resources\CRM\Livechat\ChatMessageResource::make($message->fresh())->resolve();
    expect(collect($resource['attachments'])->firstWhere('id', $old->id)['is_archived'])->toBeTrue();
});

test('automated mail from senders that match no customer is labelled filtered and never becomes a chat session', function () {
    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '1'];
    $this->shop->update(['settings' => $settings]);

    $gmailMessage = fn (string $id, string $from, string $subject) => \Illuminate\Support\Facades\Http::response([
        'id'       => $id,
        'threadId' => 't'.$id,
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => [['name' => 'From', 'value' => $from], ['name' => 'Subject', 'value' => $subject]],
            'body'     => ['data' => rtrim(strtr(base64_encode('machine text'), '+/', '-_'), '=')],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/f1*' => $gmailMessage('f1', 'Mail Delivery <mailer-daemon@googlemail.com>', 'Undelivered'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/f2*' => $gmailMessage('f2', 'reports@dmarc.example', 'Report Domain: shop.test Submitter: example'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/f3*' => $gmailMessage('f3', 'Alerts <no-reply@accounts.example>', 'Security alert'),
        'gmail.googleapis.com/gmail/v1/users/me/labels'        => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'LF', 'name' => 'aiku/filtered']]]),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $sessionsBefore = ChatSession::count();

    foreach (['f1', 'f2', 'f3'] as $id) {
        expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, $id))->toBeNull();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), "messages/$id/modify") && $request['addLabelIds'] === ['LF']);
    }

    expect(ChatSession::count())->toBe($sessionsBefore);
});

test('a chat agent can only act on sessions of the shops it is assigned to', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $strangerUser = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $stranger     = ChatAgent::create([
        'user_id'              => $strangerUser->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $this->actingAs($strangerUser);
    expect(CloseChatSession::make()->getCurrentAgent($session))->toBeNull()
        ->and($stranger->isAssignedToShop($this->shop->id, $this->shop->organisation_id))->toBeFalse();

    ShopHasChatAgent::create([
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'chat_agent_id'   => $stranger->id,
    ]);

    expect(CloseChatSession::make()->getCurrentAgent($session)->id)->toBe($stranger->id);
});

test('an org wide chat agent can act on any shop of that organisation', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    ShopHasChatAgent::create([
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => null,
        'chat_agent_id'   => $agent->id,
    ]);

    $this->actingAs($user);

    expect(CloseChatSession::make()->getCurrentAgent($session)->id)->toBe($agent->id);
});

test('customer service permission makes an agent, creating the profile on first use', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $csUser = User::factory()->create(['group_id' => $this->organisation->group_id]);
    setPermissionsTeamId($this->user->group_id);
    $csUser->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    expect(ChatAgent::where('user_id', $csUser->id)->exists())->toBeFalse();

    $this->actingAs($csUser);
    $agent = CloseChatSession::make()->getCurrentAgent($session);

    expect($agent)->toBeInstanceOf(ChatAgent::class)
        ->and($agent->user_id)->toBe($csUser->id)
        ->and($agent->is_online)->toBeFalse()
        ->and(ChatAgent::where('user_id', $csUser->id)->count())->toBe(1);

    expect(CloseChatSession::make()->getCurrentAgent($session)->id)->toBe($agent->id);
});

test('customer service viewer gets no write access to chat', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $viewer = User::factory()->create(['group_id' => $this->organisation->group_id]);
    setPermissionsTeamId($this->user->group_id);
    $viewer->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_VIEWER->value, $this->shop));

    $this->actingAs($viewer);

    expect(CloseChatSession::make()->getCurrentAgent($session))->toBeNull()
        ->and(ChatAgent::where('user_id', $viewer->id)->exists())->toBeFalse();
});

test('a departed staff member is never a chat agent', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $leaver = User::factory()->create(['group_id' => $this->organisation->group_id]);
    setPermissionsTeamId($this->user->group_id);
    $leaver->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $leaver->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
    ]);

    ShopHasChatAgent::create([
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'chat_agent_id'   => $agent->id,
    ]);

    $this->actingAs($leaver);
    expect(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull()
        ->and(ChatAgent::available()->whereKey($agent->id)->exists())->toBeTrue();

    $assignment = $session->assignments()->create([
        'chat_agent_id' => $agent->id,
        'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_at'   => now(),
    ]);

    $leaver->update(['status' => false]);

    expect(CloseChatSession::make()->getCurrentAgent($session->fresh()))->toBeNull()
        ->and(ChatAgent::available()->whereKey($agent->id)->exists())->toBeFalse();

    // Deactivating hands the conversations back even though the roles outlive the account.
    $result = \App\Actions\Chat\Agent\RevokeChatAgentAccess::run(ChatAgent::withTrashed()->find($agent->id));

    expect($result['released'])->toBe(1)
        ->and($result['suspended'])->toBeTrue()
        ->and($assignment->fresh()->status)->toBe(ChatAssignmentStatusEnum::RESOLVED)
        ->and($session->fresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and(ChatAgent::find($agent->id))->toBeNull();
});

test('working hours follow the agent contract, then the shop, then a plain weekday', function () {
    $tz = \App\Models\Helpers\Timezone::where('name', 'Europe/London')->first();
    $this->shop->update(['timezone_id' => $tz->id, 'opening_hours' => []]);
    $shop = $this->shop->fresh();

    $employee = Employee::factory()->create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'working_hours'   => ['data' => [
            // Monday to Thursday only: this agent does not work Fridays.
            '1' => ['s' => '08:00', 'e' => '16:00', 'b' => [['s' => '12:00', 'e' => '12:30']]],
            '2' => ['s' => '08:00', 'e' => '16:00', 'b' => []],
            '3' => ['s' => '08:00', 'e' => '16:00', 'b' => []],
            '4' => ['s' => '08:00', 'e' => '16:00', 'b' => []],
        ]],
    ]);

    $at = fn (string $when) => \Illuminate\Support\Carbon::parse($when, 'Europe/London');
    $within = fn (string $when) => IsWithinWorkingHours::run($shop, $at($when), $employee);

    expect($within('2026-09-21 09:00'))->toBeTrue()          // Monday morning
        ->and($within('2026-09-21 07:59'))->toBeFalse()       // before the shift
        ->and($within('2026-09-21 16:00'))->toBeFalse()       // end is exclusive
        ->and($within('2026-09-21 12:15'))->toBeFalse()       // on a break
        ->and($within('2026-09-25 09:00'))->toBeFalse()       // Friday: not contracted
        ->and($within('2026-09-26 09:00'))->toBeFalse();      // Saturday

    // No contract: the plain weekday fallback answers instead.
    expect(IsWithinWorkingHours::run($shop, $at('2026-09-25 09:30')))->toBeTrue()
        ->and(IsWithinWorkingHours::run($shop, $at('2026-09-25 07:30')))->toBeFalse()
        ->and(IsWithinWorkingHours::run($shop, $at('2026-09-25 16:30')))->toBeFalse();

    // A public holiday of the organisation is never working time, contract or not.
    $this->organisation->holidays()->create([
        'group_id' => $this->organisation->group_id,
        'type'     => \App\Enums\HumanResources\Holiday\HolidayTypeEnum::PUBLIC->value,
        'year'     => 2026,
        'label'    => 'Test bank holiday',
        'from'     => '2026-09-21',
        'to'       => '2026-09-21',
    ]);

    expect($within('2026-09-21 09:00'))->toBeFalse();
});

test('losing customer service releases the chats and suspends the agent', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    setPermissionsTeamId($this->user->group_id);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 1,
    ]);

    ShopHasChatAgent::create([
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'chat_agent_id'   => $agent->id,
    ]);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $assignment = $session->assignments()->create([
        'chat_agent_id' => $agent->id,
        'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_at'   => now(),
    ]);

    // While the position stands, nothing is taken away.
    expect(\App\Actions\Chat\Agent\RevokeChatAgentAccess::run($agent))
        ->toMatchArray(['released' => 0, 'shops_removed' => 0, 'suspended' => false]);

    $user->removeRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    \App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($user);

    $result = \App\Actions\Chat\Agent\RevokeChatAgentAccess::run($agent->fresh());

    expect($result['released'])->toBe(1)
        ->and($result['shops_removed'])->toBe(1)
        ->and($result['suspended'])->toBeTrue()
        // The chat goes back to the shop queue instead of sitting in a name nobody can act on.
        ->and($session->fresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($assignment->fresh()->status)->toBe(ChatAssignmentStatusEnum::RESOLVED)
        ->and(ChatAgent::find($agent->id))->toBeNull()
        ->and(ChatAgent::withTrashed()->find($agent->id)->trashed())->toBeTrue();
});

test('a shop administrator manages its chats without being an agent', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    setPermissionsTeamId($this->user->group_id);

    // A shop administrator holds CRM, and used to inherit chat with it.
    $admin = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $admin->assignRole(RolesEnum::getRoleName(RolesEnum::SHOP_ADMIN->value, $this->shop));

    // A shop administrator manages its chats without ever being one of its agents.
    expect($admin->authTo(['chat-m.'.$this->shop->id]))->toBeTrue()
        ->and($admin->authTo(['chat.'.$this->shop->id]))->toBeFalse();

    $this->actingAs($admin);
    expect(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull()
        ->and(ChatAgent::findAvailableAgent(shopId: $this->shop->id)?->user_id)->not->toBe($admin->id);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $this->actingAs($clerk);
    expect($clerk->authTo(['chat.'.$this->shop->id]))->toBeTrue()
        ->and(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();
});

test('a customer service supervisor supervises chat without being an agent', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    setPermissionsTeamId($this->user->group_id);

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_SUPERVISOR->value, $this->shop));

    expect($supervisor->authTo(['chat-m.'.$this->shop->id]))->toBeTrue()
        ->and($supervisor->authTo(['chat.'.$this->shop->id]))->toBeFalse();

    $this->actingAs($supervisor);

    // May take over and write, but is never one of the shop's agents.
    expect(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();

    // Adding the worker position is what makes somebody an agent, deliberately.
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    \App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($supervisor);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    expect($supervisor->fresh()->authTo(['chat.'.$this->shop->id]))->toBeTrue();
});

test('an organisation administrator manages chat on every shop, including one opened later', function () {
    setPermissionsTeamId($this->user->group_id);

    $orgAdmin = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $orgAdmin->assignRole(RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $this->organisation));

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $this->actingAs($orgAdmin);

    // Manages without holding a single per shop permission, and without being an agent.
    expect($orgAdmin->authTo(['chat.'.$this->shop->id]))->toBeFalse()
        ->and($orgAdmin->authTo(['chat-m.'.$this->shop->id]))->toBeFalse()
        ->and(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();

    // A shop opened after the fact is covered too: the grant sits on the organisation.
    $newShop = createShop($this->organisation)[2] ?? null;
    if ($newShop) {
        $laterSession = ChatSession::create([
            'ulid'             => (string) \Illuminate\Support\Str::ulid(),
            'shop_id'          => $newShop->id,
            'language_id'      => 68,
            'status'           => ChatSessionStatusEnum::ACTIVE->value,
            'priority'         => ChatPriorityEnum::NORMAL->value,
            'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
        ]);

        expect(CloseChatSession::make()->getCurrentAgent($laterSession))->not->toBeNull();
    }
});

test('a fulfilment shop staffs chat from its own positions', function () {
    $fulfilment     = createFulfilment($this->organisation);
    $fulfilmentShop = $fulfilment->shop;
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $fulfilmentShop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    // Office clerk answers the chats: an agent, routed like any other.
    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::FULFILMENT_SHOP_CLERK->value, $fulfilment));
    $this->actingAs($clerk);
    expect($clerk->authTo(['fulfilment-chat.'.$fulfilment->id]))->toBeTrue()
        ->and(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();

    // Supervisor manages without being routed.
    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::FULFILMENT_SHOP_SUPERVISOR->value, $fulfilment));
    $this->actingAs($supervisor);
    expect($supervisor->authTo(['fulfilment-chat-m.'.$fulfilment->id]))->toBeTrue()
        ->and($supervisor->authTo(['fulfilment-chat.'.$fulfilment->id]))->toBeFalse()
        ->and(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();

    // Warehouse staff stay out of it.
    $warehouse = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $warehouse->assignRole(RolesEnum::getRoleName(RolesEnum::FULFILMENT_WAREHOUSE_WORKER->value, $fulfilment));
    $this->actingAs($warehouse);
    expect(CloseChatSession::make()->getCurrentAgent($session))->toBeNull();
});

test('an external shop has no chat permissions at all', function () {
    $external = \App\Models\Catalogue\Shop::factory()->make()->toArray();
    $external['type'] = \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL->value;
    $externalShop     = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, $external);

    $values = \App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::getAllValues($externalShop);

    expect(collect($values)->filter(fn ($name) => str_starts_with($name, 'chat')))->toBeEmpty()
        ->and(collect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::getAllValues($this->shop))
            ->filter(fn ($name) => str_starts_with($name, 'chat')))->not->toBeEmpty();

    // Holding the customer service position on it therefore grants nothing.
    setPermissionsTeamId($this->user->group_id);
    $worker = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $worker->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $externalShop));

    expect($worker->authTo(['chat.'.$externalShop->id]))->toBeFalse();
});

test('regaining the position brings a suspended agent profile back', function () {
    setPermissionsTeamId($this->user->group_id);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    // Losing it suspends the profile.
    $user->removeRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    \App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($user);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    expect(\App\Actions\Chat\Agent\RevokeChatAgentAccess::run($agent->fresh())['suspended'])->toBeTrue()
        ->and(ChatAgent::find($agent->id))->toBeNull();

    // Getting it back restores it, rather than waiting for the next inbox visit.
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    \App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($user);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $result = \App\Actions\Chat\Agent\RevokeChatAgentAccess::run(ChatAgent::withTrashed()->find($agent->id));

    expect($result['restored'])->toBeTrue()
        ->and($result['suspended'])->toBeFalse()
        ->and(ChatAgent::find($agent->id))->not->toBeNull()
        // The same profile, not a second one.
        ->and(ChatAgent::withTrashed()->where('user_id', $user->id)->count())->toBe(1);
});

test('giving the login back brings the agent profile with it', function () {
    setPermissionsTeamId($this->user->group_id);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    \App\Actions\SysAdmin\User\UpdateUser::make()->action($user, ['status' => false]);
    expect(ChatAgent::find($agent->id))->toBeNull();

    \App\Actions\SysAdmin\User\UpdateUser::make()->action($user->fresh(), ['status' => true]);

    expect(ChatAgent::find($agent->id))->not->toBeNull()
        ->and(ChatAgent::withTrashed()->where('user_id', $user->id)->count())->toBe(1);
});

test('a conversation cannot be destroyed, and trashing one is written into its record', function () {
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::WAITING->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    // Nothing anywhere offers to destroy a conversation.
    expect(collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains((string) $route->getName(), 'sessions.force_delete'))
        ->isEmpty())->toBeTrue();

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $this->actingAs($clerk);

    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);
    TrashChatSession::make()->handle($session, $agent->id);

    // Out of sight, still there, and the move is on the record with a name against it.
    $trashed = ChatSession::withTrashed()->find($session->id);
    expect($trashed->trashed())->toBeTrue()
        ->and(ChatEvent::where('chat_session_id', $session->id)
            ->where('event_type', ChatEventTypeEnum::TRASH->value)
            ->value('payload')['user_id'] ?? null)->toBe($clerk->id);

    RestoreChatSession::make()->handle($trashed, $agent->id);

    expect(ChatSession::find($session->id))->not->toBeNull()
        ->and(ChatEvent::where('chat_session_id', $session->id)
            ->where('event_type', ChatEventTypeEnum::RESTORE->value)
            ->exists())->toBeTrue();
});

test('an agent takes back their own message: the customer loses it, we keep it', function () {
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);

    ChatAssignment::create([
        'chat_session_id' => $session->id,
        'chat_agent_id'   => $agent->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_at'     => now(),
    ]);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::AGENT->value,
        'sender_id'       => $agent->id,
        'message_text'    => 'sent to the wrong person',
    ]);

    $this->actingAs($clerk);
    RetractChatMessage::make()->handle(
        $session,
        $message,
        $agent,
        ChatRetractionReasonEnum::WRONG_CONVERSATION,
        'pasted from the other chat'
    );

    $public = GetChatMessages::make()->handle($session, [])->firstWhere('id', $message->id);
    $staff  = GetChatMessages::make()->handle($session, [], true)->firstWhere('id', $message->id);

    // The customer is told something was withdrawn and why, and never reads the words.
    expect($public)->not->toBeNull()
        ->and($public->message_text)->toBeNull()
        ->and($staff->message_text)->toBe('sent to the wrong person')
        ->and(ChatMessage::withTrashed()->find($message->id)->metadata['retraction_reason'])
        ->toBe(ChatRetractionReasonEnum::WRONG_CONVERSATION->value);

    // The note we keep for ourselves is never put on the wire, to either side.
    $rendered = (new \App\Http\Resources\CRM\Livechat\ChatMessageResource($staff))->resolve();
    expect($rendered['retraction_reason'])->toBe(ChatRetractionReasonEnum::WRONG_CONVERSATION->label())
        ->and(json_encode($rendered))->not->toContain('pasted from the other chat');

    // Somebody else's message is not theirs to take back.
    $other = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'the customer wrote this',
    ]);

    expect(fn () => RetractChatMessage::make()->handle($session, $other, $agent, ChatRetractionReasonEnum::EXPLAINING))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('an agent strikes a card number out of a message everywhere it was stored', function () {
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'my card is 4111111111111111 please charge it',
        'original_text'   => 'mi tarjeta es 4111111111111111 por favor',
    ]);

    ChatMessageTranslation::create([
        'chat_message_id'   => $message->id,
        'target_language_id' => 68,
        'translated_text'   => 'my card is 4111111111111111 please',
    ]);

    $this->actingAs($clerk);
    RedactChatMessage::make()->handle($session, $message->fresh(), $agent, '4111111111111111');

    $redacted = $message->fresh();
    $mask = str_repeat(RedactChatMessage::MASK, 16);

    // Gone from the message, from what it was translated from, and from the translation.
    expect($redacted->message_text)->toBe("my card is $mask please charge it")
        ->and($redacted->original_text)->toBe("mi tarjeta es $mask por favor")
        ->and($redacted->translations()->first()->translated_text)->toBe("my card is $mask please")
        ->and($redacted->metadata['redacted_by_user_id'])->toBe($clerk->id);

    // The record says it happened and never repeats what was taken out.
    $event = ChatEvent::where('chat_session_id', $session->id)
        ->where('event_type', ChatEventTypeEnum::REDACT->value)
        ->first();

    expect($event->payload['occurrences'])->toBe(3)
        ->and(json_encode($event->payload))->not->toContain('4111111111111111');

    // One careless letter would be struck out of the whole message, with no way back.
    expect(fn () => RedactChatMessage::make()->handle($session, $redacted, $agent, 'my'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Redaction only removes: text that is not there cannot be used to rewrite the message.
    expect(fn () => RedactChatMessage::make()->handle($session, $redacted, $agent, 'never written'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('an agent removes a photograph of a card from a message and from the archive', function (bool $hasArchiveCopy) {
    $archiveSchema = $hasArchiveCopy ? 'chat_redaction_archived' : 'chat_redaction_unarchived';
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => $archiveSchema])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists '.$archiveSchema);

    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'here is a picture of my card',
    ]);

    // Nothing attached: there is nothing to remove, and we say so rather than pretending.
    $this->actingAs($clerk);
    expect(fn () => RedactChatMessage::make()->handleAttachment($session, $message, $agent))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $cardPath = tempnam(sys_get_temp_dir(), 'chat').'.txt';
    file_put_contents($cardPath, 'a photograph of a card');
    $media = \App\Actions\Helpers\Media\StoreMediaFromFile::run($message, [
        'path'         => $cardPath,
        'originalName' => 'card.txt',
        'extension'    => 'txt',
        'checksum'     => md5_file($cardPath),
    ], 'chat_attachments', 'file');
    $message->update(['media_id' => $media->id]);

    $mediaId  = $media->id;
    $diskPath = $media->getPath();

    expect(is_file($diskPath))->toBeTrue();

    $archiveTable = \App\Actions\Chat\ChatSession\ArchiveChatMedia::ARCHIVE_TABLE;
    if ($hasArchiveCopy) {
        \Illuminate\Support\Facades\Schema::connection('archive')->create($archiveTable, function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedBigInteger('media_id')->primary();
            $table->binary('contents');
            $table->string('checksum', 32);
            $table->timestampTz('archived_at');
        });
        DB::connection('archive')->table($archiveTable)->insert([
            'media_id' => $mediaId,
            'contents' => 'a photograph of a card',
            'checksum' => md5('a photograph of a card'),
            'archived_at' => now(),
        ]);
        expect(DB::connection('archive')->table($archiveTable)->where('media_id', $mediaId)->exists())->toBeTrue();
    }

    RedactChatMessage::make()->handleAttachment($session, $message->fresh(), $agent);

    // Off the disk, out of the table, and the message says a file was taken out of it.
    $redacted = $message->fresh();
    expect(\App\Models\Helpers\Media::find($mediaId))->toBeNull()
        ->and(is_file($diskPath))->toBeFalse()
        ->and($redacted->media_id)->toBeNull()
        ->and($redacted->metadata['attachment_redacted_at'])->not->toBeNull()
        ->and(ChatEvent::where('chat_session_id', $session->id)
            ->where('event_type', ChatEventTypeEnum::REDACT->value)
            ->value('payload')['files'] ?? null)->toBe(1);
    if ($hasArchiveCopy) {
        expect(DB::connection('archive')->table($archiveTable)->where('media_id', $mediaId)->exists())->toBeFalse();
    }
})->with([
    'archive not initialized' => [false],
    'file also stored in archive' => [true],
]);


test('the agents a chat can be handed to come from permissions, not the old shop table', function () {
    setPermissionsTeamId($this->user->group_id);

    $worker = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $worker->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $manager = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $manager->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_SUPERVISOR->value, $this->shop));

    $online = fn (User $user) => ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
    ]);

    $workerAgent  = $online($worker);
    $managerAgent = $online($manager);

    // No shop_has_chat_agents rows exist for either: the old table is being retired.
    $listed = collect(\App\Actions\Chat\ChatSession\GetChatAgents::run())->keyBy('agent_id');

    expect($listed->has($workerAgent->id))->toBeTrue()
        ->and($listed[$workerAgent->id]['shop_names'])->toContain($this->shop->name)
        // A supervisor oversees chats, so handing one to them is not offered.
        ->and($listed->has($managerAgent->id))->toBeFalse();
});

test('the inbox is the same view whatever scope it is opened from', function () {
    setPermissionsTeamId($this->user->group_id);

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    // No shop_has_chat_agents row and no chat agent profile: the position is the whole setup.
    $inboxesFrom = function (string $url) use ($agent) {
        $props = $this->actingAs($agent)->get($url)
            ->assertOk()
            ->viewData('page')['props'];

        return [
            collect($props['inboxes'])->pluck('slug')->sort()->values()->all(),
            collect($props['inboxes'])->firstWhere('slug', $this->shop->slug),
        ];
    };

    [$fromOrg, $shopInbox]   = $inboxesFrom(route('grp.org.chat.inbox', [$this->organisation->slug]));
    [$fromShop, $shopInbox2] = $inboxesFrom(route('grp.org.shops.show.chat.inbox', [$this->organisation->slug, $this->shop->slug]));

    expect($fromOrg)->toContain($this->shop->slug)
        // Chat is not scoped: an agent covers shops across organisations, so the address the
        // inbox was opened from must not change what it holds.
        ->and($fromShop)->toBe($fromOrg)
        ->and($shopInbox['is_read_only'])->toBeFalse()
        ->and($shopInbox2['is_read_only'])->toBeFalse();
});

test('overseeing chat has its own page, scoped to the address, showing everybody\'s conversations', function () {
    setPermissionsTeamId($this->user->group_id);

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $this->organisation));

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agentProfile = ChatAgent::create(['user_id' => $agent->id, 'max_concurrent_chats' => 10]);

    // The inbox is an agent's rota: nobody else is shown it.
    $this->actingAs($supervisor)
        ->get(route('grp.org.chat.inbox', [$this->organisation->slug]))
        ->assertRedirect(route('grp.org.chat.supervision', [$this->organisation->slug]));

    $this->actingAs($supervisor)
        ->get(route('grp.org.shops.show.chat.inbox', [$this->organisation->slug, $this->shop->slug]))
        ->assertRedirect(route('grp.org.shops.show.chat.supervision', [$this->organisation->slug, $this->shop->slug]));

    $props = $this->actingAs($supervisor)
        ->get(route('grp.org.chat.supervision', [$this->organisation->slug]))
        ->assertOk()
        ->viewData('page')['props'];

    $shopOrganisations = \App\Models\Catalogue\Shop::whereIn('id', collect($props['inboxes'])->pluck('id'))->pluck('organisation_id')->unique()->all();

    expect($props['supervisor'])->toBeTrue()
        ->and($shopOrganisations)->toBe([$this->organisation->id])
        ->and(collect($props['inboxes'])->firstWhere('slug', $this->shop->slug)['is_read_only'])->toBeFalse()
        ->and(collect($props['agents'])->pluck('id')->all())->toContain($agentProfile->id);

    $held = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::ACTIVE,
    ]);
    $loose = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::ACTIVE,
    ]);

    foreach ([$held, $loose] as $session) {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'hello',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
        ]);
    }

    $assignment = ChatAssignment::create([
        'chat_session_id' => $held->id,
        'chat_agent_id'   => $agentProfile->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    // Counted with a conversation actually held: with nothing to count the query never ran
    // far enough to fail, and the page fell over the first time anybody was busy.
    $agents = $this->actingAs($supervisor)
        ->get(route('grp.org.chat.supervision', [$this->organisation->slug]))
        ->assertOk()
        ->viewData('page')['props']['agents'];

    expect(collect($agents)->firstWhere('id', $agentProfile->id)['open'])->toBe(1);

    $colleague = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $colleague->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $websiteGuestsSeenBy = fn (User $user) => collect(collect($this->actingAs($user)
        ->get(route('grp.org.chat.inbox', [$this->organisation->slug]))
        ->assertOk()
        ->viewData('page')['props']['inboxes'])
        ->firstWhere('slug', $this->shop->slug)['channels'])
        ->firstWhere('key', 'website')['guest'];

    $finished = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::CLOSED,
    ]);
    ChatMessage::create([
        'chat_session_id' => $finished->id,
        'message_text'    => 'thanks',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);
    $finishedAssignment = ChatAssignment::create([
        'chat_session_id' => $finished->id,
        'chat_agent_id'   => $agentProfile->id,
        'status'          => ChatAssignmentStatusEnum::RESOLVED->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    expect($websiteGuestsSeenBy($agent)['closed_mine'])->toBe(1)
        ->and($websiteGuestsSeenBy($colleague)['closed_mine'])->toBe(0)
        ->and($websiteGuestsSeenBy($colleague)['closed_colleagues'])->toBe($websiteGuestsSeenBy($agent)['closed_colleagues'] + 1);

    $finishedAssignment->forceDelete();
    $finished->messages()->forceDelete();
    $finished->forceDelete();

    expect($websiteGuestsSeenBy($agent)['mine'])->toBe(1)
        ->and($websiteGuestsSeenBy($colleague)['mine'])->toBe(0)
        ->and($websiteGuestsSeenBy($colleague)['colleagues'])->toBe($websiteGuestsSeenBy($agent)['colleagues'] + 1);

    $ofAgent = collect(GetChatSessions::make()->handle([
        'shop_id'   => $this->shop->id,
        'agent_ids' => [$agentProfile->id],
    ])->items())->pluck('id')->all();

    expect($ofAgent)->toContain($held->id)->not->toContain($loose->id);

    $tabs = fn (bool $isAgent, bool $isSupervisor) => collect(data_get(
        (new class () {
            use \App\Actions\Chat\WithChatNavigation;

            public function tabs(bool $isAgent, bool $isSupervisor): array
            {
                return $this->getChatNavigation('grp.org.chat.', ['aw'], $isAgent, $isSupervisor);
            }
        })->tabs($isAgent, $isSupervisor),
        'topMenu.subSections'
    ))->pluck('route.name')->all();

    expect($tabs(false, true))->toBe(['grp.org.chat.supervision', 'grp.org.chat.phone_calls.index', 'grp.org.chat.reports', 'grp.org.chat.settings'])
        ->and($tabs(true, false))->toBe(['grp.org.chat.inbox', 'grp.org.chat.phone_calls.index', 'grp.org.chat.reports', 'grp.org.chat.settings'])
        ->and($tabs(true, true))->toBe(['grp.org.chat.inbox', 'grp.org.chat.supervision', 'grp.org.chat.phone_calls.index', 'grp.org.chat.reports', 'grp.org.chat.settings']);

    $assignment->forceDelete();
    foreach ([$held, $loose] as $session) {
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
    $agentProfile->forceDelete();
});

test('a list of conversations only ever holds shops the person asking may look at', function () {
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::WAITING,
    ]);

    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_text'    => 'hello',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);

    // The queue is worked oldest first, so what this test looks for has to be old enough to be
    // on the first page of everything the rest of the file has left lying about.
    $session->update(['created_at' => now()->subYear()]);
    $session->messages()->update(['created_at' => now()->subYear()]);

    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $ulidsSeenBy = fn (User $user, string $route, array $params) => collect(
        $this->actingAs($user, 'sanctum')
            ->getJson(route($route, $params))
            ->assertOk()
            ->json('data.sessions')
    )->pluck('ulid')->all();

    foreach (['grp.api.chats.sessions.index', 'grp.api.chats.all.sessions.index'] as $route) {
        // Naming the shop, or naming a colleague as "me", used to be all it took.
        expect($ulidsSeenBy($outsider, $route, ['shop_id' => $this->shop->id]))->toBe([])
            ->and($ulidsSeenBy($outsider, $route, []))->toBe([])
            ->and($ulidsSeenBy($outsider, $route, ['shop_id' => $this->shop->id, 'assigned_to_me' => $agent->id, 'statuses' => ['waiting']]))->toBe([])
            ->and($ulidsSeenBy($agent, $route, ['shop_id' => $this->shop->id]))->toContain($session->ulid);
    }

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('email does not sit under the website tab', function () {
    setPermissionsTeamId($this->user->group_id);

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    // A session only counts once somebody has written in it: an opened widget nobody typed
    // into is not a conversation, and the rail says so too.
    $created = [];

    $session = function (ChatChannelEnum $channel) use (&$created) {
        $session = ChatSession::create([
            'shop_id' => $this->shop->id,
            'ulid'    => (string) Str::ulid(),
            'channel' => $channel,
            'status'  => ChatSessionStatusEnum::WAITING,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'hello',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
        ]);

        $created[] = $session->id;

        return $session;
    };

    $session(ChatChannelEnum::WEBSITE);
    $emailSession = $session(ChatChannelEnum::EMAIL);
    $session(ChatChannelEnum::EMAIL)->update(['status' => ChatSessionStatusEnum::ACTIVE, 'is_rubbish' => true]);

    $props = $this->actingAs($agent)
        ->get(route('grp.org.shops.show.chat.inbox', [$this->organisation->slug, $this->shop->slug]))
        ->assertOk()
        ->viewData('page')['props'];

    $channels = collect($props['inboxes'])->firstWhere('slug', $this->shop->slug)['channels'];

    // A bounce notice under a tab marked Website reads as somebody waiting on the other end.
    expect(collect($channels)->pluck('key')->all())->toBe(['website', 'email', 'whatsapp']);

    // The columns are the same three for every shop so the rail reads down as one table; the
    // ones nothing arrives on are held open and unpickable rather than dropped.
    expect(collect($channels)->firstWhere('key', 'email')['available'])->toBeTrue();

    // Put aside keeps its status, and the list leaves it out, so the rail must as well or it
    // promises an active email nobody can find.
    expect(collect($channels)->firstWhere('key', 'email')['guest']['active'])->toBe(0);
    expect(collect($channels)->firstWhere('key', 'whatsapp')['available'])->toBeFalse();

    $website = collect(GetChatSessions::make()->handle([
        'shop_id' => $this->shop->id,
        'pairs'   => ['website:customer', 'website:guest'],
    ])->items());

    expect($website->pluck('id')->all())->not->toContain($emailSession->id);

    // One database serves the whole file, so anything left behind lands in the counts another
    // test makes of the same shop.
    ChatSession::whereIn('id', $created)->each(function (ChatSession $session) {
        $session->messages()->forceDelete();
        $session->forceDelete();
    });
});

test('GetChatReports counts only conversations the visitor wrote in and measures the first reply', function () {
    $this->freezeTime();
    [, , $reportShop] = createOwnShop(__FILE__.':chat-reports');

    $session = fn (string $channel, int $shopId) => ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $shopId,
        'channel'          => $channel,
        'created_at'       => now()->subHours(2),
        'updated_at'       => now(),
    ]);

    $message = fn (ChatSession $chatSession, string $senderType, $minutesAgo) => ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => $senderType,
        'sender_id'       => null,
        'message_text'    => 'x',
        'is_read'         => false,
        'created_at'      => now()->subMinutes($minutesAgo),
        'updated_at'      => now(),
    ]);

    $answered = $session('website', $reportShop->id);
    $message($answered, ChatSenderTypeEnum::GUEST->value, 100);
    $message($answered, ChatSenderTypeEnum::AGENT->value, 90);

    $answered->update(['topic' => ChatTopicEnum::ORDER_STATUS->value]);

    $emailed = $session('email', $reportShop->id);
    $message($emailed, ChatSenderTypeEnum::USER->value, 60);

    $widgetOnlyOpened = $session('website', $reportShop->id);

    $otherShopConversation = $session('website', $this->shop->id);
    $message($otherShopConversation, ChatSenderTypeEnum::GUEST->value, 30);

    $result = GetChatReports::make()->handle(collect([$reportShop->id]), '1w');

    expect($result['conversations'])->toBe(2)
        ->and($result['answered'])->toBe(1)
        ->and($result['unanswered'])->toBe(1)
        ->and($result['median_reply_minutes'])->toBe(10.0)
        ->and(collect($result['by_channel'])->firstWhere('channel', 'email')['conversations'])->toBe(1)
        ->and(collect($result['by_channel'])->firstWhere('channel', 'whatsapp')['conversations'])->toBe(0)
        ->and($result['by_topic'])->toHaveCount(1)
        ->and($result['by_topic'][0])->toMatchArray(['topic' => 'order_status', 'conversations' => 1, 'share' => 100.0, 'website' => 1, 'unanswered' => 0])
        ->and($result['unclassified'])->toBe(1)
        ->and($widgetOnlyOpened->exists)->toBeTrue();
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

    $before = $waiting();

    MarkChatSessionAsRubbish::make()->handle($session, $agentProfile, true, ChatIgnoreReasonEnum::OUT_OF_OFFICE);
    $session->refresh();

    // The reason is countable, which is the point: the noise can be named and dealt with at
    // its source instead of one conversation at a time.
    expect($session->rubbish_reason)->toBe('out_of_office')
        // Hidden from the queue, but its status is untouched, and the sender is never blocked.
        ->and($session->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($waiting())->toBe($before - 1)
        ->and(Arr::get($this->shop->fresh()->settings, 'gmail.blocked_senders', []))->toBe([])
        ->and(GetChatSessions::make()->handle([
            'shop_id'    => $this->shop->id,
            'is_rubbish' => 1,
        ])->total())->toBe(1);

    MarkChatSessionAsRubbish::make()->handle($session, $agentProfile, false);

    expect($session->fresh()->rubbish_reason)->toBeNull()
        ->and($waiting())->toBe($before);

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('mail from one of our own shops or staff never becomes a chat session', function () {
    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '1'];
    $this->shop->update(['settings' => $settings]);

    $sibling = \App\Models\Catalogue\Shop::where('id', '!=', $this->shop->id)->first() ?? $this->shop;
    $sibling->update(['email' => 'hola@awartisan.es']);

    Employee::factory()->create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'work_email'      => 'david@ancientwisdom.biz',
    ]);

    $this->customer->update(['is_staff' => true, 'email' => 'buyer.staff@example.com']);

    \Illuminate\Support\Facades\Cache::forget('chat.our_own_email_addresses');

    $gmailMessage = fn (string $id, string $from) => \Illuminate\Support\Facades\Http::response([
        'id'       => $id,
        'threadId' => 't'.$id,
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => [['name' => 'From', 'value' => $from], ['name' => 'Subject', 'value' => 'Unlock 25% Off']],
            'body'     => ['data' => rtrim(strtr(base64_encode('newsletter'), '+/', '-_'), '=')],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                         => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/o1*' => $gmailMessage('o1', 'AW Artisan <hola@awartisan.es>'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/o2*' => $gmailMessage('o2', 'David Hardy <David@AncientWisdom.biz>'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/o3*' => $gmailMessage('o3', 'Staff Buyer <buyer.staff@example.com>'),
        'gmail.googleapis.com/gmail/v1/users/me/labels'       => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'LF', 'name' => 'aiku/filtered']]]),
        'gmail.googleapis.com/*'                              => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $sessionsBefore = ChatSession::count();

    foreach (['o1', 'o2', 'o3'] as $id) {
        expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, $id))->toBeNull();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), "messages/$id/modify") && $request['addLabelIds'] === ['LF']);
    }

    expect(ChatSession::count())->toBe($sessionsBefore);
});

test('the sweep marks email conversations already imported from our own addresses as rubbish', function () {
    $this->shop->update(['email' => 'hola@awartisan.es']);
    \Illuminate\Support\Facades\Cache::forget('chat.our_own_email_addresses');

    $makeSession = function (string $email) {
        $session = ChatSession::create([
            'ulid'        => (string) \Illuminate\Support\Str::ulid(),
            'shop_id'     => $this->shop->id,
            'language_id' => 68,
            'status'      => ChatSessionStatusEnum::ACTIVE->value,
            'priority'    => ChatPriorityEnum::NORMAL->value,
            'channel'     => \App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL->value,
        ]);
        $session->update(['metadata' => ['email' => $email]]);

        return $session;
    };

    $ours     = $makeSession('Hola@AWartisan.es');
    $customer = $makeSession('shopper@example.com');

    expect(\App\Actions\Chat\ChatSession\RubbishOwnMailChatSessions::run(dryRun: true))->toBeGreaterThanOrEqual(1)
        ->and($ours->fresh()->is_rubbish)->toBeFalse();

    \App\Actions\Chat\ChatSession\RubbishOwnMailChatSessions::run();

    expect($ours->fresh()->is_rubbish)->toBeTrue()
        ->and($ours->fresh()->rubbish_reason)->toBe(\App\Enums\CRM\Livechat\ChatIgnoreReasonEnum::MARKETING->value)
        ->and($customer->fresh()->is_rubbish)->toBeFalse()
        ->and(\App\Actions\Chat\ChatSession\RubbishOwnMailChatSessions::run())->toBe(0);
});

test('the closed list only holds what was closed today', function () {
    $closedSession = function (\Carbon\Carbon $closedAt) {
        $session = ChatSession::create([
            'ulid'             => (string) Str::ulid(),
            'status'           => ChatSessionStatusEnum::CLOSED,
            'guest_identifier' => 'guest_'.Str::random(5),
            'language_id'      => 68,
            'priority'         => ChatPriorityEnum::NORMAL,
            'shop_id'          => $this->shop->id,
            'closed_at'        => $closedAt,
            'created_at'       => $closedAt,
            'updated_at'       => $closedAt,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'thanks',
            'created_at'      => $closedAt,
            'updated_at'      => $closedAt,
        ]);

        return $session;
    };

    $today     = $closedSession(now());
    $lastMonth = $closedSession(now()->subMonth());

    $ulids = collect(GetChatSessions::make()->handle([
        'statuses' => [ChatSessionStatusEnum::CLOSED->value],
        'shop_id'  => $this->shop->id,
    ])->items())->pluck('ulid');

    expect($ulids)->toContain($today->ulid)
        ->and($ulids)->not->toContain($lastMonth->ulid);
});

test('GetChatSessions limits the list to the shops asked for', function () {
    $otherShop = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());

    $sessionOn = function (int $shopId) {
        $session = ChatSession::create([
            'ulid'             => (string)Str::ulid(),
            'status'           => ChatSessionStatusEnum::ACTIVE,
            'guest_identifier' => 'guest_'.Str::random(5),
            'language_id'      => 68,
            'priority'         => ChatPriorityEnum::NORMAL,
            'shop_id'          => $shopId,
            'ai_model_version' => 'default',
            'created_at'       => now()->subYear(),
            'updated_at'       => now()->subYear(),
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'hello',
            'created_at'      => now()->subYear(),
            'updated_at'      => now()->subYear(),
        ]);

        return $session;
    };

    $mine  = $sessionOn($this->shop->id);
    $other = $sessionOn($otherShop->id);

    $both = collect(GetChatSessions::make()->handle([
        'shop_ids' => [$this->shop->id, $otherShop->id],
    ])->items())->pluck('ulid');

    $mine->refresh();

    $one = collect(GetChatSessions::make()->handle([
        'shop_ids' => [$otherShop->id],
    ])->items())->pluck('ulid');

    expect($both)->toContain($other->ulid)
        ->and($one)->toContain($other->ulid)
        ->and($one)->not->toContain($mine->ulid);
});

test('SummarizeChatSession classifies what the customer wanted and leaves system messages out', function () {
    $webUser = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());
    config(['askbot-laravel.openai_api_key' => 'test-key']);

    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => [
            'content' => "```json\n".json_encode([
                'summary'    => 'Two candles missing from GB589048, replacement sent.',
                'topic'      => 'missing_or_damaged',
                'key_points' => ['Two candles missing'],
                'status'     => 'resolved',
                'sentiment'  => 'neutral',
            ])."\n```",
        ]]]]),
    ]);

    $chatSession = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::CLOSED,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'web_user_id'      => $webUser->id,
    ]);

    foreach ([
        [ChatSenderTypeEnum::USER, 'Two candles are missing from GB589048'],
        [ChatSenderTypeEnum::SYSTEM, 'Chat session has been closed by agent'],
    ] as [$senderType, $text]) {
        ChatMessage::create([
            'chat_session_id' => $chatSession->id,
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => $senderType,
            'message_text'    => $text,
        ]);
    }

    $chatSession = SummarizeChatSession::make()->handle($chatSession)->refresh();

    expect($chatSession->topic)->toBe(ChatTopicEnum::MISSING_OR_DAMAGED->value)
        ->and($chatSession->summarised_at)->not->toBeNull()
        ->and(Arr::get($chatSession->metadata, 'ai_summary.summary'))->toContain('GB589048')
        ->and(Arr::get($chatSession->metadata, 'ai_summary'))->not->toHaveKey('topic');

    \Illuminate\Support\Facades\Http::assertSent(
        fn ($request) => str_contains($request['messages'][1]['content'], 'customer: Two candles')
            && !str_contains($request['messages'][1]['content'], 'closed by agent')
    );

    $previousContact = GetChatCustomerProfile::make()->previousContact($this->customer, new ChatSession());

    expect($previousContact['previous_chats'][0]['ulid'])->toBe($chatSession->ulid)
        ->and($previousContact['previous_chats'][0]['summary'])->toContain('GB589048')
        ->and($previousContact['chat_topics'][0])->toMatchArray(['topic' => 'missing_or_damaged', 'count' => 1])
        ->and(GetChatCustomerProfile::make()->previousContact($this->customer, $chatSession)['previous_chats'])->toBeEmpty();
});

test('SummarizeChatSession does not ask the model about a conversation the customer never wrote in', function () {
    \Illuminate\Support\Facades\Http::fake();

    $chatSession = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::CLOSED,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
    ]);

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::AGENT,
        'message_text'    => 'Hello, can I help?',
    ]);

    $chatSession = SummarizeChatSession::make()->handle($chatSession)->refresh();

    \Illuminate\Support\Facades\Http::assertNothingSent();
    expect($chatSession->topic)->toBeNull()->and($chatSession->summarised_at)->toBeNull();
});

function noiseTestEmailSession(\App\Models\Catalogue\Shop $shop, string $from, string $subject, string $text, array $messageMetadata = []): ChatSession
{
    $chatSession = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::WAITING,
        'channel'                 => ChatChannelEnum::EMAIL,
        'shop_id'                 => $shop->id,
        'last_visitor_message_at' => now(),
        'metadata'                => ['email_from' => $from, 'email_subject' => $subject],
    ]);

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => $text,
        'metadata'        => $messageMetadata,
    ]);

    return $chatSession;
}

function noiseTestWhatsappSession(\App\Models\Catalogue\Shop $shop, string $phone, string $text): MetaChatSession
{
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $metaChatSession = MetaChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'meta_channel_id'         => $channel->id,
        'shop_id'                 => $shop->id,
        'phone_number'            => $phone,
        'status'                  => ChatSessionStatusEnum::WAITING,
        'language_id'             => 68,
        'priority'                => ChatPriorityEnum::NORMAL,
        'last_visitor_message_at' => now(),
    ]);

    \App\Models\Chat\MetaChatMessage::create([
        'meta_chat_session_id' => $metaChatSession->id,
        'meta_channel_id'      => $channel->id,
        'message_type'         => ChatMessageTypeEnum::TEXT,
        'sender_type'          => ChatSenderTypeEnum::GUEST,
        'message_text'         => $text,
    ]);

    return $metaChatSession;
}

function noiseTestFakeModel(string $verdict, int $confidence): void
{
    config([
        'askbot-laravel.openai_api_key' => 'test-key',
        'chat.noise.test_answer'        => json_encode(['verdict' => $verdict, 'confidence' => $confidence, 'reason' => 'Because.']),
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => fn () => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => [
            'content' => config('chat.noise.test_answer'),
        ]]]]),
    ]);
}

test('machine mail from a stranger is put aside by rule without asking the model', function () {
    \Illuminate\Support\Facades\Http::fake();

    $classify = fn (ChatSession $chatSession) => \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($chatSession)->refresh();

    $dmarc = $classify(noiseTestEmailSession($this->shop, 'dmarcreport@microsoft.com', '[Preview] Report Domain: ancientwisdom.biz', 'Aggregate report'));
    $testflight = $classify(noiseTestEmailSession($this->shop, 'testflight_no_reply@email.apple.com', 'You are invited', 'Invite'));
    $away = $classify(noiseTestEmailSession($this->shop, 'jane@example.com', 'Automatic reply: Offers', 'I am on leave'));
    $newsletter = $classify(noiseTestEmailSession($this->shop, 'news@example.com', 'This week', 'Offers', ['email_headers' => ['list_unsubscribe' => true]]));
    $voicemail = noiseTestEmailSession($this->shop, 'voicemail@btcloudvoice.com', 'Voicemail received', 'A caller left a message');

    \Illuminate\Support\Facades\Http::assertNothingSent();

    expect($dmarc->is_rubbish)->toBeTrue()
        ->and($dmarc->rubbish_reason)->toBe('automated_notification')
        ->and($dmarc->rubbished_by_agent_id)->toBeNull()
        ->and($dmarc->noise_source)->toBe('rule')
        ->and($testflight->rubbish_reason)->toBe('automated_notification')
        ->and($away->rubbish_reason)->toBe('out_of_office')
        ->and($newsletter->rubbish_reason)->toBe('marketing')
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->verdictByRules($voicemail))->toBeNull()
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::forList($dmarc)['automatic'])->toBeTrue();
});

test('the waiting queue is worked oldest first and the bins are still newest first', function () {
    \Illuminate\Support\Facades\Http::fake();

    $oldest = noiseTestEmailSession($this->shop, 'first@example.com', 'Waited longest', 'Where is my order');
    $newest = noiseTestEmailSession($this->shop, 'third@example.com', 'Just arrived', 'Where is my order');

    $oldest->messages()->update(['created_at' => now()->subDays(4)]);
    $newest->messages()->update(['created_at' => now()->subMinutes(2)]);

    $queue = fn (array $filters) => collect(GetChatSessions::make()->handle(array_merge(['shop_id' => $this->shop->id], $filters))->items())
        ->pluck('id')->all();

    $waiting = $queue(['statuses' => ['waiting']]);

    $oldest->update(['is_rubbish' => true, 'rubbish_at' => now()]);
    $newest->update(['is_rubbish' => true, 'rubbish_at' => now()]);

    $bin = $queue(['is_rubbish' => true]);

    expect(array_search($oldest->id, $waiting, true))->toBeLessThan(array_search($newest->id, $waiting, true))
        ->and(array_search($newest->id, $bin, true))->toBeLessThan(array_search($oldest->id, $bin, true));
});

test('an out of office is put aside in any language and whoever owns the mailbox', function () {
    \Illuminate\Support\Facades\Http::fake();

    $classify = fn (ChatSession $chatSession) => \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($chatSession)->refresh();

    $webUser = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());

    $customerAway = noiseTestEmailSession($this->shop, 'buyer@example.com', 'Automatic reply: Our newsletter', 'I am on leave');
    $customerAway->update(['web_user_id' => $webUser->id]);
    $customerAway = $classify($customerAway);

    $portuguese = $classify(noiseTestEmailSession($this->shop, 'info@misticozen.com', 'Resposta automatica', 'Estou ausente'));
    $slovak     = $classify(noiseTestEmailSession($this->shop, 'jan@example.sk', 'Automaticka odpoved: novinky', 'Som mimo'));
    $postmaster = $classify(noiseTestEmailSession($this->shop, 'postmaster@example.com', 'Undeliverable', 'The address failed'));

    \Illuminate\Support\Facades\Http::assertNothingSent();

    expect($customerAway->is_rubbish)->toBeTrue()
        ->and($customerAway->rubbish_reason)->toBe('out_of_office')
        ->and($customerAway->noise_source)->toBe('rule')
        ->and($portuguese->rubbish_reason)->toBe('out_of_office')
        ->and($slovak->rubbish_reason)->toBe('out_of_office')
        ->and($postmaster->rubbish_reason)->toBe('automated_notification');
});

test('the model only hints until it is allowed to put aside, never touches a customer, and is never asked twice', function () {
    noiseTestFakeModel('spam', 95);

    $pitch = noiseTestEmailSession($this->shop, 'sales@kaitk.com', 'Wooden gifts', 'We are a manufacturer of wooden gifts');
    $pitch = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($pitch)->refresh();

    expect($pitch->is_spam)->toBeFalse()
        ->and($pitch->noise_verdict)->toBe('spam')
        ->and($pitch->noise_confidence)->toBe(95)
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::forList($pitch))->toMatchArray(['automatic' => false, 'source' => 'ai']);

    \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($pitch);
    \Illuminate\Support\Facades\Http::assertSentCount(1);

    config(['chat.noise.auto_put_aside' => true]);

    $second = noiseTestEmailSession($this->shop, 'sales@other.com', 'Pencil cases', 'We are a manufacturer of pencil cases');
    $second = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($second)->refresh();

    $webUser  = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());
    $customer = noiseTestEmailSession($this->shop, 'buyer@example.com', 'Hello', 'We are a manufacturer too');
    $customer->update(['web_user_id' => $webUser->id]);
    $customer = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($customer)->refresh();

    noiseTestFakeModel('nonsense', 99);
    $odd = noiseTestEmailSession($this->shop, 'someone@example.com', 'Question', 'Do you ship to Norway?');
    $odd = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($odd)->refresh();

    expect($second->is_spam)->toBeTrue()
        ->and($second->spammed_by_agent_id)->toBeNull()
        ->and($customer->noise_checked_at)->toBeNull()
        ->and($customer->is_spam)->toBeFalse()
        ->and($odd->noise_verdict)->toBe('genuine')
        ->and($odd->is_spam)->toBeFalse();
});

test('a person undoing or overruling a noise verdict is counted and never checked again', function () {
    \Illuminate\Support\Facades\Http::fake();

    $agentProfile = ChatAgent::firstOrCreate(['user_id' => $this->user->id], ['max_concurrent_chats' => 5, 'language_id' => 68]);

    $dmarc = noiseTestEmailSession($this->shop, 'noreply-dmarc@zoho.com', 'Report domain: x', 'Report');
    $dmarc = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($dmarc)->refresh();

    $restored = \App\Actions\Chat\ChatSession\MarkChatSessionAsRubbish::make()->handle($dmarc, $agentProfile, false);
    $again    = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($restored)->refresh();

    $untouched = noiseTestEmailSession($this->shop, 'a@example.com', 'Hi', 'Hi');
    $untouched = \App\Actions\Chat\ChatSession\MarkChatSessionAsSpam::make()->handle($untouched, $agentProfile);

    $noise = collect(GetChatReports::make()->handle(collect([$this->shop->id]), 'all')['noise'])->firstWhere('source', 'rule');

    expect($restored->noise_reversed_at)->not->toBeNull()
        ->and($again->is_rubbish)->toBeFalse()
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::forList($again))->toBeNull()
        ->and($untouched->noise_checked_at)->not->toBeNull()
        ->and($untouched->noise_reversed_at)->toBeNull()
        ->and($noise['reversed'])->toBeGreaterThanOrEqual(1);
});

test('a WhatsApp greeting from a supplier country is put aside, and comes back when a buyer says what they want', function () {
    config(['chat.noise.greet_bare_hello' => false]);
    noiseTestFakeModel('genuine', 96);

    $hello = noiseTestWhatsappSession($this->shop, '+919062915151', 'Hi sir');
    $hello = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($hello)->refresh();

    $british = noiseTestWhatsappSession($this->shop, '+447500000001', 'Hello');
    $british = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($british)->refresh();

    \Illuminate\Support\Facades\Http::assertNothingSent();

    expect($hello->is_spam)->toBeTrue()
        ->and($hello->noise_verdict)->toBe('supplier_circular')
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::isProvisional($hello))->toBeTrue()
        ->and($british->is_spam)->toBeFalse()
        ->and($british->noise_checked_at)->toBeNull();

    \App\Models\Chat\MetaChatMessage::create([
        'meta_chat_session_id' => $hello->id,
        'meta_channel_id'      => $hello->meta_channel_id,
        'message_type'         => ChatMessageTypeEnum::TEXT,
        'sender_type'          => ChatSenderTypeEnum::GUEST,
        'message_text'         => 'I have a shop in Delhi and want prices for 30 lavender essential oils',
    ]);

    $hello = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($hello)->refresh();

    expect($hello->is_spam)->toBeFalse()
        ->and($hello->noise_verdict)->toBe('genuine')
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::isCandidate($hello))->toBeFalse();

    noiseTestFakeModel('spam', 97);

    $pitch = noiseTestWhatsappSession($this->shop, '+8615000000001', 'Dear Sir, this is Selma from Will Printing, a manufacturer of gift packaging');
    $pitch = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($pitch)->refresh();

    expect($pitch->is_spam)->toBeTrue()->and($pitch->noise_source)->toBe('rule');
});

test('a stranger who only says hello on WhatsApp is asked once what they want', function () {
    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['whatsapp' => ['phone_number_id' => '123']])]);
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['meta' => ['access_key' => 'token']])]);

    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'wamid.greeting']]])]);

    $hello = noiseTestWhatsappSession($this->shop->fresh(), '+447500000002', 'Hello');

    \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($hello);
    \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($hello->refresh());

    \Illuminate\Support\Facades\Http::assertSentCount(1);

    $greeting = $hello->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->first();

    expect($greeting->message_text)->toContain('How can we help you')
        ->and($hello->refresh()->last_agent_message_at)->toBeNull()
        ->and($hello->is_spam)->toBeFalse();
});

test('an inbound gmail message brings the rest of its gmail thread in as earlier chat messages', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $gmailMessage = fn (string $id, string $from, string $text, array $labels, int $sentAtMs) => [
        'id'           => $id,
        'threadId'     => 'th-history',
        'labelIds'     => $labels,
        'internalDate' => (string) $sentAtMs,
        'payload'      => [
            'mimeType' => 'text/plain',
            'headers'  => [
                ['name' => 'From', 'value' => $from],
                ['name' => 'Subject', 'value' => 'Broken jar'],
                ['name' => 'Message-ID', 'value' => "<$id@example.com>"],
            ],
            'body'     => ['data' => rtrim(strtr(base64_encode($text), '+/', '-_'), '=')],
        ],
    ];

    $first  = $gmailMessage('h1', 'Thread Writer <thread.writer@example.com>', 'My jar arrived broken', ['INBOX'], 1789000000000);
    $answer = $gmailMessage('h2', 'Care <care@shop.test>', 'Sorry, a new one is on its way', ['SENT'], 1789003600000);
    $latest = $gmailMessage('h3', 'Thread Writer <thread.writer@example.com>', 'Thank you, received', ['INBOX'], 1789090000000);
    $draft  = $gmailMessage('h4', 'Care <care@shop.test>', 'half written', ['DRAFT'], 1789090100000);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                                 => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/h3?*'        => \Illuminate\Support\Facades\Http::response($latest),
        'gmail.googleapis.com/gmail/v1/users/me/threads/th-history*'  => \Illuminate\Support\Facades\Http::response(['messages' => [$first, $answer, $latest, $draft]]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'               => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L2', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                                      => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'h3');

    $thread = $message->chatSession->messages()->orderBy('created_at')->get();

    expect($thread->pluck('message_text')->all())->toBe(['My jar arrived broken', 'Sorry, a new one is on its way', 'Thank you, received'])
        ->and($thread[0]->sender_type)->toBe(ChatSenderTypeEnum::GUEST)
        ->and($thread[1]->sender_type)->toBe(ChatSenderTypeEnum::AGENT)
        ->and($thread[1]->created_at->getTimestampMs())->toBe(1789003600000)
        ->and($thread[2]->id)->toBe($message->id);

    Bus::assertNotDispatched(\App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);

    \App\Actions\Comms\Mailbox\ProcessInboundEmail::make()->handle($this->shop, 'h3');
    expect($message->chatSession->messages()->count())->toBe(3);

    $message->chatSession->messages()->where('metadata->gmail_thread_history', true)->delete();
    \Illuminate\Support\Sleep::fake();
    ChatSession::where('shop_id', $this->shop->id)
        ->where('channel', ChatChannelEnum::EMAIL)
        ->whereKeyNot($message->chat_session_id)
        ->update(['status' => ChatSessionStatusEnum::CLOSED]);
    expect(\App\Actions\Comms\Mailbox\BackfillGmailThreadHistory::run($this->shop))->toBe(['sessions' => 1, 'messages' => 2, 'failed' => 0])
        ->and(\App\Actions\Comms\Mailbox\BackfillGmailThreadHistory::run($this->shop)['messages'])->toBe(0);
});

test('a ticket marked as blocking holds the chat open until it is settled', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $ticket = \App\Actions\Chat\ChatSession\StoreTicketFromChatSession::make()->handle($session, $agent, [
        'summary'       => 'Refund never arrived',
        'blocks_source' => true,
    ]);

    expect($ticket->blocks_source)->toBeTrue();

    expect(fn () => CloseChatSession::make()->handle($session, $agent->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class, $ticket->reference);

    // the customer ending the chat from their side is not held up by our backlog
    $otherSession = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);
    \App\Actions\Chat\ChatSession\StoreTicketFromChatSession::make()->handle($otherSession, $agent, [
        'summary'       => 'Also blocking',
        'blocks_source' => true,
    ]);

    expect(CloseChatSession::make()->handle($otherSession, null, \App\Enums\CRM\Livechat\ChatActorTypeEnum::GUEST)->status)
        ->toBe(ChatSessionStatusEnum::CLOSED);

    $ticket->update(['status' => \App\Enums\Helpers\Ticket\TicketStatusEnum::RESOLVED->value]);

    expect(CloseChatSession::make()->handle($session->fresh(), $agent->id)->status)
        ->toBe(ChatSessionStatusEnum::CLOSED);
});

test('a ticket raised from a chat does not block it unless it was marked as blocking', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $ticket = \App\Actions\Chat\ChatSession\StoreTicketFromChatSession::make()->handle($session, $agent, [
        'summary' => 'Nice to have, not urgent',
    ]);

    expect($ticket->blocks_source)->toBeFalse()
        ->and(CloseChatSession::make()->handle($session, $agent->id)->status)->toBe(ChatSessionStatusEnum::CLOSED);
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

test('a WhatsApp guest is suggested by the number they write from, whatever way it was stored', function () {
    config(['chat.noise.greet_bare_hello' => false]);
    $this->customer->update(['phone' => '07500 111222']);

    $metaChatSession = noiseTestWhatsappSession($this->shop, '+447500111222', 'Hello');
    $metaChatSession = \App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::make()->handle($metaChatSession)->refresh();

    expect($metaChatSession->customer_id)->toBeNull()
        ->and($metaChatSession->suggested_customer_id)->toBe($this->customer->id)
        ->and($metaChatSession->suggestion_basis)->toBe('phone');
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

test('a gmail message the sender has deleted is given up on rather than fetched again forever', function () {
    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '1'];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                         => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/d1*' => \Illuminate\Support\Facades\Http::response(['error' => ['code' => 404, 'message' => 'Requested entity was not found.']], 404),
    ]);

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'd1'))->toBeNull()
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'd1'))->toBeNull();

    \Illuminate\Support\Facades\Http::assertSentCount(2);
});

test('a photograph too large for an email arrives as a drive link and is fetched from drive', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class]);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '1'];
    $this->shop->update(['settings' => $settings]);

    $encode  = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $jpeg    = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==');

    $html = '<div>Apologies photos didn\'t attach</div>'
        .'<a href="https://drive.google.com/file/d/1xVm7efg7RmtSz1EQ8/view">IMG_8872.jpeg</a>'
        .'<a href="https://drive.google.com/file/d/1GaROJ3DR15p0kXrjv/view">private-notes.txt</a>';

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                                   => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'www.googleapis.com/drive/v3/files/1xVm7efg7RmtSz1EQ8?alt=media' => \Illuminate\Support\Facades\Http::response($jpeg),
        'www.googleapis.com/drive/v3/files/1xVm7efg7RmtSz1EQ8*'          => \Illuminate\Support\Facades\Http::response(['name' => 'IMG_8872.jpeg', 'mimeType' => 'image/jpeg', 'size' => '2400000']),
        // Never shared with us: the link stays in the message and nothing is invented for it.
        'www.googleapis.com/drive/v3/files/1GaROJ3DR15p0kXrjv*'          => \Illuminate\Support\Facades\Http::response(['error' => ['code' => 404]], 404),
        'gmail.googleapis.com/gmail/v1/users/me/messages/dr1*'           => \Illuminate\Support\Facades\Http::response([
            'id'       => 'dr1',
            'threadId' => 'tdr1',
            'payload'  => [
                'mimeType' => 'multipart/alternative',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Charlotte <charlotte@example.com>'],
                    ['name' => 'Subject', 'value' => 'Photos for previous email'],
                ],
                'parts'    => [
                    ['mimeType' => 'text/plain', 'filename' => '', 'body' => ['data' => $encode("Apologies photos didn't attach\n\n[image: Image]\nIMG_8872.jpeg")]],
                    ['mimeType' => 'text/html', 'filename' => '', 'body' => ['data' => $encode($html)]],
                ],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'                 => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                                        => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'dr1');

    expect($message->attachedFiles()->pluck('name')->all())->toBe(['IMG_8872.jpeg']);
});

test('starting an email from the customer record opens an email conversation and keeps the thread', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    actingAs($this->user);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);
    $this->customer->update(['email' => 'buyer@example.com']);
    $this->customer->refresh();

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent9', 'threadId' => 't9']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    expect(\App\Actions\Chat\ChatSession\StartCustomerEmailChat::canBeStarted($this->customer))->toBeTrue();

    $session = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->customer, [
        'subject' => 'Your order',
        'message' => 'We have a question about your order',
    ]);

    expect($session->channel)->toBe(\App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL)
        ->and($session->status)->toBe(ChatSessionStatusEnum::ACTIVE)
        ->and(Arr::get($session->metadata, 'email_from'))->toBe('buyer@example.com')
        ->and($session->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->count())->toBe(1)
        ->and($session->messages()->count())->toBe(1);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($session->messages()->first());

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return !isset($request['threadId'])
            && str_contains($raw, 'To: ')
            && str_contains($raw, 'buyer@example.com')
            && str_contains($raw, 'Subject: Your order')
            && !str_contains($raw, 'In-Reply-To:');
    });

    expect(Arr::get($session->fresh()->metadata, 'gmail_thread_id'))->toBe('t9');
});

test('GetChatCustomerTimeline puts every channel, the orders and what is still owed in one line', function () {
    $webUser = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());

    $earlierWebsite = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::CLOSED,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'web_user_id'      => $webUser->id,
        'topic'            => ChatTopicEnum::MISSING_OR_DAMAGED->value,
        'metadata'         => ['ai_summary' => ['summary' => 'Two candles missing', 'status' => 'resolved']],
        'created_at'       => now()->subDays(10),
        'last_visitor_message_at' => now()->subDays(10),
    ]);

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $whatsapp = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $this->customer->id,
        'phone_number'    => '+628123456789',
        'status'          => ChatSessionStatusEnum::CLOSED,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
        'created_at'      => now()->subDays(2),
        'last_visitor_message_at' => now()->subDays(2),
    ]);

    $current = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'web_user_id'      => $webUser->id,
    ]);

    $unpaidInvoice = \App\Actions\Accounting\Invoice\StoreInvoice::make()
        ->action($this->customer, \App\Models\Accounting\Invoice::factory()->definition());
    $unpaidInvoice->update(['pay_status' => \App\Enums\Accounting\Invoice\InvoicePayStatusEnum::UNPAID]);

    $events = collect(GetChatCustomerTimeline::make()->handle($current)['events']);

    $conversations = $events->where('type', 'conversation');

    expect($conversations->pluck('metadata.ulid')->all())->toBe([$whatsapp->ulid, $earlierWebsite->ulid])
        ->and($conversations->pluck('metadata.channel')->all())->toBe(['whatsapp', 'website'])
        ->and($conversations->last()['comment'])->toBe('Two candles missing')
        ->and($events->pluck('metadata.ulid'))->not->toContain($current->ulid)
        ->and($events->pluck('datetime')->filter()->values()->all())
        ->toBe($events->pluck('datetime')->filter()->sortDesc()->values()->all());

    expect($events->where('type', 'invoice_open')->pluck('metadata.reference'))
        ->toContain($unpaidInvoice->reference);
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

    $queue = collect(GetChatSessions::make()->handle(['unclaimed' => true])->items())->pluck('id')->all();

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

    $queue = collect($action->handle($scopeFor(['unclaimed' => true]))->items())->pluck('id')->all();

    expect($queue)->toContain($foreign->id);
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
});

test('a shop may set its own unclaimed time, and the rest follow the group default', function () {
    \Illuminate\Support\Facades\Http::fake();

    config(['chat.unclaimed.after_seconds.email' => 7200]);

    \Illuminate\Support\Facades\Cache::forget('chat:unclaimed:overrides:email');

    $patient = noiseTestEmailSession($this->shop, 'patient@example.com', 'Shop waits longer', 'Where is my order');
    $patient->update(['last_visitor_message_at' => now()->subHours(3)]);

    $queue = fn () => collect(GetChatSessions::make()->handle(['unclaimed' => true])->items())->pluck('id')->all();

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

    $offlineMessage = fn () => StoreOfflineMessage::make()->handle($this->shop->refresh(), [
        'name'        => 'Jane Doe',
        'email'       => 'jane@example.com',
        'message'     => 'Nobody was on, please write back',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'web_user_id' => null,
    ]);

    $settings = $this->shop->settings ?? [];
    data_set($settings, 'gmail.email', 'help@example.com');
    data_set($settings, 'chat.email_offline_replies', false);
    $this->shop->updateQuietly(['settings' => $settings]);

    expect($offlineMessage()->channel)->toBe(ChatChannelEnum::WEBSITE);

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

    expect($offlineMessage()->channel)->toBe(ChatChannelEnum::WEBSITE);

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

    $organisation = Organisation::first() ?? Organisation::factory()->create();
    $website      = Website::first() ?? Website::factory()->create();
    $customer     = Customer::first() ?? Customer::factory()->create();
    $group        = createGroup();

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
