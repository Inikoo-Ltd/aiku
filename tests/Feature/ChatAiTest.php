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
use App\Actions\Chat\ChatSession\GetChatCustomerProfile;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\SummarizeChatSession;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Models\Helpers\Language;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
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
        ->and(Arr::get($chatSession->metadata, 'ai_summary.model'))->toBe(config('chat.summary_writer_model'))
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

test('a long email gets a short summary beside it and a short one is left alone', function () {
    \Illuminate\Support\Facades\Http::fake();

    $long  = noiseTestEmailSession($this->shop, 'long@example.com', 'Order 1234', 'Please send order 1234 to 5 High St. '.str_repeat('Kind regards and our company disclaimer. ', 30))->messages()->first();
    $short = noiseTestEmailSession($this->shop, 'short@example.com', 'Order 99', 'Where is order 99?')->messages()->first();

    \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()->andReturn('{"summary": "Wants order 1234 sent to 5 High St."}');

    expect(\App\Actions\Chat\ChatSession\SummarizeLongEmail::run($long))->toBe('Wants order 1234 sent to 5 High St.')
        ->and(\App\Actions\Chat\ChatSession\SummarizeLongEmail::run($short))->toBeNull()
        ->and(data_get($long->refresh()->metadata, 'ai_summary'))->toBe('Wants order 1234 sent to 5 High St.')
        ->and($long->message_text)->toStartWith('Please send order 1234');
});

test('a claim is prepared for the agent: the order the customer names, and a replacement reason from what they wrote', function () {
    $customer  = createOwnCustomer($this->shop, 'claim-case');
    $webUser   = \App\Actions\CRM\WebUser\StoreWebUser::make()->action($customer, WebUser::factory()->definition());
    $reference = 'CLM'.random_int(100000, 999999);

    \Illuminate\Support\Facades\DB::table('orders')->insert([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $this->shop->currency_id,
        'tax_category_id' => \App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'slug'            => 'ord-'.uniqid(),
        'reference'       => $reference,
        'state'           => 'dispatched',
        'net_amount'      => 100,
        'org_net_amount'  => 100,
        'grp_net_amount'  => 100,
        'status'          => \App\Enums\Ordering\Order\OrderStatusEnum::SETTLED,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => now()->subDays(3),
        'dispatched_at'   => now()->subDays(2),
        'created_at'      => now()->subDays(3),
        'updated_at'      => now()->subDays(2),
    ]);

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::WAITING,
        'channel'     => ChatChannelEnum::WEBSITE,
        'shop_id'     => $this->shop->id,
        'web_user_id' => $webUser->id,
        'topic'       => \App\Enums\CRM\Livechat\ChatTopicEnum::MISSING_OR_DAMAGED->value,
    ]);
    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::USER,
        'message_text'    => "Order $reference arrived and two candles are broken",
    ]);

    $claim = \App\Actions\Chat\ChatSession\GetChatClaimCase::run($session, $customer);

    expect($claim['is_claim'])->toBeTrue()
        ->and($claim['order']['reference'])->toBe($reference)
        ->and($claim['order']['named'])->toBeTrue()
        ->and($claim['reason'])->toBe('damaged_in_transit')
        ->and($claim['replacement']['name'])->toBe('grp.models.order.replacement_delivery_note.store');
});

test('a claim with no order number falls back to the last dispatched order, and the agent can pick an earlier one', function () {
    $customer = createOwnCustomer($this->shop, 'claim-pick');
    $webUser  = \App\Actions\CRM\WebUser\StoreWebUser::make()->action($customer, WebUser::factory()->definition());

    $insertOrder = function (string $reference, int $dispatchedDaysAgo) use ($customer) {
        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'group_id'        => $this->shop->group_id,
            'organisation_id' => $this->shop->organisation_id,
            'shop_id'         => $this->shop->id,
            'customer_id'     => $customer->id,
            'currency_id'     => $this->shop->currency_id,
            'tax_category_id' => \App\Models\Helpers\TaxCategory::firstOrFail()->id,
            'slug'            => 'ord-'.uniqid(),
            'reference'       => $reference,
            'state'           => 'dispatched',
            'net_amount'      => 100,
            'org_net_amount'  => 100,
            'grp_net_amount'  => 100,
            'status'          => \App\Enums\Ordering\Order\OrderStatusEnum::SETTLED,
            'payment_data'    => '{}',
            'data'            => '{}',
            'date'            => now()->subDays($dispatchedDaysAgo + 1),
            'dispatched_at'   => now()->subDays($dispatchedDaysAgo),
            'created_at'      => now()->subDays($dispatchedDaysAgo + 1),
            'updated_at'      => now()->subDays($dispatchedDaysAgo),
        ]);
    };

    $earlier = 'CLE'.random_int(100000, 999999);
    $latest  = 'CLL'.random_int(100000, 999999);
    $insertOrder($earlier, 6);
    $insertOrder($latest, 4);

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::WAITING,
        'channel'     => ChatChannelEnum::WEBSITE,
        'shop_id'     => $this->shop->id,
        'web_user_id' => $webUser->id,
        'topic'       => \App\Enums\CRM\Livechat\ChatTopicEnum::MISSING_OR_DAMAGED->value,
    ]);
    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::USER,
        'message_text'    => 'The three large glass bowls are broken',
    ]);

    $fallback = \App\Actions\Chat\ChatSession\GetChatClaimCase::run($session, $customer);
    $picked   = \App\Actions\Chat\ChatSession\GetChatClaimCase::run($session, $customer, $earlier);
    $foreign  = \App\Actions\Chat\ChatSession\GetChatClaimCase::run($session, $customer, 'NOT-THEIRS');

    expect($fallback['order']['reference'])->toBe($latest)
        ->and($fallback['order']['named'])->toBeFalse()
        ->and($fallback['orders'])->toBe([$latest, $earlier])
        ->and($picked['order']['reference'])->toBe($earlier)
        ->and($picked['order']['named'])->toBeTrue()
        ->and($foreign['order']['reference'])->toBe($latest);
});

