<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\GetChatReports;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\HumanResources\WorkSchedule\GetChatConfig;
use App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\StoreOfflineMessage;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Actions\Helpers\Translations\DetectLanguageWithJev;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
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

test('a colleague emailing a shop mailbox stays in the queue without asking the model', function () {
    \Illuminate\Support\Facades\Http::fake();

    $this->user->update(['email' => 'goods.in@staff-test.example']);

    $colleague = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle(
        noiseTestEmailSession($this->shop, 'Goods.In@Staff-Test.example', 'NEW / BACK IN STOCK', 'Following products are new in stock')
    )->refresh();

    \Illuminate\Support\Facades\Http::assertNothingSent();

    expect($colleague->is_rubbish)->toBeFalse()
        ->and($colleague->noise_verdict)->toBe('genuine')
        ->and($colleague->noise_source)->toBe('rule')
        ->and($colleague->noise_note)->toContain('One of our own staff')
        ->and(\App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::forList($colleague))->toBeNull();
});

test('the waiting queue is worked oldest first, even ahead of a newer one told we were closed, and the bins are still newest first', function () {
    \Illuminate\Support\Facades\Http::fake();

    $oldest = noiseTestEmailSession($this->shop, 'first@example.com', 'Waited longest', 'Where is my order');
    $newest = noiseTestEmailSession($this->shop, 'third@example.com', 'Just arrived', 'Where is my order');

    $oldest->messages()->update(['created_at' => now()->subDays(4)]);
    $newest->messages()->update(['created_at' => now()->subMinutes(2)]);
    $newest->update(['metadata' => ['out_of_hours_replied_at' => now()->subMinute()->toISOString()]]);

    $queue = fn (array $filters) => collect(GetChatSessions::make()->handle(array_merge(['shop_id' => $this->shop->id], $filters))->items())
        ->pluck('id')->all();

    $waiting = $queue(['statuses' => ['waiting']]);

    $oldest->update(['is_rubbish' => true, 'rubbish_at' => now()]);
    $newest->update(['is_rubbish' => true, 'rubbish_at' => now()]);

    $bin = $queue(['is_rubbish' => true]);

    expect(array_search($oldest->id, $waiting, true))->toBeLessThan(array_search($newest->id, $waiting, true))
        ->and(array_search($newest->id, $bin, true))->toBeLessThan(array_search($oldest->id, $bin, true));
});

test('a request to cancel or change the delivery address goes first in the queue until an agent answers', function () {
    \Illuminate\Support\Facades\Http::fake();

    $older  = noiseTestEmailSession($this->shop, 'first@example.com', 'Waited longest', 'Where is my order');
    $urgent = noiseTestEmailSession($this->shop, 'second@example.com', 'Order 1234', 'Please cancel order 1234');

    $older->messages()->update(['created_at' => now()->subDays(2)]);
    $older->update(['last_visitor_message_at' => now()->subDays(2)]);

    \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()->andReturn('{"request": "cancel_order"}');

    expect(\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($urgent))->toBe('cancel_order');

    $courier = noiseTestEmailSession($this->shop, 'incidencias@gls-spain.es', 'Envío 1307', 'Falta el bulto 2, ¿autorizáis entrega parcial o anulamos el envío?');
    $courier->update(['is_carrier' => true]);
    expect(\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($courier))->toBeNull()
        ->and(data_get($courier->refresh()->metadata, 'urgent_request'))->toBeNull();

    $queue = fn () => collect(GetChatSessions::make()->handle(['shop_id' => $this->shop->id, 'statuses' => ['waiting']])->items())->pluck('id')->all();

    $waiting = $queue();
    expect(array_search($urgent->id, $waiting, true))->toBeLessThan(array_search($older->id, $waiting, true))
        ->and(\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::current($urgent->refresh()))->toBe('cancel_order');

    $urgent->update(['last_agent_message_at' => now()->addSecond()]);

    $waiting = $queue();
    expect(array_search($older->id, $waiting, true))->toBeLessThan(array_search($urgent->id, $waiting, true))
        ->and(\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::current($urgent->refresh()))->toBeNull();
});

test('a safe place or leave-it instruction for an order on its way is flagged as a delivery instruction, not a change of address', function () {
    $urgent = fn (array $probabilities) => \App\Actions\Chat\ChatSession\ClassifyChatTurn::urgent(['request' => ['probabilities' => $probabilities]]);

    expect($urgent(['delivery_instruction' => 0.7, 'change_address' => 0.2]))->toBe('delivery_instruction')
        ->and($urgent(['change_address' => 0.5, 'delivery_instruction' => 0.4]))->toBe('change_address')
        ->and($urgent(['cancel_all' => 0.45, 'delivery_instruction' => 0.45]))->toBe('cancel_order')
        ->and($urgent(['delivery_instruction' => 0.3, 'none' => 0.7]))->toBeNull();

    \Illuminate\Support\Facades\Http::fake();
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()->andReturn('{"request": "delivery_instruction"}');
    $session = noiseTestEmailSession($this->shop, 'safe-place@example.com', 'Order 1234', 'Fab thanks. Its just my shop address wouldnt be safe to leave it there if no ones open');

    expect(\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($session))->toBe('delivery_instruction')
        ->and(\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::current($session->refresh()))->toBe('delivery_instruction');
});

