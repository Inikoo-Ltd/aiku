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
use App\Actions\Chat\ChatSession\DownloadChatAttachment;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatAgent;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Actions\Helpers\Translations\DetectLanguageWithJev;
use App\Actions\Helpers\Translations\Translate;
use App\Models\Helpers\Language;
use App\Actions\Comms\WhatsappCampaign\SendWhatsappDeliveryChannel;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

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

test('an agent email reply in another language goes out translated to the customer language', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $spanish = Language::where('code', 'es')->firstOrFail();
    $english = Language::where('code', 'en')->firstOrFail();

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent1']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = ChatSession::create([
        'ulid'                    => (string)Str::ulid(),
        'status'                  => ChatSessionStatusEnum::ACTIVE,
        'channel'                 => \App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL,
        'guest_identifier'        => 'guest_'.Str::random(5),
        'language_id'             => $spanish->id,
        'active_user_language_id' => $spanish->id,
        'priority'                => ChatPriorityEnum::NORMAL,
        'shop_id'                 => $this->shop->id,
        'ai_model_version'        => 'default',
        'metadata'                => ['email_from' => 'cliente@example.com', 'email_subject' => 'Pedido', 'gmail_thread_id' => 't1'],
    ]);

    $reply = $session->messages()->create([
        'message_text'  => 'Shipped today',
        'original_text' => 'Shipped today',
        'message_type'  => ChatMessageTypeEnum::TEXT,
        'sender_type'   => ChatSenderTypeEnum::AGENT,
    ]);

    DetectLanguageWithJev::shouldRun()->andReturn($english);
    Translate::shouldRun()->once()->andReturn('Enviado hoy');

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply);

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return str_contains(base64_decode(substr($raw, strpos($raw, "\r\n\r\n") + 4)), 'Enviado hoy');
    });

    expect($reply->fresh()->original_text)->toBe('Shipped today')
        ->and(\App\Http\Resources\CRM\Livechat\ChatSessionListResource::make($session->fresh())->resolve()['customer_language'])
        ->toBe(['code' => 'es', 'name' => $spanish->name]);

    $session->messages()->forceDelete();
    $session->forceDelete();
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
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), 'messages/g2/modify') && $request['addLabelIds'] === ['L2'] && $request['removeLabelIds'] === ['INBOX', 'UNREAD']);
});

test('a showroom sender is filed unread under its label without becoming a chat, even when it was once marked as spam', function () {
    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'           => 'care@shop.test',
        'refresh_token'   => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'      => '1',
        'blocked_senders' => ['notifications@calendly.com'],
    ];
    $this->shop->update(['settings' => $settings]);

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, ['gmail_showroom_senders' => ['@Calendly.com']]);

    expect(Arr::get($this->shop->fresh()->settings, 'gmail.labeled_senders'))->toBe(['@calendly.com' => 'aiku/showroom']);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                         => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/showroom1*' => \Illuminate\Support\Facades\Http::response([
            'id' => 'showroom1', 'threadId' => 't-showroom1',
            'payload' => ['mimeType' => 'text/plain', 'headers' => [['name' => 'From', 'value' => 'Calendly <notifications@calendly.com>'], ['name' => 'Subject', 'value' => 'New Event: Showroom Appointment'], ['name' => 'Message-ID', 'value' => '<showroom1@calendly.com>']], 'body' => ['data' => rtrim(strtr(base64_encode('Your appointment is confirmed'), '+/', '-_'), '=')]],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels' => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'LS', 'name' => 'aiku/showroom']]]),
        'gmail.googleapis.com/*'                        => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop->fresh(), 'showroom1');

    expect($message)->toBeNull()
        ->and(\App\Models\Chat\ChatSession::where('shop_id', $this->shop->id)->where('metadata->email', 'notifications@calendly.com')->exists())->toBeFalse();

    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), 'messages/showroom1/modify') && $request['addLabelIds'] === ['LS'] && $request['removeLabelIds'] === ['INBOX']);
});

test('showroom senders only accept email addresses or @domain', function () {
    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, ['gmail_showroom_senders' => ['calendly']]);
})->throws(\Illuminate\Validation\ValidationException::class);

test('un-marking an email chat as spam takes the sender off the blocked list', function () {
    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::CLOSED,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'channel'          => \App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL,
        'metadata'         => ['email' => 'Notifications@Calendly.com'],
    ]);

    $agentUser = createAdminGuest($this->organisation->group)->getUser();
    $agent     = ChatAgent::firstOrCreate(['user_id' => $agentUser->id], ['max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => false, 'is_available' => false, 'current_chat_count' => 0]);

    \App\Actions\Chat\ChatSession\MarkChatSessionAsSpam::run($session, $agent);
    expect(Arr::get($this->shop->fresh()->settings, 'gmail.blocked_senders'))->toContain('notifications@calendly.com');

    \App\Actions\Chat\ChatSession\UnmarkChatSessionAsSpam::run($session->fresh(), $agent);
    expect(Arr::get($this->shop->fresh()->settings, 'gmail.blocked_senders'))->not->toContain('notifications@calendly.com');

    $session->forceDelete();
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

test('inbound guest gmail attachments wait in gmail until an agent replies, then are all saved on the email chat message', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $message = pendingAttachmentsTestInboundGuestEmail($this->shop, 'a1');

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

test('an agent can bring a guest email\'s files in from gmail before replying', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class]);

    $message = pendingAttachmentsTestInboundGuestEmail($this->shop, 'a2');
    $session = $message->chatSession;

    actingAs($this->user)
        ->postJson(route('grp.org.chat.agents.messages.pending_attachments', [$this->organisation->slug, $session->ulid, $message->id]))
        ->assertOk()
        ->assertJsonPath('data.attachments.1.file_name', 'invoice.pdf')
        ->assertJsonMissingPath('data.metadata.gmail_pending_attachments');

    expect($message->fresh()->attachedFiles()->pluck('name')->all())->toBe(['photo.png', 'invoice.pdf'])
        ->and($session->messages()->where('sender_type', ChatSenderTypeEnum::AGENT)->count())->toBe(0);

    $other = noiseTestEmailSession($this->shop, 'someone@example.com', 'Another conversation', 'Hello');

    actingAs($this->user)
        ->postJson(route('grp.org.chat.agents.messages.pending_attachments', [$this->organisation->slug, $other->ulid, $message->id]))
        ->assertStatus(422);
});

test('chat media older than the retention window moves to the archive database and is still downloadable', function () {
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => 'archive'])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists archive');

    $session = $this->action->handle(['language_id' => 68, 'priority' => ChatPriorityEnum::NORMAL->value, 'shop_id' => $this->shop->id]);
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
        'gmail.googleapis.com/gmail/v1/users/me/messages/f4*' => $gmailMessage('f4', "Luigi's Box <support@luigisbox.com>", 'The item data in the awgifts.hu catalog has not been updated for some time'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/f5*' => $gmailMessage('f5', 'complaints@eu-west-1.email-abuse.amazonses.com', 'Gandi Abuse Report'),
        'gmail.googleapis.com/gmail/v1/users/me/labels'        => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'LF', 'name' => 'aiku/filtered']]]),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $sessionsBefore = ChatSession::count();

    foreach (['f1', 'f2', 'f3', 'f4', 'f5'] as $id) {
        expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, $id))->toBeNull();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), "messages/$id/modify") && $request['addLabelIds'] === ['LF']);
    }

    expect(ChatSession::count())->toBe($sessionsBefore);
});

