<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\CRM\Customer\AnonymiseCustomer;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\CRM\ShoppingAssistant\AnswerShoppingAssistantMessage;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantBudget;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantReport;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\CRM\ShoppingAssistant\RecordShoppingAssistantBasketAdd;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Actions\SysAdmin\Group\UpdateGroupSettings;
use App\Actions\Web\Website\UpdateWebsite;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantTools;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeSourceEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantEvent;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\CRM\WebUser;
use App\Models\Helpers\TaxCategory;
use App\Models\SysAdmin\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use App\Models\Web\Website;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();
    [, $this->product] = createProduct($this->shop);

    config(['services.openrouter.api_key' => 'test']);
    actingAs($this->user);
});

function shoppingAssistantCustomer(Shop $shop, array $data = []): Customer
{
    return StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), $data));
}

function shoppingAssistantProduct(Product $seed): Product
{
    $product = StoreProduct::make()->action($seed->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $seed->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 10]
    ));
    $product->update(['state' => ProductStateEnum::ACTIVE, 'status' => ProductStatusEnum::FOR_SALE, 'is_for_sale' => true, 'has_live_webpage' => true, 'price' => 10, 'available_quantity' => 1000]);

    return $product;
}

function shoppingAssistantStoppedOrdering(Customer $customer, Product $product): void
{
    DB::table('customer_product_purchases')->insert([
        'customer_id'      => $customer->id,
        'shop_id'          => $customer->shop_id,
        'product_id'       => $product->id,
        'family_id'        => $product->family_id,
        'orders'           => 3,
        'orders_12m'       => 0,
        'quantity_12m'     => 0,
        'spend_12m'        => 0,
        'first_ordered_at' => now()->subMonths(30),
        'last_ordered_at'  => now()->subMonths(14),
    ]);
}

function shoppingAssistantToolCall(string $name, array $arguments, string $id = 'call_1'): array
{
    return ['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($arguments)]];
}

function shoppingAssistantModelReply(array $message, string $finishReason, int $promptTokens = 100, int $completionTokens = 20, float $cost = 0.01): array
{
    return [
        'choices' => [['finish_reason' => $finishReason, 'message' => array_merge(['role' => 'assistant', 'content' => null], $message)]],
        'usage'   => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens, 'cost' => $cost],
    ];
}

function shoppingAssistantPresent(array $arguments, int $promptTokens = 200, int $completionTokens = 30, float $cost = 0.02): array
{
    return shoppingAssistantModelReply(['tool_calls' => [shoppingAssistantToolCall('present_products', $arguments, 'call_present')]], 'tool_calls', $promptTokens, $completionTokens, $cost);
}

test('a shopping assistant turn runs a data tool, presents its products and logs the messages, cost and shown events', function () {
    $email    = 'zelda.'.uniqid().'@example.com';
    $customer = shoppingAssistantCustomer($this->shop, ['contact_name' => 'Zelda Quillfeather', 'company_name' => 'Moonstone Gifts Ltd', 'email' => $email]);
    $kept     = shoppingAssistantProduct($this->product);
    $addOn    = shoppingAssistantProduct($this->product);
    shoppingAssistantStoppedOrdering($customer, $kept);
    shoppingAssistantStoppedOrdering($customer, $addOn);

    Http::fake(['*chat/completions' => Http::sequence()
        ->push(shoppingAssistantModelReply(['tool_calls' => [shoppingAssistantToolCall('products_they_stopped_ordering', [])]], 'tool_calls'))
        ->push(shoppingAssistantPresent(['reply' => 'Here are favourites worth bringing back.', 'must_haves' => [$kept->id], 'add_ons' => [$addOn->id], 'follow_ups' => ['What sells well with these?']]))]);

    $conversation = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::COMMAND);
    $result       = AskShoppingAssistant::run($conversation, 'What did I use to buy that I should restock?');
    $conversation->refresh();
    $message      = $result['message'];
    $events       = $conversation->events()->orderBy('id')->get();
    $language     = DB::table('languages')->where('code', $conversation->language_code)->value('name') ?: 'English';
    $reasonText   = GetShoppingAssistantTools::make()->reasonText('used_to_order', $customer, $conversation->language_code);

    expect($result['reply'])->toBe('Here are favourites worth bringing back.')
        ->and($result['groups']['must_haves']->pluck('id')->all())->toBe([$kept->id])
        ->and($result['groups']['add_ons']->pluck('id')->all())->toBe([$addOn->id])
        ->and($result['groups']['must_haves']->first()->assistant_reason_code)->toBe('used_to_order')
        ->and($result['groups']['must_haves']->first()->assistant_reason)->toBe($reasonText)
        ->and($result['follow_ups'])->toBe(['What sells well with these?'])
        ->and($conversation->messages()->orderBy('id')->get()->map(fn ($storedMessage) => $storedMessage->role->value)->all())->toBe(['customer', 'assistant'])
        ->and($conversation->messages()->where('role', ShoppingAssistantMessageRoleEnum::CUSTOMER)->value('body'))->toBe('What did I use to buy that I should restock?')
        ->and($message->model_calls)->toBe(2)
        ->and($message->prompt_tokens)->toBe(300)
        ->and($message->completion_tokens)->toBe(50)
        ->and((float) $message->cost)->toEqualWithDelta(0.03, 0.000001)
        ->and($message->flags)->toBeNull()
        ->and($message->tool_calls[0]['name'])->toBe('products_they_stopped_ordering')
        ->and($message->tool_calls[0]['product_ids'])->toContain($kept->id, $addOn->id)
        ->and($message->tool_calls[0]['error'])->toBeNull()
        ->and($conversation->messages_count)->toBe(2)
        ->and($conversation->model_calls)->toBe(2)
        ->and($conversation->prompt_tokens)->toBe(300)
        ->and((float) $conversation->cost)->toEqualWithDelta(0.03, 0.000001)
        ->and($events->map(fn ($event) => [$event->type->value, $event->product_id, $event->group, $event->position, $event->reason_code, $event->reason, $event->shopping_assistant_message_id])->all())->toBe([
            ['shown', $kept->id, 'must_haves', 1, 'used_to_order', $reasonText, $message->id],
            ['shown', $addOn->id, 'add_ons', 1, 'used_to_order', $reasonText, $message->id],
        ]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'chat/completions') && str_contains($request['messages'][0]['content'], 'Reply in '.$language.'.'));
    Http::assertNotSent(fn (Request $request) => str_contains($request->body(), 'Zelda') || str_contains($request->body(), 'Quillfeather') || str_contains($request->body(), $email));
});

