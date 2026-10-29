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
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Actions\Chat\ChatSession\RedactChatMessage;
use Illuminate\Support\Facades\Route;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\HumanResources\Employee;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Catalogue\Product;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
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

    \Illuminate\Support\Facades\Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
});

test('starting an email from the customer record opens an email conversation and keeps the thread', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);

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

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return !isset($request['threadId'])
            && str_contains($raw, 'To: ')
            && str_contains($raw, 'buyer@example.com')
            && str_contains($raw, 'Subject: Your order')
            && str_contains($raw, 'Message-ID: <')
            && !str_contains($raw, 'In-Reply-To:');
    });

    $firstMessageId = Arr::get($session->fresh()->metadata, 'gmail_last_header_message_id');
    expect(Arr::get($session->fresh()->metadata, 'gmail_thread_id'))->toBe('t9')
        ->and($firstMessageId)->toStartWith('<');

    $second = $session->messages()->create([
        'message_text' => 'Just checking in',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
        'sender_id'    => $session->assignments()->first()->chat_agent_id,
    ]);
    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($second);

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($firstMessageId) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return ($request['threadId'] ?? null) === 't9'
            && str_contains($raw, 'Subject: Re: Your order')
            && str_contains($raw, "In-Reply-To: {$firstMessageId}")
            && str_contains($raw, "References: {$firstMessageId}");
    });
    expect(Arr::get($session->fresh()->metadata, 'gmail_references'))->toHaveCount(2);
});

test('emailing the customer from an order stays on the order and offers its out-of-stock lines', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);

    $shop              = $this->customer->shop;
    $settings          = $shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $shop->update(['settings' => $settings]);
    $this->customer->update(['email' => 'buyer@example.com']);
    $this->customer->refresh();

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent10', 'threadId' => 't10']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $product     = Product::where('shop_id', $shop->id)->first() ?? createProduct($shop)[1];
    $order       = createOrder($this->customer, $product);
    $transaction = $order->transactions()->where('model_type', 'Product')->orderBy('id')->firstOrFail();
    $originalFail = $transaction->quantity_fail;
    $transaction->update(['quantity_fail' => 2]);

    $lines = \App\Actions\Ordering\Order\UI\ShowOrder::make()->getOutOfStockLines($order);
    expect(collect($lines)->firstWhere('code', $transaction->asset->code))->toMatchArray(['quantity_short' => 2.0]);

    $transaction->update(['quantity_fail' => $originalFail]);

    expect(\App\Actions\Chat\ChatSession\StartCustomerEmailChat::canBeStartedBy($this->user, $this->customer))->toBeTrue();

    $sessionsBefore = ChatSession::where('shop_id', $shop->id)->count();

    actingAs($this->user)
        ->from('/order-page')
        ->post(route('grp.models.order.email_chat.store', ['order' => $order->id]), [
            'email'   => 'buyer@example.com',
            'subject' => 'Your order '.$order->reference,
            'message' => 'One item is out of stock',
        ])
        ->assertRedirect('/order-page');

    $session = ChatSession::where('shop_id', $shop->id)->latest('id')->first();
    expect(ChatSession::where('shop_id', $shop->id)->count())->toBe($sessionsBefore + 1)
        ->and(Arr::get($session->metadata, 'email_from'))->toBe('buyer@example.com')
        ->and(Arr::get($session->metadata, 'email_subject'))->toBe('Your order '.$order->reference);
});

test('a new email from the customer record carries the files the agent attached', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);

    actingAs($this->user);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);
    $this->customer->update(['email' => 'buyer@example.com']);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent10', 'threadId' => 't10']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->customer->fresh(), [
        'subject'     => 'Facture',
        'message'     => 'Please find your invoice attached',
        'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')],
    ]);

    expect($session->messages()->first()->attachedFiles())->toHaveCount(1);

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }

        return str_contains(base64_decode(strtr($request['raw'], '-_', '+/')), 'filename="invoice.pdf"');
    });
});

test('a new email can carry Word documents, zip files and more than ten files', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);

    actingAs($this->user);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);
    $this->customer->update(['email' => 'buyer@example.com']);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent12', 'threadId' => 't12']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $attachments = [
        \Illuminate\Http\UploadedFile::fake()->create('ingredients.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        \Illuminate\Http\UploadedFile::fake()->create('artwork.zip', 10, 'application/zip'),
    ];
    foreach (range(1, 10) as $index) {
        $attachments[] = \Illuminate\Http\UploadedFile::fake()->create("sds-$index.pdf", 10, 'application/pdf');
    }

    $session = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->customer->fresh(), [
        'subject'     => 'Product documents',
        'message'     => 'Please find the documents attached',
        'attachments' => $attachments,
    ]);

    expect($session->messages()->first()->attachedFiles())->toHaveCount(12);

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return str_contains($raw, 'filename="ingredients.docx"') && str_contains($raw, 'filename="artwork.zip"');
    });
});