test('mail from one of our own shops or a staff buying account never becomes a chat session, a colleague writing from work does', function () {
    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '1'];
    $this->shop->update(['settings' => $settings]);

    $sibling = \App\Models\Catalogue\Shop::where('id', '!=', $this->shop->id)->first() ?? $this->shop;
    $siblingEmail = $sibling->email;
    $sibling->update(['email' => 'hola@awartisan.es']);

    Employee::factory()->create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'work_email'      => 'david@ancientwisdom.biz',
    ]);

    $customerBefore = $this->customer->only(['is_staff', 'email']);
    $this->customer->update(['is_staff' => true, 'email' => 'buyer.staff@example.com']);

    $staffLogin = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->organisation->group_id, 'email' => 'Goods.In@awgifts.test']);

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

    $colleagueMessage = fn (string $id, string $to) => \Illuminate\Support\Facades\Http::response([
        'id'       => $id,
        'threadId' => 't'.$id,
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => [
                ['name' => 'From', 'value' => 'Staff Buyer <buyer.staff@example.com>'],
                ['name' => 'To', 'value' => $to],
                ['name' => 'Subject', 'value' => 'Can you call this customer back?'],
            ],
            'body'     => ['data' => rtrim(strtr(base64_encode('please call them'), '+/', '-_'), '=')],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                         => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/o1*' => $gmailMessage('o1', 'AW Artisan <hola@awartisan.es>'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/o3*' => $gmailMessage('o3', 'Staff Buyer <buyer.staff@example.com>'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/c1*' => $colleagueMessage('c1', 'care@shop.test'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/c2*' => $colleagueMessage('c2', 'care@shop.test, hola@awartisan.es'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/c3*' => $colleagueMessage('c3', 'care@shop.test'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/c4*' => $colleagueMessage('c4', 'Denisa <denisa@shop.test>'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/u1*' => \Illuminate\Support\Facades\Http::response([
            'id'       => 'u1',
            'threadId' => 'tu1',
            'payload'  => [
                'mimeType' => 'text/plain',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Goods In <goods.in@awgifts.test>'],
                    ['name' => 'To', 'value' => 'care@shop.test, hola@awartisan.es'],
                    ['name' => 'Subject', 'value' => 'BACK IN STOCK'],
                ],
                'body'     => ['data' => rtrim(strtr(base64_encode('these are back'), '+/', '-_'), '=')],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'       => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'LF', 'name' => 'aiku/filtered'], ['id' => 'LI', 'name' => 'aiku/imported'], ['id' => 'LU', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                              => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $sessionsBefore = ChatSession::count();

    foreach (['o1', 'o3', 'u1'] as $id) {
        expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, $id))->toBeNull();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), "messages/$id/modify") && $request['addLabelIds'] === ['LF']);
    }

    expect(ChatSession::count())->toBe($sessionsBefore)
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::ourOwnAddresses())->not->toHaveKey('david@ancientwisdom.biz');


    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'c2'))->toBeNull()
        ->and(ChatSession::count())->toBe($sessionsBefore);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), 'messages/c2/modify') && $request['addLabelIds'] === ['LF']);

    $customerThread = ChatSession::create([
        'ulid'        => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'     => $this->shop->id,
        'language_id' => 68,
        'status'      => ChatSessionStatusEnum::CLOSED->value,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'channel'     => \App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL->value,
    ]);
    $customerThread->update(['metadata' => ['gmail_thread_id' => 'tc3', 'email' => 'shopper@example.com']]);

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'c3'))->toBeNull()
        ->and($customerThread->fresh()->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and(data_get($customerThread->fresh()->metadata, 'email'))->toBe('shopper@example.com');
    $customerThread->forceDelete();

    $colleagueSession = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'c1')->chatSession;

    expect($colleagueSession->is_colleague)->toBeTrue()
        ->and(\App\Actions\Chat\ChatSession\GetChatSessions::make()->handle(['shop_ids' => [$this->shop->id]])->pluck('id'))->not->toContain($colleagueSession->id)
        ->and(\App\Actions\Chat\ChatSession\GetChatSessions::make()->handle(['shop_ids' => [$this->shop->id], 'colleague' => 1])->pluck('id'))->toContain($colleagueSession->id);

    $forwardedToWebmaster = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'c4')->chatSession;

    expect($forwardedToWebmaster->is_colleague)->toBeTrue();

    foreach ([$colleagueSession, $forwardedToWebmaster] as $session) {
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
    $this->customer->update($customerBefore);
    $sibling->update(['email' => $siblingEmail]);
    $staffLogin->forceDelete();
});

test('a bounced answer is told on its conversation without reopening it, a bounce of nothing of ours is filtered', function () {
    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '1'];
    $this->shop->update(['settings' => $settings]);

    $bounce = fn (string $id, string $threadId) => \Illuminate\Support\Facades\Http::response([
        'id'       => $id,
        'threadId' => $threadId,
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => [
                ['name' => 'From', 'value' => 'Mail Delivery Subsystem <mailer-daemon@googlemail.com>'],
                ['name' => 'To', 'value' => 'care@shop.test'],
                ['name' => 'Subject', 'value' => 'Delivery Status Notification (Failure)'],
                ['name' => 'X-Failed-Recipients', 'value' => 'gone@example.com'],
            ],
            'body'     => ['data' => rtrim(strtr(base64_encode("Address not found\n\nThe response was:\n\n550 5.1.1 The email account that you tried to reach does not exist."), '+/', '-_'), '=')],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                         => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/b1*' => $bounce('b1', 'tbounced'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/b2*' => $bounce('b2', 'tnobody'),
        'gmail.googleapis.com/gmail/v1/users/me/labels'       => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'LF', 'name' => 'aiku/filtered'], ['id' => 'LI', 'name' => 'aiku/imported'], ['id' => 'LU', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                              => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = ChatSession::create([
        'ulid'        => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'     => $this->shop->id,
        'language_id' => 68,
        'status'      => ChatSessionStatusEnum::CLOSED->value,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'channel'     => \App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL->value,
    ]);
    $session->update(['metadata' => ['gmail_thread_id' => 'tbounced', 'email_from' => 'gone@example.com']]);

    $sessionsBefore = ChatSession::count();

    $note = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'b1');

    expect($note->chat_session_id)->toBe($session->id)
        ->and($note->sender_type)->toBe(ChatSenderTypeEnum::SYSTEM)
        ->and($note->message_text)->toBe('Email not delivered to gone@example.com: 550 5.1.1 The email account that you tried to reach does not exist.')
        ->and($note->metadata['email_bounce'])->toBe(['recipient' => 'gone@example.com', 'reason' => '550 5.1.1 The email account that you tried to reach does not exist.'])
        ->and($session->fresh()->status)->toBe(ChatSessionStatusEnum::CLOSED);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), 'messages/b1/modify') && $request['addLabelIds'] === ['LI']);

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'b1'))->toBeNull()
        ->and($session->messages()->count())->toBe(1);

    $this->artisan('chat:backfill-email-bodies', ['--shop' => $this->shop->id])->assertSuccessful();

    expect($note->fresh()->message_text)->toBe('Email not delivered to gone@example.com: 550 5.1.1 The email account that you tried to reach does not exist.');

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'b2'))->toBeNull()
        ->and(ChatSession::count())->toBe($sessionsBefore);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), 'messages/b2/modify') && $request['addLabelIds'] === ['LF']);

    $session->messages()->forceDelete();
    $session->forceDelete();
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

test('marketplace notices wait unread under their own gmail label, their marketing is filtered, and buyers relayed by the marketplace still reach the inbox', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $encode  = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $message = fn (string $id, string $from, string $subject) => \Illuminate\Support\Facades\Http::response([
        'id'       => $id,
        'threadId' => "t$id",
        'labelIds' => ['INBOX', 'UNREAD'],
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => [['name' => 'From', 'value' => $from], ['name' => 'Subject', 'value' => $subject]],
            'body'     => ['data' => $encode('Hola')],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/mk1*' => $message('mk1', 'Faire <updates@info.faire.com>', 'Nuevo pedido al por mayor de mira belle (8BTZCY7KH4)'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/mk2*' => $message('mk2', 'Faire <sales+awartisan@brand.faire.com>', '15% OFF en nuestras marcas'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/mk3*' => $message('mk3', 'Orderchamp <service@mg.orderchamp.com>', 'Has recibido un mensaje de Charlotte'),
        'gmail.googleapis.com/gmail/v1/users/me/labels'        => \Illuminate\Support\Facades\Http::response(['labels' => [
            ['id' => 'LM', 'name' => 'Marketplaces'],
            ['id' => 'LF', 'name' => 'aiku/filtered'],
            ['id' => 'LU', 'name' => 'aiku/unmatched'],
        ]]),
        'gmail.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([]),
    ]);

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'mk1'))->toBeNull()
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'mk2'))->toBeNull()
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'mk3'))->toBeInstanceOf(ChatMessage::class);

    $filed = fn (string $id) => \Illuminate\Support\Facades\Http::recorded(fn ($request) => str_ends_with($request->url(), "messages/$id/modify"))
        ->map(fn ($pair) => $pair[0]->data())->first();

    expect($filed('mk1'))->toBe(['addLabelIds' => ['LM'], 'removeLabelIds' => ['INBOX']])
        ->and($filed('mk2'))->toBe(['addLabelIds' => ['LF'], 'removeLabelIds' => ['INBOX', 'UNREAD']]);
});

test('an email from a courier is filed in the Couriers folder and in no other list', function () {
    \Illuminate\Support\Facades\Http::fake();

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::isCarrierAddress('ops@gls-spain.es'))->toBeTrue()
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::isCarrierAddress('adelaida@gibcargo.com'))->toBeTrue()
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::isCarrierAddress('care@mail.tnt.com'))->toBeTrue()
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::isCarrierAddress('buyer@notgls-spain.es'))->toBeFalse()
        ->and(\App\Actions\Comms\Mailbox\ProcessInboundEmail::isCarrierAddress(null))->toBeFalse();

    $courier  = noiseTestEmailSession($this->shop, 'ops@gls-spain.es', 'Recogida 139675745', 'La recogida consta realizada.');
    $stranger = noiseTestEmailSession($this->shop, 'someone@example.com', 'Hello', 'Do you ship to Israel?');
    $courier->update(['is_carrier' => true]);

    $queue = fn (array $filters) => collect(GetChatSessions::make()->handle(['shop_id' => $this->shop->id, 'statuses' => ['waiting']] + $filters)->items())->pluck('id')->all();

    expect($queue(['carrier' => true]))->toContain($courier->id)->not->toContain($stranger->id)
        ->and($queue(['pairs' => ['email:guest']]))->toContain($stranger->id)->not->toContain($courier->id)
        ->and($queue([]))->not->toContain($courier->id);
});