test('the shopping assistant drops products no tool returned or that went out of stock, and replaces a reply with numbers the customer never gave', function () {
    $customer = shoppingAssistantCustomer($this->shop);
    $kept     = shoppingAssistantProduct($this->product);
    $soldOut  = shoppingAssistantProduct($this->product);
    $invented = shoppingAssistantProduct($this->product);
    shoppingAssistantStoppedOrdering($customer, $kept);
    shoppingAssistantStoppedOrdering($customer, $soldOut);

    $calls     = 0;
    $responses = [
        1 => shoppingAssistantModelReply(['tool_calls' => [shoppingAssistantToolCall('products_they_stopped_ordering', ['limit' => 12])]], 'tool_calls'),
        2 => shoppingAssistantPresent(['reply' => 'Here is how to use your €600 budget.', 'must_haves' => [$kept->id, $invented->id], 'add_ons' => [$soldOut->id]]),
        3 => shoppingAssistantPresent(['reply' => 'This one is only £4.50.', 'must_haves' => [$kept->id]]),
    ];
    Http::fake(['*chat/completions' => function () use (&$calls, $responses, $soldOut) {
        $calls++;
        if ($calls === 2) {
            DB::table('products')->where('id', $soldOut->id)->update(['available_quantity' => 0]);
        }

        return Http::response($responses[$calls]);
    }]);

    $conversation = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::COMMAND);
    $first        = AskShoppingAssistant::run($conversation, 'I have about €600 to spend on lines I used to stock');

    expect($first['reply'])->toBe('Here is how to use your €600 budget.')
        ->and($first['groups']['must_haves']->pluck('id')->all())->toBe([$kept->id])
        ->and($first['groups']['add_ons']->all())->toBe([])
        ->and($first['message']->flags)->toContain('dropped_unknown_product', 'dropped_unavailable_product')
        ->and($first['message']->flags)->not->toContain('reply_replaced_number')
        ->and($conversation->events()->pluck('product_id')->all())->toBe([$kept->id]);

    $second = AskShoppingAssistant::run($conversation->fresh(), 'Anything cheaper?');

    expect($second['reply'])->toBe(__('Here are some ideas for your shop.', [], $conversation->language_code))
        ->and($second['message']->flags)->toBe(['reply_replaced_number'])
        ->and($second['groups']['must_haves']->pluck('id')->all())->toBe([$kept->id])
        ->and($second['message']->body)->not->toContain('4.50');

    Http::assertSent(fn (Request $request) => str_contains($request->body(), 'Shown: ') && str_contains($request->body(), "(id $kept->id)"));
});

test('the shopping assistant saves the shop type a customer tells it only when none was set by the customer or staff', function () {
    $newCustomer   = shoppingAssistantCustomer($this->shop);
    $staffCustomer = UpdateCustomer::make()->action(shoppingAssistantCustomer($this->shop), ['shop_type' => CustomerShopTypeEnum::SPA_WELLNESS->value]);

    Http::fake(['*chat/completions' => Http::sequence()
        ->push(shoppingAssistantModelReply(['tool_calls' => [shoppingAssistantToolCall('save_shop_type', ['shop_type' => 'gift_shop'])]], 'tool_calls'))
        ->push(shoppingAssistantModelReply(['content' => 'Thanks, what are you looking for?'], 'stop'))
        ->push(shoppingAssistantModelReply(['tool_calls' => [shoppingAssistantToolCall('save_shop_type', ['shop_type' => 'gift_shop'])]], 'tool_calls'))
        ->push(shoppingAssistantModelReply(['content' => 'Thanks, what are you looking for?'], 'stop'))]);

    $saved = AskShoppingAssistant::run(StartShoppingAssistantConversation::run($newCustomer, ShoppingAssistantEntryPointEnum::COMMAND), 'I run a gift shop by the sea');
    AskShoppingAssistant::run(StartShoppingAssistantConversation::run($staffCustomer, ShoppingAssistantEntryPointEnum::COMMAND), 'I run a gift shop by the sea');

    $newCustomer->refresh();
    $staffCustomer->refresh();

    expect($saved['reply'])->toBe('Thanks, what are you looking for?')
        ->and($saved['groups']['must_haves']->all())->toBe([])
        ->and($saved['message']->tool_calls[0]['name'])->toBe('save_shop_type')
        ->and($newCustomer->shop_type)->toBe(CustomerShopTypeEnum::GIFT_SHOP)
        ->and($newCustomer->shop_type_source)->toBe(CustomerShopTypeSourceEnum::CUSTOMER)
        ->and($staffCustomer->shop_type)->toBe(CustomerShopTypeEnum::SPA_WELLNESS)
        ->and($staffCustomer->shop_type_source)->toBe(CustomerShopTypeSourceEnum::STAFF);
});

test('a failed model call keeps the customer message and answers with the fallback, and anonymising the customer wipes the conversation text', function () {
    $customer = shoppingAssistantCustomer($this->shop);
    $product  = shoppingAssistantProduct($this->product);
    shoppingAssistantStoppedOrdering($customer, $product);

    Http::fake(['*chat/completions' => Http::sequence()
        ->push([], 500)
        ->push(shoppingAssistantModelReply(['tool_calls' => [shoppingAssistantToolCall('products_they_stopped_ordering', [])]], 'tool_calls'))
        ->push(shoppingAssistantPresent(['reply' => 'Worth bringing back.', 'must_haves' => [$product->id], 'follow_ups' => ['Anything new in this family?']]))]);

    $conversation = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::COMMAND);
    $failed       = AskShoppingAssistant::run($conversation, 'What should I reorder?');

    expect($failed['reply'])->toBe(__('Sorry, I could not find an answer just now. Please try again.', [], $conversation->language_code))
        ->and(collect($failed['groups'])->every->isEmpty())->toBeTrue()
        ->and($failed['message']->flags)->toBe(['model_unavailable'])
        ->and($failed['message']->model_calls)->toBe(0)
        ->and($conversation->messages()->where('role', ShoppingAssistantMessageRoleEnum::CUSTOMER)->pluck('body')->all())->toBe(['What should I reorder?']);

    $answered = AskShoppingAssistant::run($conversation->fresh(), 'What should I reorder?');

    expect($answered['groups']['must_haves']->pluck('id')->all())->toBe([$product->id]);

    AnonymiseCustomer::make()->action($customer, 'Erasure request', false);

    $messages = $conversation->messages()->get();
    $events   = $conversation->events()->get();

    expect($messages)->toHaveCount(4)
        ->and($messages->pluck('body')->filter()->all())->toBe([])
        ->and($messages->pluck('tool_calls')->filter()->all())->toBe([])
        ->and($messages->pluck('follow_ups')->filter()->all())->toBe([])
        ->and($messages->sum('prompt_tokens'))->toBeGreaterThan(0)
        ->and($events->pluck('product_id')->all())->toBe([$product->id])
        ->and($events->pluck('reason')->filter()->all())->toBe([]);
});