test('with jev the urgent flag and the dropshipping queue come from the cascade, and no chat model is asked', function () {
    \Illuminate\Support\Facades\Http::fake();
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->never();
    $originalType = $this->shop->type;

    try {
        $this->shop->update(['type' => \App\Enums\Catalogue\Shop\ShopTypeEnum::DROPSHIPPING]);
        \App\Actions\Helpers\AI\AskJev::mock()->shouldReceive('handle')->andReturnUsing(fn (array $state, array $questions) => isset($questions['request']) ? [
            'request' => ['type' => 'choice', 'choice' => 'cancel_all', 'probabilities' => ['cancel_all' => 0.93, 'none' => 0.07]],
            'ds_kind' => ['type' => 'choice', 'choice' => 'documents', 'probabilities' => ['documents' => 0.9]],
        ] : null);

        $cancel = noiseTestEmailSession($this->shop->fresh(), 'jev-cancel@example.com', 'Order 1234', 'Tem como cancelar uma encomenda feita HOJE ?');

        expect(\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($cancel))->toBe('cancel_order')
            ->and(\App\Actions\Chat\ChatSession\FlagUrgentChatRequest::current($cancel->refresh()))->toBe('cancel_order')
            ->and(data_get($cancel->metadata, 'ds_kind'))->toBe('documents')
            ->and(data_get($cancel->metadata, 'ai_turn.urgent'))->toBe('cancel_order');
    } finally {
        $this->shop->update(['type' => $originalType]);
    }
});

test('a dropshipping conversation is labelled integration or documents from the same check, and can be listed by that kind', function () {
    \Illuminate\Support\Facades\Http::fake();

    $originalType = $this->shop->type;

    try {
        $this->shop->update(['type' => \App\Enums\Catalogue\Shop\ShopTypeEnum::DROPSHIPPING]);

        $integration = noiseTestEmailSession($this->shop, 'shopify@example.com', 'Shopify', 'My Shopify store says the channel is not connected');
        $documents   = noiseTestEmailSession($this->shop, 'cpsr@example.com', 'CPSR', 'Please send the CPSR for EO-01');
        $ordinary    = noiseTestEmailSession($this->shop, 'parcel@example.com', 'Parcel', 'Where is my parcel?');

        \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()
            ->withArgs(fn (string $prompt) => str_contains($prompt, '"kind"'))
            ->andReturn('{"request": "none", "only_thanks": false, "kind": "integration"}');
        \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($integration);

        \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()->andReturn('{"request": "none", "only_thanks": false, "kind": "documents"}');
        \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($documents);

        \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()->andReturn('{"request": "none", "only_thanks": false, "kind": "cs"}');
        \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($ordinary);

        $list = fn (string $kind) => collect(GetChatSessions::make()->handle(['shop_id' => $this->shop->id, 'statuses' => ['waiting'], 'ds_kind' => $kind])->items())->pluck('id')->all();

        expect(data_get($integration->refresh()->metadata, 'ds_kind'))->toBe('integration')
            ->and(data_get($documents->refresh()->metadata, 'ds_kind'))->toBe('documents')
            ->and(data_get($ordinary->refresh()->metadata, 'ds_kind'))->toBeNull()
            ->and($list('integration'))->toContain($integration->id)->not->toContain($documents->id)->not->toContain($ordinary->id)
            ->and($list('documents'))->toContain($documents->id)->not->toContain($integration->id);

        $integration->messages()->first()->update(['created_at' => now()->subMinute()]);
        $integration->update(['last_agent_message_at' => now()->subSeconds(30)]);
        $integration->messages()->create(['message_text' => 'And where is my parcel?', 'message_type' => 'text', 'sender_type' => 'user']);

        \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()->andReturn('{"request": "none", "only_thanks": false, "kind": "cs"}');
        \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($integration->refresh());

        expect(data_get($integration->refresh()->metadata, 'ds_kind'))->toBe('integration');

        $this->shop->update(['type' => $originalType]);
        $otherShop = noiseTestEmailSession($this->shop, 'b2b@example.com', 'Shopify', 'My Shopify store is not connected');

        \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()
            ->withArgs(fn (string $prompt) => !str_contains($prompt, '"kind"'))
            ->andReturn('{"request": "none", "only_thanks": false, "kind": "integration"}');
        \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($otherShop);

        expect(data_get($otherShop->refresh()->metadata, 'ds_kind'))->toBeNull();
    } finally {
        $this->shop->update(['type' => $originalType]);
    }
});

test('when the model cannot be asked, words in the customer\'s language decide what is urgent', function () {
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->andReturn(null);
    $flag = \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::make();

    expect($flag->classify('Bitte stornieren Sie meine Bestellung'))->toBe('cancel_order')
        ->and($flag->classify('Prosím zrušte objednávku'))->toBe('cancel_order')
        ->and($flag->classify('Can you change the delivery address to 5 High St?'))->toBe('change_address')
        ->and($flag->classify('Necesito cambiar la dirección de entrega'))->toBe('change_address')
        ->and($flag->classify('Where is my order?'))->toBeNull();
});

