<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\GetAgentUnreadMessagesSummary;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatAgent;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\MetaChatSession\AssignMetaChatToAgent;
use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions;
use App\Actions\Chat\MetaChatSession\UpdateMetaChatSession;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Chat\MetaChatEvent;
use App\Models\Chat\MetaChatSession;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

    \Illuminate\Support\Facades\Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
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

test('an agent replying to a waiting whatsapp chat picks it up, but cannot write in one another agent holds', function () {
    $shopSettings         = $this->shop->settings;
    $organisationSettings = $this->organisation->settings;

    $this->shop->update(['settings' => array_merge($shopSettings ?? [], [
        'whatsapp' => ['phone_number_id' => '111', 'waba_id' => '222'],
    ])]);
    $this->organisation->update(['settings' => array_merge($organisationSettings ?? [], [
        'meta' => ['access_key' => 'token'],
    ])]);

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.CLAIM']]])]);

    $channel   = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $otherUser = User::factory()->create(['group_id' => $this->organisation->group_id]);

    actingAs($this->user);
    makeChatWorker($this->user, $this->shop);

    $mine  = ChatAgent::where('user_id', $this->user->id)->first()
        ?? StoreChatAgent::make()->handle(['user_id' => $this->user->id]);
    $other = StoreChatAgent::make()->handle(['user_id' => $otherUser->id]);

    $makeWaitingSession = function (string $phone) use ($channel) {
        $session = MetaChatSession::create([
            'ulid'            => (string)Str::ulid(),
            'meta_channel_id' => $channel->id,
            'shop_id'         => $this->shop->id,
            'phone_number'    => $phone,
            'status'          => ChatSessionStatusEnum::WAITING,
            'language_id'     => 68,
            'priority'        => ChatPriorityEnum::NORMAL,
        ]);

        $session->messages()->create([
            'meta_channel_id' => $channel->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'Is my order on its way?',
            'created_at'      => now()->subMinutes(5),
        ]);

        return $session;
    };

    $waiting = $makeWaitingSession('+628777000111');

    $this->postJson(route('grp.org.chat.agents.whatsapp.messages.send', [$this->organisation->slug, $waiting->ulid]), [
        'message_text' => 'Yes, it left this morning',
    ])->assertSuccessful();

    expect($waiting->refresh()->status)->toBe(ChatSessionStatusEnum::ACTIVE)
        ->and($waiting->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->pluck('chat_agent_id')->all())->toBe([$mine->id]);

    $heldByOther = $makeWaitingSession('+628777000222');
    $heldByOther->assignments()->create([
        'meta_channel_id' => $channel->id,
        'chat_agent_id'   => $other->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    $this->postJson(route('grp.org.chat.agents.whatsapp.messages.send', [$this->organisation->slug, $heldByOther->ulid]), [
        'message_text' => 'Let me check',
    ])->assertForbidden();

    expect($heldByOther->messages()->where('sender_type', ChatSenderTypeEnum::AGENT->value)->count())->toBe(0)
        ->and(fn () => $heldByOther->assignments()->create([
            'meta_channel_id' => $channel->id,
            'chat_agent_id'   => $mine->id,
            'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
            'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
            'assigned_at'     => now(),
        ]))->toThrow(UniqueConstraintViolationException::class);

    $this->shop->update(['settings' => $shopSettings]);
    $this->organisation->update(['settings' => $organisationSettings]);
});

test('a phone video is converted to an mp4 and sent to WhatsApp as a video, and one still over 16MB is refused', function () {
    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], [
        'whatsapp' => ['phone_number_id' => '111', 'waba_id' => '222'],
    ])]);
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], [
        'meta' => ['access_key' => 'token'],
    ])]);

    Http::fake([
        'graph.facebook.com/*/media'    => Http::response(['id' => 'media-video-1']),
        'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.VIDEO']]]),
    ]);

    $converted = tempnam(sys_get_temp_dir(), 'wa-test-').'.mp4';
    file_put_contents($converted, str_repeat('v', 2048));

    $normaliser = \App\Actions\Chat\Whatsapp\NormaliseWhatsappVideo::partialMock();
    $normaliser->shouldReceive('transcode')->once()->andReturn($converted);

    actingAs($this->user);
    makeChatWorker($this->user, $this->shop);
    ChatAgent::where('user_id', $this->user->id)->exists() || StoreChatAgent::make()->handle(['user_id' => $this->user->id]);

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $session = MetaChatSession::create([
        'ulid'                   => (string)Str::ulid(),
        'meta_channel_id'        => $channel->id,
        'shop_id'                => $this->shop->id,
        'phone_number'           => '+447903000123',
        'status'                 => ChatSessionStatusEnum::WAITING,
        'language_id'            => 68,
        'priority'               => ChatPriorityEnum::NORMAL,
    ]);
    $session->messages()->create([
        'meta_channel_id' => $channel->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Can I see a video of the bowls?',
    ]);

    $this->post(route('grp.org.chat.agents.whatsapp.messages.send', [$this->organisation->slug, $session->ulid]), [
        'message_text' => 'Here it is',
        'video'        => \Illuminate\Http\UploadedFile::fake()->create('IMG_1348.MOV', 11890, 'video/quicktime'),
    ], ['Accept' => 'application/json'])->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages')
        && $request['type'] === 'video'
        && $request['video'] === ['id' => 'media-video-1', 'caption' => 'Here it is']);

    $sent = $session->messages()->where('sender_type', ChatSenderTypeEnum::AGENT->value)->latest('id')->first();

    expect($sent->message_type)->toBe(ChatMessageTypeEnum::FILE)
        ->and($sent->metadata['wa_type'])->toBe('video')
        ->and($sent->attachment->name)->toBe('IMG_1348.mp4')
        ->and(file_exists($converted))->toBeFalse();

    $tooBig = tempnam(sys_get_temp_dir(), 'wa-test-').'.mp4';
    file_put_contents($tooBig, '');
    $handle = fopen($tooBig, 'r+');
    ftruncate($handle, 17 * 1024 * 1024);
    fclose($handle);

    $normaliser->shouldReceive('transcode')->once()->andReturn($tooBig);

    $this->post(route('grp.org.chat.agents.whatsapp.messages.send', [$this->organisation->slug, $session->ulid]), [
        'video' => \Illuminate\Http\UploadedFile::fake()->create('long.mov', 40000, 'video/quicktime'),
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('message', __('This video is still over 16MB once compressed, which is the most WhatsApp takes. Send a shorter clip, or send it by email.'));

    expect(file_exists($tooBig))->toBeFalse();
});