test('a draft the assistant stored is shown to staff, and what they do with it is counted', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastChatAiDraft::class]);

    $customer  = createOwnCustomer($this->shop, 'ai-draft-orders');
    $webUser   = \App\Actions\CRM\WebUser\StoreWebUser::make()->action($customer, WebUser::factory()->definition());
    $reference = 'AID'.random_int(100000, 999999);

    $session = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::ACTIVE,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'web_user_id'             => $webUser->id,
        'last_visitor_message_at' => now(),
    ]);
    $ask = fn (ChatSession $session, string $text, ChatSenderTypeEnum $as = ChatSenderTypeEnum::USER) => ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => $as,
        'message_text'    => $text,
    ]);
    $assistantDraft = fn (string $text) => \App\Actions\Chat\ChatSession\StoreAssistantChatDraft::make()->handle($session, $this->user, $text, 'test');

    $ask($session, 'Hello, where is my order please? I have not received it yet');
    $draft = $assistantDraft("Hi, your order $reference was dispatched on 22 September.");

    $stranger = ChatSession::create([
        'ulid'    => (string) Str::ulid(),
        'status'  => ChatSessionStatusEnum::WAITING,
        'channel' => ChatChannelEnum::WEBSITE,
        'shop_id' => $this->shop->id,
    ]);
    $spanish = $ask($stranger, 'Hola, ¿dónde está mi pedido?', ChatSenderTypeEnum::GUEST);
    $spanish->update(['original_language_id' => Language::where('code', 'es')->value('id')]);
    expect(\App\Actions\Chat\ChatSession\DraftChatReply::replyLanguage($stranger, $spanish->refresh(), 'Hola, ¿dónde está mi pedido?')->name)->toBe('Spanish');

    actingAs($this->user);
    $facts = $draft->facts;
    $draft->update(['facts' => ['mode' => \App\Actions\Chat\ChatSession\DraftChatReply::SUGGESTION]]);
    $response = $this->getJson(route('grp.api.chats.sessions.ai_draft.show', [$session->ulid]))->assertOk();
    expect($response->json('data'))->toBeNull()->and($response->json())->not->toHaveKey('suggestions');
    $draft->update(['facts' => $facts]);

    $shown = $this->getJson(route('grp.api.chats.sessions.ai_draft.show', [$session->ulid]))->assertOk()->json('data');
    expect($shown['id'])->toBe($draft->id)->and($shown['text'])->toBe($draft->text);

    $this->postJson(route('grp.api.chats.ai_drafts.take', [$draft->id]))->assertOk()->assertJsonPath('data.text', $draft->text);

    $this->postJson(route('grp.api.chats.ai_drafts.rate', [$draft->id]), ['rating' => 2, 'reason' => 'wrong_fact'])->assertOk();
    expect($draft->refresh()->only(['rating', 'rating_reason', 'rated_by_user_id']))->toBe(['rating' => 2, 'rating_reason' => 'wrong_fact', 'rated_by_user_id' => $this->user->id]);
    $this->postJson(route('grp.api.chats.ai_drafts.rate', [$draft->id]), ['rating' => 5, 'reason' => 'wrong_fact'])->assertOk();
    expect($draft->refresh()->only(['rating', 'rating_reason']))->toBe(['rating' => 5, 'rating_reason' => null]);
    $this->postJson(route('grp.api.chats.ai_drafts.rate', [$draft->id]), ['rating' => 6])->assertUnprocessable();

    $agent = ChatAgent::where('user_id', $this->user->id)->firstOrFail();
    $reply = $ask($session, "  Hi, your order $reference was dispatched on 22 September. ", ChatSenderTypeEnum::AGENT);
    $reply->update(['sender_id' => $agent->id]);
    \App\Actions\Chat\ChatSession\SettleChatAiDraft::run($session, $reply);

    expect($draft->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::USED)
        ->and($draft->decided_by_user_id)->toBe($this->user->id);

    // A new question gets a new draft; the agent answers in their own words, so it was not used.
    $session->update(['last_agent_message_at' => now()]);
    $this->travel(1)->minutes();
    $ask($session, "Thanks. And is it coming with DPD or Royal Mail?");
    $second = $assistantDraft("Hi, your order $reference was dispatched on 22 September.");
    $mine   = $ask($session, 'It is with APC, the tracking came by email.', ChatSenderTypeEnum::AGENT);
    $mine->update(['sender_id' => $agent->id]);
    \App\Actions\Chat\ChatSession\SettleChatAiDraft::run($session, $mine);

    expect($second->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::SUPERSEDED);

    // An agent who copies the draft word for word without pressing Use still sent it as written.
    $session->update(['last_agent_message_at' => now()]);
    $this->travel(1)->minutes();
    $ask($session, 'Sorry, where is my order again?');
    $copied = $assistantDraft("Hi, your order $reference was dispatched on 22 September.");
    $pasted = $ask($session, $copied->text, ChatSenderTypeEnum::AGENT);
    $pasted->update(['sender_id' => $agent->id]);
    \App\Actions\Chat\ChatSession\SettleChatAiDraft::run($session, $pasted);

    expect($copied->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::USED);

    expect(\App\Actions\Chat\ChatSession\SettleChatAiDraft::draftWordsKept('Please enter Ancient Wisdom Marketing Ltd as the warehouse name in TikTok Shop.', 'Hi, if you use Ancient Wisdom Marketing Ltd as the warehouse name in TikTok Shop.'))->toBeGreaterThan(0.6)->toBeLessThan(0.9)
        ->and(\App\Actions\Chat\ChatSession\SettleChatAiDraft::draftWordsKept('Your order [[which order?]] ships today.', 'Hello Ann, your order ships today. Carmen'))->toBe(1.0)
        ->and(\App\Actions\Chat\ChatSession\SettleChatAiDraft::draftWordsKept('Your order ships today.', 'It is with APC.'))->toBe(0.0);

    // Retyped with their own greeting and name, without pressing Use: still sent as written.
    $session->update(['last_agent_message_at' => now()]);
    $this->travel(1)->minutes();
    $ask($session, 'Hi again, where is my order?');
    $retyped = $assistantDraft("Hi, your order $reference was dispatched on 22 September.");
    $typed   = $ask($session, 'Hello! '.$retyped->text.' Carmen', ChatSenderTypeEnum::AGENT);
    $typed->update(['sender_id' => $agent->id]);
    \App\Actions\Chat\ChatSession\SettleChatAiDraft::run($session, $typed);

    expect($retyped->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::USED);

    $session->update(['last_agent_message_at' => now()]);
    $this->travel(1)->minutes();
    $ask($session, 'Where is my order, please?');
    $replaced = $assistantDraft("Hi, your order $reference was dispatched on 22 September.");
    $this->travel(1)->minutes();
    $ask($session, 'Hello? Where is my order?');
    $newer = $assistantDraft('Sorry for the wait, we are checking with the courier now.');
    $sentReplaced = $ask($session, $replaced->text, ChatSenderTypeEnum::AGENT);
    $sentReplaced->update(['sender_id' => $agent->id]);
    \App\Actions\Chat\ChatSession\SettleChatAiDraft::run($session, $sentReplaced);

    expect($replaced->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::USED)
        ->and($replaced->reply_message_id)->toBe($sentReplaced->id)
        ->and($newer->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::SUPERSEDED);

    $stats = get(route('grp.chat.ai.dashboard'))->assertOk()->viewData('page')['props']['draftStats'];
    expect($stats['used'])->toBeGreaterThanOrEqual(1)->and($stats['superseded'])->toBeGreaterThanOrEqual(1);

    foreach ([$session, $stranger] as $each) {
        \App\Models\Chat\ChatAiDraft::where('chat_session_id', $each->id)->delete();
        $each->messages()->withTrashed()->forceDelete();
        $each->forceDelete();
    }
});

test('what staff changed in a draft is measured apart from its status: gaps they filled never count as sent as it was', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastChatAiDraft::class]);
    $change  = fn (string $draft, string $reply, string $status = 'used') => \App\Actions\Chat\ChatSession\MeasureChatAiDraft::changeClass($draft, $reply, \App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::from($status))->value;
    $answer  = 'Your order ships today with Royal Mail and the tracking number will be emailed to you this evening.';

    expect($change($answer, "  $answer "))->toBe('as_is')
        ->and($change($answer, "Hello Ann! $answer Kind regards, Carmen"))->toBe('cosmetic')
        ->and($change('Your order ships [[which day?]] with Royal Mail.', 'Your order ships on Friday with Royal Mail.'))->toBe('gaps_filled')
        ->and($change($answer, 'Your order ships today with Royal Mail and tracking follows by email tonight.', 'edited'))->toBe('big')
        ->and($change($answer, 'It is with APC.', 'edited'))->toBe('rewritten')
        ->and($change($answer, 'It is with APC.', 'superseded'))->toBe('own_reply');

    $session = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::ACTIVE,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'last_visitor_message_at' => now(),
    ]);
    $customerWrote = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => 'When does my order ship?',
    ]);
    $this->travel(10)->minutes();
    $draft = \App\Actions\Chat\ChatSession\StoreAssistantChatDraft::make()->handle($session, $this->user, 'Your order ships [[which day?]] with Royal Mail.', 'test');
    $customerWrote->update(['is_read' => true, 'read_at' => now()]);
    $this->travel(3)->minutes();

    $agent = ChatAgent::firstOrCreate(['user_id' => $this->user->id], ['max_concurrent_chats' => 5, 'is_online' => false, 'current_chat_count' => 0]);
    $reply = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::AGENT,
        'sender_id'       => $agent->id,
        'message_text'    => 'Your order ships on Friday with Royal Mail.',
    ]);
    \App\Actions\Chat\ChatSession\SettleChatAiDraft::run($session, $reply);

    expect($draft->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::USED)
        ->and($draft->change_class)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftChangeEnum::GAPS_FILLED)
        ->and((float) $draft->words_kept)->toBe(1.0)
        ->and($draft->lag_seconds)->toBe(600)
        ->and($draft->open_to_reply_seconds)->toBe(180);

    $draft->update(['change_class' => null, 'words_kept' => null, 'lag_seconds' => null, 'open_to_reply_seconds' => null]);
    $this->artisan('chat:measure-drafts', ['--days' => 1])->assertSuccessful();
    expect($draft->refresh()->change_class)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftChangeEnum::GAPS_FILLED)
        ->and($draft->lag_seconds)->toBe(600);

    \App\Models\Chat\ChatAiDraft::where('chat_session_id', $session->id)->delete();
    $session->messages()->withTrashed()->forceDelete();
    $session->forceDelete();
});

test('Jev gives a second opinion on each assistant draft and reads how the customer took the reply sent from it', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastChatAiDraft::class]);
    $jev = \App\Actions\Helpers\AI\AskJev::mock();
    $jev->shouldReceive('handle')->andReturn([
        'intent'      => ['choice' => 'connect_store', 'probabilities' => ['connect_store' => 0.97]],
        'risk'        => ['choice' => 'information', 'probabilities' => ['information' => 0.91]],
        'answers_all' => ['noul' => 0.84],
    ]);
    $jev->shouldReceive('choice')->andReturn(['choice' => 'confused', 'probabilities' => ['confused' => 0.88]]);

    $session = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::ACTIVE,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'last_visitor_message_at' => now(),
    ]);
    $write = fn (string $text, ChatSenderTypeEnum $as, ?int $senderId = null) => ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => $as,
        'sender_id'       => $senderId,
        'message_text'    => $text,
    ]);
    $write('How do I link my Shopify store to you?', ChatSenderTypeEnum::GUEST);
    $draft = \App\Actions\Chat\ChatSession\StoreAssistantChatDraft::make()->handle($session, $this->user, 'Go to Channels, choose Shopify and follow the steps.', 'test');

    $this->artisan('chat:judge-drafts', ['--days' => 1])->assertSuccessful();
    expect($draft->refresh()->jev_check)->toMatchArray(['intent' => 'connect_store', 'intent_p' => 0.97, 'risk' => 'information', 'answers_all_p' => 0.84])
        ->and($draft->customer_reaction)->toBeNull();

    $agent = ChatAgent::firstOrCreate(['user_id' => $this->user->id], ['max_concurrent_chats' => 5, 'is_online' => false, 'current_chat_count' => 0]);
    \App\Actions\Chat\ChatSession\SettleChatAiDraft::run($session, $write($draft->text, ChatSenderTypeEnum::AGENT, $agent->id));
    expect(\App\Actions\Chat\ChatSession\JudgeChatAiDraft::make()->reaction($draft->refresh()))->toBeNull();

    $write('I cannot find Channels anywhere, where is it?', ChatSenderTypeEnum::GUEST);
    $this->artisan('chat:judge-drafts', ['--days' => 1])->assertSuccessful();
    expect($draft->refresh()->customer_reaction)->toBe(\App\Enums\CRM\Livechat\ChatAiReactionEnum::CONFUSED)
        ->and($draft->customer_reaction->isHarm())->toBeTrue()
        ->and($draft->change_class)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftChangeEnum::AS_IS);

    // Staff wrote their own reply: how the customer took it says nothing about the draft.
    $session->update(['last_agent_message_at' => now()]);
    $this->travel(1)->minutes();
    $ownReplied = \App\Actions\Chat\ChatSession\StoreAssistantChatDraft::make()->handle($session, $this->user, 'Channels is in the left menu, under Dropshipping.', 'test');
    \App\Actions\Chat\ChatSession\SettleChatAiDraft::run($session, $write('Let me call you to walk you through it.', ChatSenderTypeEnum::AGENT, $agent->id));
    $write('That did not help at all.', ChatSenderTypeEnum::GUEST);
    $this->artisan('chat:judge-drafts', ['--days' => 1])->assertSuccessful();
    expect($ownReplied->refresh()->status)->toBe(\App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::SUPERSEDED)
        ->and($ownReplied->customer_reaction)->toBeNull();

    \App\Models\Chat\ChatAiDraft::where('chat_session_id', $session->id)->delete();
    $session->messages()->withTrashed()->forceDelete();
    $session->forceDelete();
});