test('a thanks after we answered closes the conversation quietly, but never a first message, an attachment or an open ticket', function () {
    config(['chat.close_after_thanks' => true, 'chat.wait_for_customer_hours' => 72]);
    \Illuminate\Support\Facades\Http::fake();
    fakeActionJobs(\App\Actions\Chat\ChatSession\SummarizeChatSession::class);
    \Illuminate\Support\Facades\Queue::fake();
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->andReturn('{"request": "none", "only_thanks": true}');
    \App\Actions\Helpers\AI\AskJev::mock()->shouldReceive('handle')->andReturn(['act' => ['type' => 'choice', 'choice' => 'closing', 'probabilities' => ['closing' => 0.97]]]);

    $answered = fn (string $from) => tap(noiseTestEmailSession($this->shop, $from, 'Order', 'Perfect, thank you!'), function (ChatSession $session) {
        $session->update(['last_agent_message_at' => now()->subHour()]);
    });

    $thanks = $answered('thanks@example.com');
    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($thanks);

    expect($thanks->refresh()->status)->not->toBe(ChatSessionStatusEnum::CLOSED)
        ->and(\Illuminate\Support\Carbon::parse(\App\Actions\Chat\ChatSession\WaitForCustomerReply::until($thanks))->diffInMinutes(now()->addWeekdays(3), true))->toBeLessThan(1)
        ->and(data_get($thanks->metadata, 'waiting_for_customer.reason'))->toBe('thanks');
    \App\Actions\Chat\ChatSession\WaitForCustomerReply::assertPushed(1);

    \App\Actions\Chat\ChatSession\WaitForCustomerReply::make()->asJob($thanks, data_get($thanks->metadata, 'waiting_for_customer.message_id'));
    expect($thanks->refresh()->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($thanks->closed_by)->toBe(\App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum::SYSTEM)
        ->and($thanks->messages()->where('metadata->automated', 'waited_closed')->exists())->toBeTrue();

    $first = noiseTestEmailSession($this->shop, 'first@example.com', 'Hello', 'Thank you!');
    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($first);
    expect($first->refresh()->status)->toBe(ChatSessionStatusEnum::WAITING);

    $withPhoto = $answered('photo@example.com');
    $withPhoto->messages()->where('sender_type', ChatSenderTypeEnum::GUEST)->update(['message_type' => ChatMessageTypeEnum::IMAGE]);
    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($withPhoto);
    expect($withPhoto->refresh()->status)->toBe(ChatSessionStatusEnum::WAITING);

    config(['chat.close_after_thanks' => false]);
    $switchedOff = $answered('off@example.com');
    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($switchedOff);
    expect($switchedOff->refresh()->status)->toBe(ChatSessionStatusEnum::WAITING);
});

test('a new email after a closed thanks is judged on its own, and jev must be sure the customer is closing', function () {
    config(['chat.close_after_thanks' => true]);
    \Illuminate\Support\Facades\Http::fake();
    fakeActionJobs(\App\Actions\Chat\ChatSession\SummarizeChatSession::class);
    \Illuminate\Support\Facades\Queue::fake();
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->andReturn('{"request": "none", "only_thanks": true}');
    DetectLanguageWithJev::shouldRun()->andReturn(null);
    $jevSays = ['choice' => 'asking', 'probabilities' => ['asking' => 0.64, 'pending' => 0.3, 'closing' => 0.06]];
    $jevRead = [];
    \App\Actions\Helpers\AI\AskJev::mock()->shouldReceive('handle')->andReturnUsing(function (array $state) use (&$jevSays, &$jevRead) {
        $jevRead[] = $state['customer_wrote'];

        return $jevSays ? ['act' => ['type' => 'choice'] + $jevSays] : null;
    });
    $judge = function (ChatSession $session) {
        \Illuminate\Support\Facades\Cache::flush();
        \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($session);

        return \App\Actions\Chat\ChatSession\WaitForCustomerReply::until($session->refresh());
    };

    $refund  = 'Can you please refund the full cost of the order to my AW wallet. Please let me know once the credit has been posted.';
    $session = noiseTestEmailSession($this->shop, 'root@example.com', 'Re: AWD191595', 'Thanks for sharing the return label.');
    $session->update(['last_agent_message_at' => now()->subDays(6)]);
    $session->messages()->create(['message_text' => 'Chat session has been closed by agent', 'message_type' => ChatMessageTypeEnum::TEXT, 'sender_type' => ChatSenderTypeEnum::SYSTEM]);
    $session->messages()->create(['message_text' => $refund, 'message_type' => ChatMessageTypeEnum::TEXT, 'sender_type' => ChatSenderTypeEnum::GUEST]);

    expect($judge($session))->toBeNull()
        ->and($jevRead[0])->toBe($refund);

    $jevSays = null;
    expect($judge($session))->toBeNull();

    $jevSays = ['choice' => 'closing', 'probabilities' => ['closing' => 0.99]];
    expect($judge($session))->not->toBeNull();
});

test('a website chat or whatsapp thanks is left for the agent to close, with no thumbs up', function () {
    config(['chat.close_after_thanks' => true]);
    \Illuminate\Support\Facades\Http::fake();
    fakeActionJobs(\App\Actions\Chat\ChatSession\SummarizeChatSession::class);
    \Illuminate\Support\Facades\Queue::fake();
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->andReturn('{"request": "none", "only_thanks": true}');
    \App\Actions\Helpers\AI\AskJev::mock()->shouldReceive('handle')->andReturn(['act' => ['type' => 'choice', 'choice' => 'closing', 'probabilities' => ['closing' => 0.97]]]);
    \App\Actions\Chat\Whatsapp\SendWhatsappReaction::shouldRun()->never();

    $whatsapp = noiseTestWhatsappSession($this->shop, '+447500000301', 'Thank you so much!');
    $whatsapp->update(['last_agent_message_at' => now()->subHour()]);
    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($whatsapp);

    $website = noiseTestEmailSession($this->shop, 'web-thanks@example.com', 'Chat', 'Great, thanks');
    $website->update(['channel' => ChatChannelEnum::WEBSITE, 'last_agent_message_at' => now()->subMinute()]);
    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($website);

    expect($whatsapp->refresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and(\App\Actions\Chat\ChatSession\WaitForCustomerReply::until($whatsapp))->toBeNull()
        ->and($website->refresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and(\App\Actions\Chat\ChatSession\WaitForCustomerReply::until($website))->toBeNull()
        ->and($website->messages()->where('sender_type', ChatSenderTypeEnum::GUEST)->first()->reactions()->exists())->toBeFalse()
        ->and($website->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->exists())->toBeFalse();
});

test('an agent waits for the customer: it closes when the time is up unless the customer writes, and the agent can stop waiting', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\SummarizeChatSession::class);
    \Illuminate\Support\Facades\Queue::fake();
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->andReturn('{"request": "none", "only_thanks": false}');
    $wait    = \App\Actions\Chat\ChatSession\WaitForCustomerReply::class;
    $waitFor = function (ChatSession $session, int $hours) use ($wait): int {
        $wait::run($session, $hours);

        return data_get($session->refresh()->metadata, 'waiting_for_customer.message_id');
    };

    $silent = noiseTestEmailSession($this->shop, 'wait-silent@example.com', 'Order', 'Can you check my order?');
    $lastId = $waitFor($silent, 24);
    expect(\Illuminate\Support\Carbon::parse($wait::until($silent))->diffInMinutes(now()->addWeekdays(1), true))->toBeLessThan(1);
    $wait::make()->asJob($silent, $lastId);
    expect($silent->refresh()->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($wait::until($silent))->toBeNull();

    $replied = noiseTestEmailSession($this->shop, 'wait-replied@example.com', 'Order', 'Can you check my order?');
    $lastId  = $waitFor($replied, 24);
    $replied->messages()->create(['message_type' => ChatMessageTypeEnum::TEXT, 'sender_type' => ChatSenderTypeEnum::GUEST, 'message_text' => 'Here is the photo you asked for']);
    \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::run($replied);
    expect($wait::until($replied->refresh()))->toBeNull();
    $wait::make()->asJob($replied, $lastId);
    expect($replied->refresh()->status)->not->toBe(ChatSessionStatusEnum::CLOSED);

    $stopped = noiseTestEmailSession($this->shop, 'wait-stopped@example.com', 'Order', 'Can you check my order?');
    $lastId  = $waitFor($stopped, 72);
    $wait::stop($stopped);
    $wait::make()->asJob($stopped, $lastId);
    expect($stopped->refresh()->status)->not->toBe(ChatSessionStatusEnum::CLOSED);
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
    config(['chat.noise.auto_put_aside' => false]);
    noiseTestFakeModel('spam', 95);

    $pitch = noiseTestEmailSession($this->shop, 'sales@kaitk.com', 'Wooden gifts', 'We are a manufacturer of wooden gifts');
    $pitch = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($pitch)->refresh();

    expect($pitch->is_spam)->toBeFalse()
        ->and($pitch->noise_verdict)->toBe('spam')
        ->and($pitch->noise_confidence)->toBe(95)
        ->and(data_get($pitch->metadata, 'noise_model'))->toBe(config('chat.noise_model'))
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

    noiseTestFakeModel('automated_notification', 95);
    $marketplaceOrder = noiseTestEmailSession($this->shop, 'service@marketplace.example', 'You have 1 order', 'Accept it in your portal');
    $marketplaceOrder = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($marketplaceOrder)->refresh();

    noiseTestFakeModel('nonsense', 99);
    $odd = noiseTestEmailSession($this->shop, 'someone@example.com', 'Question', 'Do you ship to Norway?');
    $odd = \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($odd)->refresh();

    expect($second->is_spam)->toBeTrue()
        ->and($second->spammed_by_agent_id)->toBeNull()
        ->and($customer->noise_checked_at)->toBeNull()
        ->and($customer->is_spam)->toBeFalse()
        ->and($marketplaceOrder->noise_verdict)->toBe('automated_notification')
        ->and($marketplaceOrder->is_rubbish)->toBeFalse()
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
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-23 10:00', $this->shop->timezoneName()));

    $hello = noiseTestWhatsappSession($this->shop->fresh(), '+447500000002', 'Hello');

    \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($hello);
    \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($hello->refresh());

    \Illuminate\Support\Facades\Http::assertSentCount(1);

    $greeting = $hello->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->first();

    expect($greeting->message_text)->toContain('How can we help you')
        ->and($hello->refresh()->last_agent_message_at)->toBeNull()
        ->and($hello->is_spam)->toBeFalse();

    \Illuminate\Support\Carbon::setTestNow();
});

test('an email out of hours is answered only when a person wrote it, once a day per address and once per conversation', function () {
    config(['chat.out_of_hours_reply' => true]);
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);
    config(['askbot-laravel.openai_api_key' => 'test-key']);
    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*'        => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => json_encode(['verdict' => 'genuine', 'confidence' => 95, 'reason' => 'Asks about stock.', 'existing_customer' => false])]]]]),
        'oauth2.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        '*'                       => \Illuminate\Support\Facades\Http::response(['id' => 'sent-1', 'threadId' => 'th-ooh']),
    ]);
    $schedule = outOfHoursTestSchedule($this->shop);
    $settings = $this->shop->settings ?? [];
    data_set($settings, 'gmail.email', 'care@shop.test');
    data_set($settings, 'gmail.refresh_token', \Illuminate\Support\Facades\Crypt::encryptString('rt'));
    $this->shop->update(['settings' => $settings]);
    \App\Actions\Chat\UpdateShopOutOfHoursMessage::make()->handle($this->shop, ['message' => "  Urgent emails are answered within 2 hours.\n\nThe Team  "]);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-26 11:00', 'Europe/London'));

    $reply    = \App\Actions\Chat\ChatSession\SendOutOfHoursReply::make();
    $answered = function (ChatSession $session) use ($reply) {
        return $reply->handle($session, $session->messages()->where('sender_type', ChatSenderTypeEnum::GUEST)->latest('id')->first());
    };

    $address = 'ooh.person.'.Str::lower(Str::random(8)).'@example.com';
    $person  = noiseTestEmailSession($this->shop->fresh(), $address, 'Lavender oil', 'Is the lavender oil back in stock?');

    // A stranger waits for the noise check, which runs at the same moment: answering first once
    // replied to spam and newsletters. The check's own genuine verdict sends the reply.
    expect($answered($person))->toBeFalse()
        ->and($person->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->exists())->toBeFalse();

    \App\Actions\Chat\ChatSession\ClassifyChatSessionNoise::make()->handle($person);

    $sent = $person->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->sole();

    expect($sent->metadata['auto_submitted'])->toBeTrue()
        ->and($sent->message_text)->toEndWith("will reply from 10am on Monday.\n\nUrgent emails are answered within 2 hours.\n\nThe Team")
        ->and($sent->message_text)->not->toContain('Please tell us how we can help');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        $raw = base64_decode(strtr((string) ($request->data()['raw'] ?? ''), '-_', '+/'));

        return str_contains($request->url(), 'messages/send') && str_contains($raw, 'Auto-Submitted: auto-replied');
    });

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-26 15:00', 'Europe/London'));
    $person->update(['last_agent_message_at' => now()->subHours(3)]);
    $person->messages()->create([
        'message_text' => 'Thanks, and is the rose oil in too?',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::GUEST,
    ]);
    \Illuminate\Support\Facades\Cache::forget('chat-out-of-hours-email:'.sha1($address));

    expect($answered($person->fresh()))->toBeFalse()
        ->and($person->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->count())->toBe(1);

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-26 11:00', 'Europe/London'));

    $sameAddress = noiseTestEmailSession($this->shop->fresh(), $address, 'Another question', 'And the rose oil?');
    $outOfOffice = noiseTestEmailSession($this->shop->fresh(), 'ooh.away@example.com', 'Automatic reply: Lavender oil', 'I am away', ['auto_reply' => true]);
    $newsletter  = noiseTestEmailSession($this->shop->fresh(), 'ooh.news@example.com', 'Our autumn offers', 'Big sale', ['email_headers' => ['list_unsubscribe' => true]]);
    $generated   = noiseTestEmailSession($this->shop->fresh(), 'ooh.robot@example.com', 'Your ticket', 'Received', ['email_headers' => ['auto_submitted' => 'auto-generated']]);
    $noReply     = noiseTestEmailSession($this->shop->fresh(), 'no-reply@example.com', 'Your invoice', 'Attached');

    $fromSpam = noiseTestEmailSession($this->shop->fresh(), 'ooh.spam.'.Str::lower(Str::random(8)).'@example.com', 'Re:- care@shop.test', 'I am still waiting for your reply');
    $fromSpam->update(['noise_verdict' => \App\Enums\CRM\Livechat\ChatNoiseVerdictEnum::GENUINE->value]);
    $fromSpam->messages()->update(['is_rescued_from_spam' => true, 'spam_rescue_kind' => 'customer_request']);

    foreach ([$sameAddress, $outOfOffice, $newsletter, $generated, $noReply, $fromSpam] as $session) {
        expect($answered($session))->toBeFalse()
            ->and($session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->exists())->toBeFalse();
    }

    \App\Actions\Chat\UpdateShopOutOfHoursMessage::make()->handle($this->shop, ['message' => '']);
    expect(data_get($this->shop->fresh()->settings, 'chat.out_of_hours_message'))->toBeNull();

    \Illuminate\Support\Facades\Cache::forget('chat-out-of-hours-email:'.sha1($address));
    outOfHoursTestCleanUp($schedule, [$person, $sameAddress, $outOfOffice, $newsletter, $generated, $noReply]);
});