test('a follow-up we offered runs on the cheaper model only when one is set, and anything else stays on the main model', function () {
    $customer = shoppingAssistantCustomer($this->shop);
    config(['services.shopping_assistant.model' => 'test/main-model', 'services.shopping_assistant.follow_up_model' => 'test/cheap-model']);

    Http::fake(['*chat/completions' => fn () => Http::response(shoppingAssistantPresent(['reply' => 'Here you go.', 'follow_ups' => ['Show me more candles']]))]);

    $conversation = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::COMMAND);
    $modelOf      = fn (string $message) => AskShoppingAssistant::run($conversation->fresh(), $message)['message']->model;

    expect($modelOf('I run a gift shop and want ideas'))->toBe('test/main-model')
        ->and($modelOf(' show me more candles '))->toBe('test/cheap-model')
        ->and($modelOf('Something completely different'))->toBe('test/main-model');

    config(['services.shopping_assistant.follow_up_model' => null]);

    expect($modelOf('Show me more candles'))->toBe('test/main-model');

    Http::assertSent(fn ($request) => $request['model'] === 'test/cheap-model');
});

test('the shopping assistant reply may repeat the customer\'s own figures and sizes in product names but no other number', function () {
    $assistant = AskShoppingAssistant::make();
    $customer  = 'about 600 pounds';
    $names     = "Chocolate & Orange Bath Dust 190g\n10 ml Lavender Essential Oil\nLemon Sherbet Whipped Soap 120g\nCrystal Ball on Stand 50mm";

    expect($assistant->onlyGivenNumbers('The Bath Dust 190g and the 50mm crystal ball fit within your £600 budget.', $customer, $names))->toBeTrue()
        ->and($assistant->onlyGivenNumbers('This one is only £4.50.', $customer, $names))->toBeFalse()
        ->and($assistant->onlyGivenNumbers('This one is only £10.', $customer, $names))->toBeFalse()
        ->and($assistant->onlyGivenNumbers('It costs 10 pounds.', $customer, $names))->toBeFalse()
        ->and($assistant->onlyGivenNumbers('Over 120 shops buy it.', $customer, $names))->toBeFalse()
        ->and($assistant->onlyGivenNumbers('Save 10% on the 190g size.', $customer, $names))->toBeFalse();
});

function shoppingAssistantStorefront(object $test, bool $enabled = true): Website
{
    $website = createWebsite($test->shop);
    $website->update(['status' => true]);
    UpdateWebsite::make()->action($website, ['shopping_assistant_enabled' => $enabled, 'shopping_assistant_name' => 'Ava']);

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($website->domain);

    return $website->refresh();
}

function shoppingAssistantWebUser(Customer $customer): WebUser
{
    $id = uniqid();

    return StoreWebUser::make()->action($customer, ['username' => 'sa-'.$id, 'email' => 'sa-'.$id.'@example.com', 'password' => 'test'], sendRegistrationEmail: false);
}

function shoppingAssistantLiveProduct(Product $seed): Product
{
    $product = shoppingAssistantProduct($seed);
    ($product->webpage ?: StoreProductWebpage::make()->action($product))->update(['state' => WebpageStateEnum::LIVE]);

    return $product->refresh();
}

function shoppingAssistantShown(Customer $customer, array $productIds): ShoppingAssistantMessage
{
    $conversation = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH);
    AskShoppingAssistant::make()->storeCustomerMessage($conversation, 'Ideas please');
    $message = $conversation->messages()->create(['role' => ShoppingAssistantMessageRoleEnum::ASSISTANT, 'body' => 'Here you go.']);
    foreach (array_values($productIds) as $index => $productId) {
        $conversation->events()->create([
            'shopping_assistant_message_id' => $message->id,
            'type'                          => ShoppingAssistantEventTypeEnum::SHOWN,
            'product_id'                    => $productId,
            'group'                         => 'must_haves',
            'position'                      => $index + 1,
            'reason_code'                   => 'used_to_order',
            'reason'                        => 'You used to order this',
        ]);
    }

    return $message;
}

test('the storefront assistant is on only for an enabled B2B website and a customer of its shop, and names itself', function () {
    $website  = shoppingAssistantStorefront($this);
    $customer = shoppingAssistantCustomer($this->shop);

    expect(GetShoppingAssistantSettings::run($website, $customer))->toBe(['name' => 'Ava'])
        ->and(GetShoppingAssistantSettings::run($website, null))->toBeNull();

    UpdateWebsite::make()->action($website, ['shopping_assistant_enabled' => false]);

    expect(GetShoppingAssistantSettings::run($website->refresh(), $customer))->toBeNull()
        ->and(data_get($website->settings, 'shopping_assistant.name'))->toBe('Ava');
});

test('asking from the storefront stores the message, queues the answer and refuses a second question while one is pending', function () {
    $website  = shoppingAssistantStorefront($this);
    $customer = shoppingAssistantCustomer($this->shop);
    $webUser  = shoppingAssistantWebUser($customer);
    $url      = 'http://'.$website->domain.'/json/shopping-assistant/ask';
    Queue::fake();

    $response = $this->actingAs($webUser, 'retina')->postJson($url, ['message' => 'Candles for a gift shop'])->assertStatus(202);

    $conversation = ShoppingAssistantConversation::where('ulid', $response->json('conversation'))->first();
    expect($response->json())->toBe(['conversation' => $conversation->ulid, 'message_id' => $response->json('message_id'), 'pending' => true])
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->web_user_id)->toBe($webUser->id)
        ->and($conversation->entry_point)->toBe(ShoppingAssistantEntryPointEnum::SEARCH)
        ->and($conversation->messages()->pluck('body')->all())->toBe(['Candles for a gift shop']);
    AnswerShoppingAssistantMessage::assertPushed(1);

    $this->actingAs($webUser, 'retina')->postJson($url, ['message' => 'And mugs?', 'conversation' => $conversation->ulid])
        ->assertStatus(409)
        ->assertExactJson(['pending' => true]);
    expect($conversation->messages()->count())->toBe(1);

    UpdateWebsite::make()->action($website, ['shopping_assistant_enabled' => false]);
    $this->actingAs($webUser, 'retina')->postJson($url, ['message' => 'Hello'])->assertNotFound();
});

test('another customer cannot ask in, read or click in a conversation that is not theirs', function () {
    $website  = shoppingAssistantStorefront($this);
    $owner    = shoppingAssistantCustomer($this->shop);
    $other    = shoppingAssistantWebUser(shoppingAssistantCustomer($this->shop));
    $product  = shoppingAssistantProduct($this->product);
    $message  = shoppingAssistantShown($owner, [$product->id]);
    $ulid     = $message->conversation->ulid;
    $base     = 'http://'.$website->domain.'/json/shopping-assistant/';
    Queue::fake();

    $this->actingAs($other, 'retina')->postJson($base.'ask', ['message' => 'Hi', 'conversation' => $ulid])->assertNotFound();
    $this->actingAs($other, 'retina')->getJson($base.$ulid)->assertNotFound();
    $this->actingAs($other, 'retina')->postJson($base.$ulid.'/events', ['type' => 'clicked', 'product_id' => $product->id, 'message_id' => $message->id])->assertNotFound();

    expect($message->conversation->events()->where('type', ShoppingAssistantEventTypeEnum::CLICKED)->exists())->toBeFalse();
    AnswerShoppingAssistantMessage::assertNotPushed();
});

