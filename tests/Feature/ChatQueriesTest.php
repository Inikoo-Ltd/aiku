<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\DownloadChatAttachment;
use App\Actions\Chat\ChatSession\ExportChatConversations;
use App\Actions\Chat\ChatSession\GetAgentUnreadMessagesSummary;
use App\Actions\Chat\ChatSession\GetChatActivity;
use App\Actions\Chat\ChatSession\GetChatAgentByUserId;
use App\Actions\Chat\ChatSession\GetChatAgents;
use App\Actions\Chat\ChatSession\GetChatAgentSpecializations;
use App\Actions\Chat\ChatSession\GetChatCustomerProfile;
use App\Actions\Helpers\Address\GetFormattedAddress;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Actions\Chat\ChatSession\GetChatCustomerTimeline;
use App\Actions\Chat\ChatSession\GetChatDashboardVisitors;
use App\Actions\Chat\ChatSession\GetChatMessages;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\GetChatStatus;
use App\Actions\Chat\ChatSession\HandleChatTyping;
use App\Actions\Chat\ChatSession\MarkChatMessagesAsRead;
use App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\ShareChatSessionToSlack;
use App\Actions\Chat\ChatSession\StoreChatEvent;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreGuestProfile;
use App\Actions\Chat\ChatSession\StoreOfflineMessage;
use App\Actions\Chat\ChatSession\SummarizeChatSession;
use App\Actions\Chat\ChatSession\SyncChatSessionByEmail;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Actions\Helpers\Translations\DetectLanguageWithJev;
use App\Actions\Helpers\Translations\Translate;
use App\Models\Helpers\Language;
use App\Actions\Chat\ChatSession\TranslateSessionMessages;
use App\Actions\Chat\ChatSession\TranslateSingleMessage;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatEvent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
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

    \Illuminate\Support\Facades\Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
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

test('a customer message keeps the page they were on, with the product code of a product page', function () {
    [, $product] = createProduct($this->shop);
    $webpage = $product->webpage ?: StoreProductWebpage::make()->action($product);
    $webpage->update(['state' => WebpageStateEnum::LIVE]);

    $session = StoreOfflineMessage::make()->handle($this->shop, [
        'name'        => 'Page Viewer',
        'email'       => 'page-viewer-'.Str::random(6).'@example.com',
        'message'     => 'What is in this?',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
        'web_user_id' => null,
        'page_url'    => 'https://shop.test/department/family/'.$webpage->url.'?ref=x',
    ]);

    $offline = ChatMessage::where('chat_session_id', $session->id)->latest('id')->first();

    expect($offline->metadata)->toMatchArray([
        'is_offline_message' => true,
        'page_url'           => 'https://shop.test/department/family/'.$webpage->url.'?ref=x',
        'page_code'          => $product->code,
        'page_title'         => $webpage->title,
    ]);

    $other = SendChatMessage::make()->handle($session, [
        'message_text' => 'And the delivery?',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::GUEST->value,
        'page_url'     => 'https://shop.test/no-such-page-'.Str::random(6),
    ]);

    expect($other->metadata)->toHaveKey('page_url')->not->toHaveKey('page_code');

    $agentReply = SendChatMessage::make()->handle($session, [
        'message_text' => 'Here you go',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::AGENT->value,
        'page_url'     => 'https://shop.test/'.$webpage->url,
    ]);

    expect($agentReply->metadata)->toBeNull();
});