test('my chats excludes a whatsapp thread now held by another agent', function () {
    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $otherUser = User::factory()->create(['group_id' => $this->organisation->group_id]);

    $mine  = ChatAgent::where('user_id', $this->user->id)->first()
        ?? StoreChatAgent::make()->handle(['user_id' => $this->user->id]);
    $other = StoreChatAgent::make()->handle(['user_id' => $otherUser->id]);

    makeChatWorker($this->user, $this->shop);
    makeChatWorker($otherUser, $this->shop);

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

test('a WhatsApp chat started from a Meta ad keeps the ad it came from', function () {
    Bus::fake();
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['whatsapp' => ['phone_number_id' => '123']])]);

    $referral = [
        'source_url'    => 'https://fb.me/ad-lavender',
        'source_id'     => '120210000000000',
        'source_type'   => 'ad',
        'headline'      => 'Lavender Oil Gift Set',
        'body'          => 'Wholesale prices for your shop',
        'media_type'    => 'image',
        'image_url'     => 'https://scontent.xx.fbcdn.net/lavender.jpg',
    ];

    \App\Actions\Chat\Whatsapp\StoreIncomingWhatsappMessage::make()->handle([
        'metadata' => ['phone_number_id' => '123'],
        'contacts' => [['profile' => ['name' => 'Zubz']]],
        'messages' => [[
            'id'       => 'wamid.ctwa-'.Str::random(8),
            'from'     => '447500000009',
            'type'     => 'text',
            'text'     => ['body' => 'Hello! Can I get more info on this?'],
            'referral' => $referral,
        ]],
    ]);

    $message = \App\Models\Chat\MetaChatMessage::where('meta_message_id', 'like', 'wamid.ctwa-%')->latest('id')->first();

    expect($message->metadata['wa_referral'])->toBe($referral);

    $message->metaChatSession->messages()->forceDelete();
    $message->metaChatSession->forceDelete();
});

test('a WhatsApp chat from a phone shared by two accounts links to the one that buys', function () {
    Bus::fake();
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['whatsapp' => ['phone_number_id' => '123']])]);

    StoreCustomer::make()->action($this->shop, array_merge(Customer::factory()->definition(), ['phone' => '+447500000077']));
    $buyer   = StoreCustomer::make()->action($this->shop, array_merge(Customer::factory()->definition(), ['phone' => '+447500000077']));
    StoreCustomer::make()->action($this->shop, array_merge(Customer::factory()->definition(), ['phone' => '+447500000077']));
    $buyer->update(['last_invoiced_at' => now()->subDays(3)]);

    \App\Actions\Chat\Whatsapp\StoreIncomingWhatsappMessage::make()->handle([
        'metadata' => ['phone_number_id' => '123'],
        'contacts' => [['profile' => ['name' => 'Jenny']]],
        'messages' => [[
            'id'   => 'wamid.shared-'.Str::random(8),
            'from' => '447500000077',
            'type' => 'text',
            'text' => ['body' => 'My diffuser stopped working'],
        ]],
    ]);

    $session = MetaChatSession::where('shop_id', $this->shop->id)->where('phone_number', '+447500000077')->latest('id')->first();

    expect($session->customer_id)->toBe($buyer->id);

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('a WhatsApp reaction from the customer opens the 24 hour window so the agent can answer', function () {
    Bus::fake();
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastMetaChatReaction::class]);
    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['whatsapp' => ['phone_number_id' => '123']])]);

    $session = noiseTestWhatsappSession($this->shop, '+447500000481', 'Hello');
    $session->messages()->update(['created_at' => now()->subDays(2)]);
    $session->update(['last_visitor_message_at' => now()->subDays(2)]);
    $template = $session->messages()->create([
        'meta_channel_id' => $session->meta_channel_id,
        'meta_message_id' => 'wamid.template-'.Str::random(8),
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::AGENT,
        'message_text'    => 'Please message us back so we can continue the conversation',
    ]);

    expect($session->refresh()->can_send_non_template_message)->toBeFalse();

    \App\Actions\Chat\Whatsapp\StoreIncomingWhatsappMessage::make()->handle([
        'metadata' => ['phone_number_id' => '123'],
        'messages' => [[
            'id'       => 'wamid.reaction-'.Str::random(8),
            'from'     => '447500000481',
            'type'     => 'reaction',
            'reaction' => ['message_id' => $template->meta_message_id, 'emoji' => '❤️'],
        ]],
    ]);

    expect($session->refresh()->can_send_non_template_message)->toBeTrue();
    \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\BroadcastMetaChatReaction::class, fn ($event) => $event->broadcastWith()['can_send_non_template_message'] === true);

    $template->reactions()->delete();
    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('a WhatsApp send Meta could not deliver tells the agent why, live', function () {
    Event::fake([\App\Events\BroadcastMetaChatMessageStatus::class]);

    $session = noiseTestWhatsappSession($this->shop, '+447500000483', 'Hello');
    $message = $session->messages()->create([
        'meta_channel_id' => $session->meta_channel_id,
        'meta_message_id' => 'wamid.undeliverable-'.Str::random(8),
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::AGENT,
        'message_text'    => 'We are now back!',
    ]);

    \App\Actions\Chat\Whatsapp\UpdateWhatsappMessageStatus::make()->handle(['statuses' => [[
        'id'        => $message->meta_message_id,
        'status'    => 'failed',
        'timestamp' => (string) now()->timestamp,
        'errors'    => [['code' => 131026, 'title' => 'Message undeliverable', 'message' => 'Message undeliverable']],
    ]]]);

    Event::assertDispatched(\App\Events\BroadcastMetaChatMessageStatus::class, fn ($event) => $event->broadcastWith()['status'] === 'failed'
        && $event->broadcastWith()['error']['code'] === 131026);

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('the WhatsApp reply window counts down from the customer message and our replies do not extend it', function () {
    $this->freezeSecond();

    $session = noiseTestWhatsappSession($this->shop, '+447500000482', 'The boxes arrived damaged');
    $customerSaidAt = now()->subDay()->addMinute();
    $session->messages()->update(['created_at' => $customerSaidAt]);
    $session->update(['last_visitor_message_at' => $customerSaidAt]);
    $session->messages()->create([
        'meta_channel_id' => $session->meta_channel_id,
        'meta_message_id' => 'wamid.reply-'.Str::random(8),
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::AGENT,
        'message_text'    => 'Sorry to hear that',
    ]);

    $session->refresh();
    expect($session->can_send_non_template_message)->toBeTrue()
        ->and($session->whatsapp_window_seconds_left)->toBe(60);

    $this->travel(61)->seconds();

    $session->refresh();
    expect($session->can_send_non_template_message)->toBeFalse()
        ->and($session->whatsapp_window_seconds_left)->toBe(0);

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('whatsapp calls ring for staff with the calls position but not for chat agents or shop admins', function () {
    setPermissionsTeamId($this->user->group_id);

    $caller = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $caller->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CALLER->value, $this->shop));

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $shopAdmin = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $shopAdmin->assignRole(RolesEnum::getRoleName(RolesEnum::SHOP_ADMIN->value, $this->shop));

    expect(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($caller)['customer_service_shops'])->toContain($this->shop->id)
        ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($agent)['customer_service_shops'])->not->toContain($this->shop->id)
        ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($shopAdmin)['customer_service_shops'])->not->toContain($this->shop->id);
});