test('website chat out of hours is answered in the conversation, but not after the offline form', function () {
    config(['chat.out_of_hours_reply' => true]);
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);
    $schedule = outOfHoursTestSchedule($this->shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 15:30', 'Europe/London'));

    $live = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::WAITING,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'last_visitor_message_at' => now(),
    ]);

    $reply = \App\Actions\Chat\ChatSession\SendOutOfHoursReply::make();

    expect($reply->handle($live))->toBeTrue()
        ->and($reply->handle($live))->toBeFalse()
        ->and($live->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->sole()->message_text)->toContain('will reply from 10am tomorrow.');

    $viaForm = StoreOfflineMessage::make()->handle($this->shop->fresh(), [
        'message'     => 'Nobody was on, please write back',
        'name'        => 'Form Writer',
        'email'       => 'form.writer@example.com',
        'language_id' => 68,
        'sender_type' => ChatSenderTypeEnum::GUEST->value,
    ]);

    expect($reply->handle($viaForm))->toBeFalse();

    actingAs($this->user);
    $rows = collect(get(route('grp.chat.ai.sent', ['elements' => ['kind' => 'out_of_hours']]))
        ->assertOk()
        ->viewData('page')['props']['data']['data']);

    // The noise checks have their own tab, so they never bury what reached customers.
    $checks = collect(get(route('grp.chat.ai.noise_checks'))->assertOk()->viewData('page')['props']['data']['data']);
    $sentTab = collect(get(route('grp.chat.ai.sent'))->assertOk()->viewData('page')['props']['data']['data']);
    expect($checks->pluck('kind')->unique()->values()->all())->toBeIn([[], ['noise_check']])
        ->and($sentTab->pluck('kind')->contains('noise_check'))->toBeFalse();

    $dashboard = get(route('grp.chat.ai.dashboard'))->assertOk()->viewData('page')['props']['dashboard'];
    expect($dashboard['readings'])->toHaveKeys(['read', 'helped', 'drafts', 'guides', 'guides_used', 'facts', 'engineer', 'engineer_used']);
    expect($dashboard['review'])->toBeArray();
    expect($dashboard['daily'])->toHaveCount(30)
        ->and(collect($dashboard['by_kind'])->firstWhere('kind', 'out_of_hours')['total'])->toBeGreaterThanOrEqual(1);

    expect($rows->pluck('kind')->unique()->all())->toBe(['out_of_hours'])
        ->and($rows->firstWhere('url', route('grp.org.chat.inbox.conversation', [$this->organisation->slug, $live->ulid])))->not->toBeNull();

    outOfHoursTestCleanUp($schedule, [$live, $viaForm]);
});