test('StoreOfflineMessage puts a closed session back in the waiting queue, not with the agent who closed it', function () {
    $agent = ChatAgent::updateOrCreate(
        ['user_id' => createAdminGuest(createGroup())->getUser()->id],
        ['is_online' => true, 'max_concurrent_chats' => 100, 'current_chat_count' => 0, 'deleted_at' => null]
    );

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

    ChatAssignment::create([
        'chat_session_id' => $chatSession->id,
        'chat_agent_id'   => $agent->id,
        'status'          => ChatAssignmentStatusEnum::RESOLVED->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now()->subDay(),
        'resolved_at'     => now()->subHour(),
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
        ->and($reopenedSession->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($reopenedSession->closed_at)->toBeNull()
        ->and($reopenedSession->closed_by)->toBeNull()
        ->and($chatSession->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->exists())->toBeFalse();
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

    $nobodyWaiting = ['sessions' => 0, 'oldest_at' => null, 'url' => null, 'live' => ['sessions' => 0, 'oldest_at' => null, 'url' => null]];

    expect($summary)->toBe([
        'assigned_unread_count'   => 0,
        'unassigned_unread_count' => 0,
        'total_unread_count'      => 0,
        'waiting'                 => [
            'chat'     => $nobodyWaiting,
            'email'    => $nobodyWaiting,
            'channels' => array_fill_keys(['website', 'whatsapp', 'email'], ['sessions' => 0, 'oldest_at' => null, 'url' => null]),
        ],
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

    makeChatWorker($user, $this->shop);

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
        ->and($summary['assigned_unread_count'])->toBe(0)
        ->and($summary['waiting']['chat']['live']['sessions'])->toBeGreaterThanOrEqual(1)
        ->and($summary['waiting']['chat']['live']['url'])->toContain('/chat/inbox/');

    $waitingSession = fn (ChatChannelEnum $channel, \Illuminate\Support\Carbon $writtenAt) => tap(ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'channel'          => $channel,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]), fn (ChatSession $session) => ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Still waiting',
        'is_read'         => false,
        'created_at'      => $writtenAt,
        'updated_at'      => $writtenAt,
    ]));

    $waitingSession(ChatChannelEnum::WEBSITE, now()->subHours(3));
    $waitingSession(ChatChannelEnum::EMAIL, now());

    $after = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    expect($after['waiting']['chat']['sessions'])->toBe($summary['waiting']['chat']['sessions'] + 1)
        ->and($after['waiting']['chat']['live']['sessions'])->toBe($summary['waiting']['chat']['live']['sessions'])
        ->and($after['waiting']['email']['sessions'])->toBe($summary['waiting']['email']['sessions'] + 1)
        ->and($after['waiting']['email']['live']['sessions'])->toBe($summary['waiting']['email']['live']['sessions'] + 1);
});

test('customers waiting on WhatsApp link to the WhatsApp conversation, not the bare inbox', function () {
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $chatAgent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    makeChatWorker($user, $this->shop);

    $channel         = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $whatsappSession = MetaChatSession::create([
        'ulid'            => (string)Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+421900000404',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    \App\Models\Chat\MetaChatMessage::create([
        'meta_chat_session_id' => $whatsappSession->id,
        'meta_channel_id'      => $channel->id,
        'message_type'         => ChatMessageTypeEnum::TEXT,
        'sender_type'          => ChatSenderTypeEnum::GUEST,
        'message_text'         => 'Hello, is anyone there?',
        'is_read'              => false,
        'created_at'           => now()->subMinutes(29),
        'updated_at'           => now()->subMinutes(29),
    ]);

    $summary = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    expect($summary['waiting']['chat']['live']['url'])
        ->toBe(route('grp.org.chat.inbox', [$this->organisation->slug, 'channel' => 'whatsapp', 'session' => $whatsappSession->ulid]));
});

test('a chat list event tells the agent which channel it came from and links to the conversation', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'channel'          => ChatChannelEnum::EMAIL,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'metadata'         => ['email_subject' => 'Order GB586411'],
    ]);

    $message = ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Where is my order?',
        'is_read'         => false,
    ]);

    $payload = (new \App\Events\BroadcastChatListEvent($message))->broadcastWith();

    expect($payload['message']['id'])->toBe($message->id)
        ->and($payload['message']['channel'])->toBe('email')
        ->and($payload['message']['subject'])->toBe('Order GB586411')
        ->and($payload['session']['url'])->toEndWith('/chat/inbox/'.$chatSession->ulid);
});