test('an agent marks a WhatsApp conversation as unread again', function () {
    $agentUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $chatAgent = ChatAgent::create(['user_id' => $agentUser->id, 'max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]);
    makeChatWorker($agentUser, $this->shop);

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $session = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+4219'.random_int(10000000, 99999999),
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);
    $write = fn (ChatSenderTypeEnum $sender, int $minutesAgo) => $session->messages()->create([
        'meta_channel_id' => $channel->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => $sender->value,
        'message_text'    => 'Message from '.$sender->value,
        'is_read'         => true,
        'read_at'         => now()->subMinutes(2),
        'created_at'      => now()->subMinutes($minutesAgo),
        'updated_at'      => now()->subMinutes($minutesAgo),
    ]);

    $answeredBefore = $write(ChatSenderTypeEnum::GUEST, 40);
    $ourReply       = $write(ChatSenderTypeEnum::AGENT, 30);
    $stillWaiting   = $write(ChatSenderTypeEnum::GUEST, 20);

    $before = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    Http::fake();
    Event::fake([\App\Events\BroadcastMetaChatListEvent::class]);
    actingAs($agentUser);

    $this->patchJson(route('grp.org.chat.agents.whatsapp.sessions.unread', [$this->organisation->slug, $session->ulid]))
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);

    $after = GetAgentUnreadMessagesSummary::make()->handle($chatAgent);

    expect($after['by_channel']['whatsapp']['unassigned'])->toBe($before['by_channel']['whatsapp']['unassigned'] + 1)
        ->and($after['total_unread_count'])->toBe($before['total_unread_count'] + 1)
        ->and($stillWaiting->refresh()->is_read)->toBeFalse()
        ->and($stillWaiting->read_at)->toBeNull()
        ->and($answeredBefore->refresh()->is_read)->toBeTrue()
        ->and($ourReply->refresh()->is_read)->toBeTrue()
        ->and($ourReply->read_at)->not->toBeNull();

    Event::assertDispatched(\App\Events\BroadcastMetaChatListEvent::class, 1);
    Http::assertNothingSent();
});


test('an agent sets and clears a wait for the customer on a WhatsApp conversation', function () {
    $agentUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    ChatAgent::create(['user_id' => $agentUser->id, 'max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => true, 'is_available' => true, 'current_chat_count' => 0]);
    makeChatWorker($agentUser, $this->shop);

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $session = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+4219'.random_int(10000000, 99999999),
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    \Illuminate\Support\Facades\Queue::fake();
    Http::fake();
    Event::fake([\App\Events\BroadcastMetaChatListEvent::class]);
    actingAs($agentUser);

    $route = route('grp.org.chat.agents.whatsapp.sessions.wait_for_customer', [$this->organisation->slug, $session->ulid]);

    $this->patchJson($route)->assertUnprocessable();

    $this->patchJson($route, ['hours' => 24])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('waiting_until', fn ($until) => $until !== null);

    $this->patchJson($route, ['hours' => null])
        ->assertOk()
        ->assertJsonPath('waiting_until', null);
});

test('a WhatsApp contact who shows only a username gets a conversation and the agent reply goes to their user id', function () {
    Bus::fake();
    Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class, \App\Events\BroadcastWhatsappCallEvent::class]);
    $shopSettings         = $this->shop->settings;
    $organisationSettings = $this->organisation->settings;
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $this->shop->update(['settings' => array_merge($shopSettings ?? [], ['whatsapp' => ['phone_number_id' => '123', 'waba_id' => '222']])]);
    $this->organisation->update(['settings' => array_merge($organisationSettings ?? [], ['meta' => ['access_key' => 'token']])]);

    $whatsappUserId = 'NP.'.random_int(1000000000, 9999999999);
    $inbound        = fn (array $message) => \App\Actions\Chat\Whatsapp\StoreIncomingWhatsappMessage::make()->handle([
        'metadata' => ['phone_number_id' => '123'],
        'contacts' => [['profile' => ['name' => 'Nepal Beads', 'username' => 'nepalbeads'], 'user_id' => $whatsappUserId]],
        'messages' => [array_merge(['id' => 'wamid.bsuid-'.Str::random(8), 'type' => 'text', 'text' => ['body' => 'Do you sell beads?']], $message)],
    ]);

    $inbound(['from_user_id' => $whatsappUserId]);
    $inbound(['from_user_id' => $whatsappUserId, 'text' => ['body' => 'Wholesale please']]);

    $session = MetaChatSession::where('whatsapp_user_id', $whatsappUserId)->sole();

    expect($session->phone_number)->toBeNull()
        ->and($session->guest_identifier)->toBe('Nepal Beads')
        ->and($session->messages()->count())->toBe(2);

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TO-USERNAME']], 'calls' => [['id' => 'wacid.TO-USERNAME']]])]);
    actingAs($this->user);
    makeChatWorker($this->user, $this->shop);

    $this->postJson(route('grp.org.chat.agents.whatsapp.messages.send', [$this->organisation->slug, $session->ulid]), [
        'message_text' => 'Yes, we do',
    ])->assertSuccessful();

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => str_ends_with($request->url(), '/messages')
        && $request['recipient'] === $whatsappUserId
        && !isset($request['to']));

    $found = fn (string $search) => collect(GetMetaChatSessions::make()->handle(['search' => $search, 'shop_id' => $this->shop->id])->items())->pluck('ulid');

    expect($session->refresh()->metadata['whatsapp_username'])->toBe('nepalbeads')
        ->and($found('@NepalBeads'))->toContain($session->ulid)
        ->and($found($whatsappUserId))->toContain($session->ulid);

    $call = \App\Actions\Chat\Whatsapp\Calls\StartWhatsappCall::make()->handle($session, $this->user, 'v=0');

    expect($call['ok'])->toBeTrue()
        ->and((new \App\Events\BroadcastWhatsappCallEvent(\App\Models\Chat\MetaChatCall::findOrFail($call['data']['id'])))->broadcastWith()['caller'])->toBe('Nepal Beads');
    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => str_ends_with($request->url(), '/calls')
        && $request['recipient'] === $whatsappUserId
        && !isset($request['to']));

    $campaignAudience = \App\Actions\Comms\WhatsappCampaign\GetWhatsappRecipientsQuery::run($this->shop, ['contacted' => true], [])->get();

    expect($campaignAudience->pluck('meta_chat_session_id'))->not->toContain($session->id);

    $inbound(['from' => '9779800000001', 'from_user_id' => $whatsappUserId]);

    expect(MetaChatSession::where('whatsapp_user_id', $whatsappUserId)->sole()->phone_number)->toBe('+9779800000001');

    \App\Models\Chat\MetaChatCall::where('meta_chat_session_id', $session->id)->delete();
    $session->assignments()->delete();
    $session->messages()->forceDelete();
    $session->forceDelete();
    $this->shop->update(['settings' => $shopSettings]);
    $this->organisation->update(['settings' => $organisationSettings]);
});