test('the confidence map counts each kind of chat on its own and only trusts it on its lower bound', function () {
    $cell = function () {
        return collect(\App\Actions\Chat\Reports\GetChatAiConfidenceMap::make()->cells($this->shop->group, 30)->get())
            ->first(fn ($row) => (int) $row->shop_id === $this->shop->id && $row->intent === 'invoice_copy' && $row->risk === 'account_facts');
    };
    $before = $cell();
    $draft  = fn (string $changeClass, array $more = []) => \App\Models\Chat\ChatAiDraft::create([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'topic'           => \App\Enums\CRM\Livechat\ChatTopicEnum::PAYMENT_INVOICE,
        'facts'           => ['mode' => \App\Actions\Chat\ChatSession\DraftChatReply::ASSISTANT],
        'text'            => 'Here is your invoice.',
        'status'          => \App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::USED,
        'intent'          => 'invoice_copy',
        'risk'            => 'account_facts',
        'change_class'    => $changeClass,
    ] + $more);

    $made = collect([
        $draft('as_is', ['would_send' => true]),
        $draft('cosmetic', ['would_send' => true]),
        $draft('as_is', ['customer_reaction' => 'confused']),
        $draft('gaps_filled', ['would_send' => true]),
        $draft('rewritten', ['status' => \App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::EDITED]),
    ]);
    $draft('as_is', ['facts' => ['mode' => \App\Actions\Chat\ChatSession\DraftChatReply::SUGGESTION]])->delete();

    $after = $cell();
    $delta = fn (string $column) => (int) $after->$column - (int) ($before?->$column ?? 0);
    expect($delta('decided'))->toBe(5)
        ->and($delta('alone'))->toBe(3)
        ->and($delta('alone_clean'))->toBe(2)
        ->and($delta('gaps_filled'))->toBe(1)
        ->and($delta('harms'))->toBe(1)
        ->and($delta('would_send'))->toBe(3)
        ->and($delta('would_send_right'))->toBe(2)
        ->and((float) $after->lower_bound)->toBe(round(\App\Actions\Chat\Reports\GetChatAiConfidenceMap::wilson((int) $after->alone_clean, (int) $after->decided), 3))
        ->and(\App\Actions\Chat\Reports\GetChatAiConfidenceMap::wilson(10, 10))->toBeGreaterThan(0.72)->toBeLessThan(0.73)
        ->and(\App\Actions\Chat\Reports\GetChatAiConfidenceMap::wilson(0, 0))->toBe(0.0);

    actingAs($this->user);
    get(route('grp.chat.ai.confidence', ['days' => 30]))->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Chat/ChatAiConfidenceMap')->has('data.data'));
    get(route('grp.chat.ai.confidence', ['days' => 12]))->assertUnprocessable();
    expect(get(route('grp.chat.ai.dashboard'))->assertOk()->viewData('page')['props']['confidence']['decided'])->toBeGreaterThanOrEqual(5);

    \App\Models\Chat\ChatAiDraft::whereIn('id', $made->pluck('id'))->delete();
});

test('an email out of hours gets the closed-now reply at once and never an AI answer, even on a topic that earned one', function () {
    config([
        'chat.out_of_hours_reply'        => true,
        'askbot-laravel.openai_api_key'  => 'test-key',
    ]);
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);
    $schedule = outOfHoursTestSchedule($this->shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-26 11:00', 'Europe/London'));

    $customer  = createOwnCustomer($this->shop, 'ai-email-once');
    $webUser   = \App\Actions\CRM\WebUser\StoreWebUser::make()->action($customer, WebUser::factory()->definition());
    $reference = 'AEO'.random_int(100000, 999999);

    \Illuminate\Support\Facades\DB::table('orders')->insert([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $this->shop->currency_id,
        'tax_category_id' => \App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'slug'            => 'ord-'.uniqid(),
        'reference'       => $reference,
        'state'           => 'packed',
        'net_amount'      => 100,
        'org_net_amount'  => 100,
        'grp_net_amount'  => 100,
        'status'          => \App\Enums\Ordering\Order\OrderStatusEnum::CREATING,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => '2026-09-24',
        'submitted_at'    => '2026-09-24 09:00:00',
        'created_at'      => '2026-09-24',
        'updated_at'      => '2026-09-24',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        '*'                       => \Illuminate\Support\Facades\Http::response(['id' => 'sent-1', 'threadId' => 'th-once']),
    ]);

    $emailFrom = function () use ($webUser) {
        $session = noiseTestEmailSession($this->shop->fresh(), 'ai.once.'.Str::lower(Str::random(8)).'@example.com', 'My order', 'Where is my order please?', ['gmail_message_id' => 'in-1']);
        $session->update(['web_user_id' => $webUser->id]);

        return $session;
    };
    $trigger        = fn (ChatSession $session) => $session->messages()->where('sender_type', ChatSenderTypeEnum::GUEST)->latest('id')->first();
    $automaticMails = fn (ChatSession $session) => $session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->whereNotNull('metadata->automated')->count();

    $answered = $emailFrom();
    foreach (range(1, 3) as $i) {
        \App\Models\Chat\ChatAiDraft::create([
            'group_id' => $this->shop->group_id, 'organisation_id' => $this->shop->organisation_id, 'shop_id' => $this->shop->id,
            'chat_session_id' => $answered->id, 'topic' => 'order_status', 'facts' => [], 'text' => 'earned '.$i,
            'status' => \App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::USED,
        ]);
    }

    expect(\App\Actions\Chat\ChatSession\SendOutOfHoursReply::makeJob($answered, $trigger($answered))->delay)->toBeNull()
        ->and(\App\Actions\Chat\ChatSession\SendOutOfHoursReply::make()->handle($answered, $trigger($answered)))->toBeTrue()
        ->and($automaticMails($answered))->toBe(1);

    \Illuminate\Support\Facades\Cache::forget('chat-out-of-hours-email:'.sha1($answered->metadata['email_from']));
    \App\Models\Chat\ChatAiDraft::where('chat_session_id', $answered->id)->delete();
    outOfHoursTestCleanUp($schedule, [$answered]);
});