test('the answer job stores the assistant reply and the conversation returns it with search cards and the reason, after the given message', function () {
    $website  = shoppingAssistantStorefront($this);
    $customer = shoppingAssistantCustomer($this->shop);
    $webUser  = shoppingAssistantWebUser($customer);
    $kept     = shoppingAssistantLiveProduct($this->product);
    shoppingAssistantStoppedOrdering($customer, $kept);

    Http::fake(['*chat/completions' => Http::sequence()
        ->push(shoppingAssistantModelReply(['tool_calls' => [shoppingAssistantToolCall('products_they_stopped_ordering', [])]], 'tool_calls'))
        ->push(shoppingAssistantPresent(['reply' => 'Worth bringing back.', 'must_haves' => [$kept->id], 'follow_ups' => ['Anything new?']]))]);

    $conversation    = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH, ['web_user_id' => $webUser->id]);
    $customerMessage = AskShoppingAssistant::make()->storeCustomerMessage($conversation, 'What should I restock?');

    expect($conversation->isPending())->toBeTrue();

    AnswerShoppingAssistantMessage::run($customerMessage);
    AnswerShoppingAssistantMessage::run($customerMessage);

    $url      = 'http://'.$website->domain.'/json/shopping-assistant/'.$conversation->ulid;
    $response = $this->actingAs($webUser, 'retina')->getJson($url)->assertOk();
    $messages = $response->json('messages');
    $card     = $messages[1]['groups']['must_haves'][0];

    expect($response->json('conversation'))->toBe($conversation->ulid)
        ->and($response->json('status'))->toBe('open')
        ->and($response->json('pending'))->toBeFalse()
        ->and(array_keys($response->json()))->toBe(['conversation', 'status', 'pending', 'messages'])
        ->and($messages)->toHaveCount(2)
        ->and(array_keys($messages[0]))->toBe(['id', 'role', 'body', 'created_at'])
        ->and(array_keys($messages[1]))->toBe(['id', 'role', 'body', 'created_at', 'follow_ups', 'groups'])
        ->and($messages[1]['role'])->toBe('assistant')
        ->and($messages[1]['body'])->toBe('Worth bringing back.')
        ->and($messages[1]['follow_ups'])->toBe(['Anything new?'])
        ->and($messages[1]['groups']['add_ons'])->toBe([])
        ->and($card['id'])->toBe($kept->id)
        ->and($card['code'])->toBe($kept->code)
        ->and($card['assistant_reason'])->toBe(GetShoppingAssistantTools::make()->reasonText('used_to_order', $customer, $conversation->language_code))
        ->and($card['assistant_message_id'])->toBe($messages[1]['id']);

    $after = $this->actingAs($webUser, 'retina')->getJson($url.'?after='.$customerMessage->id)->assertOk()->json('messages');

    expect(collect($after)->pluck('id')->all())->toBe([$messages[1]['id']]);
});

test('a failed answer stores the fallback so the conversation is not left pending', function () {
    $customer        = shoppingAssistantCustomer($this->shop);
    $conversation    = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH);
    $customerMessage = AskShoppingAssistant::make()->storeCustomerMessage($conversation, 'Hello');

    AskShoppingAssistant::mock()->shouldReceive('answer')->andThrow(new RuntimeException('boom'));

    AnswerShoppingAssistantMessage::run($customerMessage);

    $reply = $conversation->messages()->where('role', ShoppingAssistantMessageRoleEnum::ASSISTANT)->first();

    expect($reply->body)->toBe(__('Sorry, I could not find an answer just now. Please try again.', [], $conversation->language_code))
        ->and($reply->flags)->toBe(['model_unavailable'])
        ->and($conversation->fresh()->isPending())->toBeFalse();
});

test('a click on a shown card is stored once and a product that was not shown is refused', function () {
    $website  = shoppingAssistantStorefront($this);
    $customer = shoppingAssistantCustomer($this->shop);
    $webUser  = shoppingAssistantWebUser($customer);
    $shown    = shoppingAssistantProduct($this->product);
    $notShown = shoppingAssistantProduct($this->product);
    $message  = shoppingAssistantShown($customer, [$shown->id]);
    $url      = 'http://'.$website->domain.'/json/shopping-assistant/'.$message->conversation->ulid.'/events';

    $this->actingAs($webUser, 'retina')->postJson($url, ['type' => 'clicked', 'product_id' => $shown->id, 'message_id' => $message->id])->assertNoContent();
    $this->actingAs($webUser, 'retina')->postJson($url, ['type' => 'clicked', 'product_id' => $shown->id, 'message_id' => $message->id])->assertNoContent();
    $this->actingAs($webUser, 'retina')->postJson($url, ['type' => 'clicked', 'product_id' => $notShown->id, 'message_id' => $message->id])->assertStatus(422);
    $this->actingAs($webUser, 'retina')->postJson($url, ['type' => 'added', 'product_id' => $shown->id, 'message_id' => $message->id])->assertStatus(422);

    $clicks = $message->conversation->events()->where('type', ShoppingAssistantEventTypeEnum::CLICKED)->get();

    expect($clicks->map(fn ($event) => [$event->product_id, $event->shopping_assistant_message_id, $event->group, $event->position, $event->reason_code])->all())
        ->toBe([[$shown->id, $message->id, 'must_haves', 1, 'used_to_order']]);
});

test('a basket add of a product the assistant showed is recorded once, and nothing for a product it did not show', function () {
    $customer = shoppingAssistantCustomer($this->shop);
    $shown    = shoppingAssistantProduct($this->product);
    $notShown = shoppingAssistantProduct($this->product);
    $message  = shoppingAssistantShown($customer, [$shown->id]);

    RecordShoppingAssistantBasketAdd::run($customer, $shown, null, 3);
    RecordShoppingAssistantBasketAdd::run($customer, $shown, null, 5);
    RecordShoppingAssistantBasketAdd::run($customer, $notShown, null, 1);
    RecordShoppingAssistantBasketAdd::run(shoppingAssistantCustomer($this->shop), $shown, null, 1);

    $added = ShoppingAssistantEvent::where('type', ShoppingAssistantEventTypeEnum::ADDED)->whereIn('product_id', [$shown->id, $notShown->id])->get();

    expect($added->map(fn ($event) => [$event->shopping_assistant_conversation_id, $event->shopping_assistant_message_id, $event->product_id, $event->group, $event->position, $event->reason_code, (float) $event->quantity])->all())
        ->toBe([[$message->shopping_assistant_conversation_id, $message->id, $shown->id, 'must_haves', 1, 'used_to_order', 3.0]]);
});