test('the files of a WhatsApp conversation come back newest first and are closed to staff without chat rights', function () {
    $shopId = $this->shop->id;
    $phone  = fn () => '+628111'.random_int(100000, 999999);

    $session = noiseTestWhatsappSession($this->shop, $phone(), 'hello');
    $other   = noiseTestWhatsappSession($this->shop, $phone(), 'hello');

    $makeMessage = function (MetaChatSession $chatSession, ChatSenderTypeEnum $sender, string $when) {
        return \App\Models\Chat\MetaChatMessage::create([
            'meta_chat_session_id' => $chatSession->id,
            'meta_channel_id'      => $chatSession->meta_channel_id,
            'message_type'         => ChatMessageTypeEnum::FILE,
            'sender_type'          => $sender,
            'message_text'         => 'file',
            'created_at'           => $when,
        ]);
    };

    $older       = $makeMessage($session, ChatSenderTypeEnum::GUEST, now()->subHour());
    $olderMedia  = chatFileOnMessage($older, 'photo.png');
    $older->update(['media_id' => $olderMedia->id]);

    $newer       = $makeMessage($session, ChatSenderTypeEnum::AGENT, now()->subMinutes(10));
    $newerMedia  = chatFileOnMessage($newer, 'quote.pdf', false);
    $newer->update(['media_id' => $newerMedia->id]);

    $stranger      = $makeMessage($other, ChatSenderTypeEnum::GUEST, now());
    $strangerMedia = chatFileOnMessage($stranger, 'elsewhere.png');
    $stranger->update(['media_id' => $strangerMedia->id]);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    actingAsUserWithOnlyPermissions($user, ["chat.$shopId.view"]);
    $this->actingAs($user, 'sanctum');

    $data = \Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.files', $session->ulid))->assertOk()->json('data');

    expect(array_column($data, 'id'))->toBe([$newerMedia->id, $olderMedia->id])
        ->and($data[0])->toMatchArray(['from' => 'agent', 'is_image' => false, 'file_name' => 'quote.pdf', 'message_id' => $newer->id])
        ->and($data[1])->toMatchArray([
            'from'         => 'customer',
            'is_image'     => true,
            'download_url' => route('grp.api.chats.chat.attachment.download', ['ulid' => $olderMedia->ulid]),
        ])
        ->and($data[1]['thumbnail'])->not->toBeNull();

    actingAsUserWithOnlyPermissions($user, ["products.$shopId.view"]);
    $this->actingAs($user, 'sanctum');
    \Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.files', $session->ulid))->assertForbidden();
    \Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.tickets', $session->ulid))->assertForbidden();
});

test('a binned WhatsApp contact who writes again gets their old conversation back instead of a second one', function () {
    Bus::fake();
    Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    $shopSettings = $this->shop->settings;
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $this->shop->update(['settings' => array_merge($shopSettings ?? [], ['whatsapp' => ['phone_number_id' => '123']])]);

    $digits  = '4475000'.random_int(10000, 99999);
    $inbound = fn (string $text) => \App\Actions\Chat\Whatsapp\StoreIncomingWhatsappMessage::make()->handle([
        'metadata' => ['phone_number_id' => '123'],
        'contacts' => [['profile' => ['name' => 'Binned Contact']]],
        'messages' => [['id' => 'wamid.binned-'.Str::random(8), 'from' => $digits, 'type' => 'text', 'text' => ['body' => $text]]],
    ]);

    $inbound('First question');
    $session = MetaChatSession::where('phone_number', '+'.$digits)->sole();
    $session->delete();

    $inbound('Hello again');

    $sessions = MetaChatSession::withTrashed()->where('phone_number', '+'.$digits)->get();

    expect($sessions)->toHaveCount(1)
        ->and($sessions->first()->id)->toBe($session->id)
        ->and($sessions->first()->trashed())->toBeFalse()
        ->and($session->messages()->count())->toBe(2)
        ->and($session->events()->where('event_type', ChatEventTypeEnum::RESTORE)->exists())->toBeTrue();

    $session->events()->delete();
    $session->messages()->forceDelete();
    $session->forceDelete();
    $this->shop->update(['settings' => $shopSettings]);
});