test('a customer reporting a problem out of hours is asked for exactly the details still missing', function () {
    config(['chat.out_of_hours_reply' => true, 'askbot-laravel.openai_api_key' => 'test-key']);
    $schedule = outOfHoursTestSchedule($this->shop);
    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['whatsapp' => ['phone_number_id' => '123']])]);
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['meta' => ['access_key' => 'token']])]);

    $order = \App\Models\Ordering\Order::where('shop_id', $this->shop->id)->whereRaw("reference ~ '[0-9]{4,}'")->orderBy('id')->first()
        ?? createOrder($this->customer, Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1]);
    $digits = preg_replace('/\D/', '', $order->reference);

    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => '{"claim": true}']]]]),
        '*'                => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'wamid.claim']]]),
    ]);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-26 11:00', 'Europe/London'));

    $session = noiseTestWhatsappSession($this->shop->fresh(), '+447500000004', 'Hello');
    $reply   = \App\Actions\Chat\ChatSession\SendOutOfHoursReply::make();

    // A bare hello is not read by the model: it just gets the closed-now reply, asking what they need.
    expect($reply->handle($session))->toBeTrue()
        ->and($session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->sole()->message_text)->toContain('Please tell us how we can help');
    \Illuminate\Support\Facades\Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai'));

    $session->messages()->create([
        'meta_channel_id' => $session->meta_channel_id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => "Two of the candles from order $digits arrived broken",
    ]);

    expect($reply->handle($session))->toBeTrue()
        ->and($reply->handle($session))->toBeFalse();

    $asked = $session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->latest('id')->first();

    expect($asked->message_text)->toContain('which items are affected and how many')
        ->and($asked->message_text)->toContain('photos of the items')
        ->and($asked->message_text)->not->toContain('your order number')
        ->and($asked->message_text)->not->toContain('We are closed');

    $session->messages()->create([
        'meta_channel_id' => $session->meta_channel_id,
        'message_type'    => ChatMessageTypeEnum::IMAGE,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => '',
    ]);

    actingAs($this->user);
    $row = collect(get(route('grp.chat.ai.sent', ['elements' => ['kind' => 'claim_details']]))
        ->assertOk()
        ->viewData('page')['props']['data']['data'])
        ->firstWhere('contact', '+447500000004');

    expect($row['claim'])->toBe(['order_reference' => $order->reference, 'photos' => 1])
        ->and($row['url'])->toBe(route('grp.org.chat.inbox', [$this->organisation->slug, 'channel' => 'whatsapp', 'session' => $session->ulid]))
        ->and(\App\Actions\Chat\ChatSession\GetChatClaimDetails::forList($session->refresh()))->toBe(['order_reference' => $order->reference, 'photos' => 1]);

    // Once an agent has answered, the inbox stops showing it: the case is being handled.
    $session->update(['last_agent_message_at' => now()->addMinute()]);
    expect(\App\Actions\Chat\ChatSession\GetChatClaimDetails::forList($session->refresh()))->toBeNull()
        ->and(\App\Actions\Chat\ChatSession\GetChatClaimDetails::forList(noiseTestWhatsappSession($this->shop, '+447500000005', 'Hi')))->toBeNull();

    outOfHoursTestCleanUp($schedule);
});