test('the storefront assistant rate limit keeps a bucket per customer', function () {
    $website = shoppingAssistantStorefront($this);
    $first   = shoppingAssistantWebUser(shoppingAssistantCustomer($this->shop));
    $second  = shoppingAssistantWebUser(shoppingAssistantCustomer($this->shop));
    $url     = 'http://'.$website->domain.'/json/shopping-assistant/ask';
    Queue::fake();

    foreach (range(1, 6) as $ignored) {
        $this->actingAs($first, 'retina')->postJson($url, ['message' => 'Ideas'])->assertStatus(202);
    }
    $this->actingAs($first, 'retina')->postJson($url, ['message' => 'Ideas'])->assertStatus(429);
    $this->actingAs($second, 'retina')->postJson($url, ['message' => 'Ideas'])->assertStatus(202);
});

test('adding a shown product to the basket from the storefront records it, and nothing is recorded with the assistant switched off', function () {
    $website  = shoppingAssistantStorefront($this);
    $customer = shoppingAssistantCustomer($this->shop);
    $webUser  = shoppingAssistantWebUser($customer);
    $product  = shoppingAssistantProduct($this->product);
    $later    = shoppingAssistantProduct($this->product);
    shoppingAssistantShown($customer, [$product->id, $later->id]);
    $addUrl = fn (Product $added) => 'http://'.$website->domain.'/models/'.$added->id.'/store-transaction';

    $transactionId = $this->actingAs($webUser, 'retina')->postJson($addUrl($product), ['quantity' => 2])->assertOk()->json('transaction_id');

    UpdateWebsite::make()->action($website, ['shopping_assistant_enabled' => false]);
    $this->actingAs($webUser, 'retina')->postJson($addUrl($later), ['quantity' => 1])->assertOk();

    $added = ShoppingAssistantEvent::where('type', ShoppingAssistantEventTypeEnum::ADDED)->whereIn('product_id', [$product->id, $later->id])->get();

    expect($added->map(fn ($event) => [$event->product_id, $event->transaction_id, (float) $event->quantity])->all())->toBe([[$product->id, $transactionId, 2.0]]);
});

function shoppingAssistantReportFilters(Website $website, array $overrides = []): array
{
    return array_merge(['website_ids' => [$website->id], 'search_website_ids' => [$website->id], 'language' => null, 'entry_point' => null, 'shop_type' => null, 'country' => null], $overrides);
}

function shoppingAssistantReport(Website $website, string $from, string $to, array $overrides = []): array
{
    return GetShoppingAssistantReport::run(shoppingAssistantReportFilters($website, $overrides), CarbonImmutable::parse($from), CarbonImmutable::parse($to), 'GBP', true);
}

function shoppingAssistantShownAt(Customer $customer, array $productIds, string $at): ShoppingAssistantConversation
{
    Carbon::setTestNow(CarbonImmutable::parse($at));
    $conversation = shoppingAssistantShown($customer, $productIds)->conversation;
    Carbon::setTestNow();

    return $conversation;
}

function shoppingAssistantOrderLine(Customer $customer, Product $product, ?string $submittedAt, float $amount, array $transaction = []): int
{
    $shop  = $customer->shop;
    $state = $transaction['state'] ?? 'submitted';
    $date  = $submittedAt ?? now()->toDateTimeString();

    $orderId = DB::table('orders')->insertGetId([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $shop->currency_id,
        'tax_category_id' => TaxCategory::firstOrFail()->id,
        'slug'            => 'sa-'.uniqid(),
        'state'           => $state,
        'submitted_at'    => $submittedAt,
        'net_amount'      => $amount,
        'org_net_amount'  => $amount,
        'grp_net_amount'  => $amount,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => $date,
    ]);

    return DB::table('transactions')->insertGetId(array_merge([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'order_id'        => $orderId,
        'tax_category_id' => TaxCategory::firstOrFail()->id,
        'data'            => '{}',
        'date'            => $date,
        'model_type'      => 'Product',
        'model_id'        => $product->id,
        'family_id'       => $product->family_id,
        'state'           => $state,
        'submitted_at'    => $submittedAt,
        'net_amount'      => $amount,
        'grp_net_amount'  => $amount,
        'is_gift'         => false,
    ], $transaction));
}

function shoppingAssistantVariantOption(Product $leader, Product $option): void
{
    $masterVariantId = DB::table('master_variants')->insertGetId(['group_id' => $leader->group_id, 'code' => 'SAV-'.uniqid(), 'slug' => 'sav-'.uniqid(), 'data' => '{}']);
    $variantId       = DB::table('variants')->insertGetId([
        'group_id'          => $leader->group_id,
        'organisation_id'   => $leader->organisation_id,
        'shop_id'           => $leader->shop_id,
        'family_id'         => $leader->family_id,
        'leader_id'         => $leader->id,
        'master_variant_id' => $masterVariantId,
        'code'              => 'SAV-'.uniqid(),
        'slug'              => 'sav-'.uniqid(),
        'data'              => '{}',
    ]);
    DB::table('products')->where('id', $option->id)->update(['variant_id' => $variantId]);
}