test('two conversations of one WhatsApp contact merge into the open one, and a different contact never does', function () {
    Bus::fake();
    Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    $shopSettings = $this->shop->settings;
    $this->shop->update(['settings' => array_merge($shopSettings ?? [], ['whatsapp' => ['phone_number_id' => '123']])]);

    $digits   = '4475001'.random_int(10000, 99999);
    $open     = noiseTestWhatsappSession($this->shop, '+'.$digits, 'I need help with my order');
    $older    = noiseTestWhatsappSession($this->shop, $digits, 'Earlier question');
    $stranger = noiseTestWhatsappSession($this->shop, '+4475002'.random_int(10000, 99999), 'Someone else');
    $older->delete();

    actingAs($this->user);
    makeChatWorker($this->user, $this->shop);
    $this->actingAs($this->user, 'sanctum');

    $duplicates = \Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.duplicates', $open->ulid))->assertOk()->json();

    expect(array_column($duplicates, 'ulid'))->toBe([$older->ulid])
        ->and($duplicates[0])->toMatchArray(['messages_count' => 1, 'is_binned' => true]);

    \Pest\Laravel\postJson(route('grp.api.chats.meta.sessions.merge', $open->ulid), ['duplicate_ulid' => $stranger->ulid])->assertStatus(422);
    \Pest\Laravel\postJson(route('grp.api.chats.meta.sessions.merge', $open->ulid), ['duplicate_ulid' => $older->ulid])->assertOk();

    $older = MetaChatSession::withTrashed()->findOrFail($older->id);

    expect($open->messages()->count())->toBe(2)
        ->and($older->messages()->count())->toBe(0)
        ->and($older->trashed())->toBeTrue()
        ->and($older->metadata[\App\Actions\Chat\MetaChatSession\MergeMetaChatSessions::MERGED_INTO_KEY])->toBe($open->ulid)
        ->and($open->events()->where('payload->action_type', 'conversation_merged')->exists())->toBeTrue()
        ->and(\Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.duplicates', $open->ulid))->json())->toBe([]);

    \Pest\Laravel\patchJson(route('grp.org.chat.agents.whatsapp.sessions.restore', [$this->organisation->slug, $older->ulid]))->assertStatus(422);

    $open->delete();

    \App\Actions\Chat\Whatsapp\StoreIncomingWhatsappMessage::make()->handle([
        'metadata' => ['phone_number_id' => '123'],
        'contacts' => [['profile' => ['name' => 'Merged Contact']]],
        'messages' => [['id' => 'wamid.merged-'.Str::random(8), 'from' => $digits, 'type' => 'text', 'text' => ['body' => 'Still there?']]],
    ]);

    expect(MetaChatSession::find($open->id))->not->toBeNull()
        ->and(MetaChatSession::find($older->id))->toBeNull()
        ->and($open->messages()->count())->toBe(3);

    foreach ([$open, $older, $stranger] as $session) {
        $session->events()->delete();
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
    $this->shop->update(['settings' => $shopSettings]);
});

test('an agent finds the account of a WhatsApp contact by hand and can unlink it again', function () {
    $session = noiseTestWhatsappSession($this->shop, '+4475003'.random_int(10000, 99999), 'Where is my parcel?');

    actingAs($this->user);
    makeChatWorker($this->user, $this->shop);
    $this->actingAs($this->user, 'sanctum');

    $candidates = \Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.customer_candidates', [$session->ulid, 'q' => $this->customer->reference]))
        ->assertOk()->json();

    expect(array_column($candidates, 'id'))->toContain($this->customer->id);

    \Pest\Laravel\putJson(route('grp.api.chats.meta.sessions.customer.link', $session->ulid), ['customer_id' => $this->customer->id])
        ->assertOk()
        ->assertJsonPath('data.customer.id', $this->customer->id);

    expect($session->refresh()->customer_id)->toBe($this->customer->id);

    \Pest\Laravel\deleteJson(route('grp.api.chats.meta.sessions.customer.unlink', $session->ulid))->assertOk();

    expect($session->refresh()->customer_id)->toBeNull()
        ->and($session->suggestion_rejected_at)->not->toBeNull()
        ->and($session->events()->where('payload->action_type', 'customer_unlinked')->exists())->toBeTrue();

    $session->events()->delete();
    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('the history beside a WhatsApp conversation lists the same person on email too, and only for staff with chat on the shop', function () {
    $shopId  = $this->shop->id;
    $email   = 'history-'.Str::lower(Str::random(8)).'@example.com';
    $this->customer->update(['email' => $email]);

    $whatsapp = noiseTestWhatsappSession($this->shop, '+4475004'.random_int(10000, 99999), 'Hi on WhatsApp');
    $whatsapp->update(['customer_id' => $this->customer->id]);

    $emailSession = noiseTestEmailSession($this->shop, $email, 'Order question', 'Hi by email');
    $emailSession->update(['metadata' => array_merge($emailSession->metadata, ['email' => Str::upper($email)])]);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    actingAsUserWithOnlyPermissions($user, ["chat.$shopId.view"]);
    $this->actingAs($user, 'sanctum');

    $ulids = collect(\Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.history', $whatsapp->ulid))->assertOk()->json('data.sessions'))->pluck('ulid');

    expect($ulids)->toContain($emailSession->ulid)
        ->and($ulids)->not->toContain($whatsapp->ulid);

    $secondEmail = noiseTestEmailSession($this->shop, $email, 'Another question', 'Hi again');
    $secondEmail->update(['metadata' => array_merge($secondEmail->metadata, ['email' => $email]), 'last_visitor_message_at' => now()->subDay()]);
    $paged = fn (int $page) => \Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.history', [$whatsapp->ulid, 'limit' => 1, 'page' => $page]))->assertOk()->json('data');

    expect(array_merge(array_column($paged(1)['sessions'], 'ulid'), array_column($paged(2)['sessions'], 'ulid')))->toBe([$emailSession->ulid, $secondEmail->ulid])
        ->and($paged(1)['pagination']['has_more'])->toBeTrue()
        ->and($paged(2)['pagination']['has_more'])->toBeFalse();

    actingAsUserWithOnlyPermissions($user, ["products.$shopId.view"]);
    $this->actingAs($user, 'sanctum');
    \Pest\Laravel\getJson(route('grp.api.chats.meta.sessions.history', $whatsapp->ulid))->assertForbidden();

    $whatsapp->messages()->forceDelete();
    $whatsapp->forceDelete();
    foreach ([$emailSession, $secondEmail] as $chatSession) {
        $chatSession->messages()->forceDelete();
        $chatSession->forceDelete();
    }
});

test('a WhatsApp account only counts as subscribed when the organisation\'s own Meta app is among its subscribers', function () {
    $this->organisation->update([
        'settings' => array_merge($this->organisation->settings ?? [], [
            'meta' => ['app_id' => '1062209193190372', 'access_key' => 'test-token'],
        ]),
    ]);
    $this->shop->update([
        'settings' => array_merge($this->shop->settings ?? [], [
            'whatsapp' => ['waba_id' => '1680815520097856', 'phone_number_id' => '1332362659970687'],
        ]),
    ]);
    $shop = $this->shop->fresh();

    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['data' => [['whatsapp_business_api_data' => ['id' => '999', 'name' => 'Old provider']]]])
        ->push(['data' => [
            ['whatsapp_business_api_data' => ['id' => '999', 'name' => 'Old provider']],
            ['whatsapp_business_api_data' => ['id' => '1062209193190372', 'name' => 'Aiku ES']],
        ]])
        ->push(['status' => 'CONNECTED', 'platform_type' => 'CLOUD_API'])]);

    $otherAppOnly = \App\Actions\Chat\Whatsapp\GetWhatsappSubscribedApps::make()->handle($shop);
    expect($otherAppOnly['data']['subscribed'])->toBeFalse()
        ->and($otherAppOnly['data']['apps'])->toBe([['id' => '999', 'name' => 'Old provider']]);

    expect(\App\Actions\Chat\Whatsapp\GetWhatsappSubscribedApps::make()->handle($shop)['data']['subscribed'])->toBeTrue();

    expect(\App\Actions\Chat\Whatsapp\GetWhatsappPhoneNumberStatus::make()->handle($shop)['data']['platform_type'])->toBe('CLOUD_API');
});

/**
 * Puts the test shop on a Coexistence number and hands back the settings it had, so the test can
 * put them back: every later test in the worker shares this shop.
 *
 * @return array<string, mixed>|null
 */
function useCoexistenceNumber(\App\Models\Catalogue\Shop $shop, string $phoneNumberId, string $mode = 'coexistence'): ?array
{
    $original = $shop->settings;

    $shop->update(['settings' => array_merge($shop->settings ?? [], [
        'whatsapp' => ['phone_number_id' => $phoneNumberId, 'waba_id' => 'waba-'.$phoneNumberId, 'mode' => $mode],
    ])]);

    return $original;
}

test('a reply typed on the WhatsApp Business app is filed as a staff reply without the customer message side effects', function () {
    \Illuminate\Support\Facades\Queue::fake();
    Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $phoneNumberId = 'coex-'.Str::random(8);
    $original      = useCoexistenceNumber($this->shop, $phoneNumberId);

    $session = StoreMetaChatSession::run(['shop_id' => $this->shop->id, 'phone_number' => '+447500000188']);
    $session->update(['status' => ChatSessionStatusEnum::CLOSED]);

    $echo = [
        'metadata'       => ['phone_number_id' => $phoneNumberId, 'display_phone_number' => '34678502048'],
        'message_echoes' => [[
            'from'      => '34678502048',
            'to'        => '447500000188',
            'id'        => 'wamid.echo-'.Str::random(8),
            'timestamp' => (string) now()->timestamp,
            'type'      => 'text',
            'text'      => ['body' => 'Your parcel left this morning'],
        ]],
    ];

    \App\Actions\Chat\Whatsapp\StoreWhatsappMessageEcho::make()->handle($echo);
    \App\Actions\Chat\Whatsapp\StoreWhatsappMessageEcho::make()->handle($echo);

    $messages = $session->messages()->get();

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->sender_type)->toBe(ChatSenderTypeEnum::AGENT)
        ->and($messages->first()->message_text)->toBe('Your parcel left this morning')
        ->and($messages->first()->is_read)->toBeTrue()
        ->and($session->fresh()->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($session->fresh()->last_agent_message_at)->not->toBeNull();

    \App\Actions\Chat\ChatSession\SendOutOfHoursReply::assertNotPushed();
    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::assertNotPushed();
    \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::assertNotPushed();
    \App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::assertNotPushed();

    $session->messages()->forceDelete();
    $session->forceDelete();
    $this->shop->update(['settings' => $original]);
});

test('a message echo for a Cloud API number is ignored', function () {
    \Illuminate\Support\Facades\Queue::fake();
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $phoneNumberId = 'cloud-'.Str::random(8);
    $original      = useCoexistenceNumber($this->shop, $phoneNumberId, 'cloud_api');
    $waMessageId   = 'wamid.echo-'.Str::random(8);

    \App\Actions\Chat\Whatsapp\StoreWhatsappMessageEcho::make()->handle([
        'metadata'       => ['phone_number_id' => $phoneNumberId],
        'message_echoes' => [['to' => '447500000189', 'id' => $waMessageId, 'type' => 'text', 'text' => ['body' => 'Hi']]],
    ]);

    expect(\App\Models\Chat\MetaChatMessage::where('meta_message_id', $waMessageId)->exists())->toBeFalse();

    $this->shop->update(['settings' => $original]);
});

test('the WhatsApp Business app history is filed as dated, read messages on a closed thread', function () {
    \Illuminate\Support\Facades\Queue::fake();
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $phoneNumberId = 'coex-'.Str::random(8);
    $original      = useCoexistenceNumber($this->shop, $phoneNumberId);
    $sentAt        = now()->subMonths(2)->startOfMinute();

    \App\Actions\Chat\Whatsapp\ImportWhatsappChatHistory::make()->handle([
        'metadata' => ['phone_number_id' => $phoneNumberId, 'display_phone_number' => '+34 678 50 20 48'],
        'history'  => [
            ['errors' => [['code' => 2593109, 'title' => 'History sync is turned off by the business from the WhatsApp Business App.']]],
            [
                'metadata' => ['phase' => 1, 'chunk_order' => 1, 'progress' => 100],
                'threads'  => [[
                    'id'       => '447500000190',
                    'messages' => [
                        ['from' => '447500000190', 'id' => 'wamid.hist-'.Str::random(8), 'timestamp' => (string) $sentAt->timestamp, 'type' => 'text', 'text' => ['body' => 'Do you ship to Leeds?'], 'history_context' => ['status' => 'read']],
                        ['from' => '34678502048', 'to' => '447500000190', 'id' => 'wamid.hist-'.Str::random(8), 'timestamp' => (string) $sentAt->copy()->addMinutes(5)->timestamp, 'type' => 'media_placeholder', 'history_context' => ['status' => 'read']],
                    ],
                ]],
            ],
        ],
    ]);

    $session  = MetaChatSession::where('shop_id', $this->shop->id)->where('phone_number', '+447500000190')->latest('id')->first();
    $messages = $session->messages()->orderBy('created_at')->get();

    expect($session->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($messages)->toHaveCount(2)
        ->and($messages[0]->sender_type)->toBe(ChatSenderTypeEnum::GUEST)
        ->and($messages[0]->created_at->timestamp)->toBe($sentAt->timestamp)
        ->and($messages[1]->sender_type)->toBe(ChatSenderTypeEnum::AGENT)
        ->and($messages[1]->message_text)->not->toBeEmpty()
        ->and($messages->every(fn ($message) => $message->is_read))->toBeTrue()
        ->and($session->last_visitor_message_at->timestamp)->toBe($sentAt->timestamp);

    \App\Actions\Chat\ChatSession\SendOutOfHoursReply::assertNotPushed();

    $session->messages()->forceDelete();
    $session->forceDelete();
    $this->shop->update(['settings' => $original]);
});

test('the webhook routes echoes and history to their jobs and still skips fields Aiku does not use', function () {
    \Illuminate\Support\Facades\Queue::fake();

    \App\Actions\Chat\Whatsapp\HandleWhatsappWebhook::make()->handle(['entry' => [['changes' => [
        ['field' => 'smb_message_echoes', 'value' => ['message_echoes' => [['id' => 'x']]]],
        ['field' => 'history', 'value' => ['history' => []]],
        ['field' => 'smb_app_state_sync', 'value' => ['state_sync' => []]],
    ]]]]);

    \App\Actions\Chat\Whatsapp\StoreWhatsappMessageEcho::assertPushed(1);
    \App\Actions\Chat\Whatsapp\ImportWhatsappChatHistory::assertPushed(1);
    // Both jobs extend the inbound one, so these two are the only inbound jobs: smb_app_state_sync started none.
    \App\Actions\Chat\Whatsapp\StoreIncomingWhatsappMessage::assertPushed(2);
});

test('WhatsApp calls stay on the phone for a Coexistence number', function () {
    Event::fake([\App\Events\BroadcastWhatsappCallEvent::class]);
    Http::fake();
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $phoneNumberId = 'coex-'.Str::random(8);
    $original      = useCoexistenceNumber($this->shop, $phoneNumberId);
    $waCallId      = 'wacid.'.Str::random(8);

    \App\Actions\Chat\Whatsapp\Calls\HandleWhatsappCallEvent::make()->handle([
        'metadata' => ['phone_number_id' => $phoneNumberId],
        'calls'    => [['id' => $waCallId, 'event' => 'connect', 'direction' => 'USER_INITIATED', 'from' => '447500000191']],
    ]);

    $session = StoreMetaChatSession::run(['shop_id' => $this->shop->id, 'phone_number' => '+447500000191']);
    $started = \App\Actions\Chat\Whatsapp\Calls\StartWhatsappCall::make()->handle($session->fresh(), $this->user, 'v=0');

    expect(\App\Models\Chat\MetaChatCall::where('wa_call_id', $waCallId)->exists())->toBeFalse()
        ->and($started['ok'])->toBeFalse()
        ->and($started['code'])->toBe(422);

    Http::assertNothingSent();

    $session->forceDelete();
    $this->shop->update(['settings' => $original]);
});

test('Embedded Signup connects a WhatsApp Business app number as Coexistence with its own token and never registers it', function () {
    $originalOrganisation = $this->organisation->settings;
    $originalShop         = $this->shop->settings;
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], [
        'meta' => ['app_id' => '1062209193190372', 'app_secret' => 'secret', 'access_key' => 'org-token'],
    ])]);

    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'business-token']),
        'graph.facebook.com/*/subscribed_apps'     => Http::response(['success' => true]),
        'graph.facebook.com/*/smb_app_data'        => Http::response(['messaging_product' => 'whatsapp', 'request_id' => 'req-1']),
        'graph.facebook.com/*'                     => Http::response(['display_phone_number' => '+34 678 50 20 48', 'is_on_biz_app' => true, 'status' => 'CONNECTED']),
    ]);

    $result = \App\Actions\Chat\Whatsapp\ConnectWhatsappEmbeddedSignup::make()->handle($this->shop->fresh(), [
        'code'            => 'signup-code',
        'waba_id'         => '7770001',
        'phone_number_id' => '7770002',
    ]);

    $whatsapp = $this->shop->fresh()->settings['whatsapp'];

    expect($result['ok'])->toBeTrue()
        ->and($whatsapp['mode'])->toBe('coexistence')
        ->and($whatsapp['phone_number_id'])->toBe('7770002')
        ->and($whatsapp['phone_number'])->toBe('+34678502048')
        ->and($whatsapp['access_token'])->toBe('business-token')
        ->and($whatsapp['coexistence_sync'])->toEqualCanonicalizing(['smb_app_state_sync' => 'req-1', 'history' => 'req-1']);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/7770001/subscribed_apps') && $request->hasHeader('Authorization', 'Bearer business-token'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/7770002/smb_app_data') && $request['sync_type'] === 'history');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/register'));

    $this->shop->update(['settings' => $originalShop]);
    $this->organisation->update(['settings' => $originalOrganisation]);
});