test('what agents keep telling different customers is learned and put to staff once enough customers heard it, and only a person turns it on', function () {
    $shop = $this->shop;
    \App\Actions\Helpers\AI\EmbedTexts::mock()->shouldReceive('handle')->andReturnUsing(fn (array $texts) => array_map(fn (string $text) => array_pad(str_contains($text, 'VAT') ? [1.0, 0.0] : [0.0, 1.0], 1024, 0.0), $texts));
    \App\Models\Chat\ChatKnowledgeEntry::where('shop_id', $shop->id)->delete();
    $customers = [];
    $thread    = function (string $question, string $reply, string $key, ?string $customerKey = null) use ($shop, &$customers) {
        $customerKey ??= $key;
        $customers[$customerKey] ??= StoreCustomer::make()->action($shop, Customer::factory()->definition());
        $base = ['group_id' => $shop->group_id, 'organisation_id' => $shop->organisation_id, 'shop_id' => $shop->id, 'customer_id' => $customers[$customerKey]->id, 'gmail_thread_id' => 'th-learn-'.$key, 'subject' => 'Question', 'counterpart_address' => "learn.$key@example.com"];
        \App\Models\Comms\EmailArchiveMessage::create($base + ['gmail_message_id' => "q-$key", 'is_outbound' => false, 'text' => $question, 'sent_at' => now()->subDays(2)]);
        \App\Models\Comms\EmailArchiveMessage::create($base + ['gmail_message_id' => "r-$key", 'is_outbound' => true, 'text' => $reply, 'sent_at' => now()->subDay()]);
    };
    foreach (['a', 'b'] as $key) {
        $thread('Is VAT charged on shipping?', 'Hi, yes VAT applies to both the products and the shipping cost, standard UK rate of 20%.', $key);
    }

    $rule = ['general' => true, 'title' => 'VAT on shipping', 'note' => 'VAT is charged on products and on shipping at the standard UK rate of 20%.', 'temporary' => false];
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->andReturnUsing(function () use (&$rule) {
        return json_encode($rule);
    });
    $contradicts = 0.1;
    $jev = \App\Actions\Helpers\AI\AskJev::mock();
    $jev->shouldReceive('handle')->andReturnUsing(function (array $state, array $questions) {
        $options = array_keys($questions['same']['criteria'] ?? $questions['entry']['criteria'] ?? []);
        $first   = $options[0] ?? 'new';

        $note = (string) ($state['new_note'] ?? '');
        $same = collect($questions['same']['criteria'] ?? [])
            ->filter(fn (string $label, string $key) => $key !== 'new' && collect(['VAT', 'dispatched'])->contains(fn (string $word) => str_contains($label, $word) && str_contains($note, $word)))
            ->keys()
            ->first() ?? 'new';

        return isset($questions['same'])
            ? ['same' => ['choice' => $same, 'probabilities' => [$same => 0.9]]]
            : ['entry' => ['choice' => $first, 'probabilities' => [$first => 0.9]]];
    });
    $jev->shouldReceive('noul')->andReturnUsing(function () use (&$contradicts) {
        return $contradicts;
    });

    $twice = \App\Actions\Chat\ChatSession\LearnChatKnowledge::run($shop);
    $vat   = \App\Models\Chat\ChatKnowledgeEntry::where('shop_id', $shop->id)->where('source_type', 'learned')->sole();
    expect($twice)->toMatchArray(['replies' => 2, 'rules' => 2, 'promoted' => 0])
        ->and($vat->only(['status', 'customers_count']))->toBe(['status' => 'candidate', 'customers_count' => 2])
        ->and($vat->embedding)->toHaveCount(1024)
        ->and(\App\Models\Chat\ChatKnowledgeEntry::forShop($shop)->pluck('id'))->not->toContain($vat->id)
        ->and(\App\Actions\Chat\ChatSession\LearnChatKnowledge::run($shop)['replies'])->toBe(0);

    $thread('Is VAT added to delivery?', 'Yes, VAT at 20% is added to the delivery charge too, like on the products.', 'a-again', 'a');
    $thread('A question without a customer', 'VAT at the standard rate of 20% applies to delivery as well as to goods.', 'stranger');
    \App\Models\Comms\EmailArchiveMessage::where('gmail_thread_id', 'th-learn-stranger')->update(['customer_id' => null]);
    expect(\App\Actions\Chat\ChatSession\LearnChatKnowledge::run($shop))->toMatchArray(['replies' => 1, 'promoted' => 0])
        ->and($vat->refresh()->only(['status', 'customers_count']))->toBe(['status' => 'candidate', 'customers_count' => 2]);

    $thread('Do you charge VAT on delivery?', 'Yes, 20% VAT is added to the delivery charge as well.', 'c');
    expect(\App\Actions\Chat\ChatSession\LearnChatKnowledge::run($shop)['promoted'])->toBe(1)
        ->and($vat->refresh()->only(['status', 'customers_count']))->toBe(['status' => 'proposed', 'customers_count' => 3])
        ->and(\App\Models\Chat\ChatKnowledgeEntry::forShop($shop)->pluck('id'))->not->toContain($vat->id);

    $vat->update(['status' => 'active']);
    expect(\App\Models\Chat\ChatKnowledgeEntry::forShop($shop)->pluck('id'))->toContain($vat->id);

    $vat->update(['status' => 'removed']);
    $contradicts = 0.8;
    $rule        = ['general' => true, 'title' => 'Dispatch', 'note' => 'Orders are dispatched the same day.', 'temporary' => false];
    \App\Models\Chat\ChatKnowledgeEntry::create(['group_id' => $shop->group_id, 'organisation_id' => $shop->organisation_id, 'shop_id' => $shop->id, 'kind' => 'note', 'title' => 'Dispatch times', 'body' => 'Orders are dispatched within 2 working days.', 'source_type' => 'manual', 'is_manual' => true]);
    foreach (['d', 'e', 'f'] as $key) {
        $thread('When do you dispatch?', 'We dispatch every order the same day it is placed.', $key);
    }
    \App\Actions\Chat\ChatSession\LearnChatKnowledge::run($shop);
    $dispatch = \App\Models\Chat\ChatKnowledgeEntry::where('shop_id', $shop->id)->where('source_type', 'learned')->where('title', 'Dispatch')->first();

    expect($dispatch?->only(['status', 'customers_count', 'conflict']))->toBe(['status' => 'conflict', 'customers_count' => 3, 'conflict' => 'Dispatch times'])
        ->and(\App\Models\Chat\ChatKnowledgeEntry::forShop($shop)->where('source_type', 'learned')->count())->toBe(0);

    $rule = null;
    $thread('Do you ship to Spain?', 'Yes, we deliver to Spain within five working days of dispatch.', 'no-answer');
    expect(\App\Actions\Chat\ChatSession\LearnChatKnowledge::run($shop)['rules'])->toBe(0)
        ->and(\App\Models\Comms\EmailArchiveMessage::where('gmail_message_id', 'r-no-answer')->value('learned_at'))->toBeNull();

    $rule = ['general' => true, 'title' => 'Missing items', 'note' => 'A missing item on a shipped order is credited to the account.', 'temporary' => false];
    $thread('One candle is missing', 'Sorry about that, we have credited the missing candle to your account.', 'years-ago');
    \App\Models\Comms\EmailArchiveMessage::where('gmail_message_id', 'q-years-ago')->update(['sent_at' => now()->subYears(5)->subDay()]);
    \App\Models\Comms\EmailArchiveMessage::where('gmail_message_id', 'r-years-ago')->update(['sent_at' => now()->subYears(5)]);
    expect(\App\Actions\Chat\ChatSession\LearnChatKnowledge::run($shop)['replies'])->toBe(1)
        ->and(\App\Actions\Chat\ChatSession\LearnChatKnowledge::run($shop, 0)['replies'])->toBe(1)
        ->and(\App\Models\Comms\EmailArchiveMessage::where('gmail_message_id', 'r-years-ago')->value('learned_at'))->not->toBeNull();

    \App\Models\Chat\ChatKnowledgeEntry::where('shop_id', $shop->id)->delete();
    \App\Models\Comms\EmailArchiveMessage::where('gmail_thread_id', 'like', 'th-learn-%')->delete();
});