test('chat closes 15 minutes before work ends unless the shop sets its own chat hours, while reports keep the working hours', function () {
    $schedule = outOfHoursTestSchedule($this->shop);
    $shop = $this->shop->fresh();
    $this->web->update(['settings' => array_merge($this->web->settings ?? [], ['enable_chat' => true])]);
    $at       = fn (string $when) => \Illuminate\Support\Carbon::parse($when, 'Europe/London');
    $chatOpen = fn (\App\Models\Catalogue\Shop $shop, string $when) => IsWithinWorkingHours::make()->chatHours()->handle($shop, $at($when));

    expect($chatOpen($shop, '2026-09-23 13:40'))->toBeTrue()
        ->and($chatOpen($shop, '2026-09-23 13:50'))->toBeFalse()
        ->and(IsWithinWorkingHours::run($shop, $at('2026-09-23 13:50')))->toBeTrue();

    \Illuminate\Support\Carbon::setTestNow($at('2026-09-23 10:30'));
    $config = GetChatConfig::run($this->web->fresh());
    expect($config['is_online'])->toBeTrue()
        ->and($config['schedule']['start'])->toBe('10:00')
        ->and($config['schedule']['end'])->toBe('13:45');

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($shop, ['chat_hours_start' => '10:45', 'chat_hours_end' => '13:30']);
    $shop = $shop->fresh();
    expect($shop->settings['chat']['hours'])->toEqual(['start' => '10:45', 'end' => '13:30'])
        ->and($chatOpen($shop, '2026-09-23 10:30'))->toBeFalse()
        ->and($chatOpen($shop, '2026-09-23 13:35'))->toBeFalse()
        ->and($chatOpen($shop, '2026-09-23 11:00'))->toBeTrue();

    $config = GetChatConfig::run($this->web->fresh());
    expect($config['is_online'])->toBeFalse()
        ->and($config['schedule']['start'])->toBe('10:45')
        ->and($config['schedule']['end'])->toBe('13:30')
        ->and($config['offline_info']['next_opening']['start'])->toBe('10:45');

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($shop, ['chat_hours_start' => null]);
    expect($chatOpen($shop->fresh(), '2026-09-23 10:30'))->toBeTrue();

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($shop->fresh(), ['chat_hours_end' => null]);
    \Illuminate\Support\Carbon::setTestNow();
    outOfHoursTestCleanUp($schedule);
});

