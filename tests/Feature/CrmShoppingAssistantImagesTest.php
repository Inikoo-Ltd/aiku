<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\ShoppingAssistant\AnswerShoppingAssistantMessage;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantEngineerTicket;
use App\Actions\CRM\ShoppingAssistant\WipeShoppingAssistantConversations;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Actions\Web\Website\UpdateWebsite;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();

    config(['services.openrouter.api_key' => 'test', 'services.shopping_assistant.plain_search' => false, 'services.shopping_assistant.light_model' => null, 'services.shopping_assistant.premium_model' => null]);
    actingAs($this->user);
});

/**
 * @return array{0: App\Models\Web\Website, 1: Customer, 2: App\Models\CRM\WebUser}
 */
function merlinImagesStorefront(object $test): array
{
    $website = createWebsite($test->shop);
    $website->update(['status' => true]);
    UpdateWebsite::make()->action($website, ['shopping_assistant_enabled' => true, 'shopping_assistant_name' => 'Ava']);
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($website->domain);

    $customer = StoreCustomer::make()->action($test->shop, Customer::factory()->definition());
    $webUser  = StoreWebUser::make()->action($customer, ['username' => 'mi-'.uniqid(), 'email' => 'mi-'.uniqid().'@example.com', 'password' => 'test'], sendRegistrationEmail: false);

    return [$website->refresh(), $customer, $webUser];
}

test('a customer sends Merlin a screenshot: it is kept on their message, read by the main model, shown back to them and to staff, and wiped with the conversation', function () {
    [$website, $customer, $webUser] = merlinImagesStorefront($this);
    $url = 'http://'.$website->domain.'/json/shopping-assistant/ask';
    Queue::fake();

    $this->actingAs($webUser, 'retina')->post($url, ['images' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertStatus(422);
    $this->actingAs($webUser, 'retina')->post($url, ['images' => array_map(fn ($n) => UploadedFile::fake()->image("shot-$n.png"), range(1, ShoppingAssistantMessage::MAX_IMAGES + 1))], ['Accept' => 'application/json'])->assertStatus(422);

    $response = $this->actingAs($webUser, 'retina')->post($url, ['entry_point' => 'chat', 'images' => [UploadedFile::fake()->image('checkout.png', 2400, 1200)]], ['Accept' => 'application/json'])->assertStatus(202);

    $conversation = ShoppingAssistantConversation::where('ulid', $response->json('conversation'))->firstOrFail();
    $question     = ShoppingAssistantMessage::findOrFail($response->json('message_id'));
    expect($question->body)->toBe('')
        ->and($question->images()->pluck('name')->all())->toBe(['checkout.png']);

    $sent = null;
    Http::fake(['*chat/completions' => function (Request $request) use (&$sent) {
        $sent ??= $request->data();

        return Http::response([
            'choices' => [['finish_reason' => 'tool_calls', 'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'call_present', 'type' => 'function', 'function' => ['name' => 'present_products', 'arguments' => json_encode(['reply' => 'I can see your payment page.', 'must_haves' => [], 'add_ons' => [], 'follow_ups' => []])]]]]]],
            'usage'   => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'cost' => 0.01],
        ]);
    }]);
    AnswerShoppingAssistantMessage::run($question);

    $userTurn = collect($sent['messages'])->last(fn (array $message) => $message['role'] === 'user');
    $image    = collect($userTurn['content'])->firstWhere('type', 'image_url');
    [$width, $height] = getimagesizefromstring(base64_decode(Illuminate\Support\Str::after($image['image_url']['url'], 'base64,')));
    expect($sent['model'])->toContain((string) config('services.shopping_assistant.model'))
        ->and($userTurn['content'][0])->toBe(['type' => 'text', 'text' => 'The customer sent these images without a message.'])
        ->and(collect($sent['messages'])->firstWhere('role', 'system')['content'])->toContain('say in one sentence what the images show and ask what the customer needs help with')
        ->and($image['image_url']['url'])->toStartWith('data:image/jpeg;base64,')
        ->and(max($width, $height))->toBe(ShoppingAssistantMessage::MODEL_IMAGE_SIDE);

    $shown = $this->actingAs($webUser, 'retina')->getJson('http://'.$website->domain.'/json/shopping-assistant/'.$conversation->ulid)->assertOk()->json('messages');
    expect($shown[0]['images'])->toHaveCount(1)
        ->and($shown[0]['images'][0]['name'])->toBe('checkout.png')
        ->and($shown[0]['images'][0]['url'])->not->toBeEmpty();

    WipeShoppingAssistantConversations::run([$conversation->id]);

    expect($question->fresh()->load('media')->images())->toBeEmpty();
});

test('what the customer showed Merlin goes on the MER ticket once, also when they report more to it', function () {
    $customer     = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $conversation = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::CHAT);
    AskShoppingAssistant::make()->storeCustomerMessage($conversation, 'It fails here', [UploadedFile::fake()->image('error.png')]);
    Http::fake(['*alpha/decisions' => Http::response(['answers' => ['answer' => ['noul' => 0.95]]])]);

    $first = StoreShoppingAssistantEngineerTicket::make()->fromQuestion($conversation, 'The checkout page shows an error when paying.', 'It fails here');
    expect($first['status'])->toBe('created')
        ->and($first['ticket']->media()->where('collection_name', 'ticket_images')->pluck('name')->all())->toBe(['error.png']);

    $again = StoreShoppingAssistantEngineerTicket::make()->fromQuestion($conversation, 'Still failing when paying.', 'Still failing');
    expect($again['status'])->toBe('already_reported')
        ->and($again['ticket']->media()->where('collection_name', 'ticket_images')->count())->toBe(1);
});