test('the knowledge base splits pages into sections and keeps the notes staff write', function () {
    $sections = \App\Actions\Chat\ChatSession\HydrateChatKnowledge::sections(['blocks' => ['<h2>Returns</h2><p>You may return goods within 30 days of delivery, unused and in their original packaging.</p><h3>Refunds</h3><p>Refunds are made to your account balance once the goods are back with us.</p>']], 'Returns page');
    expect(array_column($sections, 'title'))->toBe(['Returns page · Returns', 'Returns page · Refunds'])
        ->and($sections[0]['body'])->toBe('You may return goods within 30 days of delivery, unused and in their original packaging.');

    $note = \App\Models\Chat\ChatKnowledgeEntry::create([
        'group_id' => $this->shop->group_id, 'organisation_id' => $this->shop->organisation_id, 'shop_id' => $this->shop->id,
        'kind' => 'note', 'title' => 'Shipping to Germany', 'source_type' => 'manual', 'is_manual' => true,
        'body' => 'We cannot ship from the UK to Germany because we do not have a LUCID registration.',
    ]);
    expect(\App\Models\Chat\ChatKnowledgeEntry::forShop($this->shop)->pluck('id'))->toContain($note->id);

    $saved = \App\Actions\Chat\UpdateShopChatKnowledgeNote::make()->handle($this->shop, null, ['title' => 'Testers', 'body' => 'Diffuser testers are not available until the website variants are fixed.'], $this->user);
    \App\Actions\Chat\UpdateShopChatKnowledgeNote::make()->handle($this->shop, $saved, ['title' => 'Diffuser testers', 'body' => $saved->body]);
    \App\Actions\Chat\ChatSession\HydrateChatKnowledge::run($this->shop);

    expect($saved->refresh()->only(['title', 'is_manual', 'kind', 'created_by_user_id']))->toBe(['title' => 'Diffuser testers', 'is_manual' => true, 'kind' => 'note', 'created_by_user_id' => $this->user->id])
        ->and(\App\Models\Chat\ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->count())->toBe(2)
        ->and(\App\Models\Chat\ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', false)->whereNotNull('hydrated_at')->count())
        ->toBe(\App\Models\Chat\ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', false)->count());

    $note->delete();
    $saved->delete();
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

test('a task asked of a colleague from a chat labels it in the inbox and holds it open until done', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);
    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Please can I request the 51st amendment forms for rose geranium',
        'is_read'         => true,
    ]);

    $task = \App\Actions\Chat\ChatSession\StoreStaffTaskFromChatSession::make()->handle($session, $this->user, [
        'subject'     => 'Awaiting amendment forms from Aromatics',
        'assignee_id' => $this->user->id,
    ]);

    expect($task->model_type)->toBe('ChatSession')
        ->and($task->model_id)->toBe($session->id)
        ->and($task->description)->toContain($session->ulid);

    $listed = collect(GetChatSessions::make()->handle(['ulid' => $session->ulid])->items())->firstWhere('id', $session->id);
    expect(\App\Http\Resources\CRM\Livechat\ChatSessionListResource::make($listed)->resolve()['open_tasks'])
        ->toHaveCount(1)
        ->sequence(fn ($openTask) => $openTask->reference->toBe($task->reference)->subject->toBe('Awaiting amendment forms from Aromatics'));

    $agent = ChatAgent::create([
        'user_id'              => User::factory()->create(['group_id' => $this->organisation->group_id])->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    expect(fn () => CloseChatSession::make()->handle($session, $agent->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class, $task->reference);

    $task->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE]);

    expect(CloseChatSession::make()->handle($session->fresh(), $agent->id)->status)->toBe(ChatSessionStatusEnum::CLOSED);
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

test('the jobs a customer message starts share one reading of it and never write back each other metadata stale', function () {
    config()->set('services.openrouter.api_key', 'or-key');
    $jevCalls = 0;
    \App\Actions\Helpers\AI\AskJev::mock()->shouldReceive('handle')->andReturnUsing(function () use (&$jevCalls) {
        $jevCalls++;

        return ['wants_something' => ['type' => 'noul', 'noul' => 0.1], 'act' => ['type' => 'choice', 'choice' => 'informing', 'probabilities' => ['informing' => 0.5]]];
    });

    $session = ChatSession::create([
        'ulid'     => (string) Str::ulid(),
        'status'   => ChatSessionStatusEnum::WAITING,
        'channel'  => ChatChannelEnum::WEBSITE,
        'shop_id'  => $this->shop->id,
        'metadata' => ['waiting_for_customer' => ['message_id' => 1]],
    ]);
    $message = $session->messages()->create(['message_text' => 'I will send the photos tomorrow', 'message_type' => ChatMessageTypeEnum::TEXT, 'sender_type' => ChatSenderTypeEnum::GUEST]);

    $urgentJob = ChatSession::find($session->id);
    $draftJob  = ChatSession::find($session->id);
    \App\Actions\Chat\ChatSession\SetChatSessionMetadata::run($urgentJob, [\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::KEY => 'cancel_order', 'waiting_for_customer' => null]);
    \App\Actions\Chat\ChatSession\ClassifyChatTurn::run($draftJob, 'I will send the photos tomorrow', '(nothing yet)', $message->id);
    \App\Actions\Chat\ChatSession\ClassifyChatTurn::run($draftJob, 'I will send the photos tomorrow', '(nothing yet)', $message->id);

    $metadata = $session->refresh()->metadata;
    $reading  = \App\Models\Chat\ChatTurnReading::where('chat_session_id', $session->id)->sole();
    expect($metadata[\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::KEY])->toBe('cancel_order')
        ->and($metadata)->not->toHaveKey('waiting_for_customer')
        ->and($metadata[\App\Actions\Chat\ChatSession\ClassifyChatTurn::KEY]['reading_id'])->toBe($reading->id)
        ->and($draftJob->metadata[\App\Actions\Chat\ChatSession\ClassifyChatTurn::KEY]['reading_id'])->toBe($reading->id);

    $jevCalls = 0;
    \Illuminate\Support\Facades\Cache::flush();
    \App\Actions\Chat\ChatSession\ClassifyChatTurn::forSession($session);
    \App\Actions\Chat\ChatSession\ClassifyChatTurn::forSession(ChatSession::find($session->id));
    expect($jevCalls)->toBe(1)
        ->and(\App\Models\Chat\ChatTurnReading::where('chat_session_id', $session->id)->count())->toBe(1);
});

test('a thanks is read as the end of the chat, and an out of office is not read', function () {
    config()->set('services.openrouter.api_key', 'or-key');
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->never();
    $jevCalls = 0;
    \App\Actions\Helpers\AI\AskJev::mock()->shouldReceive('handle')->andReturnUsing(function () use (&$jevCalls) {
        $jevCalls++;

        return ['wants_something' => ['type' => 'noul', 'noul' => 0.05], 'act' => ['type' => 'choice', 'choice' => 'closing', 'probabilities' => ['closing' => 0.8]]];
    });

    $session = ChatSession::create(['ulid' => (string) Str::ulid(), 'status' => ChatSessionStatusEnum::WAITING, 'channel' => ChatChannelEnum::EMAIL, 'shop_id' => $this->shop->id]);
    $session->messages()->create(['message_text' => 'Thank you so much, all sorted', 'message_type' => ChatMessageTypeEnum::TEXT, 'sender_type' => ChatSenderTypeEnum::USER, 'created_at' => now()->subMinutes(2)]);

    $turn = \App\Actions\Chat\ChatSession\ClassifyChatTurn::forSession($session);
    expect($turn['closing'])->toBe(0.8);

    $jevCalls = 0;
    $session->messages()->create(['message_text' => 'I am out of the office until Monday', 'message_type' => ChatMessageTypeEnum::TEXT, 'sender_type' => ChatSenderTypeEnum::USER, 'metadata' => ['auto_reply' => true]]);
    expect(\App\Actions\Chat\ChatSession\ClassifyChatTurn::forSession($session->refresh()))->toBeNull()
        ->and($jevCalls)->toBe(0);
});

test('jev reads the customer in one round, and asks what went wrong only when they report a problem with an order', function () {
    config()->set('services.openrouter.api_key', 'or-key');

    $noul   = fn (float $p) => ['type' => 'noul', 'noul' => $p];
    $choice = fn (string $picked, float $p) => ['type' => 'choice', 'choice' => $picked, 'probabilities' => [$picked => $p]];
    $asking = ['wants_something' => $noul(0.97), 'about_existing_order' => $noul(0.1), 'problem' => $noul(0.05), 'act' => $choice('asking', 0.9)];

    \Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/alpha/decisions' => \Illuminate\Support\Facades\Http::sequence()
            ->push(['answers' => $asking])
            ->push(['answers' => array_merge($asking, ['problem' => $noul(0.9), 'about_existing_order' => $noul(0.9)])])
            ->push(['answers' => ['problem_kind' => $choice('damaged', 0.9)]])
            ->push([], 500),
    ]);

    $session = ChatSession::create([
        'ulid'    => (string) Str::ulid(),
        'status'  => ChatSessionStatusEnum::WAITING,
        'channel' => ChatChannelEnum::WEBSITE,
        'shop_id' => $this->shop->id,
    ]);
    $classify = fn (string $text) => \App\Actions\Chat\ChatSession\ClassifyChatTurn::run($session, $text, '(nothing yet)');

    $readingsBefore = \App\Models\Chat\ChatTurnReading::where('chat_session_id', $session->id)->count();
    $question       = $classify('When is NSBag-09 back in stock?');
    expect(\App\Models\Chat\ChatTurnReading::where('chat_session_id', $session->id)->count())->toBe($readingsBefore + 1)
        ->and(\App\Models\Chat\ChatTurnReading::where('chat_session_id', $session->id)->latest('id')->first()->customer_wrote)->toBe('When is NSBag-09 back in stock?')
        ->and($question['branch'])->toBeNull()
        ->and($question['claim'])->toBeFalse();

    $damaged = $classify('Two candles arrived broken');
    expect($damaged['branch'])->toBe('problem')
        ->and($damaged['claim'])->toBeTrue()
        ->and($session->refresh()->metadata['ai_turn']['answers']['problem_kind']['choice'])->toBe('damaged');

    expect($classify('Anything'))->toBeNull();

    expect(\App\Actions\Chat\ChatSession\ClassifyChatTurn::urgent(['request' => ['probabilities' => ['cancel_all' => 0.45, 'change_address' => 0.1]]]))->toBe('cancel_order')
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatTurn::urgent(['request' => ['probabilities' => ['remove_items' => 0.25, 'hold' => 0.2, 'none' => 0.55]]]))->toBe('cancel_order')
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatTurn::urgent(['request' => ['probabilities' => ['cancel_all' => 0.02, 'change_address' => 0.92]]]))->toBe('change_address')
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatTurn::urgent(['request' => ['probabilities' => ['cancel_all' => 0.04, 'accept_ours' => 0.94]]]))->toBeNull()
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatTurn::urgent(['request' => ['probabilities' => ['cancel_all' => 0.2, 'none' => 0.8]]]))->toBeNull();

    $onlyReports = ['wants_something' => $noul(0.48), 'problem' => $noul(0.98), 'about_existing_order' => $noul(0.96), 'act' => $choice('asking', 0.8)];
    expect(\App\Actions\Chat\ChatSession\ClassifyChatTurn::branch($onlyReports))->toBe('problem')
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatTurn::branch(['act' => $choice('closing', 0.9)] + $onlyReports))->toBeNull();

    $asked = \Illuminate\Support\Facades\Http::recorded()->map(fn (array $pair) => array_keys($pair[0]->data()['questions']))->values();
    expect($asked)->toHaveCount(4)
        ->and($asked[0])->toContain('wants_something', 'request', 'act')
        ->and($asked[0])->not->toContain('subject')
        ->and($asked[2])->toBe(['problem_kind']);

    $session->forceDelete();
});

test('suggestions staff answered after are scored against the real reply once, per shop with shadow apart', function () {
    $session  = ChatSession::create(['ulid' => (string) Str::ulid(), 'status' => ChatSessionStatusEnum::ACTIVE, 'channel' => ChatChannelEnum::WEBSITE, 'shop_id' => $this->shop->id]);
    $customer = ChatMessage::create(['chat_session_id' => $session->id, 'message_type' => ChatMessageTypeEnum::TEXT, 'sender_type' => ChatSenderTypeEnum::GUEST, 'message_text' => 'Do you ship to Spain?']);
    $staff    = ChatMessage::create(['chat_session_id' => $session->id, 'message_type' => ChatMessageTypeEnum::TEXT, 'sender_type' => ChatSenderTypeEnum::AGENT, 'message_text' => 'Yes, every day.']);
    $day      = now('UTC')->subYears(3)->startOfDay();
    $draft    = \App\Models\Chat\ChatAiDraft::create([
        'group_id'         => $this->shop->group_id, 'organisation_id' => $this->shop->organisation_id, 'shop_id' => $this->shop->id,
        'chat_session_id'  => $session->id, 'trigger_message_id' => $customer->id, 'topic' => \App\Enums\CRM\Livechat\ChatTopicEnum::OTHER,
        'facts'            => ['mode' => \App\Actions\Chat\ChatSession\DraftChatReply::SUGGESTION, 'shadow' => true], 'text' => 'Yes, we ship to Spain daily.',
        'status'           => \App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::SUPERSEDED, 'reply_message_id' => $staff->id, 'decided_at' => $day->copy()->addHours(10),
    ]);

    \App\Actions\Chat\ChatSession\JudgeChatSuggestion::mock()->shouldReceive('nearMatch')->once()
        ->with('Do you ship to Spain?', 'Yes, every day.', 'Yes, we ship to Spain daily.')
        ->andReturn(['score' => 3, 'label' => 'nearly the same']);

    expect(\App\Actions\Chat\ChatSession\ScoreChatSuggestions::run($day))->toBe([$this->shop->slug.' (shadow)' => ['scored' => 1, 'close' => 1]])
        ->and(data_get($draft->refresh()->facts, 'near_match.score'))->toBe(3)
        ->and(\App\Actions\Chat\ChatSession\ScoreChatSuggestions::run($day))->toBe([]);

    $session->forceDelete();
});