test('a reply typed on the WhatsApp Business app to a new contact starts a closed thread, not a waiting one', function () {
    \Illuminate\Support\Facades\Queue::fake();
    Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $phoneNumberId = 'coex-'.Str::random(8);
    $original      = useCoexistenceNumber($this->shop, $phoneNumberId);

    \App\Actions\Chat\Whatsapp\StoreWhatsappMessageEcho::make()->handle([
        'metadata'       => ['phone_number_id' => $phoneNumberId],
        'message_echoes' => [['to' => '447500000192', 'id' => 'wamid.echo-'.Str::random(8), 'type' => 'text', 'text' => ['body' => 'Hello from the shop']]],
    ]);

    $session = MetaChatSession::where('shop_id', $this->shop->id)->where('phone_number', '+447500000192')->latest('id')->first();

    expect($session->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($session->messages()->count())->toBe(1);

    $session->messages()->forceDelete();
    $session->forceDelete();
    $this->shop->update(['settings' => $original]);
});

test('typing a different Phone Number ID by hand drops the mode and token an Embedded Signup left', function () {
    $original = useCoexistenceNumber($this->shop, 'coex-'.Str::random(8));
    $this->shop->update(['settings' => array_merge($this->shop->settings, [
        'whatsapp' => array_merge($this->shop->settings['whatsapp'], ['access_token' => 'business-token']),
    ])]);

    $shop = \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['whatsapp_phone_number_id' => '8880001']);

    expect(Arr::get($shop->fresh()->settings, 'whatsapp.phone_number_id'))->toBe('8880001')
        ->and(Arr::get($shop->fresh()->settings, 'whatsapp.mode'))->toBeNull()
        ->and(Arr::get($shop->fresh()->settings, 'whatsapp.access_token'))->toBeNull();

    $this->shop->update(['settings' => $original]);
});