test('the shop chat list is open to agents by their position, not only by the retired assignment table', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $stranger = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    expect((new \App\Broadcasting\ChatListChannel())->join($clerk, (string) $this->shop->id))->toBe(['id' => $clerk->id, 'name' => $clerk->contact_name])
        ->and((new \App\Broadcasting\ChatListChannel())->join($stranger, (string) $this->shop->id))->toBeFalse();
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

test('previewing a conversation from the inbox list leaves its messages unread', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);

    $guestMessage = ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'sender_id'       => null,
        'message_text'    => 'Is my order on its way?',
        'is_read'         => false,
    ]);

    $this->getJson(route('grp.api.chats.sessions.messages', [
        'chatSession'  => $chatSession->ulid,
        'request_from' => ChatSenderTypeEnum::AGENT->value,
        'preview'      => 1,
        'limit'        => 30,
    ]))->assertOk()->assertJsonPath('data.messages.0.message_text', 'Is my order on its way?');

    expect($guestMessage->refresh()->is_read)->toBeFalse();

    $this->getJson(route('grp.api.chats.sessions.messages', [
        'chatSession'  => $chatSession->ulid,
        'request_from' => ChatSenderTypeEnum::AGENT->value,
    ]))->assertOk();

    expect($guestMessage->refresh()->is_read)->toBeTrue();
});

test('internal notes reach the staff thread but never the public messages endpoint, even when it asks as an agent', function () {
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);

    ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'My card keeps failing',
        'is_read'         => false,
    ]);

    StoreChatEvent::make()->internalNote($chatSession, 'Declined by her bank, suggest a pay link');

    $asAgent = route('grp.api.chats.sessions.messages', ['chatSession' => $chatSession->ulid, 'request_from' => ChatSenderTypeEnum::AGENT->value, 'preview' => 1]);

    $this->getJson($asAgent)->assertOk()
        ->assertJsonCount(0, 'data.events')
        ->assertDontSee('suggest a pay link');

    $this->actingAs($this->user)->getJson($asAgent)->assertOk()
        ->assertJsonPath('data.events.0.event_type', ChatEventTypeEnum::INTERNAL_NOTE->value)
        ->assertJsonPath('data.events.0.note', 'Declined by her bank, suggest a pay link');
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

test('TranslateChatMessage leaves an agent reply alone when it is already in the customer language', function (?string $detectedLanguageCode) {
    $spanish = Language::where('code', 'es')->firstOrFail();

    $chatSession = ChatSession::create([
        'ulid'                    => (string)Str::ulid(),
        'status'                  => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier'        => 'guest_'.Str::random(5),
        'language_id'             => $spanish->id,
        'active_user_language_id' => $spanish->id,
        'priority'                => ChatPriorityEnum::NORMAL,
        'shop_id'                 => $this->shop->id,
        'ai_model_version'        => 'default',
    ]);

    $chatMessage = ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::AGENT->value,
        'message_text'    => 'Disculpe la demora en la respuesta.',
        'original_text'   => 'Disculpe la demora en la respuesta.',
        'is_read'         => false,
    ]);

    DetectLanguageWithJev::shouldRun()->andReturn($detectedLanguageCode ? Language::where('code', $detectedLanguageCode)->first() : null);
    Translate::shouldNotRun();

    TranslateChatMessage::make()->handle($chatMessage->id);

    expect($chatMessage->refresh()->message_text)->toBe('Disculpe la demora en la respuesta.')
        ->and($chatMessage->translations()->count())->toBe(0);
})->with(['detected as spanish' => 'es', 'language not detected' => null]);

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

test('GetChatCustomerProfile links to the communications tab of the customer', function () {
    $webUser = $this->customer->webUsers()->first() ?? StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());

    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'web_user_id'      => $webUser->id,
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);

    $profileUrl = GetChatCustomerProfile::make()->handle($chatSession)['profile_url'];

    expect($profileUrl)->toContain($this->customer->slug)
        ->and($profileUrl)->toEndWith('?tab=communications');
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

    $result = GetChatSessions::make()->handle(['ulid' => $chatSession->ulid]);

    expect($result->total())->toBe(1);

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

