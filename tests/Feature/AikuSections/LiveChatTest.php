<?php

use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Models\SysAdmin\Organisation;
use App\Models\CRM\Livechat\ChatEvent;
use App\Models\CRM\Livechat\ChatSession;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Actions\CRM\ChatSession\StoreChatSession;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->action = new StoreChatSession();

});

test('can create chat session for guest with minimal data', function () {
    $modelData = [
        'language_id' => 68,
        'priority' => ChatPriorityEnum::NORMAL->value,
    ];

    $chatSession = $this->action->handle($modelData);

    expect($chatSession)->toBeInstanceOf(ChatSession::class)
        ->and($chatSession->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($chatSession->web_user_id)->toBeNull()
        ->and($chatSession->language_id)->toBe(68)
        ->and($chatSession->priority)->toBe(ChatPriorityEnum::NORMAL)
        ->and($chatSession->guest_identifier)->toMatch('/^guest_[a-zA-Z0-9]{16}$/')
        ->and($chatSession->ai_model_version)->toBe('default')
        ->and($chatSession->ulid)->not->toBeNull();
});

test('can create chat session for guest with custom guest identifier', function () {
    $guestIdentifier = 'guest_custom_123';

    $modelData = [
        'language_id' => 68,
        'priority' => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => $guestIdentifier,
    ];

    $chatSession = $this->action->handle($modelData);

    expect($chatSession->guest_identifier)->toBe($guestIdentifier);
});

test('can create chat session for authenticated web user', function () {
    $organisation = Organisation::first() ?? Organisation::factory()->create();
    $website = Website::first() ?? Website::factory()->create();
    $customer = Customer::first() ?? Customer::factory()->create();
    $group = Group::first() ?? Group::factory()->create();

    $webUser = WebUser::factory()->create([
        'organisation_id' => $organisation->id,
        'group_id' => $group->id,
        'website_id' => $website->id,
        'customer_id' => $customer->id,
        'type' => WebUserTypeEnum::WEB->value,
    ]);

    $modelData = [
        'web_user_id' => $webUser->id,
        'language_id' => 68,
        'priority' => ChatPriorityEnum::HIGH->value,
        'ai_model_version' => 'gpt-4-turbo',
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
        'priority' => ChatPriorityEnum::NORMAL->value,
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
    $website = Website::first() ?? Website::factory()->create();
    $customer = Customer::first() ?? Customer::factory()->create();
    $group = Group::first() ?? Group::factory()->create();

    $webUser = WebUser::factory()->create([
        'organisation_id' => $organisation->id,
        'group_id' => $group->id,
        'website_id' => $website->id,
        'customer_id' => $customer->id,
        'type' => WebUserTypeEnum::WEB->value,
    ]);

    $modelData = [
        'web_user_id' => $webUser->id,
        'language_id' => 68,
        'priority' => ChatPriorityEnum::NORMAL->value,
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
        'web_user_id', 'language_id', 'guest_identifier',
        'ai_model_version', 'priority', 'ulid'
    ]);

    expect($rules['web_user_id'])->toEqual(['nullable', 'exists:web_users,id'])
        ->and($rules['language_id'])->toEqual(['required', 'exists:languages,id'])
        ->and($rules['priority'])->toEqual(['required', Rule::enum(ChatPriorityEnum::class)])
        ->and($rules['guest_identifier'])->toEqual(['nullable', 'string', 'max:255'])
        ->and($rules['ai_model_version'])->toEqual(['nullable', 'string', 'max:50'])
        ->and($rules['ulid'])->toEqual(['sometimes', 'string', 'size:26', 'unique:chat_sessions,ulid']);
});


test('json response structure is correct', function () {
    $modelData = [
        'language_id' => 68,
        'priority' => ChatPriorityEnum::NORMAL->value,
    ];

    $chatSession = $this->action->handle($modelData);
    $response = $this->action->jsonResponse($chatSession);

    expect($response)->toBeInstanceOf(JsonResponse::class);

    $responseData = $response->getData(true);

    expect($responseData)->toHaveKeys(['success', 'message', 'data'])
        ->and($responseData['success'])->toBeTrue()
        ->and($responseData['message'])->toBe('Chat session started successfully')
        ->and($responseData['data'])->toHaveKeys([
            'ulid',
            'status',
            'is_guest',
            'guest_identifier',
            'created_at'
        ])
        ->and($responseData['data']['ulid'])->toBe($chatSession->ulid->toString())
        ->and($responseData['data']['status'])->toBe($chatSession->status->value)
        ->and($responseData['data']['is_guest'])->toBeTrue()
        ->and($responseData['data']['guest_identifier'])->toBe($chatSession->guest_identifier)
        ->and($responseData['data']['created_at'])->toBe($chatSession->created_at->toJSON());
});

test('html response returns json response', function () {
    $modelData = [
        'language_id' => 68,
        'priority' => ChatPriorityEnum::NORMAL->value,
    ];

    $chatSession = $this->action->handle($modelData);
    $htmlResponse = $this->action->htmlResponse($chatSession);
    $jsonResponse = $this->action->jsonResponse($chatSession);

    expect($htmlResponse->getContent())->toBe($jsonResponse->getContent())
        ->and($htmlResponse->headers->get('Content-Type'))->toContain('application/json');
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
            'priority' => $priority,
        ];

        $chatSession = $this->action->handle($modelData);

        expect($chatSession->priority->value)->toBe($priority);
    }
});

test('uses default values when not provided', function () {
    $modelData = [
        'language_id' => 68,
        'priority' => ChatPriorityEnum::NORMAL->value,
    ];

    $chatSession = $this->action->handle($modelData);

    expect($chatSession->ai_model_version)->toBe('default')
        ->and($chatSession->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($chatSession->language_id)->toBe(68);
});

test('guest identifier is generated when not provided for guest', function () {
    $modelData = [
        'language_id' => 68,
        'priority' => ChatPriorityEnum::NORMAL->value,
    ];

    $chatSession1 = $this->action->handle($modelData);
    $chatSession2 = $this->action->handle($modelData);


    expect($chatSession1->guest_identifier)->not->toBeNull()
        ->and($chatSession2->guest_identifier)->not->toBeNull()
        ->and($chatSession1->guest_identifier)->not->toBe($chatSession2->guest_identifier);
});