test('a new email to several addresses goes to the first and copies the rest', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);

    actingAs($this->user);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent11', 'threadId' => 't11']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->shop, [
        'email'   => 'one@supplier.example.com; two@supplier.example.com, three@supplier.example.com',
        'subject' => 'Samples',
        'message' => 'Hello all',
    ]);

    expect(Arr::get($session->metadata, 'email_from'))->toBe('one@supplier.example.com')
        ->and(array_keys(Arr::get($session->metadata, 'email_participants')))->toBe(['two@supplier.example.com', 'three@supplier.example.com']);

    expect($session->is_carrier)->toBeFalse();

    $courierDomain = \App\Actions\Comms\Mailbox\ProcessInboundEmail::carrierDomains($this->shop->group)[0];
    $toCourier     = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->shop, [
        'email'   => "customerrelations@$courierDomain",
        'subject' => 'Second parcel',
        'message' => 'When will it arrive?',
    ]);

    expect($toCourier->is_carrier)->toBeTrue();

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return str_contains($raw, 'To: one@supplier.example.com')
            && str_contains($raw, 'two@supplier.example.com')
            && str_contains($raw, 'three@supplier.example.com')
            && str_contains($raw, 'Cc: ');
    });

    expect(fn () => \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->shop, [
        'email'   => 'one@supplier.example.com, not-an-address',
        'subject' => 'Samples',
        'message' => 'Hello all',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('a new email can go to an address the customer does not have on file, or to somebody saved there and then as a prospect', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);

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
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent10', 'threadId' => 't10']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->customer, [
        'email'   => 'hello@colleague.example.com',
        'subject' => 'Your spare boxes',
        'message' => 'They go out today',
    ]);

    expect(Arr::get($session->metadata, 'email_from'))->toBe('hello@colleague.example.com')
        ->and(Arr::get($session->metadata, 'email_from_name'))->toBeNull()
        ->and($session->suggested_customer_id)->toBe($session->web_user_id ? $this->customer->id : null)
        ->and($session->suggestion_basis)->toBe($session->web_user_id ? \App\Actions\Chat\ChatSession\SuggestChatSessionCustomer::BASIS_MANUAL : null);

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return str_contains($raw, 'hello@colleague.example.com') && !str_contains($raw, 'buyer@example.com');
    });

    $toStranger = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->shop, [
        'email'            => 'owner@newshop.example.com',
        'subject'          => 'Wholesale',
        'message'          => 'Our trade prices',
        'save_as_prospect' => true,
        'contact_name'     => 'New Owner',
        'company_name'     => 'New Shop',
    ]);

    $prospect = \App\Models\CRM\Prospect::where('shop_id', $this->shop->id)->where('email', 'owner@newshop.example.com')->first();

    expect($toStranger->web_user_id)->toBeNull()
        ->and(Arr::get($toStranger->metadata, 'email_from'))->toBe('owner@newshop.example.com')
        ->and(Arr::get($toStranger->metadata, 'email_from_name'))->toBe('New Owner')
        ->and($prospect)->not->toBeNull()
        ->and($prospect->company_name)->toBe('New Shop')
        ->and($prospect->user_id)->toBe($this->user->id);

    \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->shop, [
        'email'            => 'owner@newshop.example.com',
        'subject'          => 'Wholesale again',
        'message'          => 'Following up',
        'save_as_prospect' => true,
    ]);
    expect(\App\Models\CRM\Prospect::where('shop_id', $this->shop->id)->where('email', 'owner@newshop.example.com')->count())->toBe(1);
    expect(\App\Actions\CRM\Prospect\Json\GetProspectByEmail::make()->handle($this->shop, 'owner@newshop.example.com'))
        ->toMatchArray(['name' => 'New Owner', 'company_name' => 'New Shop', 'owner' => $this->user->contact_name])
        ->and(\App\Actions\CRM\Prospect\Json\GetProspectByEmail::make()->handle($this->shop, 'nobody@example.com'))->toBeNull();

    $toKnownCustomer = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->shop, [
        'email'   => 'buyer@example.com',
        'subject' => 'Hello',
        'message' => 'Typed the address of a customer',
    ]);
    expect(Arr::get($toKnownCustomer->metadata, 'email_from_name'))->toBe($this->customer->contact_name ?? $this->customer->name);

    expect(fn () => \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->customer, [
        'email'   => 'not an address',
        'subject' => 'Your spare boxes',
        'message' => 'They go out today',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('the customer picker for a new email only offers customers with an address', function () {
    createCustomer($this->shop)->update(['email' => 'reachable@example.com']);
    createCustomer($this->shop)->update(['email' => null]);

    actingAs($this->user);

    $total = function (array $filters) {
        $response = $this->get(route('grp.json.shop.customers', ['shop' => $this->shop->id] + $filters));
        $response->assertOk();

        return $response->json('total');
    };

    $withoutEmail = \App\Models\CRM\Customer::where('shop_id', $this->shop->id)
        ->where(fn ($query) => $query->whereNull('email')->orWhere('email', ''))
        ->count();

    expect($withoutEmail)->toBeGreaterThan(0)
        ->and($total([]) - $total(['filter[has_email]' => 1]))->toBe($withoutEmail);
});

test('searching the new email customer picker by an email does not match phones on the digits inside it', function () {
    $owner = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $owner->update(['email' => 'picker.tommy68@example.com', 'phone' => '+44 7700 900111']);
    $phoneOnDigits = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $phoneOnDigits->update(['email' => 'unrelated.picker@example.com', 'phone' => '+689 87 12 34 56']);

    actingAs($this->user);

    $ids = fn (string $search) => collect($this->get(route('grp.json.shop.customers', [
        'shop'           => $this->shop->id,
        'filter[global]' => $search,
    ]))->assertOk()->json('data'))->pluck('id');

    expect($ids('picker.tommy68@example.com')->all())->toBe([$owner->id])
        ->and($ids('68987')->all())->toContain($phoneOnDigits->id);
});

test('the new email customer picker lists word-start matches before names that only contain the search', function () {
    $startsWithSearch = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $startsWithSearch->update(['name' => 'Older Shop', 'email' => 'zqpicker@example.com']);
    $containsSearch = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $containsSearch->update(['name' => 'Pickerzq Holisticszq', 'email' => 'contains.zq@example.com']);

    actingAs($this->user);

    $ids = collect($this->get(route('grp.json.shop.customers', [
        'shop'           => $this->shop->id,
        'filter[global]' => 'zq',
    ]))->assertOk()->json('data'))->pluck('id')->all();

    expect(array_search($startsWithSearch->id, $ids))->toBeLessThan(array_search($containsSearch->id, $ids));
});

test('the customer picker searches a name typed with a comma or sent as a list instead of failing', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $customer->update(['name' => 'Commaqx, Janeqx', 'email' => 'comma.picker@example.com']);

    actingAs($this->user);

    $ids = fn (array $filter) => collect($this->get(route('grp.json.shop.customers', ['shop' => $this->shop->id] + $filter))
        ->assertOk()->json('data'))->pluck('id')->all();

    expect($ids(['filter[global]' => 'Commaqx, Janeqx']))->toContain($customer->id)
        ->and($ids(['filter[global][]' => 'Commaqx']))->toContain($customer->id)
        ->and($ids(['filter[phone]' => '0770,0900']))->toBeArray();
});

test('an email reply does not also send a chat notification, and carries the shop sender name and an html signature', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $notificationsSent = fn () => collect(\Illuminate\Support\Facades\Queue::pushedJobs())
        ->flatten(1)
        ->pluck('job')
        ->filter(fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator
            && ($job->decorates(\App\Actions\Comms\Email\SendChatNotificationToCustomer::class)
                || $job->decorates(\App\Actions\Comms\Email\SendChatNotificationToExternal::class)))
        ->count();

    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $webUser  = StoreWebUser::make()->action($customer, array_merge(WebUser::factory()->definition(), ['email' => 'writer@example.com']));

    $agentUser = createAdminGuest($this->organisation->group)->getUser();
    $agent     = ChatAgent::updateOrCreate(['user_id' => $agentUser->id], [
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'signature'            => '<p>Kind regards,<br>Sig Agent</p><p><img src="https://media.aiku.io/logo.png" alt="logo"></p>',
    ]);

    $newSession = fn (ChatChannelEnum $channel) => ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'web_user_id' => $webUser->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
        'channel'     => $channel,
    ]);

    $agentReply = fn (ChatSession $session, string $text) => SendChatMessage::make()->handle($session, [
        'message_text'   => $text,
        'message_type'   => ChatMessageTypeEnum::TEXT->value,
        'sender_type'    => ChatSenderTypeEnum::AGENT->value,
        'sender_id'      => $agent->id,
        'is_email_notif' => true,
    ]);

    // A live chat still needs telling there is an answer waiting.
    $agentReply($newSession(ChatChannelEnum::WEBSITE), 'Answered on the website');
    expect($notificationsSent())->toBe(1);

    // The email reply is the notification: a second mail would reach the same inbox twice.
    $emailSession = $newSession(ChatChannelEnum::EMAIL);
    $emailSession->update(['metadata' => ['email_from' => 'writer@example.com', 'email_subject' => 'A question']]);

    $reply = $agentReply($emailSession, 'Answered by email');

    expect($notificationsSent())->toBe(1);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'sender_name'   => 'AW Artisan France',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent-html']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply->fresh());

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }

        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        preg_match_all('/Content-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n--/s', $raw.'\r\n--', $matches);
        $parts = array_map(fn ($part) => base64_decode($part), $matches[1]);

        return str_contains($raw, 'From: AW Artisan France <care@shop.test>')
            && str_contains($raw, 'Content-Type: multipart/alternative')
            && collect($parts)->contains(fn ($part) => str_contains($part, "Answered by email\n\nKind regards,\nSig Agent") && !str_contains($part, '<img'))
            && collect($parts)->contains(fn ($part) => str_contains($part, '<img src="https://media.aiku.io/logo.png"'));
    });
});

test('formatting an agent picks reaches the customer as html beside the readable text', function () {
    $markupToHtml = fn (string $text) => \App\Actions\Comms\Mailbox\SendChatMessageByGmail::markupToHtml($text);

    expect($markupToHtml("*Bold* _italic_ __under__ ~gone~\n• one"))->toBe("<strong>Bold</strong> <em>italic</em> <u>under</u> <s>gone</s><br />\n• one")
        ->and($markupToHtml('snake_case_name costs 2*3*4'))->toBe('snake_case_name costs 2*3*4')
        ->and($markupToHtml('*<script>alert(1)</script>*'))->toBe('<strong>&lt;script&gt;alert(1)&lt;/script&gt;</strong>')
        ->and($markupToHtml('See https://shop.test/a_b_c?x=1&y=2.'))->toBe('See <a href="https://shop.test/a_b_c?x=1&amp;y=2" target="_blank" rel="noopener noreferrer">https://shop.test/a_b_c?x=1&amp;y=2</a>.')
        ->and($markupToHtml('*visit www.shop.test/p*'))->toBe('<strong>visit <a href="https://www.shop.test/p" target="_blank" rel="noopener noreferrer">www.shop.test/p</a></strong>')
        ->and($markupToHtml('https://shop.test/"onclick=1'))->toBe('<a href="https://shop.test/" target="_blank" rel="noopener noreferrer">https://shop.test/</a>&quot;onclick=1');

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent-formatted']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $agentUser = createAdminGuest($this->organisation->group)->getUser();
    $agent     = ChatAgent::updateOrCreate(['user_id' => $agentUser->id], ['max_concurrent_chats' => 5, 'language_id' => 68, 'signature' => "Kind regards,\nSig Agent"]);

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
        'channel'     => ChatChannelEnum::EMAIL,
        'metadata'    => ['email_from' => 'buyer@example.com', 'email_subject' => 'Order'],
    ]);

    $reply = $session->messages()->create([
        'message_text' => "Your order is *on its way*\n• Tracking: _AB123_",
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

        preg_match_all('/Content-Type: (text\/\w+); charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n--/s', $raw, $matches);
        $parts = array_combine($matches[1], array_map(fn ($part) => base64_decode($part), $matches[2]));

        return str_contains($raw, 'Content-Type: multipart/alternative')
            && $parts['text/plain'] === "Your order is *on its way*\n• Tracking: _AB123_\n\nKind regards,\nSig Agent"
            && $parts['text/html'] === "Your order is <strong>on its way</strong><br />\n• Tracking: <em>AB123</em><br><br>Kind regards,<br />\nSig Agent";
    });
});

