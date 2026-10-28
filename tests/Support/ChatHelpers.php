<?php

use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;

/**
 * Grants chat access the real way: the customer service clerk position on the shop, the
 * only thing anybody has to set up now that the legacy shop_has_chat_agents table is gone.
 */
function makeChatWorker(User $user, \App\Models\Catalogue\Shop $shop): void
{
    setPermissionsTeamId($user->group_id);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $shop));
    \App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($user);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

function pendingAttachmentsTestInboundGuestEmail(\App\Models\Catalogue\Shop $shop, string $gmailMessageId): ChatMessage
{
    $settings = $shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $shop->update(['settings' => $settings]);

    $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                                         => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        "gmail.googleapis.com/gmail/v1/users/me/messages/$gmailMessageId/attachments/att1" => \Illuminate\Support\Facades\Http::response(['data' => $encode('%PDF-1.4 invoice')]),
        "gmail.googleapis.com/gmail/v1/users/me/messages/$gmailMessageId*"                 => \Illuminate\Support\Facades\Http::response([
            'id'       => $gmailMessageId,
            'threadId' => "t$gmailMessageId",
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

    return \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($shop, $gmailMessageId);
}

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

function outOfHoursTestSchedule(\App\Models\Catalogue\Shop $shop): \App\Models\HumanResources\WorkSchedule
{
    $tz = \App\Models\Helpers\Timezone::where('name', 'Europe/London')->first();
    $shop->update(['timezone_id' => $tz->id, 'language_id' => \App\Models\Helpers\Language::where('code', 'en')->value('id')]);

    $schedule = \App\Models\HumanResources\WorkSchedule::create([
        'name'             => 'Chat hours',
        'schedulable_type' => 'Shop',
        'schedulable_id'   => $shop->id,
        'timezone_id'      => $tz->id,
        'type'             => 'default',
        'is_active'        => true,
    ]);

    foreach (range(1, 7) as $dayOfWeek) {
        $schedule->days()->create([
            'day_of_week'    => $dayOfWeek,
            'is_working_day' => $dayOfWeek <= 5,
            'start_time'     => '10:00:00',
            'end_time'       => '14:00:00',
        ]);
    }

    return $schedule;
}

function outOfHoursTestCleanUp(\App\Models\HumanResources\WorkSchedule $schedule, array $sessions = []): void
{
    foreach ($sessions as $session) {
        $session->messages()->withTrashed()->forceDelete();
        $session->chatEvents()->delete();
        $session->forceDelete();
    }

    $schedule->days()->delete();
    $schedule->delete();
    \Illuminate\Support\Carbon::setTestNow();
}

/**
 * Stores a real picture or pdf on a chat or WhatsApp message the way the inbound flows do.
 */
function chatFileOnMessage(\Illuminate\Database\Eloquent\Model $message, string $name, bool $isImage = true): \App\Models\Helpers\Media
{
    $png  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $body = ($isImage ? $png : '%PDF-1.4 ').$name.Str::random(8);
    $path = tempnam(sys_get_temp_dir(), 'chat-file-');
    file_put_contents($path, $body);

    try {
        return \App\Actions\Helpers\Media\StoreMediaFromFile::run(
            $message,
            [
                'path'         => $path,
                'originalName' => $name,
                'extension'    => pathinfo($name, PATHINFO_EXTENSION),
                'checksum'     => md5_file($path),
            ],
            $isImage ? 'chat_images' : 'chat_attachments',
            $isImage ? 'image' : 'file'
        );
    } finally {
        unlink($path);
    }
}