test('the assistant report counts a line ordered within the attribution window of the first showing, once, and for variant options of the shown leader', function () {
    GetCurrencyExchange::mock()->shouldReceive('handle')->andReturn(1.0);
    $website  = createWebsite($this->shop);
    $customer = shoppingAssistantCustomer($this->shop);
    $shown    = shoppingAssistantProduct($this->product);
    $leader   = shoppingAssistantProduct($this->product);
    $option   = shoppingAssistantProduct($this->product);
    shoppingAssistantVariantOption($leader, $option);

    $first  = shoppingAssistantShownAt($customer, [$shown->id, $leader->id], '2024-01-05 10:00:00');
    $second = shoppingAssistantShownAt($customer, [$shown->id], '2024-01-06 10:00:00');

    $lastSecond = shoppingAssistantOrderLine($customer, $shown, '2024-01-12 09:59:59', 10);
    shoppingAssistantOrderLine($customer, $shown, '2024-01-13 10:00:00', 20);
    shoppingAssistantOrderLine($customer, $shown, '2024-01-05 09:00:00', 40);
    $cancelled = shoppingAssistantOrderLine($customer, $shown, '2024-01-07 10:00:00', 80, ['state' => 'cancelled']);
    shoppingAssistantOrderLine($customer, $shown, null, 160, ['state' => 'creating']);
    shoppingAssistantOrderLine($customer, $shown, '2024-01-07 10:00:00', 320, ['deleted_at' => '2024-01-08 10:00:00']);
    shoppingAssistantOrderLine($customer, $shown, '2024-01-07 10:00:00', 640, ['is_gift' => true]);
    shoppingAssistantOrderLine(shoppingAssistantCustomer($this->shop), $shown, '2024-01-07 10:00:00', 1280);
    shoppingAssistantOrderLine($customer, $option, '2024-01-08 10:00:00', 5);
    shoppingAssistantOrderLine($customer, $shown, '2024-01-06 12:00:00', 1);

    foreach ([$lastSecond, $cancelled] as $transactionId) {
        $first->events()->create(['type' => ShoppingAssistantEventTypeEnum::ADDED, 'product_id' => $shown->id, 'transaction_id' => $transactionId, 'created_at' => '2024-01-05 10:05:00']);
    }

    $this->travelTo(CarbonImmutable::parse('2024-03-01 12:00:00'));
    $report = shoppingAssistantReport($website, '2024-01-01', '2024-01-31');

    expect($report['kpis']['revenue']['value'])->toBe(16.0)
        ->and($report['kpis']['orders']['value'])->toBe(3)
        ->and($report['kpis']['strict_orders']['value'])->toBe(1)
        ->and($report['kpis']['strict_revenue']['value'])->toBe(10.0)
        ->and($report['kpis']['new_families']['value'])->toBe(0)
        ->and(collect($report['funnel'])->pluck('value', 'key')->only(['conversations', 'shown', 'added', 'ordered'])->all())->toBe(['conversations' => 2, 'shown' => 2, 'added' => 1, 'ordered' => 2])
        ->and($report['mature_until'])->toBeNull();

    $weekly = shoppingAssistantReport($website, '2023-12-01', '2024-02-29')['trend'];
    $this->travelBack();

    $weeks = collect($weekly['points'])->keyBy('date');

    expect($second->id)->not->toBe($first->id)
        ->and($weekly['granularity'])->toBe('week')
        ->and($weeks->keys()->first())->toBe('2023-11-27')
        ->and($weeks->keys()->last())->toBe('2024-02-26')
        ->and($weeks)->toHaveCount(14)
        ->and($weeks->keys()->every(fn (string $date) => CarbonImmutable::parse($date)->isMonday()))->toBeTrue()
        ->and($weeks['2024-01-01']['conversations'])->toBe(2)
        ->and($weeks['2024-01-01']['ordered'])->toBe(2)
        ->and($weeks['2024-01-08']['conversations'])->toBe(0)
        ->and($weeks['2023-12-25']['conversations'])->toBe(0);
});

test('the assistant report leaves out staff and command line conversations, rates conversion on mature conversations only and compares with the period before', function () {
    GetCurrencyExchange::mock()->shouldReceive('handle')->andReturn(1.0);
    $website   = createWebsite($this->shop);
    $product   = shoppingAssistantProduct($this->product);
    $returning = shoppingAssistantCustomer($this->shop);
    $late      = shoppingAssistantCustomer($this->shop);

    shoppingAssistantShownAt($returning, [$product->id], '2023-05-20 10:00:00');
    $ordered = shoppingAssistantShownAt($returning, [$product->id], '2023-06-02 10:00:00');
    $ordered->events()->create(['type' => ShoppingAssistantEventTypeEnum::CLICKED, 'product_id' => $product->id, 'created_at' => '2023-06-02 10:01:00']);
    $ordered->events()->create(['type' => ShoppingAssistantEventTypeEnum::ADDED, 'product_id' => $product->id, 'created_at' => '2023-06-02 10:02:00']);
    shoppingAssistantOrderLine($returning, $product, '2023-06-03 10:00:00', 30);

    Carbon::setTestNow(CarbonImmutable::parse('2023-06-03 10:00:00'));
    AskShoppingAssistant::make()->storeCustomerMessage(StartShoppingAssistantConversation::run(shoppingAssistantCustomer($this->shop), ShoppingAssistantEntryPointEnum::PROFILE), 'Hello');
    Carbon::setTestNow();

    shoppingAssistantShownAt($late, [$product->id], '2023-06-18 10:00:00');
    shoppingAssistantOrderLine($late, $product, '2023-06-18 11:00:00', 50);

    $staff = shoppingAssistantShownAt(shoppingAssistantCustomer($this->shop), [$product->id], '2023-06-05 10:00:00')->customer;
    $staff->update(['is_staff' => true]);
    shoppingAssistantOrderLine($staff, $product, '2023-06-10 10:00:00', 1000);
    shoppingAssistantOrderLine(shoppingAssistantCustomer($this->shop), $product, '2023-06-10 10:00:00', 100);
    shoppingAssistantShownAt(shoppingAssistantCustomer($this->shop), [$product->id], '2023-06-05 10:00:00')->update(['entry_point' => ShoppingAssistantEntryPointEnum::COMMAND]);

    $this->travelTo(CarbonImmutable::parse('2023-06-20 12:00:00'));
    $report = shoppingAssistantReport($website, '2023-06-01', '2023-06-20');
    $this->travelBack();

    $points = collect($report['trend']['points'])->keyBy('date');

    expect($report['mature_until'])->toBe('2023-06-13')
        ->and($report['kpis']['conversations'])->toBe(['value' => 3, 'previous' => 1])
        ->and($report['kpis']['customers']['value'])->toBe(3)
        ->and($report['kpis']['returning_customers']['value'])->toBe(1)
        ->and($report['kpis']['conversion_rate'])->toBe(['value' => 0.5, 'previous' => 0.0])
        ->and($report['kpis']['revenue'])->toBe(['value' => 80.0, 'previous' => 0.0])
        ->and($report['kpis']['new_families']['value'])->toBe(2)
        ->and(collect($report['funnel'])->pluck('value', 'key')->all())->toBe(['conversations' => 3, 'shown' => 2, 'clicked' => 1, 'added' => 1, 'ordered' => 2])
        ->and(collect($report['funnel'])->firstWhere('key', 'shown')['previous'])->toBe(1)
        ->and($report['drop_off'])->toBe([['questions' => '1', 'conversations' => 3], ['questions' => '2', 'conversations' => 0], ['questions' => '3', 'conversations' => 0], ['questions' => '4+', 'conversations' => 0]])
        ->and($report['trend']['granularity'])->toBe('day')
        ->and($points)->toHaveCount(20)
        ->and($points['2023-06-02']['ordered'])->toBe(1)
        ->and($points['2023-06-10']['conversations'])->toBe(0)
        ->and($points['2023-06-13']['complete'])->toBeTrue()
        ->and($points['2023-06-14']['complete'])->toBeFalse()
        ->and($report['kpis']['aov_assistant']['value'])->toBe(40.0)
        ->and($report['kpis']['aov_others']['value'])->toBe(100.0)
        ->and(collect($report['families'])->firstWhere('id', $product->family_id))->toBe([
            'id'      => $product->family_id,
            'code'    => $product->family->code,
            'name'    => $product->family->name,
            'shown'   => 2,
            'clicked' => 1,
            'added'   => 1,
            'ordered' => 2,
            'revenue' => 80.0,
        ]);
});