test('jev answers yes/no, choice and score questions through openrouter, and nothing without a key', function () {
    config()->set('services.openrouter.api_key', 'or-key');

    \Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/alpha/decisions' => \Illuminate\Support\Facades\Http::sequence()
            ->push(['answers' => ['answer' => ['type' => 'noul', 'noul' => 0.91]]])
            ->push(['answers' => ['answer' => ['type' => 'choice', 'choice' => 'billing', 'probabilities' => ['billing' => 0.8, 'sales' => 0.2]]]])
            ->push(['answers' => ['answer' => ['type' => 'score', 'score' => 2, 'probabilities' => [0.1, 0.2, 0.7]]]])
            ->push([], 500),
    ]);

    $jev = \App\Actions\Helpers\AI\AskJev::make();

    expect($jev->noul('My payouts failed', 'Is it urgent?', 'Time-sensitive', 'No urgency'))->toBe(0.91)
        ->and($jev->choice('Refund please', 'Which team?', ['billing' => 'Payments', 'sales' => 'New accounts']))
        ->toBe(['choice' => 'billing', 'probabilities' => ['billing' => 0.8, 'sales' => 0.2]])
        ->and($jev->score('This is outrageous!', 'How frustrated?', ['Calm', 'Frustrated', 'Very angry']))
        ->toBe(['score' => 2, 'probabilities' => [0.1, 0.2, 0.7]])
        ->and($jev->noul('x', 'y', 'a', 'b'))->toBeNull();

    $sent = \Illuminate\Support\Facades\Http::recorded()->map(fn ($pair) => $pair[0]->data())->values();

    expect($sent[0]['model'])->toBe('~typesafe/jev-latest')
        ->and($sent[0]['questions']['answer'])->toBe(['type' => 'noul', 'instructions' => 'Is it urgent?', 'criteria' => ['true' => 'Time-sensitive', 'false' => 'No urgency']])
        ->and($sent[2]['questions']['answer']['criteria'])->toBe(['Calm', 'Frustrated', 'Very angry']);

    config()->set('services.openrouter.api_key', null);

    expect($jev->noul('x', 'y', 'a', 'b'))->toBeNull();
});

test('ai calls go through openrouter with the provider prefix, and straight to openai without its key', function () {
    config()->set('services.openrouter.api_key', 'or-key');
    config()->set('askbot-laravel.openai_api_key', 'openai-key');

    \Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/v1/chat/completions' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => 'via openrouter']]]]),
        'api.openai.com/v1/chat/completions'    => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => 'via openai']]]]),
    ]);

    expect(\App\Actions\Helpers\AI\AskToAi::run('hi', 'gpt-4o-mini'))->toBe('via openrouter');

    $request = \Illuminate\Support\Facades\Http::recorded()->last()[0];
    expect($request->data()['model'])->toBe('openai/gpt-4o-mini')
        ->and($request->data()['temperature'])->toBe(0.3)
        ->and($request->hasHeader('Authorization', 'Bearer or-key'))->toBeTrue();

    config()->set('services.openrouter.api_key', null);

    expect(\App\Actions\Helpers\AI\AskToAi::run('hi', 'gpt-4o-mini'))->toBe('via openai');

    $request = \Illuminate\Support\Facades\Http::recorded()->last()[0];
    expect($request->data()['model'])->toBe('gpt-4o-mini')
        ->and($request->hasHeader('Authorization', 'Bearer openai-key'))->toBeTrue();
});

test('staff flag an AI summary of an email or of a chat as wrong, saying why, once per summary', function () {
    $session = ChatSession::create([
        'ulid'     => (string) Str::ulid(),
        'status'   => ChatSessionStatusEnum::CLOSED,
        'channel'  => ChatChannelEnum::WEBSITE,
        'shop_id'  => $this->shop->id,
        'metadata' => ['ai_summary' => ['summary' => 'Customer asks about an order', 'key_points' => []]],
    ]);
    $email = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::USER,
        'message_text'    => 'A long email',
        'metadata'        => ['ai_summary' => 'Longer than the email itself'],
    ]);
    $plain = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::USER,
        'message_text'    => 'Short',
    ]);

    actingAs($this->user);

    $this->postJson(route('grp.chat.ai.summaries.message.flag', [$plain->id]), ['reason' => 'x'])->assertNotFound();
    $this->postJson(route('grp.chat.ai.summaries.message.flag', [$email->id]))->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->postJson(route('grp.chat.ai.summaries.message.flag', [$email->id]), ['reason' => 'Too long'])->assertOk();
    expect(Arr::get($email->refresh()->metadata, 'ai_summary_flags.0'))->toMatchArray(['summary' => 'Longer than the email itself', 'reason' => 'Too long', 'user_id' => $this->user->id])
        ->and(\App\Http\Resources\CRM\Livechat\ChatMessageResource::make($email)->resolve())->toMatchArray(['ai_summary_flagged' => true])
        ->and(\App\Http\Resources\CRM\Livechat\ChatMessageResource::make($email)->resolve()['metadata'])->not->toHaveKey('ai_summary_flags');
    $this->postJson(route('grp.chat.ai.summaries.message.flag', [$email->id]), ['reason' => 'Again'])->assertStatus(422);

    $this->postJson(route('grp.chat.ai.summaries.session.flag', [$session->ulid]), ['reason' => 'Missed the refund'])->assertOk();
    expect(\App\Actions\Chat\UI\FlagChatAiSummary::isFlagged($session->refresh()))->toBeTrue();
    $this->postJson(route('grp.chat.ai.summaries.session.flag', [$session->ulid]), ['reason' => 'Again'])->assertStatus(422);

    $session->update(['metadata' => array_merge($session->metadata, ['ai_summary' => ['summary' => 'Customer wants a refund']])]);
    expect(\App\Actions\Chat\UI\FlagChatAiSummary::isFlagged($session->refresh()))->toBeFalse()
        ->and(Arr::get($session->metadata, 'ai_summary_flags.0.summary'))->toBe('Customer asks about an order');
    $this->postJson(route('grp.chat.ai.summaries.session.flag', [$session->ulid]), ['reason' => 'Still wrong'])->assertOk();
    expect(Arr::get($session->refresh()->metadata, 'ai_summary_flags'))->toHaveCount(2);

    $whatsappSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp'])->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+421900003676',
        'status'          => ChatSessionStatusEnum::CLOSED,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
        'metadata'        => ['ai_summary' => ['summary' => 'Customer asks for a catalogue']],
    ]);
    $this->postJson(route('grp.chat.ai.summaries.meta_session.flag', [$whatsappSession->ulid]), ['reason' => 'They asked for prices'])->assertOk();
    expect(\App\Http\Resources\CRM\Livechat\MetaChatSessionListResource::make($whatsappSession->refresh())->resolve()['ai_summary']['flagged'])->toBeTrue();
    $whatsappSession->delete();

    $email->delete();
    $plain->delete();
    $session->delete();
});

test('a delivery zone priced by weight is described in kg of estimated order weight, not in money', function () {
    $facts = \App\Actions\Chat\ChatSession\GetChatShopFacts::make();
    $steps = [['from' => 0, 'to' => 10, 'price' => 39.95], ['from' => 10, 'to' => 75.5, 'price' => 249.95], ['from' => 75.5, 'to' => 'INF', 'price' => 0]];
    $money = fn (float $amount) => \Illuminate\Support\Number::currency($amount, $this->shop->currency?->code ?? 'GBP');

    expect($facts->deliveryPrice($this->shop, ['type' => 'Step Order Estimated Weight', 'steps' => $steps]))
        ->toBe($money(39.95).' up to 10 kg, '.$money(249.95).' up to 75.5 kg, free from 75.5 kg (estimated order weight)')
        ->and($facts->deliveryPrice($this->shop, ['type' => 'Step Order Items Net Amount', 'steps' => $steps]))
        ->toBe($money(39.95).' up to '.$money(10).', '.$money(249.95).' up to '.$money(75.5).', free from '.$money(75.5).' (goods before VAT)')
        ->and($facts->deliveryPrice($this->shop, ['type' => 'Step Order Items Net Amount', 'steps' => [['from' => 0, 'to' => 30, 'price' => 18.5], ['from' => 30, 'to' => 'INF', 'price' => 'TBC']]]))
        ->toBe($money(18.5).' up to '.$money(30).', from '.$money(30).': delivery price to be confirmed by customer service (goods before VAT)');

    $zone = new \App\Models\Billables\ShippingZone([
        'price'          => ['type' => 'Step Order Items Net Amount', 'steps' => [['from' => 0, 'to' => 'INF', 'price' => 0]]],
        'shippers_price' => [['shipper_id' => 1, 'type' => 'Step Order Estimated Weight', 'steps' => $steps]],
    ]);
    expect((fn () => $this->zonePrice($zone))->call($facts))->toBe($zone->shippers_price[0]);
});