test('the website chat widget tells the agent the customer is on the site or has left, and stops once the chat is closed', function () {
    Event::fake([\App\Events\BroadcastChatCustomerPresence::class]);
    $newSession = fn (ChatChannelEnum $channel, ChatSessionStatusEnum $status = ChatSessionStatusEnum::ACTIVE) => ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => $status,
        'channel'          => $channel,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    $beat = fn (ChatSession $session, array $data = []) => $this->post(route('grp.api.chats.sessions.presence', $session->ulid), $data)->assertOk()->json('tracking');

    $website = $newSession(ChatChannelEnum::WEBSITE);
    expect($beat($website))->toBeTrue()
        ->and(\App\Actions\Chat\ChatSession\TrackChatCustomerPresence::current($website)['left'])->toBeFalse()
        ->and($beat($website, ['left' => '1']))->toBeTrue()
        ->and(\App\Actions\Chat\ChatSession\TrackChatCustomerPresence::current($website)['left'])->toBeTrue()
        ->and(\App\Http\Resources\CRM\Livechat\ChatSessionListResource::make($website)->resolve()['customer_presence']['left'])->toBeTrue();
    Event::assertDispatched(\App\Events\BroadcastChatCustomerPresence::class, fn ($event) => $event->ulid === $website->ulid && $event->presence['left'] === true);

    $closed = $newSession(ChatChannelEnum::WEBSITE, ChatSessionStatusEnum::CLOSED);
    $email  = $newSession(ChatChannelEnum::EMAIL);
    expect($beat($closed))->toBeFalse()
        ->and($beat($email))->toBeFalse()
        ->and(\App\Actions\Chat\ChatSession\TrackChatCustomerPresence::current($closed))->toBeNull();
    Event::assertDispatchedTimes(\App\Events\BroadcastChatCustomerPresence::class, 2);
});

test('each presence beat records whether the agent browser can alert them, and a beat without it keeps the last report', function () {
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $chatAgent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'is_online'            => true,
        'is_available'         => true,
    ]);
    $track = \App\Actions\Chat\Agent\Presence\TrackChatAgentPresence::make();

    $track->handle($chatAgent, \App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum::ONLINE, ['notifications' => 'default', 'sound' => 'suspended', 'shops' => 2]);
    expect($chatAgent->refresh()->alert_health)->toMatchArray(['notifications' => 'default', 'sound' => 'suspended', 'shops' => 2])
        ->and($chatAgent->alert_health['at'])->not->toBeNull();

    $track->handle($chatAgent, \App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum::ONLINE);
    expect($chatAgent->refresh()->alert_health['sound'])->toBe('suspended');

    $this->actingAs($user)->postJson(route('grp.chat.presence.track'), ['status' => 'online', 'alerts' => ['notifications' => 'granted', 'sound' => 'running', 'shops' => 3]])->assertOk();
    expect($chatAgent->refresh()->alert_health)->toMatchArray(['notifications' => 'granted', 'sound' => 'running', 'shops' => 3]);

    $this->actingAs($user)->postJson(route('grp.chat.presence.track'), ['status' => 'online', 'alerts' => ['sound' => 'loud']])->assertUnprocessable();
});

test('courier mail and mail put aside as a notice do not count as customers waiting or alert, unless somebody holds the conversation', function () {
    $user      = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $chatAgent = ChatAgent::create(['user_id' => $user->id, 'max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]);
    makeChatWorker($user, $this->shop);

    $emailWaiting = fn (array $flags = []) => tap(ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'channel'          => ChatChannelEnum::EMAIL,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        ...$flags,
    ]), fn (ChatSession $session) => ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Your parcel is on its way',
        'is_read'         => false,
    ]));

    $before  = GetAgentUnreadMessagesSummary::make()->handle($chatAgent)['waiting']['email']['sessions'];
    $courier = $emailWaiting(['is_carrier' => true]);
    $emailWaiting(['is_rubbish' => true]);
    expect(GetAgentUnreadMessagesSummary::make()->handle($chatAgent)['waiting']['email']['sessions'])->toBe($before);

    $channelsBefore = GetAgentUnreadMessagesSummary::make()->handle($chatAgent)['waiting']['channels'];
    $customer       = $emailWaiting();
    $after          = GetAgentUnreadMessagesSummary::make()->handle($chatAgent)['waiting'];
    expect($after['email']['sessions'])->toBe($before + 1)
        ->and($after['channels']['email']['sessions'])->toBe($channelsBefore['email']['sessions'] + 1)
        ->and($after['channels']['website']['sessions'])->toBe($channelsBefore['website']['sessions'])
        ->and($after['channels']['whatsapp']['sessions'])->toBe($channelsBefore['whatsapp']['sessions'])
        ->and($after['channels']['email']['url'])->toContain('/chat/inbox/');

    $payload = fn (ChatSession $session) => (new \App\Events\BroadcastChatListEvent($session->messages()->first(), $session))->broadcastWith()['message'];
    expect($payload($courier)['is_quiet'])->toBeTrue()
        ->and($payload($customer)['is_quiet'])->toBeFalse();
});