test('a courier case mail marked automatic opens a Couriers conversation that is never put aside, a stranger\'s is still filtered', function () {
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '1'];
    $this->shop->update(['settings' => $settings]);

    $message = fn (string $id, string $from, string $threadId) => \Illuminate\Support\Facades\Http::response([
        'id'       => $id,
        'threadId' => $threadId,
        'labelIds' => ['INBOX', 'UNREAD'],
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => [
                ['name' => 'From', 'value' => $from],
                ['name' => 'Subject', 'value' => 'DHL 1287151073 - CSTASK29362102 - Trace - Damaged Shipment'],
                ['name' => 'Auto-Submitted', 'value' => 'auto-generated'],
            ],
            'body' => ['data' => rtrim(strtr(base64_encode('This shipment has been declared as damaged during transit, please advise.'), '+/', '-_'), '=')],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                           => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/dhl1*' => $message('dhl1', 'DHL <gb.deliveryinfo@dhl.com>', 'tdhl'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/dhl2*' => $message('dhl2', 'DHL <gb.deliveryinfo@dhl.com>', 'tdhl'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/oth1*' => $message('oth1', 'Desk <desk@example.com>', 'toth1'),
        'gmail.googleapis.com/gmail/v1/users/me/threads/*'      => \Illuminate\Support\Facades\Http::response(['messages' => []]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'         => \Illuminate\Support\Facades\Http::response(['labels' => [
            ['id' => 'LF', 'name' => 'aiku/filtered'],
            ['id' => 'LU', 'name' => 'aiku/unmatched'],
        ]]),
        'gmail.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $courierMessage = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'dhl1');
    $courierSession = $courierMessage->chatSession->refresh();

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'oth1'))->toBeNull()
        ->and($courierSession->is_carrier)->toBeTrue()
        ->and(data_get($courierMessage->refresh()->metadata, 'auto_reply'))->toBeTrue()
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($courierSession)->refresh()->is_rubbish)->toBeFalse();

    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), 'messages/oth1/modify') && $request['addLabelIds'] === ['LF']);

    $courierSession->update(['status' => ChatSessionStatusEnum::CLOSED, 'closed_at' => now()]);
    \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'dhl2');

    $newsletter = noiseTestEmailSession($this->shop, 'news@dhl.com', 'Ship more for less', 'Our new rates', ['email_headers' => ['list_unsubscribe' => true]]);
    $newsletter->update(['is_carrier' => true]);

    expect($courierSession->refresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($newsletter)->refresh()->rubbish_reason)->toBe('marketing');
});

test('customer service edits the courier domains in the group chat settings, subdomains included', function () {
    actingAs($this->user);
    $group    = $this->organisation->group;
    $settings = $group->settings;
    $update   = route('grp.org.chat.settings.carrier_domains.update', [$this->organisation->slug]);
    $isCourier = fn (string $address) => \App\Actions\Comms\Mailbox\ProcessInboundEmail::isCarrierAddress($address, $group->fresh());

    expect($isCourier('ops@gls-spain.es'))->toBeTrue()
        ->and($isCourier('bookings@newcourier.example'))->toBeFalse();

    get(route('grp.org.chat.settings', [$this->organisation->slug]).'?tab=couriers')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('tabs.navigation.couriers')
        ->where('couriers.domains.0.domain', config('chat.carrier_domains')[0])
        ->where('couriers.update_route.name', 'grp.org.chat.settings.carrier_domains.update'));

    get(route('grp.org.shops.show.chat.settings', [$this->organisation->slug, $this->shop->slug]))->assertInertia(fn (AssertableInertia $page) => $page
        ->missing('tabs.navigation.couriers')
        ->where('couriers', null));

    patch($update, ['domains' => "NewCourier.example\nhttps://www.dsv.com/contact\n@gls-spain.es\n\nnewcourier.example"])->assertRedirect()->assertSessionHasNoErrors();

    expect(data_get($group->fresh()->settings, 'chat.carrier_domains'))->toBe(['dsv.com', 'gls-spain.es', 'newcourier.example'])
        ->and($isCourier('bookings@newcourier.example'))->toBeTrue()
        ->and($isCourier('bookings@eu.newcourier.example'))->toBeTrue()
        ->and($isCourier('bookings@notnewcourier.example'))->toBeFalse()
        ->and($isCourier('care@mail.tnt.com'))->toBeFalse();

    $courier = noiseTestEmailSession($this->shop, 'bookings@eu.newcourier.example', 'Pickup', 'Pickup booked.');
    $courier->update(['is_carrier' => true]);
    get(route('grp.org.chat.settings', [$this->organisation->slug]).'?tab=couriers')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('couriers.domains.2', ['domain' => 'newcourier.example', 'sessions' => 1]));

    patch($update, ['domains' => "dsv.com\nnot a domain\nlocalhost"])->assertSessionHasErrors('domains');
    expect(data_get($group->fresh()->settings, 'chat.carrier_domains'))->toBe(['dsv.com', 'gls-spain.es', 'newcourier.example']);

    actingAs(User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]));
    patch($update, ['domains' => 'hijack.example'])->assertForbidden();

    $courier->delete();
    $group->update(['settings' => $settings]);
});

test('a supervisor moves a conversation to Couriers, which adds the sender domain and files its open conversations', function () {
    actingAs($this->user);
    $group    = $this->organisation->group;
    $settings = $group->settings;
    $move     = fn (ChatSession $session) => $this->patchJson(route('grp.org.chat.agents.sessions.couriers', [$this->organisation->slug, $session->ulid]));

    $courier = noiseTestEmailSession($this->shop, 'Bookings@Parcels-Move.example', 'Pickup', 'Pickup booked for tomorrow.');
    $sibling = noiseTestEmailSession($this->shop, 'tracking@eu.parcels-move.example', 'Tracking', 'Parcel in transit.');
    $gmail   = noiseTestEmailSession($this->shop, 'driver@gmail.com', 'Pickup', 'I am outside.');
    $other   = noiseTestEmailSession($this->shop, 'someone@example.com', 'Hello', 'Do you ship to Israel?');

    expect(\App\Actions\Chat\ChatSession\MoveChatSessionToCouriers::make()->userMayMove($this->user, $courier))->toBeTrue()
        ->and(\App\Actions\Chat\ChatSession\MoveChatSessionToCouriers::make()->userMayMove($this->user, $gmail))->toBeFalse();

    $move($courier)->assertOk()->assertJsonPath('data.filed', 2);

    expect(data_get($group->fresh()->settings, 'chat.carrier_domains'))->toContain('parcels-move.example', 'gls-spain.es')
        ->and($courier->fresh()->is_carrier)->toBeTrue()
        ->and($sibling->fresh()->is_carrier)->toBeTrue()
        ->and($other->fresh()->is_carrier)->toBeFalse()
        ->and($sibling->chatEvents()->where('event_type', 'moved_to_couriers')->first()?->payload)->toMatchArray(['domain' => 'parcels-move.example', 'moved_by_name' => $this->user->contact_name])
        ->and((new \App\Http\Resources\CRM\Livechat\ChatTimelineEventResource($courier->chatEvents()->where('event_type', 'moved_to_couriers')->first()))->resolve()['description'])
        ->toBe($this->user->contact_name.' moved the chat to Couriers and added parcels-move.example to the courier list')
        ->and(\App\Enums\CRM\Livechat\ChatEventTypeEnum::timelineTypes())->toContain('moved_to_couriers');

    $move($courier)->assertUnprocessable();
    $move($gmail)->assertUnprocessable();

    actingAs(User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]));
    $move($other)->assertForbidden();

    ChatSession::whereIn('id', [$courier->id, $sibling->id, $gmail->id, $other->id])->delete();
    $group->update(['settings' => $settings]);
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