test('a delivery zone priced as to be confirmed says the price is confirmed later by customer service', function () {
    $schema = \App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema::make()->action($this->shop, ['name' => 'Chat TBC shipping']);
    \App\Actions\Billables\ShippingZone\StoreShippingZone::make()->action($schema, [
        'code'        => 'CHAT-TBC',
        'name'        => 'Far away',
        'status'      => true,
        'price'       => ['type' => 'TBC'],
        'position'    => 1,
        'is_failover' => false,
    ]);
    $shop = $this->shop->replicate()->setRelation('currentShippingZoneSchema', $schema);

    expect(\App\Actions\Chat\ChatSession\GetChatShopFacts::make()->shopFacts($shop))
        ->toContain(['title' => 'Delivery: Far away', 'body' => 'Far away: delivery price to be confirmed by customer service']);
});

test('each active charge is described with its amount and when the checkout adds it', function () {
    $facts  = \App\Actions\Chat\ChatSession\GetChatShopFacts::make();
    $money  = fn (float $amount) => \Illuminate\Support\Number::currency($amount, $this->shop->currency?->code ?? 'GBP');
    $line   = fn (array $attributes) => (fn (\App\Models\Catalogue\Shop $shop, \App\Models\Billables\Charge $model) => $this->charge($shop, $model))->call($facts, $this->shop, new \App\Models\Billables\Charge($attributes));

    expect($line(['name' => 'SOC', 'type' => 'hanging', 'trigger' => 'order', 'settings' => ['rules' => '<;50', 'amount' => '5', 'rule_subject' => 'Order Items Net Amount']]))
        ->toBe('SOC '.$money(5).' when goods before VAT are under '.$money(50))
        ->and($line(['name' => 'SOC', 'type' => 'hanging', 'trigger' => 'order', 'settings' => ['rules' => '']]))->toBeNull()
        ->and($line(['name' => 'Pedido de recogida', 'type' => 'collection', 'trigger' => 'order', 'settings' => ['amount' => '1']]))->toBe('Pedido de recogida '.$money(1).' on orders for collection')
        ->and($line(['name' => 'Premium dispatch', 'type' => 'premium', 'trigger' => 'selected-by-customer', 'settings' => ['amount' => '7.5']]))->toBe('Premium dispatch '.$money(7.5).' when the customer picks it at checkout')
        ->and($line(['name' => 'Pastpay charge (30 days)', 'type' => 'payment', 'trigger' => 'payment-account', 'settings' => ['rules' => null]]))->toBe('Pastpay charge (30 days) amount worked out at checkout when the customer pays with that method')
        ->and($line(['name' => 'Discretionary charge', 'type' => 'discretionary', 'trigger' => 'selected-by-user', 'settings' => []]))->toBe('Discretionary charge amount worked out at checkout only when staff add it by hand');
});

test('a delivery zone kept to some postcodes says which, and is matched by postcode the way the checkout charges it', function () {
    $northernIreland = new \App\Models\Billables\ShippingZone(['slug' => 'ni', 'name' => 'Zone 2', 'territories' => [['country_code' => 'GB', 'included_postal_codes' => '/^BT/']]]);
    $restOfUk        = new \App\Models\Billables\ShippingZone(['slug' => 'uk', 'name' => 'Zone 1', 'territories' => [['country_code' => 'GB', 'excluded_postal_codes' => '/^BT/']]]);

    expect(\App\Actions\Ordering\Order\CalculateOrderShipping::zoneCoversAddress($northernIreland, 'GB', 'BT1 1AA'))->toBeTrue()
        ->and(\App\Actions\Ordering\Order\CalculateOrderShipping::zoneCoversAddress($northernIreland, 'GB', 'SW1A 1AA'))->toBeFalse()
        ->and((fn () => $this->zoneCountries($northernIreland))->call(\App\Actions\Chat\ChatSession\GetChatShopFacts::make()))->toBe('United Kingdom (only postcodes matching /^BT/)')
        ->and((fn () => $this->zoneCountries($restOfUk))->call(\App\Actions\Chat\ChatSession\GetChatShopFacts::make()))->toBe('United Kingdom (except postcodes matching /^BT/)');
});

test('a customer message no longer starts an in-app draft: only the cloud assistant writes drafts', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $session = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::ACTIVE,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'last_visitor_message_at' => now(),
    ]);
    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::USER,
        'message_text'    => 'Hello, I cannot pay for the bags in my basket',
    ]);

    \App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::make()->handle($session, ChatSenderTypeEnum::USER->value, null, $message);

    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::assertPushed(1);
    expect(\App\Models\Chat\ChatAiDraft::where('chat_session_id', $session->id)->exists())->toBeFalse();
});

test('the AI coverage report counts every chat a customer wrote to once, by what was done with their latest message', function () {
    $group  = $this->organisation->group;
    $before = \App\Actions\Chat\Reports\GetChatAiCoverage::run($group, 'last_hour')['matrix'];

    $customerWrites = fn (ChatSession $session, string $text) => ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => $text,
    ]);
    $staffReplies = fn (ChatSession $session) => ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::AGENT,
        'message_text'    => 'Thanks, done.',
    ]);
    $record = fn (ChatSession $session, \App\Enums\CRM\Livechat\ChatAiOutcomeEnum $outcome) => \App\Actions\Chat\ChatSession\StoreChatAiOutcome::run($session, $outcome);

    $drafted = noiseTestEmailSession($this->shop, 'drafted@example.com', 'Where is my order', 'Where is my order?');
    $record($drafted, \App\Enums\CRM\Livechat\ChatAiOutcomeEnum::DRAFT);
    $record($drafted, \App\Enums\CRM\Livechat\ChatAiOutcomeEnum::SPAM);

    $wroteAgain = noiseTestEmailSession($this->shop, 'again@example.com', 'Refund', 'Can I have a refund?');
    $record($wroteAgain, \App\Enums\CRM\Livechat\ChatAiOutcomeEnum::NOTE);
    $customerWrites($wroteAgain, 'Also, the lid was cracked.');

    $answered = noiseTestEmailSession($this->shop, 'answered@example.com', 'Invoice', 'Send me the invoice please');
    $staffReplies($answered);

    $binned = noiseTestEmailSession($this->shop, 'promo@example.com', 'SEO offer', 'Rank first on Google');
    $binned->update(['is_spam' => true]);

    $thanked = noiseTestEmailSession($this->shop, 'thanks@example.com', 'Re: order', 'Thank you!');
    $record($thanked, \App\Enums\CRM\Livechat\ChatAiOutcomeEnum::THANKS);
    $staffReplies($thanked);

    $held = noiseTestEmailSession($this->shop, 'held@example.com', 'Wholesale', 'Can I open an account?');
    $record($held, \App\Enums\CRM\Livechat\ChatAiOutcomeEnum::SPAM);
    $record($held, \App\Enums\CRM\Livechat\ChatAiOutcomeEnum::HELD);

    $whatsapp = noiseTestWhatsappSession($this->shop, '+447700900'.mt_rand(100, 999), 'Is the shop open on Sunday?');

    $old = noiseTestEmailSession($this->shop, 'old@example.com', 'Old', 'Written yesterday');
    $old->messages()->update(['created_at' => now()->subDay()]);

    $after = \App\Actions\Chat\Reports\GetChatAiCoverage::run($group, 'last_hour')['matrix'];
    $delta = fn (string $row, string $column = 'total') => $after[$row][$column] - $before[$row][$column];

    expect($delta('received'))->toBe(7)
        ->and($delta('received', 'email'))->toBe(6)
        ->and($delta('received', 'whatsapp'))->toBe(1)
        ->and($delta('draft', 'email'))->toBe(1)
        ->and($delta('note'))->toBe(0)
        ->and($delta('answered_by_staff', 'email'))->toBe(1)
        ->and($delta('spam', 'email'))->toBe(1)
        ->and($delta('thanks', 'email'))->toBe(1)
        ->and($delta('held', 'email'))->toBe(1)
        ->and($delta('worked'))->toBe(4)
        ->and($delta('not_worked', 'email'))->toBe(1)
        ->and($delta('not_worked', 'whatsapp'))->toBe(1);

    foreach ([$after, $before] as $matrix) {
        foreach (['email', 'website', 'whatsapp', 'total'] as $column) {
            $rows = collect(['answered_by_staff', 'suggestion_already_waiting', 'worked', 'not_worked'])->sum(fn ($row) => $matrix[$row][$column]);
            $worked = collect(\App\Actions\Chat\Reports\GetChatAiCoverage::WORKED)->sum(fn ($row) => $matrix[$row][$column]);
            expect($rows)->toBe($matrix['received'][$column])->and($worked)->toBe($matrix['worked'][$column]);
        }
    }

    $segments = \App\Actions\Chat\Reports\GetChatAiCoverage::make()->segments($group, 'last_hour');
    $listed   = \Illuminate\Support\Facades\DB::query()->fromSub(\App\Actions\Chat\Reports\GetChatAiCoverage::make()->chats($group, $segments), 'coverage')
        ->where('channel', '<>', 'whatsapp')
        ->whereIn('session_id', [$drafted->id, $wroteAgain->id, $answered->id, $binned->id, $thanked->id, $held->id, $old->id])
        ->pluck('bucket', 'session_id');

    expect($listed->sortKeys()->all())->toBe([
        $drafted->id    => 'draft',
        $wroteAgain->id => 'not_worked',
        $answered->id   => 'answered_by_staff',
        $binned->id     => 'spam',
        $thanked->id    => 'thanks',
        $held->id       => 'held',
    ])->and(\App\Models\Chat\ChatAiOutcome::where('chat_session_id', $held->id)->count())->toBe(1);

    expect(fn () => \App\Models\Chat\ChatAiOutcome::factory()->forChatSession($drafted, $drafted->messages()->max('id'))->create())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    actingAs($this->user);
    $page = get(route('grp.chat.ai.dashboard', ['window' => 'last_hour']))->assertOk()->viewData('page')['props'];
    expect($page['coverage']['matrix']['received']['total'])->toBe($after['received']['total'])
        ->and($page['coverage']['trend']['unit'])->toBe('hour');

    $chats = get(route('grp.chat.ai.coverage', ['window' => 'last_hour', 'row' => 'not_worked', 'channel' => 'email']))->assertOk()->viewData('page')['props']['data']['data'];
    expect(collect($chats)->pluck('contact'))->toContain('again@example.com')->not->toContain('drafted@example.com');

    foreach ([$drafted, $wroteAgain, $answered, $binned, $thanked, $held, $old] as $session) {
        $session->messages()->withTrashed()->forceDelete();
        $session->forceDelete();
    }
    $whatsapp->messages()->withTrashed()->forceDelete();
    $whatsapp->forceDelete();
});