test('the assistant report search block counts submitted searches, rescued zero results and suggestion kinds, and names the filters it cannot apply', function () {
    GetCurrencyExchange::mock()->shouldReceive('handle')->andReturn(1.0);
    $website = createWebsite($this->shop);
    $log     = fn (array $data) => DB::table('website_search_logs')->insert(array_merge([
        'ulid'            => (string) Str::ulid(),
        'group_id'        => $website->group_id,
        'organisation_id' => $website->organisation_id,
        'shop_id'         => $website->shop_id,
        'website_id'      => $website->id,
        'scope'           => 'catalogue_page',
        'results_count'   => 0,
        'created_at'      => '2022-03-05 10:00:00',
    ], $data));

    $log(['query' => 'Lavender Oil ', 'results_count' => 5, 'clicked_at' => '2022-03-05 10:01:00']);
    $log(['query' => 'xyzzq', 'suggestion_kind' => 'did_you_mean', 'suggestion_clicked_at' => '2022-03-05 10:01:00']);
    $log(['query' => 'XYZZQ']);
    $log(['query' => 'xyzzq', 'scope' => 'dropdown']);
    $log(['query' => 'xyzzq', 'created_at' => '2022-02-20 10:00:00']);

    $search = shoppingAssistantReport($website, '2022-03-01', '2022-03-10', ['entry_point' => 'chat', 'country' => 'GB'])['search'];

    expect($search['ignored_filters'])->toBe(['entry_point', 'country'])
        ->and($search['searches'])->toBe(['value' => 3, 'previous' => 1])
        ->and($search['zero_results']['value'])->toBe(2)
        ->and($search['suggestion_clicks']['value'])->toBe(1)
        ->and($search['zero_rescued']['value'])->toBe(1)
        ->and($search['by_kind'])->toHaveCount(7)
        ->and(collect($search['by_kind'])->pluck('clicks', 'kind')->all())->toMatchArray(['did_you_mean' => 1, 'family' => 0])
        ->and($search['top_zero_queries'][0])->toBe(['query' => 'xyzzq', 'searches' => 2, 'rescued' => 1])
        ->and(collect($search['top_queries'])->firstWhere('query', 'lavender oil'))->toBe(['query' => 'lavender oil', 'searches' => 1, 'clicks' => 1]);
});

test('the group AI assistant report is only for group-wide viewers, a shop webmaster uses the website one, and a transcript of another website is not found there', function () {
    GetCurrencyExchange::mock()->shouldReceive('handle')->andReturn(1.0);
    $website  = createWebsite($this->shop);
    $product  = shoppingAssistantProduct($this->product);
    $customer = shoppingAssistantCustomer($this->shop);
    $ordered  = shoppingAssistantShownAt($customer, [$product->id], '2021-04-02 10:00:00');
    shoppingAssistantOrderLine($customer, $product, '2021-04-03 10:00:00', 30);
    $clicked = shoppingAssistantShownAt(shoppingAssistantCustomer($this->shop), [$product->id], '2021-04-03 10:00:00');
    $clicked->events()->create(['type' => ShoppingAssistantEventTypeEnum::CLICKED, 'product_id' => $product->id, 'created_at' => '2021-04-03 10:01:00']);
    $elsewhere = shoppingAssistantShownAt(shoppingAssistantCustomer($this->shop), [$product->id], '2021-04-03 10:00:00');
    $elsewhere->update(['website_id' => DB::table('websites')->where('id', '!=', $website->id)->value('id') ?? 32000]);

    $websiteParameters = [$this->organisation->slug, $this->shop->slug, $website->slug];
    $query             = ['from' => '2021-04-01', 'to' => '2021-04-30', 'tab' => 'conversations'];

    $this->withoutVite();
    $this->get(route('grp.websites.shopping_assistant.show', $query))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Web/ShoppingAssistantPerformance', false)->where('filters.website_locked', false));

    $this->get(route('grp.websites.shopping_assistant.show', [...$query, 'outcome' => 'ordered']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('conversations.data', fn ($rows) => collect($rows)->pluck('ulid')->all() === [$ordered->ulid])
            ->where('conversations.data.0.ordered', true)
            ->where('conversations.data.0.revenue', fn ($revenue) => (float) $revenue === 30.0));

    $this->get(route('grp.websites.shopping_assistant.show', [...$query, 'outcome' => 'clicked']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('conversations.data', fn ($rows) => collect($rows)->pluck('ulid')->all() === [$clicked->ulid]));

    $this->get(route('grp.websites.shopping_assistant.conversations.show', [$ordered->ulid]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Web/ShoppingAssistantConversation', false)
            ->where('messages.1.products.0.ordered', true)
            ->where('messages.1.products.0.revenue', fn ($revenue) => (float) $revenue === 30.0));

    $webmaster = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    actingAsUserWithOnlyPermissions($webmaster, ["web.{$this->shop->id}.view"]);

    $this->get(route('grp.websites.shopping_assistant.show'))->assertForbidden();
    $this->get(route('grp.websites.shopping_assistant.conversations.show', [$ordered->ulid]))->assertForbidden();

    $this->get(route('grp.org.shops.show.web.analytics.shopping_assistant.show', [...$websiteParameters, ...$query]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.website_locked', true)
            ->where('conversations.data', fn ($rows) => collect($rows)->pluck('ulid')->sort()->values()->all() === collect([$ordered->ulid, $clicked->ulid])->sort()->values()->all()));

    $this->get(route('grp.org.shops.show.web.analytics.shopping_assistant.show', [...$websiteParameters, 'from' => '2021-04-01', 'to' => '2021-04-30']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('overview.kpis.revenue.value', fn ($revenue) => (float) $revenue === 30.0));

    $this->get(route('grp.org.shops.show.web.analytics.shopping_assistant.conversations.show', [...$websiteParameters, $ordered->ulid]))->assertOk();
    $this->get(route('grp.org.shops.show.web.analytics.shopping_assistant.conversations.show', [...$websiteParameters, $elsewhere->ulid]))->assertNotFound();
});

function shoppingAssistantSpend(Website $website, float $cost, ?string $createdAt = null, ShoppingAssistantEntryPointEnum $entryPoint = ShoppingAssistantEntryPointEnum::SEARCH): void
{
    $conversation = StartShoppingAssistantConversation::run(shoppingAssistantCustomer($website->shop), $entryPoint);
    $conversation->update(['website_id' => $website->id]);
    $message = $conversation->messages()->create(['role' => ShoppingAssistantMessageRoleEnum::ASSISTANT, 'body' => 'Here you go.', 'cost' => $cost]);
    if ($createdAt) {
        DB::table('shopping_assistant_messages')->where('id', $message->id)->update(['created_at' => $createdAt]);
    }
}

function shoppingAssistantBudget(Website $website): array
{
    GetShoppingAssistantBudget::forget($website);

    return GetShoppingAssistantBudget::run($website);
}

test('the assistant budget is the config default unless the website sets one, and a budget of 0 pauses new questions at once', function () {
    $website  = shoppingAssistantStorefront($this);
    $customer = shoppingAssistantCustomer($this->shop);
    config(['services.shopping_assistant.monthly_budget' => 1000]);

    UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => null]);
    expect(shoppingAssistantBudget($website))->toMatchArray(['monthly' => 1000.0, 'paused' => false, 'resumes_on' => now()->startOfMonth()->addMonth()->toDateString()]);

    UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => 500]);
    expect(shoppingAssistantBudget($website)['monthly'])->toBe(500.0);

    UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => 0]);
    expect(shoppingAssistantBudget($website))->toMatchArray(['monthly' => 0.0, 'paused' => true])
        ->and(GetShoppingAssistantSettings::run($website->refresh(), $customer, true))->toBeNull()
        ->and(GetShoppingAssistantSettings::run($website, $customer))->toBe(['name' => 'Ava']);

    UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => 1000]);
    expect(GetShoppingAssistantSettings::run($website->refresh(), $customer, true))->toBe(['name' => 'Ava']);

    $settings = $website->settings;
    DB::table('websites')->where('id', $website->id)->update(['settings' => json_encode(data_set($settings, 'shopping_assistant.monthly_budget', 0))]);
    expect(data_get($website->settings, 'shopping_assistant.monthly_budget'))->toEqual(1000)
        ->and(GetShoppingAssistantBudget::run($website)['paused'])->toBeFalse()
        ->and(shoppingAssistantBudget($website)['paused'])->toBeTrue();

    UpdateWebsite::make()->action($website->refresh(), ['shopping_assistant_monthly_budget' => null]);
    expect(GetShoppingAssistantBudget::run($website)['monthly'])->toBe(1000.0);
});