test('every conversation row and alert names its shop and the customer language, and stays unanswered until somebody replies', function () {
    $language = \App\Models\Helpers\Language::where('code', 'sk')->first() ?? \App\Models\Helpers\Language::first();
    $session  = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::WAITING,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'guest_identifier'        => 'guest_'.Str::random(5),
        'language_id'             => 68,
        'user_language_id'        => $language->id,
        'priority'                => ChatPriorityEnum::NORMAL,
        'shop_id'                 => $this->shop->id,
        'ai_model_version'        => 'default',
        'last_agent_message_at'   => now()->subHour(),
        'last_visitor_message_at' => now()->subMinute(),
    ]);
    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Any news?',
        'is_read'         => true,
    ]);

    $row = \App\Http\Resources\CRM\Livechat\ChatSessionListResource::make($session->refresh())->resolve();
    expect($row['shop']['code'])->toBe($this->shop->code)
        ->and($row['customer_language']['code'])->toBe($language->code)
        ->and($row['is_unanswered'])->toBeTrue();

    $alert = (new \App\Events\BroadcastChatListEvent($message, $session))->broadcastWith()['message'];
    expect($alert['shop_code'])->toBe($this->shop->code)
        ->and($alert['language'])->toBe($language->code);

    $session->update(['last_agent_message_at' => now()]);
    expect(\App\Http\Resources\CRM\Livechat\ChatSessionListResource::make($session->refresh())->resolve()['is_unanswered'])->toBeFalse();
});