test('the AI coverage windows follow each team clock: overnight, team hours and today', function () {
    $coverage = \App\Actions\Chat\Reports\GetChatAiCoverage::make();
    $group    = $this->organisation->group;
    $window   = fn (string $key, string $now) => collect($coverage->segments($group, $key, now: \Illuminate\Support\Carbon::parse($now)))
        ->mapWithKeys(fn ($segment) => [$segment['label'] => [$segment['from']->toDateTimeString(), $segment['to']->toDateTimeString()]])
        ->all();

    expect($window('overnight', '2026-10-23 10:00:00'))->toBe([
        'ES + SK'    => ['2026-10-22 14:00:00', '2026-10-23 06:00:00'],
        'AW + Aroma' => ['2026-10-22 15:00:00', '2026-10-23 07:00:00'],
    ])->and($window('overnight', '2026-10-23 03:30:00'))->toBe([
        'ES + SK'    => ['2026-10-22 14:00:00', '2026-10-23 03:30:00'],
        'AW + Aroma' => ['2026-10-22 15:00:00', '2026-10-23 03:30:00'],
    ])->and($window('overnight', '2026-10-26 06:30:00'))->toBe([
        'ES + SK'    => ['2026-10-23 14:00:00', '2026-10-26 06:30:00'],
        'AW + Aroma' => ['2026-10-23 15:00:00', '2026-10-26 06:30:00'],
    ])->and($window('team_hours', '2026-10-23 10:00:00'))->toBe([
        'ES + SK'    => ['2026-10-23 06:00:00', '2026-10-23 10:00:00'],
        'AW + Aroma' => ['2026-10-23 07:00:00', '2026-10-23 10:00:00'],
    ])->and($window('team_hours', '2026-10-24 12:00:00'))->toBe([
        'ES + SK'    => ['2026-10-23 06:00:00', '2026-10-23 14:00:00'],
        'AW + Aroma' => ['2026-10-23 07:00:00', '2026-10-23 15:00:00'],
    ])->and($window('today', '2026-10-23 10:00:00'))->toBe([
        'ES + SK'    => ['2026-10-22 22:00:00', '2026-10-23 10:00:00'],
        'AW + Aroma' => ['2026-10-22 23:00:00', '2026-10-23 10:00:00'],
    ]);
});

test('Polish rewrites a staff reply but keeps pictures, merge fields, links and numbers, and keeps the original when anything is lost', function () {
    config()->set('services.openrouter.api_key', 'or-key');

    $original = "hola Ana, tu pedido 123456 sale mañana, total 45.90 €. mira [image:abc-1] y https://example.com/p?a_b=1 o escribe a cs@shop.com {first_name}";
    $polish   = \App\Actions\Helpers\WritingQuality\PolishWriting::make();
    [$masked, $tokens] = $polish->mask($original);

    expect($tokens)->toBe(['[image:abc-1]', 'https://example.com/p?a_b=1', 'cs@shop.com', '{first_name}'])
        ->and($masked)->toBe('hola Ana, tu pedido 123456 sale mañana, total 45.90 €. mira ⟦1⟧ y ⟦2⟧ o escribe a ⟦3⟧ ⟦4⟧');

    $reply  = fn (string $text) => ['choices' => [['message' => ['content' => json_encode(['text' => $text])]]]];
    $jev    = fn (float $meaning) => ['answers' => [
        'same_meaning'  => ['type' => 'noul', 'noul' => $meaning],
        'same_language' => ['type' => 'noul', 'noul' => 0.98],
        'same_voice'    => ['type' => 'noul', 'noul' => 0.9],
    ]];
    $polishedText = 'Hola, Ana: tu pedido 123456 sale mañana, con un total de 45.90 €. Mira ⟦1⟧ y ⟦2⟧, o escribe a ⟦3⟧ ⟦4⟧';

    \Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/v1/chat/completions' => \Illuminate\Support\Facades\Http::sequence()
            ->push($reply($polishedText))
            ->push($reply('Hola, Ana: tu pedido sale mañana. Mira ⟦1⟧ y ⟦2⟧, o escribe a ⟦3⟧ ⟦4⟧'))
            ->push($reply('Hola, Ana: tu pedido 123456 sale mañana, total *45.90* €. Mira ⟦1⟧ y ⟦2⟧ o escribe a ⟦3⟧'))
            ->push($reply($polishedText))
            ->push($reply($masked))
            ->push($reply($polishedText)),
        'openrouter.ai/api/alpha/decisions'     => \Illuminate\Support\Facades\Http::sequence()
            ->push($jev(0.95))
            ->push($jev(0.2))
            ->push([], 500),
    ]);

    expect($polish->handle($original, 'chat'))->toBe([
        'status' => 'polished',
        'text'   => 'Hola, Ana: tu pedido 123456 sale mañana, con un total de 45.90 €. Mira [image:abc-1] y https://example.com/p?a_b=1, o escribe a cs@shop.com {first_name}',
    ])
        ->and($polish->handle($original, 'chat'))->toBe(['status' => 'kept', 'text' => $original])
        ->and($polish->handle($original, 'chat'))->toBe(['status' => 'kept', 'text' => $original])
        ->and($polish->handle($original, 'chat'))->toBe(['status' => 'kept', 'text' => $original])
        ->and($polish->handle($original, 'chat'))->toBe(['status' => 'unchanged', 'text' => $original])
        ->and($polish->handle($original, 'chat'))->toBe(['status' => 'kept', 'text' => $original]);

    \Illuminate\Support\Facades\Http::assertSentCount(9);

    expect($polish->unmask('Refund to ⟦2⟧, not ⟦1⟧', ['a@x.com', 'b@x.com']))->toBeNull()
        ->and($polish->keepsDetails('ships in 3 days', 'it ships in 3-5 days'))->toBeFalse()
        ->and($polish->keepsDetails('2 boxes of SoapD-05', 'two boxes of Soap D-05'))->toBeFalse()
        ->and($polish->keepsDetails('total 45.90 €', 'total £45.90'))->toBeFalse()
        ->and($polish->keepsDetails('your order (ORD-88231) ships on 28/10.', 'Your order ORD-88231 ships on 28/10!'))->toBeTrue()
        ->and($polish->keepsFormatting('it is *not* broken', 'it is *not* _really_ broken'))->toBeFalse()
        ->and($polish->keepsLayout("Here:\n⟦1⟧\nThanks", 'Here: ⟦1⟧ Thanks'))->toBeFalse()
        ->and($polish->keepsLayout("- one\n- two", "- One\n- Two"))->toBeTrue()
        ->and($polish->mask('_write to a@b.com_')[1])->toBe(['a@b.com']);

    actingAs($this->user)
        ->postJson(route('grp.json.writing_quality.polish'), ['text' => 'hi', 'channel' => 'fax'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('channel');
});

test('a draft is shown translated to an agent who reads another language than the customer wrote in', function () {
    $spanish = Language::where('code', 'es')->value('id');
    $english = Language::where('code', 'en')->value('id');

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING,
        'channel'          => ChatChannelEnum::WEBSITE,
        'shop_id'          => $this->shop->id,
        'user_language_id' => $english,
    ]);
    $trigger = ChatMessage::create([
        'chat_session_id'      => $session->id,
        'message_type'         => ChatMessageTypeEnum::TEXT,
        'sender_type'          => ChatSenderTypeEnum::GUEST,
        'message_text'         => 'Hola, ¿dónde está mi pedido?',
        'original_language_id' => $spanish,
    ]);
    $draft   = new \App\Models\Chat\ChatAiDraft(['trigger_message_id' => $trigger->id]);
    $inOther = fn (?int $agentLanguageId) => \App\Actions\Chat\ChatSession\HandleChatAiDraft::isInAnotherLanguage($session, $draft, $agentLanguageId);

    expect($inOther($english))->toBeTrue()
        ->and($inOther($spanish))->toBeFalse()
        ->and($inOther(null))->toBeFalse();

    $draft->trigger_message_id = null;
    expect($inOther($english))->toBeFalse()
        ->and($inOther($spanish))->toBeTrue();
});