test('a picture pasted into an email reply goes inside the body where it was pasted, not as an attachment', function () {
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sent-pasted']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
        'channel'     => ChatChannelEnum::EMAIL,
        'metadata'    => ['email_from' => 'buyer@example.com', 'email_subject' => 'Damaged'],
    ]);

    $reply = $session->messages()->create([
        'message_text' => "See the box:\n[imagen: pasted-1]\nRef [Note: A12]\n[image:unknown-9]",
        'message_type' => ChatMessageTypeEnum::IMAGE,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
    ]);

    $png   = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $files = collect(['box.png', 'label.png', 'invoice.png'])->map(function (string $name) use ($png) {
        $path = tempnam(sys_get_temp_dir(), 'pic');
        file_put_contents($path, $png.$name);

        return new \Illuminate\Http\UploadedFile($path, $name, 'image/png', null, true);
    })->all();
    $files[] = \Illuminate\Http\UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf');

    \App\Actions\Chat\ChatSession\SendChatMessage::make()->processMessageAttachments($reply, $files, ['pasted-1', 'pasted-2', null, 'pasted-3']);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply->fresh());

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }

        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        preg_match_all('/Content-Type: (text\/\w+); charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n--/s', $raw, $matches);
        $parts = array_combine($matches[1], array_map(fn ($part) => base64_decode($part), $matches[2]));

        $picture = '<div><img src="cid:%s@aiku.io" alt="" style="max-width:100%%;height:auto"></div>';

        return str_contains($raw, 'Content-Type: multipart/mixed')
            && str_contains($raw, 'Content-Type: multipart/related')
            && str_contains($raw, "Content-Disposition: inline; filename=\"box.png\"\r\nContent-ID: <pasted-1@aiku.io>")
            && str_contains($raw, "Content-Disposition: inline; filename=\"label.png\"\r\nContent-ID: <pasted-2@aiku.io>")
            && str_contains($raw, 'Content-Disposition: attachment; filename="invoice.png"')
            && str_contains($raw, 'Content-Disposition: attachment; filename="spec.pdf"')
            && ! str_contains($raw, 'attachment; filename="box.png"')
            && ! str_contains($raw, 'cid:pasted-3')
            && $parts['text/plain'] === "See the box:\nRef [Note: A12]"
            && $parts['text/html'] === "See the box:<br />\n".sprintf($picture, 'pasted-1')."\nRef [Note: A12]<br />\n<br>".sprintf($picture, 'pasted-2');
    });

    $picture = '<div><img src="cid:%s@aiku.io" alt="" style="max-width:100%%;height:auto"></div>';
    $reply->refresh();

    expect($reply->html_body)->toBe("See the box:<br />\n".sprintf($picture, 'pasted-1')."\nRef [Note: A12]<br />\n<br>".sprintf($picture, 'pasted-2'))
        ->and(Arr::get($reply->metadata, 'email_to'))->toBe([['address' => 'buyer@example.com', 'name' => null]]);

    $shown = \App\Http\Resources\CRM\Livechat\ChatMessageResource::make($reply)->resolve();

    expect($shown['html_body'])->not->toContain('cid:')
        ->and(substr_count($shown['html_body'], '<img'))->toBe(2)
        ->and(collect($shown['attachments'])->pluck('is_inline')->all())->toBe([true, true, false, false]);
});

test('an inline picture in an inbound email is shown inside the body where the sender put it', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $png    = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                   => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/c1*' => \Illuminate\Support\Facades\Http::response([
            'id'       => 'c1',
            'threadId' => 'tc1',
            'payload'  => [
                'mimeType' => 'multipart/related',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Daryl <daryl@example.com>'],
                    ['name' => 'Subject', 'value' => 'Re: Thank you for your order'],
                ],
                'parts'    => [
                    ['mimeType' => 'text/plain', 'filename' => '', 'body' => ['data' => $encode('It is broken, see picture below')]],
                    ['mimeType' => 'text/html', 'filename' => '', 'body' => ['data' => $encode('<p>It is broken, see picture below</p><img src="cid:broken@mail">')]],
                    [
                        'mimeType' => 'image/png',
                        'filename' => 'broken.png',
                        'headers'  => [
                            ['name' => 'Content-Disposition', 'value' => 'inline; filename="broken.png"'],
                            ['name' => 'Content-ID', 'value' => '<broken@mail>'],
                        ],
                        'body'     => ['data' => $encode($png), 'size' => 9000],
                    ],
                ],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels' => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                        => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'c1');
    $media   = $message->attachedFiles()->first();

    // The body keeps the address the mail was written with. Where the file is served from
    // changes over its life, so no url is ever frozen into what is stored.
    expect($media?->name)->toBe('broken.png')
        ->and($media->getCustomProperty('content_id'))->toBe('broken@mail')
        ->and($message->html_body)->toContain('src="cid:broken@mail"')
        ->and($message->html_body)->not->toContain('/storage/');

    $resource = \App\Http\Resources\CRM\Livechat\ChatMessageResource::make($message->fresh())->resolve();
    $picture  = $resource['attachments'][0]['media_url']['original'];

    expect($picture)->toStartWith('http')
        ->and($resource['html_body'])->toContain('src="'.e($picture).'"')
        ->and($resource['html_body'])->not->toContain('cid:')
        ->and($resource['html_body'])->not->toContain('/storage/')
        ->and($resource['attachments'][0]['is_inline'])->toBeTrue();
});

test('a small inline picture in an inbound email is written into the body instead of being lost', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);

    $settings = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $png    = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                               => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/c2/attachments/att1' => \Illuminate\Support\Facades\Http::response(['data' => $encode($png)]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/c2*'             => \Illuminate\Support\Facades\Http::response([
            'id'       => 'c2',
            'threadId' => 'tc2',
            'payload'  => [
                'mimeType' => 'multipart/related',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Montana <montana@example.com>'],
                    ['name' => 'Subject', 'value' => 'Two invoices missing'],
                ],
                'parts'    => [
                    ['mimeType' => 'text/plain', 'filename' => '', 'body' => ['data' => $encode('Here are the two invoice numbers')]],
                    ['mimeType' => 'text/html', 'filename' => '', 'body' => ['data' => $encode('<p>Here are the two invoice numbers</p><img src="cid:shot@open-xchange.com">')]],
                    [
                        'mimeType' => 'image/png',
                        'filename' => 'image.png',
                        'headers'  => [
                            ['name' => 'Content-Disposition', 'value' => 'inline; filename="image.png"'],
                            ['name' => 'Content-ID', 'value' => '<shot@open-xchange.com>'],
                        ],
                        'body'     => ['attachmentId' => 'att1', 'size' => 1652],
                    ],
                ],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels' => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                        => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'c2');

    expect($message->attachedFiles())->toBeEmpty()
        ->and($message->html_body)->toContain('src="data:image/png;base64,'.base64_encode($png).'"')
        ->and($message->html_body)->not->toContain('cid:');
});