test('an agent starts a chat on the main phone or the WhatsApp marketing phone of a customer', function () {
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $customer->update(['phone' => '+447500000301', 'marketing_whatsapp_phone' => '+447500000302']);

    $main = StoreMetaChatSession::run(['shop_id' => $this->shop->id, 'customer_id' => $customer->id, 'phone_source' => StoreMetaChatSession::PHONE_SOURCE_MAIN]);
    $whatsapp = StoreMetaChatSession::run(['shop_id' => $this->shop->id, 'customer_id' => $customer->id, 'phone_source' => StoreMetaChatSession::PHONE_SOURCE_MARKETING_WHATSAPP]);
    $default = StoreMetaChatSession::run(['shop_id' => $this->shop->id, 'customer_id' => $customer->id]);

    expect($main->phone_number)->toBe('+447500000301')
        ->and($whatsapp->phone_number)->toBe('+447500000302')
        ->and($whatsapp->customer_id)->toBe($customer->id)
        ->and($whatsapp->id)->not->toBe($main->id)
        ->and($default->id)->toBe($main->id);
});

test('an agent can start a chat with a customer who only has a WhatsApp marketing phone', function () {
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $customer->update(['phone' => null, 'marketing_whatsapp_phone' => '+447500000303']);

    $session = StoreMetaChatSession::run(['shop_id' => $this->shop->id, 'customer_id' => $customer->id, 'phone_source' => StoreMetaChatSession::PHONE_SOURCE_MAIN]);

    expect($session->phone_number)->toBe('+447500000303')
        ->and($session->customer_id)->toBe($customer->id);
});