test('a spent budget stops new questions and the Ask button, while an answer under way, clicks and this month\'s command line costs still count', function () {
    Queue::fake();
    $website  = shoppingAssistantStorefront($this);
    $customer = shoppingAssistantCustomer($this->shop);
    $webUser  = shoppingAssistantWebUser($customer);
    $product  = shoppingAssistantProduct($this->product);
    $message  = shoppingAssistantShown($customer, [$product->id]);
    $base     = 'http://'.$website->domain.'/json/';

    $before = shoppingAssistantBudget($website)['spent'];
    shoppingAssistantSpend($website, 0.40, now()->startOfMonth()->subDay()->toDateTimeString());
    shoppingAssistantSpend($website, 0.25, null, ShoppingAssistantEntryPointEnum::COMMAND);
    $spent = shoppingAssistantBudget($website)['spent'];
    expect($spent)->toEqualWithDelta($before + 0.25, 0.000001);

    UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => $spent + 0.01]);
    $this->actingAs($webUser, 'retina')->getJson($base.'ecom-customer-data')->assertOk()->assertJsonPath('shopping_assistant.name', 'Ava');
    $this->actingAs($webUser, 'retina')->postJson($base.'shopping-assistant/ask', ['message' => 'Candles'])->assertStatus(202);

    foreach ([$spent, $spent - 0.01] as $budget) {
        UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => $budget]);
        $this->actingAs($webUser, 'retina')->getJson($base.'ecom-customer-data')->assertOk()->assertJsonPath('shopping_assistant', null);
        $this->actingAs($webUser, 'retina')->postJson($base.'shopping-assistant/ask', ['message' => 'Mugs'])->assertNotFound();
        $this->actingAs($webUser, 'retina')->getJson($base.'shopping-assistant/'.$message->conversation->ulid)->assertOk();
    }

    $this->actingAs($webUser, 'retina')->postJson($base.'shopping-assistant/'.$message->conversation->ulid.'/events', ['type' => 'clicked', 'product_id' => $product->id, 'message_id' => $message->id])->assertNoContent();
    expect($message->conversation->events()->where('type', ShoppingAssistantEventTypeEnum::CLICKED)->count())->toBe(1);
    AnswerShoppingAssistantMessage::assertPushed(1);

    UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => null]);
});

test('the AI assistant report sends this month\'s budget of each website with the assistant on, and nothing when it is off', function () {
    GetCurrencyExchange::mock()->shouldReceive('handle')->andReturn(1.0);
    $website = shoppingAssistantStorefront($this);
    UpdateWebsite::make()->action($website, ['shopping_assistant_monthly_budget' => 12.5]);
    $spent = shoppingAssistantBudget($website)['spent'];
    $url   = route('grp.org.shops.show.web.analytics.shopping_assistant.show', [$this->organisation->slug, $this->shop->slug, $website->slug]);

    $this->withoutVite();
    $this->get($url)->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('budgets', 1)
            ->where('budgets.0.website', $website->domain)
            ->where('budgets.0.monthly', fn ($monthly) => (float) $monthly === 12.5)
            ->where('budgets.0.spent', fn ($amount) => abs((float) $amount - $spent) < 0.000001)
            ->where('budgets.0.paused', $spent >= 12.5)
            ->where('budgets.0.resumes_on', now()->startOfMonth()->addMonth()->toDateString()));

    UpdateWebsite::make()->action($website, ['shopping_assistant_enabled' => false, 'shopping_assistant_monthly_budget' => null]);
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('budgets', 0));
});

test('the assistant is called Aura unless the group sets a name, and a website\'s own name wins', function () {
    $website  = shoppingAssistantStorefront($this);
    $customer = shoppingAssistantCustomer($this->shop);
    $group    = $website->group;

    expect(GetShoppingAssistantSettings::run($website, $customer))->toBe(['name' => 'Ava']);

    UpdateWebsite::make()->action($website, ['shopping_assistant_name' => null]);
    UpdateGroupSettings::make()->action($group, ['shopping_assistant_name' => null]);
    expect(GetShoppingAssistantSettings::run($website->refresh(), $customer))->toBe(['name' => 'Aura']);

    UpdateGroupSettings::make()->action($group, ['shopping_assistant_name' => 'Sage']);
    expect(GetShoppingAssistantSettings::run($website, $customer))->toBe(['name' => 'Sage'])
        ->and(data_get($group->refresh()->settings, 'shopping_assistant.name'))->toBe('Sage');

    UpdateWebsite::make()->action($website, ['shopping_assistant_name' => 'Ava']);
    expect(GetShoppingAssistantSettings::run($website->refresh(), $customer))->toBe(['name' => 'Ava']);

    UpdateGroupSettings::make()->action($group, ['shopping_assistant_name' => '  ']);
    UpdateWebsite::make()->action($website, ['shopping_assistant_name' => null]);
    expect(GetShoppingAssistantSettings::run($website->refresh(), $customer))->toBe(['name' => 'Aura'])
        ->and(data_get($group->refresh()->settings, 'shopping_assistant.name'))->toBeNull();
});