test('an email picture is pointed at wherever its file is served from when the message is shown', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);
    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'two pictures',
    ]);

    $png   = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $files = collect(['first.png', 'second.png'])->map(function (string $name) use ($png) {
        $path = tempnam(sys_get_temp_dir(), 'pic');
        file_put_contents($path, $png.$name);

        return new \Illuminate\Http\UploadedFile($path, $name, 'image/png', null, true);
    })->all();

    \App\Actions\Chat\ChatSession\SendChatMessage::make()->processMessageAttachments($message, $files, ['one@mail', 'two@mail']);
    [$first, $second] = $message->fresh()->attachedFiles()->all();

    // Referenced in the other order from the files, one never fetched, one stored by path the way
    // mail was between 22 and 28 Sep 2026, and a remote logo left as the sender wrote it.
    $message->update(['html_body' => '<p>see below</p>'
        .'<img src="cid:two@mail" alt="cid:two@mail" />'
        .'<img src="cid:one@mail" alt="one" />'
        .'<img src="cid:never-fetched@mail" alt="cid:never-fetched@mail" />'
        .'<img src="/storage/AA/BB/XYZ/'.$first->file_name.'" alt="legacy" />'
        .'<img src="https://example.com/logo.png" alt="logo" />']);

    $sources = function () use ($message) {
        $resource = \App\Http\Resources\CRM\Livechat\ChatMessageResource::make($message->fresh())->resolve();
        preg_match_all('/src="([^"]*)"/', $resource['html_body'], $matches);

        return [array_map('html_entity_decode', $matches[1]), $resource];
    };
    $picture = fn ($media) => \App\Actions\Helpers\Images\GetPictureSources::run($media->getImage()->resize(0, 0))['original'];

    [$shown, $resource] = $sources();
    expect($shown)->toBe([$picture($second), $picture($first), $picture($first), 'https://example.com/logo.png'])
        ->and($resource['html_body'])->toContain('alt="second.png"')
        ->and(collect($resource['attachments'])->pluck('is_inline')->all())->toBe([true, true]);

    // Once archived the file is off the disk, and only the download route can still read it.
    $second->setCustomProperty('archived_at', now()->toISOString())->save();

    [$shown] = $sources();
    expect($shown[0])->toBe(route('grp.api.chats.chat.attachment.download', ['ulid' => $second->ulid, 'inline' => 1]));

    // Redacted: the file is gone, and so is the picture, rather than a broken frame in its place.
    $first->delete();

    [$shown] = $sources();
    expect($shown)->toBe([route('grp.api.chats.chat.attachment.download', ['ulid' => $second->ulid, 'inline' => 1]), 'https://example.com/logo.png']);
});

test('removing the photographs of an email also removes the small pictures written into its body', function () {
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => 'chat_redaction_unarchived'])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists chat_redaction_unarchived');

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
        'message_text'    => 'my card',
        'html_body'       => '<p>my card</p><img src="data:image/png;base64,iVBORw0KGgo=" alt="card" />',
    ]);

    expect(\App\Http\Resources\CRM\Livechat\ChatMessageResource::make($message)->resolve()['has_embedded_pictures'])->toBeTrue();

    $this->actingAs($clerk);
    RedactChatMessage::make()->handleAttachment($session, $message, $agent);

    $redacted = $message->fresh();
    expect($redacted->html_body)->toBe('<p>my card</p>')
        ->and($redacted->metadata['attachment_redacted_at'])->not->toBeNull();
});

test('a picture in an earlier mail of the thread is fetched on request and shown where it was written', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class);

    $original          = $this->shop->settings ?? [];
    $settings          = $original;
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $png    = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $mail   = fn (string $id, array $payload) => [
        'id'           => $id,
        'threadId'     => 't9',
        'internalDate' => '1758500000000',
        'payload'      => $payload + ['headers' => [
            ['name' => 'From', 'value' => 'Nicky <nicky@example.com>'],
            ['name' => 'Subject', 'value' => 'Order GB588056'],
            ['name' => 'Message-ID', 'value' => "<$id@example.com>"],
        ]],
    ];
    $earlier = $mail('old9', [
        'mimeType' => 'multipart/related',
        'parts'    => [
            ['mimeType' => 'text/plain', 'filename' => '', 'body' => ['data' => $encode('One is broken, see photo')]],
            ['mimeType' => 'text/html', 'filename' => '', 'body' => ['data' => $encode('<p>One is broken, see photo</p><img src="cid:photo@mail">')]],
            [
                'mimeType' => 'image/png',
                'filename' => 'photo.png',
                'headers'  => [
                    ['name' => 'Content-Disposition', 'value' => 'inline; filename="photo.png"'],
                    ['name' => 'Content-ID', 'value' => '<photo@mail>'],
                ],
                'body'     => ['attachmentId' => 'att9', 'size' => 9000],
            ],
        ],
    ]);
    $latest = $mail('m9', ['mimeType' => 'text/plain', 'body' => ['data' => $encode('Any news?')]]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                                   => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/old9/attachments/att9' => \Illuminate\Support\Facades\Http::response(['data' => $encode($png)]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/old9*'         => \Illuminate\Support\Facades\Http::response($earlier),
        'gmail.googleapis.com/gmail/v1/users/me/messages/m9*'           => \Illuminate\Support\Facades\Http::response($latest),
        'gmail.googleapis.com/gmail/v1/users/me/threads/t9*'            => \Illuminate\Support\Facades\Http::response(['messages' => [$earlier, $latest]]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'                 => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/*'                                        => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $message = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'm9');
    $history = ChatMessage::where('metadata->gmail_message_id', 'old9')->firstOrFail();

    expect($history->html_body)->toContain('src="cid:photo@mail"')
        ->and(Arr::get($history->metadata, 'gmail_pending_attachments'))->toBe(1);

    \App\Actions\Comms\Mailbox\ImportPendingGmailAttachments::make()->importMessage(
        \App\Services\Gmail\GmailClient::forShop($this->shop),
        $history
    );

    $resource = \App\Http\Resources\CRM\Livechat\ChatMessageResource::make($history->fresh())->resolve();

    expect($resource['attachments'])->toHaveCount(1)
        ->and($resource['attachments'][0]['is_inline'])->toBeTrue()
        ->and($resource['html_body'])->toContain('src="'.e($resource['attachments'][0]['media_url']['original']).'"');

    // Asked again, the picture already here is not fetched a second time.
    \App\Actions\Comms\Mailbox\ImportPendingGmailAttachments::make()->importMessage(
        \App\Services\Gmail\GmailClient::forShop($this->shop),
        $history->fresh()
    );
    expect($history->fresh()->attachedFiles())->toHaveCount(1)
        ->and($message)->not->toBeNull();

    $this->shop->update(['settings' => $original]);
});

test('forwarding a conversation to a colleague opens one staff thread and optionally mails them', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
        'metadata'         => ['email_subject' => 'Invoice for October'],
    ]);

    $session->messages()->create([
        'message_type' => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::GUEST->value,
        'message_text' => 'Who do I send the paperwork to?',
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

    $management = User::factory()->create(['group_id' => $this->organisation->group_id, 'email' => 'management@example.com']);
    $warehouse  = User::factory()->create(['group_id' => $this->organisation->group_id, 'email' => 'warehouse@example.com']);

    $conversation = \App\Actions\Chat\ChatSession\ForwardChatSessionToColleague::make()->handle($session, $agent, [
        'user_ids'   => [$management->id],
        'note'       => 'This one is for you to decide',
        'also_email' => true,
    ]);

    expect($conversation->context_type)->toBe('ChatSession')
        ->and($conversation->context_id)->toBe($session->id)
        ->and($conversation->participants->pluck('id'))->toContain($management->id, $user->id)
        ->and($conversation->messages()->first()->body)->toContain('This one is for you to decide')
        ->and($conversation->messages()->first()->body)->toContain('Invoice for October');

    $mailedTo = fn (string $address) => \Illuminate\Support\Facades\Notification::assertSentOnDemand(
        \App\Notifications\ForwardedChatSessionNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $address
    );

    $mailedTo('management@example.com');

    // Staff with no address on their login are mailed at the work email on their employee record;
    // the personal one is never used, and whoever has no work email is named back to the sender.
    $employeeOnly = User::factory()->create(['group_id' => $this->organisation->group_id, 'email' => null]);
    $employee     = Employee::factory()->create(['group_id' => $this->organisation->group_id, 'organisation_id' => $this->organisation->id, 'work_email' => 'alvaro@example.es', 'email' => 'alvaro@gmail.test']);
    \Illuminate\Support\Facades\DB::table('user_has_models')->insert(['group_id' => $this->organisation->group_id, 'organisation_id' => $this->organisation->id, 'user_id' => $employeeOnly->id, 'model_type' => 'Employee', 'model_id' => $employee->id]);
    $noAddress = User::factory()->create(['group_id' => $this->organisation->group_id, 'email' => null, 'contact_name' => 'Nobody Mailable']);

    $forward = \App\Actions\Chat\ChatSession\ForwardChatSessionToColleague::make();
    $forward->handle($session, $agent, ['user_ids' => [$employeeOnly->id, $noAddress->id], 'also_email' => true]);

    $mailedTo('alvaro@example.es');
    \Illuminate\Support\Facades\Notification::assertSentOnDemandTimes(\App\Notifications\ForwardedChatSessionNotification::class, 2);
    expect($forward->notEmailed)->toBe(['Nobody Mailable']);

    // The second colleague joins the thread that already exists instead of getting a bare new one,
    // and asking for no mail sends none.
    $again = \App\Actions\Chat\ChatSession\ForwardChatSessionToColleague::make()->handle($session, $agent, [
        'user_ids'   => [$warehouse->id],
        'also_email' => false,
    ]);

    expect($again->id)->toBe($conversation->id)
        ->and($again->messages()->count())->toBe(3)
        ->and($again->participants()->count())->toBe(5);

    \Illuminate\Support\Facades\Notification::assertNotSentTo($warehouse, \App\Notifications\ForwardedChatSessionNotification::class);

    $event = $session->chatEvents()->where('event_type', 'forward')->latest('id')->first();
    expect($event->payload['recipient_user_ids'])->toBe([$warehouse->id])
        ->and($event->payload['also_emailed'])->toBeFalse();

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $this->actingAs($clerk)
        ->postJson(route('grp.org.chat.agents.sessions.forward', [$this->organisation->slug, $session->ulid]), [
            'user_ids'   => [$management->id],
            'note'       => 'From the inbox',
            'also_email' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.staff_conversation_id', $conversation->id)
        ->assertJsonPath('data.not_emailed', []);
});

test('an agent can hand a conversation to another named agent', function () {
    $user = $this->user;

    actingAs($user);

    makeChatWorker($user, $this->shop);

    $agent = ChatAgent::firstOrCreate(
        ['user_id' => $user->id],
        [
            'is_online'            => true,
            'max_concurrent_chats' => 100,
            'current_chat_count'   => 0,
        ]
    );

    $colleagueUser = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $colleague     = ChatAgent::create([
        'user_id'              => $colleagueUser->id,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
        'max_concurrent_chats' => 100,
        'current_chat_count'   => 0,
    ]);

    $chatSession = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::WAITING->value,
        'guest_identifier' => 'guest_assign_other',
        'language_id'      => 68,
        'shop_id'          => $this->shop->id,
        'priority'         => ChatPriorityEnum::NORMAL,
        'ai_model_version' => 'default',
    ]);

    app(AssignChatToAgent::class)->assignToSelf($this->organisation, $chatSession->ulid);

    $chatSession->refresh();

    $response = app(AssignChatToAgent::class)->asController(
        $this->organisation->slug,
        $chatSession,
        Request::create('/', 'PATCH', ['agent_id' => $colleague->id, 'note' => 'This is yours'])
    );

    $data = $response->getData(true);

    expect($data['success'])->toBeTrue()
        ->and($data['data']['assigned_agent_id'])->toBe($colleague->id);

    // One owner at a time: the active row moves to the colleague, it is not duplicated.
    expect(ChatAssignment::where('chat_session_id', $chatSession->id)
        ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
        ->pluck('chat_agent_id')->all())->toBe([$colleague->id]);

    $event = $chatSession->chatEvents()->where('event_type', 'transfer_to_agent')->latest('id')->first();
    expect($event->payload['to_agent_id'])->toBe($colleague->id)
        ->and($event->payload['from_agent_id'])->toBe($agent->id);
});