test('chat hours come from the work schedule, and the next opening skips closed days and bank holidays', function () {
    $schedule = outOfHoursTestSchedule($this->shop);
    $shop = $this->shop->fresh();

    $at = fn (string $when) => \Illuminate\Support\Carbon::parse($when, 'Europe/London');

    expect(IsWithinWorkingHours::run($shop, $at('2026-09-23 10:30')))->toBeTrue()
        ->and(IsWithinWorkingHours::run($shop, $at('2026-09-23 09:30')))->toBeFalse()
        ->and(IsWithinWorkingHours::run($shop, $at('2026-09-23 15:00')))->toBeFalse();

    $next = fn (string $when) => IsWithinWorkingHours::make()->nextOpening($shop, $at($when))['opens']->format('Y-m-d H:i');

    expect($next('2026-09-23 07:00'))->toBe('2026-09-23 10:00')
        ->and($next('2026-09-23 15:00'))->toBe('2026-09-24 10:00')
        ->and($next('2026-09-25 15:00'))->toBe('2026-09-28 10:00');

    $holiday = $this->organisation->holidays()->create([
        'group_id' => $this->organisation->group_id,
        'type'     => \App\Enums\HumanResources\Holiday\HolidayTypeEnum::PUBLIC->value,
        'year'     => 2026,
        'label'    => 'Monday bank holiday',
        'from'     => '2026-09-28',
        'to'       => '2026-09-28',
    ]);

    expect($next('2026-09-25 15:00'))->toBe('2026-09-29 10:00');

    \Illuminate\Support\Carbon::setTestNow($at('2026-09-25 15:00'));
    $this->web->update(['settings' => array_merge($this->web->settings ?? [], ['enable_chat' => true])]);

    $config = GetChatConfig::run($this->web->fresh());

    expect($config['is_online'])->toBeFalse()
        ->and($config['offline_info']['next_opening']['day_of_week'])->toBe(2)
        ->and($config['offline_info']['next_opening']['start'])->toBe('10:00');

    \App\Actions\SysAdmin\Organisation\UpdateOrganisation::make()->action($this->organisation, ['time_format' => '12h_short']);
    expect(GetChatConfig::run($this->web->fresh())['offline_info']['next_opening']['start'])->toBe('10am');

    \App\Actions\SysAdmin\Organisation\UpdateOrganisation::make()->action($this->organisation->fresh(), ['time_format' => '12h']);
    expect(GetChatConfig::run($this->web->fresh())['offline_info']['next_opening']['start'])->toBe('10:00 am')
        ->and($this->organisation->fresh()->formatClockTime(\Illuminate\Support\Carbon::parse('16:30')))->toBe('4:30 pm');

    \App\Actions\SysAdmin\Organisation\UpdateOrganisation::make()->action($this->organisation->fresh(), ['time_format' => '24h']);

    $closedLine = fn (bool $saidWhatTheyNeed) => \App\Actions\Chat\ChatSession\SendOutOfHoursReply::make()->text($shop, true, null, $saidWhatTheyNeed);

    expect($closedLine(true))->toBe('Thank you for your message. We are closed at the moment and will reply from 10am on Tuesday.');

    \Illuminate\Support\Carbon::setTestNow($at('2026-09-28 11:00'));

    expect($closedLine(true))->toBe('Thank you for your message. We are closed for Monday bank holiday and will reply from 10am tomorrow.')
        ->and($closedLine(false))->toEndWith('Please tell us how we can help and we will pick it up first thing.')
        ->and(__('Thank you for your message. We are closed for :holiday and will reply :when.', ['holiday' => 'Navidad', 'when' => 'mañana'], 'es'))->toStartWith('Gracias');

    $holiday->delete();
    outOfHoursTestCleanUp($schedule);
});

test('a WhatsApp message out of hours is answered once per wait with when the shop opens', function () {
    config(['chat.out_of_hours_reply' => true]);
    $schedule = outOfHoursTestSchedule($this->shop);
    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['whatsapp' => ['phone_number_id' => '123']])]);
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['meta' => ['access_key' => 'token']])]);

    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'wamid.closed']]])]);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-26 11:00', 'Europe/London'));

    $session = noiseTestWhatsappSession($this->shop->fresh(), '+447500000003', 'Do you have the lavender oil in stock?');
    $reply   = \App\Actions\Chat\ChatSession\SendOutOfHoursReply::make();

    expect($reply->handle($session))->toBeTrue()
        ->and($reply->handle($session))->toBeFalse();

    $closed = $session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->sole();

    expect($closed->message_text)->toContain('will reply from 10am on Monday.')
        ->and($session->refresh()->last_agent_message_at)->toBeNull();

    // An agent answers just before closing: still taken to be there half an hour later.
    $session->update(['last_agent_message_at' => \Illuminate\Support\Carbon::parse('2026-09-28 13:50', 'Europe/London')]);

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-28 13:30', 'Europe/London'));
    expect($reply->handle($session))->toBeFalse();

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-28 14:20', 'Europe/London'));
    expect($reply->handle($session))->toBeFalse();

    // The customer writes again that night: a new wait, a new reply.

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-28 20:00', 'Europe/London'));
    $session->messages()->create([
        'meta_channel_id' => $session->meta_channel_id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => 'And the rose one?',
    ]);
    expect($reply->handle($session))->toBeTrue();

    \Illuminate\Support\Facades\Http::assertSentCount(2);

    outOfHoursTestCleanUp($schedule);
});