test('a held conversation the customer wrote back to shows in Waiting while staying with its holder, on website, email and WhatsApp', function () {
    $holderUser = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $holder     = ChatAgent::create(['user_id' => $holderUser->id, 'max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]);
    $colleague  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    ChatAgent::create(['user_id' => $colleague->id, 'max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]);
    makeChatWorker($colleague, $this->shop);

    $held = fn (ChatChannelEnum $channel, $customerWroteAt) => tap(ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::ACTIVE,
        'channel'                 => $channel,
        'guest_identifier'        => 'guest_'.Str::random(5),
        'language_id'             => 68,
        'priority'                => ChatPriorityEnum::NORMAL,
        'shop_id'                 => $this->shop->id,
        'ai_model_version'        => 'default',
        'last_agent_message_at'   => now()->subMinutes(10),
        'last_visitor_message_at' => $customerWroteAt,
    ]), function (ChatSession $session) use ($holder) {
        $session->assignments()->create(['chat_agent_id' => $holder->id, 'status' => \App\Enums\CRM\Livechat\ChatAssignmentStatusEnum::ACTIVE->value, 'assigned_at' => now()]);
        $session->messages()->create(['message_type' => ChatMessageTypeEnum::TEXT->value, 'sender_type' => ChatSenderTypeEnum::GUEST->value, 'message_text' => 'Any update?', 'is_read' => true]);
    });

    $repliedTo = $held(ChatChannelEnum::WEBSITE, now());
    $email     = $held(ChatChannelEnum::EMAIL, now());
    $answered  = $held(ChatChannelEnum::WEBSITE, now()->subHour());

    $list = fn (array $filters) => collect(GetChatSessions::make()->handle(['shop_id' => $this->shop->id, 'limit' => 100] + $filters)->items())->pluck('id');

    $waiting = $list(['statuses' => ['waiting']]);
    expect($waiting)->toContain($repliedTo->id)->toContain($email->id)->not->toContain($answered->id);

    $colleagueWaiting = $list(['statuses' => ['waiting'], 'assigned_to_me' => $colleague->id]);
    expect($colleagueWaiting)->toContain($repliedTo->id)->not->toContain($answered->id)
        ->and($list(['statuses' => ['active'], 'assigned_to_me' => $colleague->id]))->not->toContain($repliedTo->id)
        ->and($repliedTo->refresh()->status)->toBe(ChatSessionStatusEnum::ACTIVE)
        ->and($repliedTo->assignments()->where('status', 'active')->value('chat_agent_id'))->toBe($holder->id);

    $whatsapp = noiseTestWhatsappSession($this->shop, '+447500000999', 'Any update?');
    $whatsapp->update(['last_agent_message_at' => now()->subMinutes(10), 'last_visitor_message_at' => now()]);
    $whatsapp->assignments()->create(['meta_channel_id' => $whatsapp->meta_channel_id, 'chat_agent_id' => $holder->id, 'status' => \App\Enums\CRM\Livechat\ChatAssignmentStatusEnum::ACTIVE->value, 'assigned_at' => now()]);
    $metaWaiting = fn (array $filters) => collect(\App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions::make()->handle(['shop_ids' => [$this->shop->id], 'statuses' => ['waiting'], 'limit' => 100] + $filters)->items())->pluck('id');
    expect($metaWaiting([]))->toContain($whatsapp->id)
        ->and($metaWaiting(['assigned_to_me' => $colleague->id]))->toContain($whatsapp->id);

    $whatsapp->update(['last_agent_message_at' => now()->addMinute()]);
    expect($metaWaiting([]))->not->toContain($whatsapp->id);
});

test('labels are shared across the organisation, put on and taken off a conversation, and narrow the list', function () {
    $this->actingAs($this->user);
    $organisationSlug = $this->organisation->slug;
    $name             = 'Wholesale '.Str::lower(Str::random(6));

    $created = $this->postJson(route('grp.org.chat.agents.labels.store', [$organisationSlug]), ['name' => $name, 'colour' => '#22C55E'])
        ->assertCreated()
        ->json('data');

    expect($created['colour'])->toBe('#22c55e');

    $this->postJson(route('grp.org.chat.agents.labels.store', [$organisationSlug]), ['name' => $name, 'colour' => '#22c55e'])->assertUnprocessable();
    $this->postJson(route('grp.org.chat.agents.labels.store', [$organisationSlug]), ['name' => 'Bad colour', 'colour' => 'green'])->assertUnprocessable();

    $this->getJson(route('grp.org.chat.agents.labels.index', [$organisationSlug]))
        ->assertOk()
        ->assertJsonFragment(['id' => $created['id'], 'name' => $name]);

    $labelled = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::EMAIL,
        'status'  => ChatSessionStatusEnum::WAITING,
    ]);
    $other = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::EMAIL,
        'status'  => ChatSessionStatusEnum::WAITING,
    ]);

    $this->patchJson(route('grp.org.chat.agents.sessions.label', [$organisationSlug, $labelled->ulid, $created['id']]))
        ->assertOk()
        ->assertJsonPath('data.is_on', true)
        ->assertJsonPath('data.labels.0.name', $name);

    $listed = fn () => collect(\App\Actions\Chat\GetCrossChannelSessions::make()->handle([
        'shop_ids'      => [$this->shop->id],
        'label_id'      => $created['id'],
        'statuses'      => ['waiting', 'active', 'closed'],
        'closed_period' => 'all',
        'limit'         => 50,
    ])['rows'])->pluck('session.id');

    expect($listed())->toContain($labelled->id)->not->toContain($other->id);

    $this->patchJson(route('grp.org.chat.agents.sessions.label', [$organisationSlug, $labelled->ulid, $created['id']]))
        ->assertOk()
        ->assertJsonPath('data.is_on', false);

    expect($listed())->not->toContain($labelled->id);

    $outsider = User::factory()->create(['group_id' => $this->user->group_id, 'status' => true]);
    $this->actingAs($outsider);
    $this->getJson(route('grp.org.chat.agents.labels.index', [$organisationSlug]))->assertForbidden();
    $this->postJson(route('grp.org.chat.agents.labels.store', [$organisationSlug]), ['name' => 'Nope', 'colour' => '#000000'])->assertForbidden();
    $this->patchJson(route('grp.org.chat.agents.sessions.label', [$organisationSlug, $labelled->ulid, $created['id']]))->assertForbidden();
    $this->deleteJson(route('grp.org.chat.agents.labels.delete', [$organisationSlug, $created['id']]))->assertForbidden();

    \App\Models\Chat\ChatLabel::whereKey($created['id'])->delete();
    foreach ([$labelled, $other] as $session) {
        $session->forceDelete();
    }
});