test('an agent can give a conversation back to the queue', function () {
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

    \App\Models\Chat\ChatAssignment::create([
        'chat_session_id' => $session->id,
        'chat_agent_id'   => $agent->id,
        'status'          => \App\Enums\CRM\Livechat\ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => \App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    \App\Actions\Chat\ChatSession\ReleaseChatSession::make()->handle($session, $agent, 'Cannot answer in Slovak');

    // Waiting is what puts it back in front of the other agents, and no active assignment is left
    // holding it.
    expect($session->fresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and(\App\Models\Chat\ChatAssignment::where('chat_session_id', $session->id)
            ->where('status', \App\Enums\CRM\Livechat\ChatAssignmentStatusEnum::ACTIVE->value)->count())->toBe(0);

    $event = $session->chatEvents()->where('event_type', 'released')->latest('id')->first();
    expect($event->payload['from_agent_id'])->toBe($agent->id)
        ->and($event->payload['reason'])->toBe('agent_released')
        ->and($event->payload['note'])->toBe('Cannot answer in Slovak');

    // A closed conversation released by HR revoking someone's access stays closed: released is
    // not reopened.
    $closed = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::CLOSED->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    \App\Actions\Chat\ChatSession\ReleaseChatSession::make()->handle($closed, $agent, null, 'permission_revoked');

    expect($closed->fresh()->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($closed->chatEvents()->where('event_type', 'released')->first()->payload['reason'])->toBe('permission_revoked');
});

test('an email reply goes to whoever wrote last and copies the colleagues the thread named, less those unticked', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class, \App\Actions\Comms\Mailbox\ImportPendingGmailAttachments::class);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    $gmailMessage = fn (string $id, array $headers) => \Illuminate\Support\Facades\Http::response([
        'id'       => $id,
        'threadId' => 'cc-thread',
        'payload'  => [
            'mimeType' => 'text/plain',
            'headers'  => $headers,
            'body'     => ['data' => rtrim(strtr(base64_encode('About our order'), '+/', '-_'), '=')],
        ],
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                            => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/cc1*'   => $gmailMessage('cc1', [
            ['name' => 'From', 'value' => 'Anna Buyer <anna@bigaccount.test>'],
            ['name' => 'To', 'value' => 'Care <care@shop.test>'],
            ['name' => 'Cc', 'value' => '"Doe, Jane" <jane@bigaccount.test>, ops@bigaccount.test, Shop <hello@shop.test>'],
            ['name' => 'Subject', 'value' => 'Big order'],
            ['name' => 'Message-ID', 'value' => '<cc1@bigaccount.test>'],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/cc2*'   => $gmailMessage('cc2', [
            ['name' => 'From', 'value' => '"Doe, Jane" <jane@bigaccount.test>'],
            ['name' => 'To', 'value' => 'care@shop.test, Anna Buyer <anna@bigaccount.test>'],
            ['name' => 'Subject', 'value' => 'Re: Big order'],
            ['name' => 'Message-ID', 'value' => '<cc2@bigaccount.test>'],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'          => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send'   => \Illuminate\Support\Facades\Http::response(['id' => 'sent-cc']),
        'gmail.googleapis.com/*'                                 => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $firstMail = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'cc1');

    expect(Arr::only($firstMail->fresh()->metadata, ['email_from', 'email_to', 'email_cc']))->toEqual([
        'email_from' => ['address' => 'anna@bigaccount.test', 'name' => 'Anna Buyer'],
        'email_to'   => [['address' => 'care@shop.test', 'name' => 'Care']],
        'email_cc'   => [
            ['address' => 'jane@bigaccount.test', 'name' => 'Doe, Jane'],
            ['address' => 'ops@bigaccount.test', 'name' => null],
            ['address' => 'hello@shop.test', 'name' => 'Shop'],
        ],
    ]);

    $session = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'cc2')->chatSession->fresh();

    expect(Arr::get($session->metadata, 'email_reply_to'))->toBe('jane@bigaccount.test')
        ->and(array_keys(Arr::get($session->metadata, 'email_participants')))
        ->toEqualCanonicalizing(['anna@bigaccount.test', 'jane@bigaccount.test', 'ops@bigaccount.test']);

    $agentUser = createAdminGuest($this->organisation->group)->getUser();
    $agent     = ChatAgent::updateOrCreate(['user_id' => $agentUser->id], ['max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => false, 'is_available' => false, 'current_chat_count' => 0, 'signature' => null]);

    $session->update(['metadata' => array_merge($session->metadata, [
        'email_participants' => Arr::get($session->metadata, 'email_participants') + ['old@shop.test' => ['address' => 'old@shop.test', 'name' => null]],
    ])]);

    $reply = SendChatMessage::make()->handle($session, [
        'message_text'      => 'Sent today',
        'message_type'      => ChatMessageTypeEnum::TEXT->value,
        'sender_type'       => ChatSenderTypeEnum::AGENT->value,
        'sender_id'         => $agent->id,
        'email_cc_excluded' => ['OPS@bigaccount.test'],
    ]);

    expect(Arr::get($reply->fresh()->metadata, 'email_cc'))->toBe([['address' => 'anna@bigaccount.test', 'name' => 'Anna Buyer']]);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply->fresh());

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        if (! str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return str_contains($raw, 'To: "Doe, Jane" <jane@bigaccount.test>')
            && str_contains($raw, 'Cc: Anna Buyer <anna@bigaccount.test>')
            && ! str_contains($raw, 'ops@bigaccount.test');
    });

    $reply->refresh();
    expect($reply->html_body)->toBe('Sent today')
        ->and(Arr::get($reply->metadata, 'email_to'))->toBe([['address' => 'jane@bigaccount.test', 'name' => 'Doe, Jane']]);
});

test('an email reply to an accented domain goes to its punycode form, and one to an address no header can carry fails saying so', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class, \App\Actions\Comms\Mailbox\ImportPendingGmailAttachments::class);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                              => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/idn1*'    => \Illuminate\Support\Facades\Http::response([
            'id'       => 'idn1',
            'threadId' => 'idn-thread',
            'payload'  => [
                'mimeType' => 'text/plain',
                'headers'  => [
                    ['name' => 'From', 'value' => 'info@mémorablemoments.be'],
                    ['name' => 'To', 'value' => 'care@shop.test'],
                    ['name' => 'Subject', 'value' => 'Commande'],
                    ['name' => 'Message-ID', 'value' => '<idn1@mémorablemoments.be>'],
                ],
                'body'     => ['data' => rtrim(strtr(base64_encode('Bonjour'), '+/', '-_'), '=')],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'            => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/unmatched']]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send'     => \Illuminate\Support\Facades\Http::response(['id' => 'sent-idn']),
        'gmail.googleapis.com/*'                                   => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'idn1')->chatSession->fresh();

    $reply = SendChatMessage::make()->handle($session, [
        'message_text' => 'Merci',
        'message_type' => ChatMessageTypeEnum::TEXT->value,
        'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
    ]);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply->fresh());

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return str_ends_with($request->url(), 'users/me/messages/send')
            && str_contains(base64_decode(strtr($request['raw'], '-_', '+/')), "To: info@xn--mmorablemoments-bnb.be\r\n");
    });

    $session->fresh()->update(['metadata' => array_merge($session->fresh()->metadata, ['email_reply_to' => 'jöhn@example.test', 'email_from' => 'jöhn@example.test'])]);

    $unsendable = $session->messages()->create([
        'message_text' => 'Merci',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
    ]);

    expect(fn () => \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($unsendable))
        ->toThrow(RuntimeException::class, 'is not one Gmail can send to');
});