test('the widget goes offline on a bank holiday even though the week says open', function () {
    $tz = \App\Models\Helpers\Timezone::where('name', 'Europe/London')->first();
    $this->shop->update(['timezone_id' => $tz->id, 'opening_hours' => []]);
    $this->web->update(['settings' => array_merge($this->web->settings ?? [], ['enable_chat' => true])]);
    $this->shop->workSchedules()->update(['is_active' => false]);

    $schedule = \App\Models\HumanResources\WorkSchedule::create([
        'name'             => 'Widget cover',
        'schedulable_type' => 'Shop',
        'schedulable_id'   => $this->shop->id,
        'timezone_id'      => $tz->id,
        'type'             => 'default',
        'is_active'        => true,
    ]);

    foreach (range(1, 7) as $dayOfWeek) {
        $schedule->days()->create([
            'day_of_week'    => $dayOfWeek,
            'is_working_day' => true,
            'start_time'     => '00:00:00',
            'end_time'       => '23:59:00',
        ]);
    }

    $shop = $this->shop->fresh();
    $web  = $this->web->fresh();

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-23 09:00', 'Europe/London'));
    expect(GetChatConfig::run($web)['is_online'])->toBeTrue();

    $holiday = $this->organisation->holidays()->create([
        'group_id' => $this->organisation->group_id,
        'type'     => \App\Enums\HumanResources\Holiday\HolidayTypeEnum::PUBLIC->value,
        'year'     => 2026,
        'label'    => 'Widget bank holiday',
        'from'     => '2026-09-23',
        'to'       => '2026-09-23',
    ]);

    $config = GetChatConfig::run($web);

    expect($config['is_online'])->toBeFalse()
        ->and($config['offline_info'])->not->toBeNull()
        ->and($shop->workSchedules()->where('is_active', true)->first()->isOpenNow('Europe/London'))->toBeTrue();

    // An always-open schedule left behind makes the shop open for every later test, and the
    // out-of-hours reply then never fires.
    $holiday->delete();
    $schedule->days()->delete();
    $schedule->delete();
    \Illuminate\Support\Carbon::setTestNow();
});

test('the reply promise says when the shop opens, turns overdue an hour later, and is kept when an agent answers in time', function () {
    config(['chat.out_of_hours_reply' => true]);
    fakeActionJobs(ProcessChatMessageSideEffects::class, TranslateChatMessage::class);
    $schedule = outOfHoursTestSchedule($this->shop);
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-24 15:30', 'Europe/London'));

    $waiting = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::WAITING,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'last_visitor_message_at' => now(),
    ]);
    $keptCase = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::WAITING,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'last_visitor_message_at' => now(),
    ]);

    $reply = \App\Actions\Chat\ChatSession\SendOutOfHoursReply::make();
    expect($reply->handle($waiting))->toBeTrue()
        ->and($reply->handle($keptCase))->toBeTrue();

    $promised = \App\Actions\Chat\ChatSession\GetChatReplyPromise::run($waiting->refresh());
    expect($promised)->not->toBeNull()->and($promised->format('Y-m-d H:i'))->toBe('2026-09-25 10:00');

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-25 10:30', 'Europe/London'));
    expect(\App\Actions\Chat\ChatSession\GetChatReplyPromise::forList($waiting)['overdue'])->toBeFalse();

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-25 11:30', 'Europe/London'));
    expect(\App\Actions\Chat\ChatSession\GetChatReplyPromise::forList($waiting)['overdue'])->toBeTrue();

    $olderPlain = ChatSession::create([
        'ulid'                    => (string) Str::ulid(),
        'status'                  => ChatSessionStatusEnum::WAITING,
        'channel'                 => ChatChannelEnum::WEBSITE,
        'shop_id'                 => $this->shop->id,
        'last_visitor_message_at' => \Illuminate\Support\Carbon::parse('2026-09-20 09:00'),
    ]);
    ChatMessage::create([
        'chat_session_id' => $olderPlain->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => 'Asked long before anybody promised anything',
        'created_at'      => \Illuminate\Support\Carbon::parse('2026-09-20 09:00'),
    ]);

    $queue = collect(\App\Actions\Chat\ChatSession\GetChatSessions::make()->handle(['statuses' => ['waiting'], 'allowed_shop_ids' => [$this->shop->id]])->items())->pluck('id');
    expect($queue->search($olderPlain->id))->toBeLessThan($queue->search($waiting->id));

    $promisedList = collect(\App\Actions\Chat\ChatSession\GetChatSessions::make()->handle(['statuses' => ['waiting', 'active'], 'promised' => true, 'allowed_shop_ids' => [$this->shop->id]])->items())->pluck('id');
    expect($promisedList)->toContain($waiting->id)
        ->and($promisedList)->toContain($keptCase->id)
        ->and($promisedList)->not->toContain($olderPlain->id);

    $waiting->update(['status' => ChatSessionStatusEnum::CLOSED]);
    expect(\App\Actions\Chat\ChatSession\GetChatReplyPromise::forList($waiting->refresh()))->toBeNull();
    $waiting->update(['status' => ChatSessionStatusEnum::WAITING]);

    $waiting->update(['last_agent_message_at' => now()]);
    expect(\App\Actions\Chat\ChatSession\GetChatReplyPromise::run($waiting->refresh()))->toBeNull();
    expect(collect(\App\Actions\Chat\ChatSession\GetChatSessions::make()->handle(['statuses' => ['waiting', 'active'], 'promised' => true, 'allowed_shop_ids' => [$this->shop->id]])->items())->pluck('id'))
        ->not->toContain($waiting->id);

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-25 10:20', 'Europe/London'));
    ChatMessage::create([
        'chat_session_id' => $keptCase->id,
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::AGENT,
        'message_text'    => 'Good morning, here is the update',
    ]);

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-25 12:00', 'Europe/London'));
    actingAs($this->user);
    $dashboard = get(route('grp.chat.ai.dashboard'))->assertOk()->viewData('page')['props']['dashboard'];

    expect($dashboard['promises']['made'])->toBeGreaterThanOrEqual(2)
        ->and($dashboard['promises']['kept'])->toBeGreaterThanOrEqual(1);

    outOfHoursTestCleanUp($schedule, [$waiting, $keptCase, $olderPlain]);
});