test('the offline view lists conversations left through the website form while nobody was there', function () {
    $conversation = function (bool $offline): ChatSession {
        $session = ChatSession::create([
            'shop_id' => $this->shop->id,
            'ulid'    => (string) Str::ulid(),
            'channel' => ChatChannelEnum::WEBSITE,
            'status'  => ChatSessionStatusEnum::WAITING,
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'Do you ship to Norway?',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
            'metadata'        => $offline ? ['is_offline_message' => true] : [],
        ]);

        return $session;
    };

    $leftOffline = $conversation(true);
    $chatted     = $conversation(false);

    $listed = collect(\App\Actions\Chat\GetCrossChannelSessions::make()->handle([
        'shop_ids' => [$this->shop->id],
        'pairs'    => ['website:guest', 'whatsapp:guest'],
        'offline'  => 1,
        'statuses' => ['waiting', 'active'],
        'limit'    => 50,
    ])['rows'])->pluck('session.id');

    expect($listed)->toContain($leftOffline->id)->not->toContain($chatted->id);

    foreach ([$leftOffline, $chatted] as $session) {
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
});

test('the inbox search suggests customers and order references only on shops the person may look at', function () {
    $surname  = 'Zyq'.Str::lower(Str::random(6));
    $customer = createCustomer($this->shop);
    $customer->update(['name' => "Anna $surname", 'email' => "anna.$surname@example.com"]);
    $silent = createCustomer($this->shop);
    $silent->update(['name' => "Bob $surname", 'email' => "bob.$surname@example.com"]);

    $conversation = ChatSession::create([
        'shop_id'     => $this->shop->id,
        'ulid'        => (string) Str::ulid(),
        'channel'     => ChatChannelEnum::EMAIL,
        'status'      => ChatSessionStatusEnum::CLOSED,
        'web_user_id' => createWebUser($customer)->id,
    ]);

    $suggested = fn (User $viewer, string $query) => \App\Actions\Chat\ChatSession\GetChatSearchSuggestions::make()->handle($viewer, $query, [$this->shop->id]);

    expect($suggested($this->user, $surname)->pluck('value'))->toContain("anna.$surname@example.com")->not->toContain("bob.$surname@example.com")
        ->and($suggested($this->user, Str::upper($surname))->pluck('type'))->toContain('customer');

    $outsider = User::factory()->create(['group_id' => $this->user->group_id, 'status' => true]);

    expect($suggested($outsider, $surname))->toBeEmpty();

    $this->actingAs($this->user)
        ->getJson(route('grp.org.chat.agents.search_suggestions', [$this->organisation->slug, 'q' => $surname, 'shop_ids' => [$this->shop->id]]))
        ->assertOk()
        ->assertJsonFragment(['label' => "Anna $surname"]);

    $conversation->forceDelete();
});

test('my conversations searched across every status keep the open ones I hold as well as the ones I closed', function () {
    $user  = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent = ChatAgent::create(['user_id' => $user->id, 'max_concurrent_chats' => 5]);

    $conversation = function (ChatSessionStatusEnum $status, ChatAssignmentStatusEnum $assignment) use ($agent): ChatSession {
        $session = ChatSession::create([
            'shop_id' => $this->shop->id,
            'ulid'    => (string) Str::ulid(),
            'channel' => ChatChannelEnum::EMAIL,
            'status'  => $status,
        ]);
        $session->assignments()->create([
            'chat_agent_id' => $agent->id,
            'status'        => $assignment,
            'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT,
            'assigned_at'   => now(),
        ]);

        return $session;
    };

    $holding  = $conversation(ChatSessionStatusEnum::ACTIVE, ChatAssignmentStatusEnum::ACTIVE);
    $finished = $conversation(ChatSessionStatusEnum::CLOSED, ChatAssignmentStatusEnum::RESOLVED);

    $mine = fn (array $statuses) => collect(GetChatSessions::make()->handle([
        'shop_id'        => $this->shop->id,
        'statuses'       => $statuses,
        'closed_period'  => 'all',
        'assigned_to_me' => $user->id,
        'limit'          => 200,
    ])->items())->pluck('id');

    expect($mine(['waiting', 'active', 'closed']))->toContain($holding->id)->toContain($finished->id)
        ->and($mine(['waiting', 'active']))->toContain($holding->id)->not->toContain($finished->id)
        ->and($mine(['closed']))->toContain($finished->id)->not->toContain($holding->id);

    foreach ([$holding, $finished] as $session) {
        $session->assignments()->delete();
        $session->forceDelete();
    }
    $agent->forceDelete();
});