function whatsappStopTestInbound(\App\Models\Catalogue\Shop $shop, string $phoneNumberId, string $digits, string $text): void
{
    \App\Actions\Chat\Whatsapp\StoreIncomingWhatsappMessage::make()->handle([
        'metadata' => ['phone_number_id' => $phoneNumberId],
        'contacts' => [['profile' => ['name' => 'Stop Contact']]],
        'messages' => [['id' => 'wamid.stop-'.Str::random(10), 'from' => $digits, 'type' => 'text', 'text' => ['body' => $text]]],
    ]);
}

function whatsappStopTestNumber(\App\Models\Catalogue\Shop $shop): ?array
{
    $original = $shop->settings;
    $shop->update(['settings' => array_merge($original ?? [], ['whatsapp' => ['phone_number_id' => 'stop-'.Str::random(8)]])]);

    return $original;
}

function whatsappStopTestSubscriber(\App\Models\Catalogue\Shop $shop, array $phones): Customer
{
    $customer = StoreCustomer::make()->action($shop, Customer::factory()->definition());
    $customer->update($phones);
    \App\Actions\CRM\CustomerComms\UpdateCustomerComms::run($customer->comms, ['is_subscribed_to_whatsapp_newsletter' => true]);

    return $customer->fresh();
}

test('replying STOP from the WhatsApp marketing phone unsubscribes the customer from WhatsApp marketing', function () {
    Bus::fake();
    Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $original = whatsappStopTestNumber($this->shop);

    $digits   = '4475003'.random_int(10000, 99999);
    $customer = whatsappStopTestSubscriber($this->shop, ['phone' => '+447500000401', 'marketing_whatsapp_phone' => '+'.$digits]);

    whatsappStopTestInbound($this->shop, $this->shop->settings['whatsapp']['phone_number_id'], $digits, ' stop ');

    $comms = $customer->comms->fresh();

    expect($comms->is_subscribed_to_whatsapp_newsletter)->toBeFalse()
        ->and($comms->whatsapp_newsletter_unsubscribed_at)->not->toBeNull()
        ->and($comms->whatsapp_newsletter_unsubscribed_origin_type)->toBe('MetaChatSession')
        ->and(\App\Models\Comms\WhatsappSubscriber::where('parent_type', 'Customer')->where('parent_id', $customer->id)->exists())->toBeFalse()
        ->and(MetaChatSession::where('phone_number', '+'.$digits)->sole()->messages()->count())->toBe(1);

    $this->shop->update(['settings' => $original]);
});

test('replying STOP from the main phone unsubscribes a customer without a WhatsApp marketing phone', function () {
    Bus::fake();
    Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $original = whatsappStopTestNumber($this->shop);

    $digits   = '4475004'.random_int(10000, 99999);
    $customer = whatsappStopTestSubscriber($this->shop, ['phone' => '+'.$digits, 'marketing_whatsapp_phone' => null]);

    whatsappStopTestInbound($this->shop, $this->shop->settings['whatsapp']['phone_number_id'], $digits, 'UNSUBSCRIBE');

    expect($customer->comms->fresh()->is_subscribed_to_whatsapp_newsletter)->toBeFalse();

    $this->shop->update(['settings' => $original]);
});

test('a message that only mentions stop does not unsubscribe, and the main phone of a customer with a WhatsApp marketing phone is ignored', function () {
    Bus::fake();
    Event::fake([\App\Events\BroadcastRealtimeMetaChat::class, \App\Events\BroadcastMetaChatListEvent::class]);
    MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);
    $original = whatsappStopTestNumber($this->shop);

    $mainDigits = '4475005'.random_int(10000, 99999);
    $customer   = whatsappStopTestSubscriber($this->shop, ['phone' => '+'.$mainDigits, 'marketing_whatsapp_phone' => '+4475006'.random_int(10000, 99999)]);

    whatsappStopTestInbound($this->shop, $this->shop->settings['whatsapp']['phone_number_id'], $mainDigits, 'Please do not stop my order');
    expect($customer->comms->fresh()->is_subscribed_to_whatsapp_newsletter)->toBeTrue();

    MetaChatSession::where('phone_number', '+'.$mainDigits)->update(['customer_id' => null]);
    whatsappStopTestInbound($this->shop, $this->shop->settings['whatsapp']['phone_number_id'], $mainDigits, 'STOP');
    expect($customer->comms->fresh()->is_subscribed_to_whatsapp_newsletter)->toBeTrue();

    $this->shop->update(['settings' => $original]);
});

test('the STOP keywords are matched as the whole message only', function () {
    expect(\App\Actions\Comms\WhatsappSubscriber\UnsubscribeWhatsappNumberFromMarketing::isStopRequest('Stop!'))->toBeTrue()
        ->and(\App\Actions\Comms\WhatsappSubscriber\UnsubscribeWhatsappNumberFromMarketing::isStopRequest('stop promotions'))->toBeTrue()
        ->and(\App\Actions\Comms\WhatsappSubscriber\UnsubscribeWhatsappNumberFromMarketing::isStopRequest('stop it please'))->toBeFalse()
        ->and(\App\Actions\Comms\WhatsappSubscriber\UnsubscribeWhatsappNumberFromMarketing::isStopRequest(null))->toBeFalse();
});