test('the mailbox history is archived as text for the customer it was with, leaving machine mail out, and shows on their page with their chats', function () {
    $customer = createOwnCustomer($this->shop, 'archive-'.Str::lower(Str::random(6)));
    $customer->update(['email' => 'archive.'.Str::lower(Str::random(6)).'@example.com']);
    $mail = fn (string $id, string $from, string $to, string $text, array $labels = ['INBOX'], array $headers = []) => [
        'id'           => $id,
        'threadId'     => 'th-archive-'.$customer->id,
        'labelIds'     => $labels,
        'internalDate' => (string) (1780000000000 + (int) preg_replace('/\D.*$/', '', substr($id, 1)) * 60000),
        'payload'      => [
            'mimeType' => 'text/plain',
            'headers'  => [['name' => 'From', 'value' => $from], ['name' => 'To', 'value' => $to], ['name' => 'Subject', 'value' => 'Broken jar'], ...$headers],
            'body'     => ['data' => rtrim(strtr(base64_encode($text), '+/', '-_'), '=')],
        ],
    ];
    $archive = fn (array $raw) => \App\Actions\Comms\Mailbox\ArchiveShopMailbox::make()->archive($this->shop, 'care@shop.test', $raw);

    $question = $archive($mail('a1-'.$customer->id, "Jo <{$customer->email}>", 'care@shop.test', 'My jar arrived broken'));
    $answer   = $archive($mail('a2-'.$customer->id, 'Care <care@shop.test>', $customer->email, 'Sorry, a new one is on its way', ['SENT']));

    expect($question->only(['customer_id', 'is_outbound', 'counterpart_address', 'text']))->toBe(['customer_id' => $customer->id, 'is_outbound' => false, 'counterpart_address' => $customer->email, 'text' => 'My jar arrived broken'])
        ->and($answer->only(['customer_id', 'is_outbound']))->toBe(['customer_id' => $customer->id, 'is_outbound' => true])
        ->and($archive($mail('a3', 'no-reply@example.com', 'care@shop.test', 'Your report')))->toBeNull()
        ->and($archive($mail('a4', 'news@example.com', 'care@shop.test', 'Big sale', ['INBOX'], [['name' => 'List-Unsubscribe', 'value' => '<mailto:u@example.com>']])))->toBeNull()
        ->and($archive($mail('a5', 'away@example.com', 'care@shop.test', 'I am away', ['INBOX'], [['name' => 'Auto-Submitted', 'value' => 'auto-replied']])))->toBeNull()
        ->and($archive($mail('a6', 'Care <care@shop.test>', $customer->email, 'We are closed at the moment', ['SENT'], [['name' => 'Auto-Submitted', 'value' => 'auto-replied']])))->toBeNull()
        ->and($archive($mail('a1-'.$customer->id, "Jo <{$customer->email}>", 'care@shop.test', 'My jar arrived broken'))->id)->toBe($question->id)
        ->and($archive($mail('a8', 'a.supplier@example.com', 'care@shop.test', 'Our new price list')))->toBeNull()
        ->and($archive(array_replace_recursive($mail('a10', 'Care <care@shop.test>', $customer->email, '--Transaction Information-- Merchant: Shop', ['SENT']), ['payload' => ['headers' => [2 => ['name' => 'Subject', 'value' => 'Transaction receipt for order GB411978']]]])))->toBeNull();

    $shared  = 'shared.'.Str::lower(Str::random(6)).'@example.com';
    $twoOfUs = [createOwnCustomer($this->shop, 'archive-shared-a-'.$customer->id), createOwnCustomer($this->shop, 'archive-shared-b-'.$customer->id)];
    foreach ($twoOfUs as $one) {
        $one->update(['email' => $shared]);
    }
    expect($archive($mail('a9', "Jo <$shared>", 'care@shop.test', 'Where is my order?')))->toBeNull();

    $inInbox = noiseTestEmailSession($this->shop, 'inbox.'.Str::lower(Str::random(6)).'@example.com', 'Already here', 'Came in through the inbox');
    $inInbox->messages()->first()->update(['metadata' => ['gmail_message_id' => 'a7-inbox']]);
    expect($archive($mail('a7-inbox', "Jo <{$customer->email}>", 'care@shop.test', 'Came in through the inbox')))->toBeNull();

    $thread = collect(\App\Actions\CRM\Customer\GetCustomerCommunications::run($customer)['threads'])->firstWhere('kind', 'email_archive');
    expect($thread['title'])->toBe('Broken jar')
        ->and(array_column($thread['messages'], 'from_us'))->toBe([false, true]);

    \App\Models\Comms\EmailArchiveMessage::where('customer_id', $customer->id)->delete();
});

test('a mail in one shop mailbox about an order of another shop is archived for the customer of that order', function () {
    $otherShop = \App\Models\Catalogue\Shop::where('organisation_id', $this->organisation->id)->where('id', '!=', $this->shop->id)->first()
        ?? \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());
    $email           = 'two.shops.'.Str::lower(Str::random(6)).'@example.com';
    $here            = createOwnCustomer($this->shop, 'archive-two-shops-here-'.$email);
    $there           = createOwnCustomer($otherShop, 'archive-two-shops-there-'.$email);
    $here->update(['email' => $email]);
    $there->update(['email' => $email]);
    $orderReference  = 'AWD'.random_int(100000, 999999);
    $order           = \App\Actions\Ordering\Order\StoreOrder::make()->action($there, [
        'date'             => date('Y-m-d'),
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);
    $order->update(['reference' => $orderReference]);

    $mail = fn (string $id, string $subject) => [
        'id'           => $id,
        'threadId'     => 'th-'.$id,
        'labelIds'     => ['SENT'],
        'internalDate' => '1780000000000',
        'payload'      => [
            'mimeType' => 'text/plain',
            'headers'  => [['name' => 'From', 'value' => 'care@shop.test'], ['name' => 'To', 'value' => $email], ['name' => 'Subject', 'value' => $subject]],
            'body'     => ['data' => rtrim(strtr(base64_encode('Thank you for your order'), '+/', '-_'), '=')],
        ],
    ];
    $archive = fn (array $raw) => \App\Actions\Comms\Mailbox\ArchiveShopMailbox::make()->archive($this->shop, 'care@shop.test', $raw);

    expect($archive($mail('x1-'.$order->id, "Order $orderReference out of stock"))->customer_id)->toBe($there->id)
        ->and($archive($mail('x2-'.$order->id, 'Broken jar'))->customer_id)->toBe($here->id);

    $there->update(['email' => 'someone.else.'.Str::lower(Str::random(6)).'@example.com']);
    expect($archive($mail('x3-'.$order->id, "Order $orderReference out of stock"))->customer_id)->toBe($here->id);

    \App\Models\Comms\EmailArchiveMessage::whereIn('customer_id', [$here->id, $there->id])->delete();
});