test('email reply attaches files while they fit in 18MB and links the rest', function () {
    $megabyte = 1024 * 1024;
    $files    = collect([
        (object) ['ulid' => 'a', 'size' => 10 * $megabyte],
        (object) ['ulid' => 'b', 'size' => 27 * $megabyte],
        (object) ['ulid' => 'c', 'size' => 8 * $megabyte],
    ]);

    [$attached, $linked] = \App\Actions\Comms\Mailbox\SendChatMessageByGmail::splitByGmailLimit($files);

    expect($attached->pluck('ulid')->all())->toBe(['a', 'c'])
        ->and($linked->pluck('ulid')->all())->toBe(['b']);
});

test('a chat file linked from an email opens in the browser under the name the agent gave it', function () {
    $media = new \App\Models\Helpers\Media(['name' => 'EO-CLP-NEW', 'file_name' => 'b21bdd7a.pdf']);

    expect(\App\Actions\Chat\ChatSession\DownloadChatAttachment::make()->fileName($media))->toBe('EO-CLP-NEW.pdf');

    $media->name = 'EO-CLP-NEW.pdf';
    expect(\App\Actions\Chat\ChatSession\DownloadChatAttachment::make()->fileName($media))->toBe('EO-CLP-NEW.pdf');
});

test('a shop starts with ten customer email templates that staff edit and send with the customer filled in', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class, \App\Actions\CRM\CustomerEmailTemplate\TranslateShopCustomerEmailTemplates::class);

    actingAs($this->user);

    $this->shop->customerEmailTemplates()->delete();
    \App\Actions\CRM\CustomerEmailTemplate\SeedShopCustomerEmailTemplates::run($this->shop);
    \App\Actions\CRM\CustomerEmailTemplate\SeedShopCustomerEmailTemplates::run($this->shop);

    $templates = $this->shop->customerEmailTemplates()->get();
    expect($templates)->toHaveCount(10)
        ->and($templates->pluck('position')->all())->toBe(range(1, 10));

    $template = \App\Actions\CRM\CustomerEmailTemplate\UpdateCustomerEmailTemplate::make()->action($templates->first(), [
        'subject' => 'Your basket at {shop_name}',
        'body'    => "Hello {contact_name},\n{basket}\nTotal {basket_total}\n{my_name}",
    ]);
    expect($template->updated_by_user_id)->toBe($this->user->id);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);
    $this->customer->update(['email' => 'buyer@example.com', 'contact_name' => 'Matt Shaw']);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/*'      => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = \App\Actions\Chat\ChatSession\StartCustomerEmailChat::make()->action($this->customer->fresh(), [
        'subject' => $template->subject,
        'message' => $template->body,
    ]);

    $customer = $this->customer->fresh();
    $this->withoutVite();
    $this->get(route('grp.org.shops.show.crm.customers.show', [$this->shop->organisation->slug, $this->shop->slug, $customer->slug]))
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('can_email_customer', true)
            ->has('email_templates', 10)
            ->where('email_templates.0.subject', 'Your basket at {shop_name}')
            ->where('email_template_values.{contact_name}', 'Matt Shaw'));

    $sent = $session->messages()->first()->message_text;
    expect(Arr::get($session->metadata, 'email_subject'))->toBe('Your basket at '.$this->shop->name)
        ->and($sent)->toStartWith('Hello Matt Shaw,')
        ->and($sent)->not->toContain('{');

    $editedMeanwhile = $this->shop->customerEmailTemplates()->where('position', 3)->first();
    \App\Actions\Helpers\Translations\Translate::shouldRun()->andReturnUsing(function (string $text) use ($editedMeanwhile) {
        if ($text === $editedMeanwhile->name) {
            $editedMeanwhile->update(['subject' => 'Edited meanwhile', 'updated_by_user_id' => $this->user->id]);
        }

        return 'ES '.str_replace('{contact_name}', 'cliente', $text);
    });
    \App\Actions\CRM\CustomerEmailTemplate\TranslateShopCustomerEmailTemplates::run($this->shop);

    $untouched = $this->shop->customerEmailTemplates()->where('position', 2)->first();
    expect($template->fresh()->subject)->toBe('Your basket at {shop_name}')
        ->and($editedMeanwhile->fresh()->subject)->toBe('Edited meanwhile')
        ->and($untouched->name)->toStartWith('ES ')
        ->and($untouched->body)->toStartWith('Hello {contact_name}');
});

test('shopkeepers email a customer without working chat and customer service answers the reply', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class, \App\Actions\Chat\ChatSession\SummarizeChatSession::class);
    Event::fake([\App\Events\BroadcastRealtimeChat::class]);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);
    $this->customer->update(['email' => 'buyer@example.com']);
    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/*'      => \Illuminate\Support\Facades\Http::response([]),
    ]);

    setPermissionsTeamId($this->shop->group_id);
    $shopkeeper = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action(
        $this->shop->group,
        array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []])
    )->getUser();

    expect(\App\Actions\Chat\ChatSession\StartCustomerEmailChat::canBeStartedBy($shopkeeper, $this->customer->fresh()))->toBeFalse();

    $shopkeeper->givePermissionTo("orders.{$this->shop->id}.edit");
    $shopkeeper->refresh();
    \Illuminate\Support\Facades\Cache::tags('auth-user:'.$shopkeeper->id)->flush();

    expect(\App\Actions\Chat\ChatSession\StartCustomerEmailChat::canBeStartedBy($shopkeeper, $this->customer->fresh()))->toBeTrue();

    $sessionsBefore = ChatSession::where('shop_id', $this->shop->id)->max('id') ?? 0;

    actingAs($shopkeeper)
        ->from('/customer-page')
        ->post(route('grp.models.customer.email_chat.store', ['customer' => $this->customer->id]), [
            'subject' => 'Your basket',
            'message' => 'Can I help you finish your order?',
        ])
        ->assertRedirect('/customer-page');

    $session = ChatSession::where('shop_id', $this->shop->id)->where('id', '>', $sessionsBefore)->sole();
    expect($session->status)->toBe(ChatSessionStatusEnum::CLOSED)
        ->and($session->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->exists())->toBeFalse()
        ->and($session->messages()->where('sender_type', ChatSenderTypeEnum::AGENT->value)->value('message_text'))->toBe('Can I help you finish your order?');
});

test('staff can send an email reply up to 100000 characters, customers keep the 5000 limit', function () {
    $passes = fn (int $length) => validator(['message_text' => str_repeat('a', $length)], ['message_text' => SendChatMessage::make()->rules()['message_text']])->passes();

    expect($passes(5000))->toBeTrue()
        ->and($passes(5001))->toBeFalse();

    actingAs($this->user);

    expect($passes(100000))->toBeTrue()
        ->and($passes(100001))->toBeFalse();
});

test('an email reply Gmail refuses on every try says so inside the conversation and is sent again from there', function () {
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/*'      => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
        'channel'     => ChatChannelEnum::EMAIL,
        'metadata'    => ['email_from' => 'buyer@example.com', 'email_subject' => 'Label'],
    ]);

    $reply = $session->messages()->create([
        'message_text' => 'Please find the label attached',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
    ]);

    $refusal = new \Illuminate\Http\Client\RequestException(new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(400, [], json_encode(['error' => ['message' => 'Invalid To header']]))
    ));

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::make()->jobFailed($refusal, $reply);

    $note = $session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->latest('id')->first();

    expect(Arr::get($reply->fresh()->metadata, 'email_send_failed.reason'))->toBe('Gmail refused it (400): Invalid To header')
        ->and($note->message_text)->toBe('Email not sent to buyer@example.com: Gmail refused it (400): Invalid To header')
        ->and(Arr::get($note->metadata, 'email_not_sent.message_id'))->toBe($reply->id);

    setPermissionsTeamId($this->shop->group_id);
    $supervisor = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action(
        $this->shop->group,
        array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []])
    )->getUser();
    $supervisor->givePermissionTo("chat-m.{$this->shop->id}");
    \Illuminate\Support\Facades\Cache::tags('auth-user:'.$supervisor->id)->flush();

    \Illuminate\Support\Facades\Queue::fake();

    actingAs($supervisor)
        ->postJson(route('grp.org.chat.agents.messages.resend_email', [$this->organisation->slug, $session->ulid, $reply->id]))
        ->assertOk();

    expect(Arr::get($reply->fresh()->metadata, 'email_send_failed'))->toBeNull()
        ->and(Arr::get($reply->fresh()->metadata, 'email_resent_at'))->not->toBeNull();
    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::assertPushed(1);

    // A second click, in another tab or by a colleague, finds nothing left to send again.
    actingAs($supervisor)
        ->postJson(route('grp.org.chat.agents.messages.resend_email', [$this->organisation->slug, $session->ulid, $reply->id]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'This email is already being sent');

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::assertPushed(1);

    // Gmail took it in the meantime: sending it again would mail the customer twice.
    $reply->update(['metadata' => array_merge($reply->fresh()->metadata ?? [], ['gmail_message_id' => 'sent-label'])]);

    actingAs($supervisor)
        ->postJson(route('grp.org.chat.agents.messages.resend_email', [$this->organisation->slug, $session->ulid, $reply->id]))
        ->assertStatus(422);

    // A failure after Gmail took it (the broadcast, the database) is not a mail the customer lacks.
    $notesBefore = $session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->count();
    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::make()->jobFailed(new RuntimeException('broadcast failed'), $reply->fresh());

    expect($session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->count())->toBe($notesBefore)
        ->and(Arr::get($reply->fresh()->metadata, 'email_send_failed'))->toBeNull();

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply->fresh());
    \Illuminate\Support\Facades\Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request) => str_ends_with($request->url(), 'users/me/messages/send'));

    $stranger = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action(
        $this->shop->group,
        array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []])
    )->getUser();

    actingAs($stranger)
        ->postJson(route('grp.org.chat.agents.messages.resend_email', [$this->organisation->slug, $session->ulid, $reply->id]))
        ->assertForbidden();
});

test('an email reply that cannot be sent at all is marked not sent instead of being skipped', function () {
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
        'channel'     => ChatChannelEnum::EMAIL,
        'metadata'    => ['email_subject' => 'No address'],
    ]);

    $reply = $session->messages()->create([
        'message_text' => 'Thank you',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
    ]);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply);

    expect(Arr::get($reply->fresh()->metadata, 'email_send_failed.reason'))->toBe('this conversation has no email address to answer')
        ->and($session->messages()->where('sender_type', ChatSenderTypeEnum::SYSTEM)->value('message_text'))
        ->toBe('Email not sent to the customer: this conversation has no email address to answer');
});

test('two pictures sent a moment apart are two messages, each with its picture', function () {
    fakeActionJobs(\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class);

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'guest_identifier' => 'guest-'.Str::random(8),
    ]);

    $png     = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $picture = function (string $name) use ($png) {
        $path = tempnam(sys_get_temp_dir(), 'pic');
        file_put_contents($path, $png.$name);

        return new \Illuminate\Http\UploadedFile($path, $name, 'image/png', null, true);
    };

    $first = SendChatMessage::make()->handle($session, [
        'message_text' => '',
        'message_type' => ChatMessageTypeEnum::IMAGE->value,
        'sender_type'  => ChatSenderTypeEnum::AGENT->value,
        'attachments'  => [$picture('front.png')],
    ]);
    $second = SendChatMessage::make()->handle($session, [
        'message_text' => '',
        'message_type' => ChatMessageTypeEnum::IMAGE->value,
        'sender_type'  => ChatSenderTypeEnum::AGENT->value,
        'attachments'  => [$picture('back.png')],
    ]);

    expect($second->id)->not->toBe($first->id)
        ->and($first->fresh()->attachedFiles()->pluck('name')->all())->toBe(['front.png'])
        ->and($second->fresh()->attachedFiles()->pluck('name')->all())->toBe(['back.png']);
});

test('a retry of an email reply finds the mail a timed-out try already got into Gmail and does not send it again', function () {
    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = ['email' => 'care@shop.test', 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt')];
    $this->shop->update(['settings' => $settings]);

    $inMailbox = [];
    \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$inMailbox) {
        if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
            return \Illuminate\Support\Facades\Http::response(['access_token' => 'at']);
        }

        if (str_ends_with($request->url(), 'users/me/messages/send')) {
            return \Illuminate\Support\Facades\Http::response(['id' => 'sent-first', 'threadId' => 'thread-1']);
        }

        if (str_contains($request->url(), 'users/me/messages?')) {
            return \Illuminate\Support\Facades\Http::response(['messages' => array_map(fn ($id) => ['id' => $id], $inMailbox)]);
        }

        return \Illuminate\Support\Facades\Http::response([]);
    });

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
        'channel'     => ChatChannelEnum::EMAIL,
        'metadata'    => ['email_from' => 'buyer@example.com', 'email_subject' => 'Label'],
    ]);

    $reply = $session->messages()->create([
        'message_text' => 'The label is attached',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
    ]);

    // The first try keeps the Message-ID it sends under, before Gmail answers.
    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply);

    $headerId = Arr::get($reply->fresh()->metadata, 'email_header_message_id');

    expect($headerId)->toMatch('/^<[0-9A-Z]{26}@shop\.test>$/');
    \Illuminate\Support\Facades\Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => str_ends_with($request->url(), 'users/me/messages/send')
        && str_contains(base64_decode(strtr($request['raw'], '-_', '+/')), "Message-ID: $headerId\r\n"));

    // Gmail took a second reply but did not answer in time: the retry finds it in the mailbox.
    $timedOut = $session->messages()->create([
        'message_text' => 'And the invoice',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
        'metadata'     => ['email_header_message_id' => '<01TIMEDOUT0000000000000000@shop.test>'],
    ]);
    $inMailbox = ['in-gmail-already'];
    $sendsBefore = collect(\Illuminate\Support\Facades\Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), 'users/me/messages/send'))->count();

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($timedOut);

    $sendsAfter = collect(\Illuminate\Support\Facades\Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), 'users/me/messages/send'))->count();

    expect($sendsAfter)->toBe($sendsBefore)
        ->and(Arr::get($timedOut->fresh()->metadata, 'gmail_message_id'))->toBe('in-gmail-already');
    \Illuminate\Support\Facades\Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => str_contains(urldecode($request->url()), 'q=rfc822msgid:01TIMEDOUT0000000000000000@shop.test in:anywhere'));

    // Not in the mailbox: the try did not get through, so it is sent.
    $inMailbox = [];
    $lost = $session->messages()->create([
        'message_text' => 'And the delivery note',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
        'metadata'     => ['email_header_message_id' => '<01LOSTTRY00000000000000000@shop.test>'],
    ]);

    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($lost);

    expect(Arr::get($lost->fresh()->metadata, 'gmail_message_id'))->toBe('sent-first');
});

test('forwarding a conversation by email sends it from the shop mailbox as Fwd: with the messages and records it on the conversation', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'fwd1', 'threadId' => 'tfwd1']),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
        'channel'     => ChatChannelEnum::EMAIL,
        'metadata'    => ['email_subject' => 'Where is my order?', 'email_from' => 'buyer@example.com', 'email' => 'buyer@example.com', 'name' => 'Buyer Person'],
    ]);

    $agentUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true, 'contact_name' => 'Forwarding Agent']);
    makeChatWorker($agentUser, $this->shop);
    $agent = ChatAgent::updateOrCreate(['user_id' => $agentUser->id], ['max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => false, 'is_available' => false, 'current_chat_count' => 0]);

    $session->messages()->create([
        'message_text' => 'Hello, any news?',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::GUEST,
        'metadata'     => ['email_from' => ['address' => 'buyer@example.com', 'name' => 'Buyer Person']],
        'created_at'   => now()->subHours(2),
    ]);
    $session->messages()->create([
        'message_text' => 'Hoy enviado',
        'original_text' => 'Shipped today',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
        'sender_id'    => $agent->id,
        'created_at'   => now()->subHour(),
    ]);
    $session->messages()->create([
        'message_text' => 'Chat closed by the system',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::SYSTEM,
        'created_at'   => now()->subMinutes(30),
    ]);

    $route  = route('grp.org.chat.agents.sessions.forward_email', [$this->organisation->slug, $session->ulid]);
    $lastId = ChatSession::max('id');

    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    actingAs($outsider)->postJson($route, ['email' => 'supplier@example.com'])->assertForbidden();
    actingAs($outsider)->getJson(route('grp.org.chat.agents.sessions.forward_email.preview', [$this->organisation->slug, $session->ulid]))->assertForbidden();
    expect(ChatSession::where('id', '>', $lastId)->exists())->toBeFalse();

    actingAs($agentUser)->postJson($route, ['email' => 'not-an-address'])->assertUnprocessable();

    actingAs($agentUser)->getJson(route('grp.org.chat.agents.sessions.forward_email.preview', [$this->organisation->slug, $session->ulid]))
        ->assertOk()
        ->assertJsonPath('subject', 'Fwd: Where is my order?');

    actingAs($agentUser)->postJson($route, ['email' => 'supplier@example.com, boss@example.com', 'note' => 'Can you check this one?'])->assertOk();

    $forwarded = ChatSession::where('id', '>', $lastId)->sole();
    $text      = $forwarded->messages()->where('sender_type', ChatSenderTypeEnum::AGENT->value)->value('message_text');

    expect($forwarded->channel)->toBe(ChatChannelEnum::EMAIL)
        ->and($forwarded->shop_id)->toBe($this->shop->id)
        ->and(Arr::get($forwarded->metadata, 'email_subject'))->toBe('Fwd: Where is my order?')
        ->and(Arr::get($forwarded->metadata, 'email_from'))->toBe('supplier@example.com')
        ->and(array_keys(Arr::get($forwarded->metadata, 'email_participants', [])))->toBe(['boss@example.com'])
        ->and($text)->toStartWith("Can you check this one?\n\n".\App\Actions\Chat\ChatSession\ForwardChatSessionByEmail::SEPARATOR)
        ->and($text)->toContain('From: Buyer Person <buyer@example.com>')
        ->and($text)->toContain('Hello, any news?')
        ->and($text)->toContain('From: Forwarding Agent, '.$this->shop->name)
        ->and($text)->toContain('Shipped today')
        ->and($text)->not->toContain('Hoy enviado')
        ->and($text)->not->toContain('Chat closed by the system')
        ->and(strpos($text, 'Hello, any news?'))->toBeLessThan(strpos($text, 'Shipped today'));

    $event = $session->chatEvents()->where('event_type', \App\Enums\CRM\Livechat\ChatEventTypeEnum::FORWARDED_BY_EMAIL->value)->sole();
    expect(Arr::get($event->payload, 'recipients'))->toBe(['supplier@example.com', 'boss@example.com'])
        ->and(Arr::get($event->payload, 'forwarded_session_ulid'))->toBe($forwarded->ulid)
        ->and(Arr::get($event->payload, 'note'))->toBe('Can you check this one?');

    expect(\App\Actions\Chat\ChatSession\ForwardChatSessionByEmail::message($session, ''))
        ->toBe(\App\Actions\Chat\ChatSession\ForwardChatSessionByEmail::forwardedBlock($session));
});

test('an email conversation moved to another shop still replies from the original mailbox in its thread, and mail on that thread still finds it', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class]);

    $gmail = fn (string $address) => [
        'email'         => $address,
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = $gmail('care@shop.test');
    $this->shop->update(['settings' => $settings]);

    $otherShop = \App\Models\Catalogue\Shop::where('organisation_id', $this->organisation->id)->where('id', '!=', $this->shop->id)->first()
        ?? \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());
    $otherSettings          = $otherShop->settings ?? [];
    $otherSettings['gmail'] = $gmail('hello@other-shop.test');
    $otherShop->update(['settings' => $otherSettings]);

    $threadId = 'tmove'.Str::random(6);
    $session  = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'status'      => ChatSessionStatusEnum::ACTIVE,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL,
        'shop_id'     => $this->shop->id,
        'channel'     => ChatChannelEnum::EMAIL,
        'metadata'    => [
            'email_subject'                => 'Wrong shop question',
            'email_from'                   => 'mover@example.com',
            'email'                        => 'mover@example.com',
            'gmail_thread_id'              => $threadId,
            'gmail_last_header_message_id' => '<first@example.com>',
        ],
    ]);

    $agentUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent     = ChatAgent::updateOrCreate(['user_id' => $agentUser->id], ['max_concurrent_chats' => 5, 'language_id' => 68, 'is_online' => false, 'is_available' => false, 'current_chat_count' => 0]);
    $session->assignments()->create([
        'chat_agent_id' => $agent->id,
        'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'   => \App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'   => now(),
    ]);

    \App\Actions\Chat\ChatSession\MoveChatSessionToShop::make()->handle($session, $otherShop, $agent);
    $session->refresh();

    expect($session->shop_id)->toBe($otherShop->id)
        ->and($session->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and(Arr::get($session->metadata, 'mailbox_shop_id'))->toBe($this->shop->id)
        ->and($session->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->exists())->toBeFalse()
        ->and($session->chatEvents()->where('event_type', \App\Enums\CRM\Livechat\ChatEventTypeEnum::MOVED_TO_SHOP->value)->exists())->toBeTrue()
        ->and(\App\Actions\Comms\Mailbox\SendChatMessageByGmail::mailboxShop($session)->id)->toBe($this->shop->id);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                          => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send' => \Illuminate\Support\Facades\Http::response(['id' => 'sentmove', 'threadId' => $threadId]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/mmove*' => \Illuminate\Support\Facades\Http::response([
            'id'       => 'mmove',
            'threadId' => $threadId,
            'payload'  => [
                'mimeType' => 'text/plain',
                'headers'  => [
                    ['name' => 'From', 'value' => 'Mover <mover@example.com>'],
                    ['name' => 'Subject', 'value' => 'Re: Wrong shop question'],
                    ['name' => 'Message-ID', 'value' => '<second@example.com>'],
                ],
                'body'     => ['data' => rtrim(strtr(base64_encode('Thanks, still waiting'), '+/', '-_'), '=')],
            ],
        ]),
        'gmail.googleapis.com/gmail/v1/users/me/labels'        => \Illuminate\Support\Facades\Http::response(['labels' => [['id' => 'L1', 'name' => 'aiku/imported']]]),
        'gmail.googleapis.com/*'                               => \Illuminate\Support\Facades\Http::response([]),
    ]);

    $reply = $session->messages()->create([
        'message_text' => 'Passing you to the right team',
        'message_type' => ChatMessageTypeEnum::TEXT,
        'sender_type'  => ChatSenderTypeEnum::AGENT,
        'sender_id'    => $agent->id,
    ]);
    \App\Actions\Comms\Mailbox\SendChatMessageByGmail::run($reply);

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($threadId) {
        if (!str_ends_with($request->url(), 'users/me/messages/send')) {
            return false;
        }
        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return ($request['threadId'] ?? null) === $threadId
            && str_contains($raw, '<care@shop.test>')
            && !str_contains($raw, 'other-shop.test')
            && str_contains($raw, 'In-Reply-To: <first@example.com>');
    });

    $inbound = \App\Actions\Comms\Mailbox\ProcessInboundEmail::run($this->shop, 'mmove');

    expect($inbound)->toBeInstanceOf(ChatMessage::class)
        ->and($inbound->chat_session_id)->toBe($session->id)
        ->and($session->fresh()->shop_id)->toBe($otherShop->id);

    \App\Actions\Chat\ChatSession\MoveChatSessionToShop::make()->handle($session->fresh(), $this->shop, $agent);
    expect(Arr::has($session->fresh()->metadata, 'mailbox_shop_id'))->toBeFalse();
});