test('when gmail refuses the live fetch for its quota, the archive of that mailbox stands down so customer mail comes first', function () {
    \Illuminate\Support\Facades\Queue::fake();
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '100'];
    $this->shop->update(['settings' => $settings]);
    \Illuminate\Support\Facades\Cache::flush();

    $quota = ['error' => ['code' => 403, 'message' => "Quota exceeded for quota metric 'Total Query Cost'", 'status' => 'RESOURCE_EXHAUSTED']];
    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/*'   => \Illuminate\Support\Facades\Http::response(['access_token' => 'at', 'expires_in' => 3600]),
        'gmail.googleapis.com/*'    => \Illuminate\Support\Facades\Http::response($quota, 403),
    ]);

    \Illuminate\Support\Facades\Log::spy();

    expect(\App\Actions\Comms\Mailbox\FetchShopMailboxMessages::wasRecentlyRefused($this->shop))->toBeFalse()
        ->and(fn () => \App\Actions\Comms\Mailbox\FetchShopMailboxMessages::run($this->shop))->toThrow(\Illuminate\Http\Client\RequestException::class)
        ->and(\App\Actions\Comms\Mailbox\FetchShopMailboxMessages::wasRecentlyRefused($this->shop))->toBeTrue()
        ->and(fn () => \App\Actions\Comms\Mailbox\FetchShopMailboxMessages::run($this->shop))->toThrow(\Illuminate\Http\Client\RequestException::class);

    // The whole of Gmail's answer is logged once per refusal, not the truncated exception message.
    \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'Gmail refused the fetch of new mail') && str_contains($context['body'], 'Total Query Cost'))
        ->once();

    $requestsBefore = count(\Illuminate\Support\Facades\Http::recorded());
    $page           = \App\Actions\Comms\Mailbox\ArchiveShopMailbox::make()->archivePage($this->shop, 12);

    expect($page['rate_limited'])->toBeTrue()
        ->and($page['pause'])->toBe(900)
        ->and(count(\Illuminate\Support\Facades\Http::recorded()))->toBe($requestsBefore);

    $this->travel(16)->minutes();
    expect(\App\Actions\Comms\Mailbox\FetchShopMailboxMessages::wasRecentlyRefused($this->shop))->toBeFalse();
});

test('a mailbox whose google token is revoked is marked and left alone until it is reconnected', function () {
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'history_id' => '100'];
    $this->shop->update(['settings' => $settings]);
    \Illuminate\Support\Facades\Cache::flush();

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400),
    ]);

    expect(fn () => \App\Actions\Comms\Mailbox\FetchShopMailboxMessages::run($this->shop))->toThrow(\Illuminate\Http\Client\RequestException::class)
        ->and(Arr::get($this->shop->fresh()->settings, 'gmail.revoked_at'))->not->toBeNull();

    $requestsBefore = count(\Illuminate\Support\Facades\Http::recorded());

    expect(\App\Actions\Comms\Mailbox\FetchShopMailboxMessages::run($this->shop->fresh()))->toBe(0)
        ->and(count(\Illuminate\Support\Facades\Http::recorded()))->toBe($requestsBefore);

    expect(\App\Services\Gmail\GmailClient::isShopMailboxUsable($this->shop->fresh()))->toBeFalse();

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'channel'          => \App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL,
        'guest_identifier' => 'guest_'.Str::random(5),
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'metadata'         => ['email_from' => 'cliente@example.com', 'gmail_thread_id' => 't1'],
    ]);

    expect(fn () => \App\Actions\Chat\ChatSession\SendChatMessage::make()->handle($session->fresh(), ['message_text' => 'Shipped today', 'sender_type' => ChatSenderTypeEnum::AGENT->value]))
        ->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and($session->messages()->count())->toBe(0);

    $session->delete();
    $settings = $this->shop->fresh()->settings;
    Arr::forget($settings, 'gmail');
    $this->shop->update(['settings' => $settings]);
});

test('the archive reads at most its hourly share of a mailbox, a hundred mails a page, and waits for the next hour once it is spent', function () {
    \Illuminate\Support\Facades\Queue::fake();
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);
    \Illuminate\Support\Facades\Cache::flush();
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 10:50', 'UTC'));

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/*'                           => \Illuminate\Support\Facades\Http::response(['access_token' => 'at', 'expires_in' => 3600]),
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => \Illuminate\Support\Facades\Http::response(['messages' => collect(range(1, 4))->map(fn ($n) => ['id' => "budget-$n"])->all(), 'nextPageToken' => 'next']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/*' => fn ($request) => \Illuminate\Support\Facades\Http::response([
            'id' => basename(parse_url($request->url(), PHP_URL_PATH)), 'threadId' => 'th-budget', 'labelIds' => ['INBOX'], 'internalDate' => '1780000000000',
            'payload' => ['mimeType' => 'text/plain', 'headers' => [['name' => 'From', 'value' => 'nobody@example.com'], ['name' => 'To', 'value' => 'care@shop.test']]],
        ]),
    ]);

    $action = \App\Actions\Comms\Mailbox\ArchiveShopMailbox::make();
    expect($action->archivePage($this->shop, 12))->toMatchArray(['rate_limited' => false, 'read' => 4]);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), 'users/me/messages?') && str_contains($request->url(), 'maxResults=100'));

    \Illuminate\Support\Facades\Cache::increment('mailbox-archive-reads:'.$this->shop->id.':'.now()->format('YmdH'), 1996);
    $requestsBefore = count(\Illuminate\Support\Facades\Http::recorded());
    $spent          = $action->archivePage($this->shop, 12);

    expect($spent['rate_limited'])->toBeTrue()
        ->and($spent['pause'])->toBe(601)
        ->and(count(\Illuminate\Support\Facades\Http::recorded()))->toBe($requestsBefore);

    $this->travel(11)->minutes();
    expect($action->archivePage($this->shop, 12)['rate_limited'])->toBeFalse();
});

test('the mailbox is archived a page per job, a few mails at a time, and a page gmail refuses is kept to read again, after longer and longer pauses while it keeps refusing', function () {
    \Illuminate\Support\Facades\Queue::fake();
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);
    \Illuminate\Support\Facades\Cache::forget(\App\Actions\Comms\Mailbox\ArchiveShopMailbox::cursorKey($this->shop, 12));
    \Illuminate\Support\Carbon::setTestNow(now());
    \Illuminate\Support\Sleep::fake();

    foreach (['p1', 'p2'] as $id) {
        createOwnCustomer($this->shop, "archive-page-$id")->update(['email' => "page.$id@example.com"]);
    }
    $raw = fn (string $id) => [
        'id' => $id, 'threadId' => 'th-page', 'labelIds' => ['INBOX'], 'internalDate' => '1780000000000',
        'payload' => ['mimeType' => 'text/plain', 'headers' => [['name' => 'From', 'value' => "page.$id@example.com"], ['name' => 'To', 'value' => 'care@shop.test'], ['name' => 'Subject', 'value' => 'Hello']],
            'body' => ['data' => rtrim(strtr(base64_encode("Question $id"), '+/', '-_'), '=')]],
    ];
    $refuse = true;
    $forbid = false;
    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/*'                       => \Illuminate\Support\Facades\Http::response(['access_token' => 'at', 'expires_in' => 3600]),
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'p1'], ['id' => 'p2'], ['id' => 'p3']], 'nextPageToken' => 'page-2']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/*' => function ($request) use ($raw, &$refuse, &$forbid) {
            $id = basename(parse_url($request->url(), PHP_URL_PATH));

            return match (true) {
                $forbid && $id === 'p2' => \Illuminate\Support\Facades\Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'insufficientPermissions']]]], 403),
                $refuse && $id === 'p2' => \Illuminate\Support\Facades\Http::response([], 429),
                default                 => \Illuminate\Support\Facades\Http::response($raw($id)),
            };
        },
    ]);

    $action  = \App\Actions\Comms\Mailbox\ArchiveShopMailbox::make();
    $refused = $action->archivePage($this->shop, 12);
    expect($refused['rate_limited'])->toBeTrue()
        ->and(\Illuminate\Support\Facades\Cache::get(\App\Actions\Comms\Mailbox\ArchiveShopMailbox::cursorKey($this->shop, 12)))->toBeNull();

    $refuse = false;
    $action->asJob($this->shop, 12);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), '/messages/p3?format=metadata&metadataHeaders=From'));
    \Illuminate\Support\Facades\Http::assertNotSent(fn ($request) => str_contains($request->url(), '/messages/p3?format=full'));
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), '/messages/p1?format=full'));
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), 'q=newer_than%3A12m') && str_contains(urldecode($request->url()), '-category:promotions') && str_contains(urldecode($request->url()), '-subject:"transaction receipt"'));
    expect(\App\Models\Comms\EmailArchiveMessage::where('shop_id', $this->shop->id)->whereIn('gmail_message_id', ['p1', 'p2', 'p3'])->count())->toBe(2)
        ->and(\Illuminate\Support\Facades\Cache::get(\App\Actions\Comms\Mailbox\ArchiveShopMailbox::cursorKey($this->shop, 12)))->toBe('page-2');
    \App\Actions\Comms\Mailbox\ArchiveShopMailbox::assertPushed(1);

    $this->artisan('mailbox:archive', ['shop' => $this->shop->slug, '--queue' => true])->expectsOutput($this->shop->slug.': queued')->assertExitCode(0);
    \App\Actions\Comms\Mailbox\ArchiveShopMailbox::assertPushed(2);

    $action->asJob($this->shop, 12, 'a-chain-the-command-replaced');
    \App\Actions\Comms\Mailbox\ArchiveShopMailbox::assertPushed(2);

    $reset = function () {
        \App\Models\Comms\EmailArchiveMessage::where('shop_id', $this->shop->id)->whereIn('gmail_message_id', ['p1', 'p2'])->delete();
        \Illuminate\Support\Facades\Cache::forget(\App\Actions\Comms\Mailbox\ArchiveShopMailbox::cursorKey($this->shop, 12));
    };

    $reset();
    $refuse = true;
    $pages  = collect(range(1, 10))->map(fn () => $action->archivePage($this->shop, 12));
    expect($pages->pluck('pause')->all())->toBe([60, 120, 240, 480, 960, 1800, 1800, 1800, 1800, 1800])
        ->and($pages->last())->toMatchArray(['rate_limited' => true, 'stopped' => false]);

    $reset();
    $refuse = false;
    $forbid = true;
    expect($action->archivePage($this->shop, 12))->toMatchArray(['rate_limited' => false, 'failed' => 1, 'archived' => 1]);

    $reset();
    \Illuminate\Support\Facades\Cache::forget(\App\Actions\Comms\Mailbox\ArchiveShopMailbox::runKey($this->shop));
    \Illuminate\Support\Carbon::setTestNow();
});

test('the archive reads more of a mailbox an hour at night and at weekends, when customer service is not answering', function () {
    $mailbox = \App\Actions\Comms\Mailbox\ArchiveShopMailbox::class;
    $at      = fn (string $when) => \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse($when, 'UTC'));

    $at('2026-10-06 10:00');
    expect($mailbox::readsPerHour())->toBe(2000);
    $at('2026-10-06 04:59');
    expect($mailbox::readsPerHour())->toBe(6000);
    $at('2026-10-06 18:00');
    expect($mailbox::readsPerHour())->toBe(6000);
    $at('2026-10-04 12:00');
    expect($mailbox::readsPerHour())->toBe(6000);

    \Illuminate\Support\Carbon::setTestNow();
});

test('oldest first reads the mailbox a calendar month at a time from the oldest, and a run whose mark left the cache carries on', function () {
    \Illuminate\Support\Facades\Queue::fake();
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);
    $mailbox = \App\Actions\Comms\Mailbox\ArchiveShopMailbox::class;
    \Illuminate\Support\Carbon::setTestNow('2026-10-15 12:00:00');
    \Illuminate\Support\Facades\Cache::forget($mailbox::cursorKey($this->shop, 3, false, true));
    \Illuminate\Support\Facades\Cache::forget($mailbox::runKey($this->shop));
    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/*'                           => \Illuminate\Support\Facades\Http::response(['access_token' => 'at', 'expires_in' => 3600]),
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => \Illuminate\Support\Facades\Http::response(['messages' => []]),
    ]);

    $july   = \Illuminate\Support\Carbon::parse('2026-07-01')->timestamp;
    $august = \Illuminate\Support\Carbon::parse('2026-08-01')->timestamp;
    expect($mailbox::monthByMonth(\Illuminate\Support\Carbon::parse('2026-07-20')))->toHaveCount(4)->and($mailbox::monthByMonth(now())[0])->toStartWith('after:'.now()->startOfMonth()->timestamp);

    $mailbox::make()->asJob($this->shop, 3, 'a-run-whose-mark-was-cleared', false, true);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), "after:$july before:$august"));
    expect(\Illuminate\Support\Facades\Cache::get($mailbox::cursorKey($this->shop, 3, false, true)))->toMatchArray(['search' => 1, 'page' => null, 'from' => $july]);
    $mailbox::assertPushed(1);

    $mailbox::make()->asJob($this->shop, 3, 'a-run-whose-mark-was-cleared', false, true);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), "after:$august before:"));

    \Illuminate\Support\Facades\Cache::forget($mailbox::cursorKey($this->shop, 3, false, true));
    \Illuminate\Support\Carbon::setTestNow();
});

test('the best customers have all their mail archived, searched by their addresses with no date limit', function () {
    \Illuminate\Support\Facades\Queue::fake();
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);
    $mailbox = \App\Actions\Comms\Mailbox\ArchiveShopMailbox::class;

    $buyer = function (string $key, float $amount) {
        $customer = createOwnCustomer($this->shop, "archive-top-$key");
        $customer->update(['email' => "top.$key.".Str::lower(Str::random(6)).'@example.com']);
        foreach (range(1, 12) as $ignored) {
            \App\Actions\Accounting\Invoice\StoreInvoice::make()->action($customer, \App\Models\Accounting\Invoice::factory()->definition())
                ->update(['grp_net_amount' => $amount, 'date' => now()->subMonth()]);
        }

        return $customer;
    };
    $big   = $buyer('big', 1000000000);
    $small = $buyer('small', 0.01);
    $vip   = createOwnCustomer($this->shop, 'archive-top-vip');
    $vip->update(['email' => 'top.vip.'.Str::lower(Str::random(6)).'@example.com', 'is_vip' => true]);

    \Illuminate\Support\Facades\Cache::forget('mailbox-archive-top-addresses:'.$this->shop->id);
    \Illuminate\Support\Facades\Cache::forget($mailbox::cursorKey($this->shop, 12, true));
    expect($mailbox::topAddresses($this->shop))->toContain($big->email, $vip->email)->not->toContain($small->email);

    $old = [
        'id' => 'top-old-'.$vip->id, 'threadId' => 'th-top', 'labelIds' => ['INBOX'], 'internalDate' => '1500000000000',
        'payload' => ['mimeType' => 'text/plain', 'headers' => [['name' => 'From', 'value' => $vip->email], ['name' => 'To', 'value' => 'care@shop.test'], ['name' => 'Subject', 'value' => 'Our first order']],
            'body' => ['data' => rtrim(strtr(base64_encode('Can we open an account?'), '+/', '-_'), '=')]],
    ];
    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/*'                           => \Illuminate\Support\Facades\Http::response(['access_token' => 'at', 'expires_in' => 3600]),
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => $old['id']]]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/*' => \Illuminate\Support\Facades\Http::response($old),
    ]);

    $this->artisan('mailbox:archive', ['shop' => $this->shop->slug, '--top' => true])->assertExitCode(0);

    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), "from:{$vip->email} to:{$vip->email} cc:{$vip->email}") && !str_contains($request->url(), 'newer_than'));
    expect(\App\Models\Comms\EmailArchiveMessage::where('shop_id', $this->shop->id)->where('gmail_message_id', $old['id'])->value('customer_id'))->toBe($vip->id)
        ->and(\Illuminate\Support\Facades\Cache::get($mailbox::cursorKey($this->shop, 12, true)))->toBeNull();

    \App\Models\Comms\EmailArchiveMessage::where('gmail_message_id', $old['id'])->delete();
    \Illuminate\Support\Facades\Cache::forget('mailbox-archive-top-addresses:'.$this->shop->id);
    \Illuminate\Support\Facades\Cache::forget($mailbox::runKey($this->shop, true));
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

test('a mail that is not utf8 still comes through, and one left in the inbox is swept up', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    // Written in Latin-1 and labelled as such: stored as it arrived these are bytes Postgres
    // refuses, and the whole mail used to be lost on the way in.
    $latin1 = mb_convert_encoding('Où est ma commande ?', 'ISO-8859-1', 'UTF-8');

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/u1?*' => \Illuminate\Support\Facades\Http::response([
            'id'       => 'u1',
            'threadId' => 'tu1',
            'payload'  => [
                'mimeType' => 'text/plain',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Stranger <stranger@example.com>'],
                    ['name' => 'Subject', 'value' => 'Commande'],
                    ['name' => 'Content-Type', 'value' => 'text/plain; charset="ISO-8859-1"'],
                ],
                'body'     => ['data' => rtrim(strtr(base64_encode($latin1), '+/', '-_'), '=')],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/messages?*'    => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'u1']]]),
        'gmail.googleapis.com/gmail/v1/users/me/history*'      => \Illuminate\Support\Facades\Http::response(['history' => [], 'historyId' => '2']),
        'gmail.googleapis.com/gmail/v1/users/me/labels'        => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L2', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    // History knows nothing about it, which is the case for every mail whose job died: the inbox
    // sweep is what still finds it.
    Bus::fake();
    expect(\App\Actions\Comms\Mailbox\FetchShopMailboxMessages::make()->handle($this->shop))->toBe(1);
    Bus::assertDispatched(\Lorisleiva\Actions\Decorators\JobDecorator::class);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::make()->handle($this->shop->fresh(), 'u1');

    expect($message->message_text)->toBe('Où est ma commande ?');
});

test('a mailbox already belonging to another shop is refused rather than connected to a second one', function () {
    $this->shop->update(['settings' => Arr::except($this->shop->settings ?? [], 'gmail')]);

    $other = \App\Models\Catalogue\Shop::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->organisation->group_id,
        'settings'        => ['gmail' => ['email' => 'kundservice@shop.test']],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response([
            'access_token'  => 'at',
            'refresh_token' => 'rt',
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/profile'       => \Illuminate\Support\Facades\Http::response([
            'emailAddress' => 'kundservice@shop.test',
            'historyId'    => '7',
        ]),
    ]);

    $state = ['shop_id' => $this->shop->id, 'user_id' => 1, 'return' => '/back'];

    $response = \App\Actions\Comms\Mailbox\CallbackShopMailbox::make()->handle('code', $state);

    // Refused, and nothing written: the second shop never gets a token, so it never fetches the
    // same inbox and the mail is taken in once.
    expect(session('notification')['status'])->toBe('error')
        ->and(session('notification')['description'])->toContain($other->name)
        ->and(Arr::get($this->shop->fresh()->settings, 'gmail'))->toBeNull()
        ->and($response->getTargetUrl())->toContain('/back');
});

test('a gmail message already being imported is refused, and a failed import gives the id back', function () {
    $original          = $this->shop->settings ?? [];
    $settings          = $original;
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                        => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/m2*' => \Illuminate\Support\Facades\Http::response([], 500),
        'gmail.googleapis.com/*'                             => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $sessions = ChatSession::count();

    \Illuminate\Support\Facades\Cache::add('gmail-message-claim:m2', true, now()->addMinutes(10));

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'm2'))->toBeNull()
        ->and(ChatSession::count())->toBe($sessions);

    \Illuminate\Support\Facades\Http::assertNothingSent();

    \Illuminate\Support\Facades\Cache::forget('gmail-message-claim:m2');

    expect(fn () => \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'm2'))
        ->toThrow(\Illuminate\Http\Client\RequestException::class)
        ->and(\Illuminate\Support\Facades\Cache::has('gmail-message-claim:m2'))->toBeFalse();

    $this->shop->update(['settings' => $original]);
});

test('a letter whose second byte looks like a line break survives the quoted reply trim', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    // The second byte of "a with ogonek" is 0x85, which PCRE's \R treats as a line break: splitting
    // on it cut the letter in half and left bytes the database refuses.
    $body = "Dzień dobry,\n\njestem zainteresowana Państwa ofertą produktów 📅\n\nOn Mon, Bob wrote:\n> old";

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/p1?*' => \Illuminate\Support\Facades\Http::response([
            'id'       => 'p1',
            'threadId' => 'tp1',
            'payload'  => [
                'mimeType' => 'text/plain',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Aleksandra <ala@example.com>'],
                    ['name' => 'Subject', 'value' => 'White Label'],
                    ['name' => 'Content-Type', 'value' => 'text/plain; charset="UTF-8"'],
                ],
                'body'     => ['data' => rtrim(strtr(base64_encode($body), '+/', '-_'), '=')],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'        => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L2', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'p1');

    expect($message->message_text)->toBe("Dzień dobry,\n\njestem zainteresowana Państwa ofertą produktów 📅")
        ->and(mb_check_encoding($message->message_text, 'UTF-8'))->toBeTrue();
});

test('thread history already taken in under another conversation is not written a second time', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $original          = $this->shop->settings ?? [];
    $settings          = $original;
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $body = fn (string $text) => rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    $mail = fn (string $id, string $subject, string $text) => [
        'id'       => $id,
        'threadId' => 't3',
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => [
                ['name' => 'From', 'value' => 'Split Sender <split@example.com>'],
                ['name' => 'Subject', 'value' => $subject],
                ['name' => 'Message-ID', 'value' => "<$id@example.com>"],
            ],
            'body'     => ['data' => $body($text)],
        ],
        'internalDate' => '1758500000000',
    ];

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                        => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/m3*' => \Illuminate\Support\Facades\Http::response($mail('m3', 'Second mail', 'The newer one')),
        'gmail.googleapis.com/gmail/v1/users/me/threads/t3*'  => \Illuminate\Support\Facades\Http::response([
            'messages' => [$mail('old3', 'First mail', 'The older one'), $mail('m3', 'Second mail', 'The newer one')],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'      => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L2', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                             => \Illuminate\Support\Facades\Http::response([]),
    ]);

    // The same thread already read under another conversation, which is what a mailbox shared by
    // two shops left behind: its history must not be written into this one as well.
    $elsewhere = StoreChatSession::run([
        'shop_id'     => $this->shop->id,
        'language_id' => $this->shop->language_id,
        'priority'    => \App\Enums\CRM\Livechat\ChatPriorityEnum::NORMAL,
        'channel'     => \App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL,
    ]);
    $elsewhere->messages()->create([
        'message_text' => 'The older one',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::GUEST,
        'metadata'     => ['gmail_message_id' => 'old3'],
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'm3');

    expect($message)->not->toBeNull()
        ->and($message->chatSession->messages()->count())->toBe(1)
        ->and(ChatMessage::where('metadata->gmail_message_id', 'old3')->count())->toBe(1);

    $this->shop->update(['settings' => $original]);
});

test('the inbox sweep never reaches back past the day the mailbox was connected', function () {
    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'connected_at'  => '2026-09-16T09:00:00+00:00',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/*'      => \Illuminate\Support\Facades\Http::response(['messages' => [], 'historyId' => '9']),
    ]);

    \App\Actions\Comms\Mailbox\FetchShopMailboxMessages::make()->handle($this->shop);

    // Years of mail predate the connection and were never offered to Aiku. Sweeping them would
    // open a conversation dated today for every one of them.
    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return str_contains($request->url(), 'users/me/messages')
            && str_contains(urldecode($request->url()), 'in:inbox after:2026/09/16');
    });
});

test('filing an imported mail away writes down whether it was unread', function () {
    $original          = $this->shop->settings ?? [];
    $settings          = $original;
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                        => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/uf1*' => \Illuminate\Support\Facades\Http::response([
            'id'       => 'uf1',
            'threadId' => 'tuf1',
            'labelIds' => ['INBOX', 'UNREAD'],
            'payload'  => [
                'mimeType' => 'text/plain',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Unread Sender <unread@example.com>'],
                    ['name' => 'Subject', 'value' => 'Still unread'],
                    ['name' => 'Message-ID', 'value' => '<uf1@example.com>'],
                ],
                'body'     => ['data' => rtrim(strtr(base64_encode('Hello there'), '+/', '-_'), '=')],
            ],
            'internalDate' => '1758500000000',
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'      => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L2', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                             => \Illuminate\Support\Facades\Http::response([]),
    ]);

    \Illuminate\Support\Facades\Log::spy();

    expect(\App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'uf1'))->not->toBeNull();

    \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->withArgs(fn ($message, $context = []) => $message === 'gmail-file-away'
            && $context['message'] === 'uf1'
            && $context['was_unread'] === true
            && $context['was_inbox'] === true);

    $this->shop->update(['settings' => $original]);
});

test('email attachments over the media library limit are left in the mail', function () {
    $isWorthImporting = fn (int $size) => (fn () => $this->isWorthImporting(['size' => $size, 'mimeType' => 'video/mp4', 'inline' => false], true))
        ->call(new App\Actions\Comms\Mailbox\ImportPendingGmailAttachments());

    expect($isWorthImporting(1024))->toBeTrue()
        ->and($isWorthImporting(91 * 1024 * 1024))->toBeFalse();
});

test('gmail spam from customers who bought, replies and genuine strangers comes in with its files held back, the rest stays in gmail spam labelled so it is never read twice, vague buyers and replies to conversations put aside included', function () {
    Bus::fake();
    config()->set('services.openrouter.api_key', 'or-key');

    StoreWebUser::make()->action($this->customer, array_merge(WebUser::factory()->definition(), ['email' => 'spam-buyer@example.com']));

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $encode  = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $message = fn (string $id, string $from, string $subject, array $extraHeaders = [], ?string $threadId = null) => \Illuminate\Support\Facades\Http::response([
        'id'       => $id,
        'threadId' => $threadId ?? "t$id",
        'labelIds' => ['SPAM', 'UNREAD'],
        'payload'  => [
            'mimeType' => 'multipart/mixed',
            'headers'  => array_merge([['name' => 'From', 'value' => $from], ['name' => 'Subject', 'value' => $subject]], $extraHeaders),
            'parts'    => [
                ['mimeType' => 'text/plain', 'filename' => '', 'body' => ['data' => $encode('Hello')]],
                ['mimeType' => 'application/pdf', 'filename' => 'invoice.pdf', 'body' => ['attachmentId' => 'att1', 'size' => 2000]],
            ],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/sp0*' => $message('sp0', 'Buyer <spam-buyer@example.com>', 'Just registered'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/sp1*' => $message('sp1', 'Buyer <spam-buyer@example.com>', 'Where is my order?'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/sp2*' => $message('sp2', 'Deals <deals@promo.example.net>', 'Grow your SEO', [['name' => 'List-Unsubscribe', 'value' => '<mailto:x@promo.example.net>']]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/sp3*' => $message('sp3', 'New Shop <owner@newshop.example.net>', 'Wholesale account'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/sp4*' => $message('sp4', 'Prince <prince@scam.example.net>', 'Urgent transfer'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/sp5*' => $message('sp5', 'Sourcing <buyer@sourcing.example.net>', 'Send your price list'),
        'gmail.googleapis.com/gmail/v1/users/me/messages/sp6*' => $message('sp6', 'New Shop <owner@newshop.example.net>', 'Re: Wholesale account', [], 'tsp3'),
        'openrouter.ai/api/alpha/decisions'                    => \Illuminate\Support\Facades\Http::sequence()
            ->push(['answers' => [
                'kind'      => ['type' => 'choice', 'choice' => 'service_pitch', 'probabilities' => ['service_pitch' => 0.9, 'prospect' => 0.1]],
                'scam_form' => ['type' => 'choice', 'choice' => 'none', 'probabilities' => ['none' => 0.95]],
            ]])
            ->push(['answers' => ['scam_form' => ['type' => 'choice', 'choice' => 'none', 'probabilities' => ['link_to_details' => 0.35, 'none' => 0.65]]]])
            ->push(['answers' => [
                'kind'      => ['type' => 'choice', 'choice' => 'prospect', 'probabilities' => ['prospect' => 0.35, 'vague_buyer' => 0.3, 'customer_request' => 0.05]],
                'scam_form' => ['type' => 'choice', 'choice' => 'none', 'probabilities' => ['none' => 0.9]],
            ]])
            ->push(['answers' => [
                'kind'      => ['type' => 'choice', 'choice' => 'prospect', 'probabilities' => ['prospect' => 0.6, 'scam' => 0.4]],
                'scam_form' => ['type' => 'choice', 'choice' => 'payment_copy', 'probabilities' => ['payment_copy' => 0.7, 'none' => 0.3]],
            ]])
            ->push(['answers' => [
                'kind'      => ['type' => 'choice', 'choice' => 'vague_buyer', 'probabilities' => ['vague_buyer' => 0.5, 'prospect' => 0.4]],
                'scam_form' => ['type' => 'choice', 'choice' => 'none', 'probabilities' => ['none' => 0.95]],
            ]]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'        => \Illuminate\Support\Facades\Http::response(['labels' => [
            ['id' => 'LI', 'name' => 'aiku/imported'],
            ['id' => 'LU', 'name' => 'aiku/unmatched'],
            ['id' => 'LC', 'name' => 'aiku/spam-checked'],
            ['id' => 'LF', 'name' => 'aiku/filtered'],
            ['id' => 'LS', 'name' => 'aiku/spam'],
        ]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => fn ($request) => \Illuminate\Support\Facades\Http::response(
            str_contains($request->data()['q'] ?? '', 'in:spam') ? ['messages' => [['id' => 'sp1']]] : []
        ),
        'gmail.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $inbound = \App\Actions\Comms\Mailbox\ProcessInboundEmail::class;

    expect($inbound::run($this->shop, 'sp0'))->toBeNull();

    $this->customer->stats()->update(['number_invoices_type_invoice' => 1]);

    $fromCustomer = $inbound::run($this->shop, 'sp1');

    expect($fromCustomer)->toBeInstanceOf(ChatMessage::class)
        ->and($fromCustomer->is_rescued_from_spam)->toBeTrue()
        ->and($fromCustomer->is_possible_scam)->toBeTrue()
        ->and($fromCustomer->attachedFiles())->toHaveCount(0)
        ->and(Arr::get($fromCustomer->metadata, 'gmail_pending_attachments'))->toBe(1)
        ->and(\App\Actions\Comms\Mailbox\ImportPendingGmailAttachments::make()->handle($fromCustomer->chatSession))->toBe(0)
        ->and(\App\Http\Resources\CRM\Livechat\ChatMessageResource::make($fromCustomer)->resolve()['is_rescued_from_spam'])->toBeTrue()
        ->and(\App\Http\Resources\CRM\Livechat\ChatMessageResource::make($fromCustomer)->resolve()['spam_rescue_kind_label'])->toBeNull()
        ->and($inbound::run($this->shop, 'sp2'))->toBeNull()
        ->and($stranger = $inbound::run($this->shop, 'sp3'))->toBeInstanceOf(ChatMessage::class)
        ->and($stranger->spam_rescue_kind)->toBe(\App\Enums\CRM\Livechat\ChatSpamRescueKindEnum::PROSPECT)
        ->and($stranger->is_possible_scam)->toBeFalse()
        ->and($fromCustomer->spam_rescue_kind)->toBeNull()
        ->and($inbound::run($this->shop, 'sp4'))->toBeNull()
        ->and($inbound::run($this->shop, 'sp5'))->toBeNull();

    $stranger->chatSession->update(['is_spam' => true]);
    $askedBeforeReply = \Illuminate\Support\Facades\Http::recorded(fn ($request) => str_contains($request->url(), 'openrouter.ai'))->count();

    expect($inbound::run($this->shop, 'sp6'))->toBeNull()
        ->and(\Illuminate\Support\Facades\Http::recorded(fn ($request) => str_contains($request->url(), 'openrouter.ai'))->count())->toBe($askedBeforeReply);

    $filed = fn (string $id) => \Illuminate\Support\Facades\Http::recorded(fn ($request) => str_ends_with($request->url(), "messages/$id/modify"))
        ->map(fn ($pair) => $pair[0]->data())->first();

    expect($filed('sp1'))->toBe(['addLabelIds' => ['LI'], 'removeLabelIds' => ['INBOX', 'UNREAD', 'SPAM']])
        ->and($filed('sp3'))->toBe(['addLabelIds' => ['LU'], 'removeLabelIds' => ['INBOX', 'UNREAD', 'SPAM']])
        ->and($filed('sp0'))->toBe(['addLabelIds' => ['LC'], 'removeLabelIds' => ['INBOX']])
        ->and($filed('sp2'))->toBe(['addLabelIds' => ['LC'], 'removeLabelIds' => ['INBOX']])
        ->and($filed('sp4'))->toBe(['addLabelIds' => ['LC'], 'removeLabelIds' => ['INBOX']])
        ->and($filed('sp5'))->toBe(['addLabelIds' => ['LC'], 'removeLabelIds' => ['INBOX']])
        ->and($filed('sp6'))->toBe(['addLabelIds' => ['LC'], 'removeLabelIds' => ['INBOX']]);

    \App\Actions\Comms\Mailbox\FetchShopMailboxMessages::make()->handle($this->shop->fresh());

    $relabelled = \Illuminate\Support\Facades\Http::recorded(fn ($request) => str_ends_with($request->url(), 'messages/sp1/modify'))->map(fn ($pair) => $pair[0]->data())->last();

    expect($relabelled)->toBe(['addLabelIds' => ['LC'], 'removeLabelIds' => ['INBOX']]);

    $spamSweep = \Illuminate\Support\Facades\Http::recorded(fn ($request) => str_contains(urldecode($request->url()), 'in:spam'))->first()[0];

    expect($spamSweep->data()['q'])->toContain('in:spam -label:aiku-spam-checked')
        ->and((int) $spamSweep->data()['maxResults'])->toBe(25);

    $asked = \Illuminate\Support\Facades\Http::recorded(fn ($request) => str_contains($request->url(), 'openrouter.ai'))->first()[0]->data();

    expect($asked['questions']['kind']['criteria'])->toBe(\App\Enums\CRM\Livechat\ChatSpamRescueKindEnum::definitions())
        ->and($asked['questions']['scam_form']['criteria'])->toBe(\App\Enums\CRM\Livechat\ChatSpamRescueKindEnum::scamForms());
});

test('a gmail quota 403 releases the job to try again, while a permissions 403 still fails it', function () {
    $job = new class () {
        public ?int $releasedFor = null;

        public function release(int $delay): void
        {
            $this->releasedFor = $delay;
        }
    };

    $refuse = function (array $body, int $status) use ($job) {
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response($body, $status)]);

        (new \App\Services\Gmail\ReleaseWhenGmailRateLimited())->handle($job, fn () => \Illuminate\Support\Facades\Http::post('https://gmail.googleapis.com/x')->throw());
    };

    $refuse(['error' => ['code' => 403, 'message' => "Quota exceeded for quota metric 'Total Query Cost' and limit 'Units per minute per user'", 'status' => 'RESOURCE_EXHAUSTED']], 403);
    expect($job->releasedFor)->toBeBetween(60, 120);

    $job->releasedFor = null;
    $refuse(['error' => ['code' => 429, 'message' => 'Too many requests']], 429);
    expect($job->releasedFor)->toBeBetween(60, 120);

    $job->releasedFor = null;
    expect(fn () => $refuse(['error' => ['code' => 403, 'message' => 'Request had insufficient authentication scopes.', 'status' => 'PERMISSION_DENIED', 'errors' => [['reason' => 'insufficientPermissions']]]], 403))
        ->toThrow(\Illuminate\Http\Client\RequestException::class)
        ->and($job->releasedFor)->toBeNull();
});

test('a mailbox whose Gmail access was revoked does not stop the other shops being fetched', function () {
    Bus::fake();

    $otherShop = \App\Models\Catalogue\Shop::where('id', '!=', $this->shop->id)->first()
        ?? \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());

    foreach ([$this->shop, $otherShop] as $shop) {
        $settings          = $shop->settings ?? [];
        $settings['gmail'] = [
            'email'         => "care{$shop->id}@shop.test",
            'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString($shop->is($this->shop) ? 'revoked' : 'rt'),
            'history_id'    => '1',
        ];
        $shop->update(['settings' => $settings]);
    }

    \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
            return $request['refresh_token'] === 'revoked'
                ? \Illuminate\Support\Facades\Http::response(['error' => 'invalid_grant'], 400)
                : \Illuminate\Support\Facades\Http::response(['access_token' => 'at']);
        }

        return str_contains($request->url(), 'users/me/history')
            ? \Illuminate\Support\Facades\Http::response(['history' => [], 'historyId' => '2'])
            : \Illuminate\Support\Facades\Http::response([]);
    });

    $this->artisan('mailbox:fetch')->assertSuccessful();

    expect(Arr::get($otherShop->fresh()->settings, 'gmail.history_id'))->toBe('2')
        ->and(Arr::get($this->shop->fresh()->settings, 'gmail.history_id'))->toBe('1');
});

test('the marketing folder lists what the shop sent its customers lately and shows the email', function () {
    actingAs($this->user);
    $outbox = $this->shop->outboxes()->where('code', \App\Enums\Comms\Outbox\OutboxCodeEnum::MARKETING)->first();
    $make   = function (array $data) use ($outbox) {
        $mailshot = \App\Actions\Comms\Mailshot\StoreMailshot::make()->action($outbox, \App\Models\Comms\Mailshot::factory()->definition());
        $mailshot->update($data);

        return $mailshot;
    };

    $sent     = $make(['state' => \App\Enums\Comms\Mailshot\MailshotStateEnum::SENT, 'type' => \App\Enums\Comms\Mailshot\MailshotTypeEnum::MARKETING, 'sent_at' => now()->subDay()]);
    $old      = $make(['state' => \App\Enums\Comms\Mailshot\MailshotStateEnum::SENT, 'sent_at' => now()->subDays(40)]);
    $draft    = $make(['state' => \App\Enums\Comms\Mailshot\MailshotStateEnum::IN_PROCESS]);
    $invite   = $make(['state' => \App\Enums\Comms\Mailshot\MailshotStateEnum::SENT, 'type' => \App\Enums\Comms\Mailshot\MailshotTypeEnum::INVITE, 'sent_at' => now()]);
    $snapshot = $sent->email->unpublishedSnapshot;
    $snapshot->update(['compiled_layout' => '<p>15% off incense</p>']);
    $sent->email->update(['live_snapshot_id' => $snapshot->id]);

    $ids = collect(getJson(route('grp.org.chat.marketing.index', [$this->organisation->slug]).'?shop_ids[]='.$this->shop->id)->assertOk()->json())->pluck('id');

    expect($ids)->toContain($sent->id)
        ->not->toContain($old->id)
        ->not->toContain($draft->id)
        ->not->toContain($invite->id);

    getJson(route('grp.org.chat.marketing.show', [$this->organisation->slug, $sent->id]))
        ->assertOk()
        ->assertJsonPath('html', '<p>15% off incense</p>');
});

test('a reply in an email conversation with no customer address left is not sent to gmail', function () {
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['id' => 'sent1'])]);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $session = ChatSession::create([
        'ulid'     => (string) Str::ulid(),
        'shop_id'  => $this->shop->id,
        'status'   => ChatSessionStatusEnum::ACTIVE,
        'channel'          => ChatChannelEnum::EMAIL,
        'priority'         => ChatPriorityEnum::NORMAL,
        'ai_model_version' => 'default',
        'metadata'         => ['ai_summary' => ['summary' => 'profile deleted']],
    ]);
    $reply = $session->messages()->create([
        'message_text' => 'Your profile is deleted',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::SYSTEM,
    ]);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply);

    \Illuminate\Support\Facades\Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request) => str_ends_with($request->url(), 'users/me/messages/send'));
});

test('gmail mail is imported, answered and sent on the urgent queue, clear of the default queue backlog', function () {
    \Illuminate\Support\Facades\Queue::fake();

    \App\Actions\Comms\Mailbox\ProcessInboundEmail::dispatch($this->shop, 'q1');
    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::dispatch(new ChatMessage());
    \App\Actions\Comms\Mailbox\ImportPendingGmailAttachments::dispatch(new ChatSession());
    \App\Actions\Chat\ChatSession\SendOutOfHoursReply::dispatch(new ChatSession(), new ChatMessage());

    expect(collect(\Illuminate\Support\Facades\Queue::pushedJobs())->flatten(1)->pluck('queue')->all())
        ->toBe(['urgent', 'urgent', 'urgent', 'urgent']);
});
