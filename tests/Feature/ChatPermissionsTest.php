<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\HumanResources\Employee;
use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
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

test('customer service permission makes an agent, creating the profile on first use', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $csUser = User::factory()->create(['group_id' => $this->organisation->group_id]);
    setPermissionsTeamId($this->user->group_id);
    $csUser->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    expect(ChatAgent::where('user_id', $csUser->id)->exists())->toBeFalse();

    $this->actingAs($csUser);
    $agent = CloseChatSession::make()->getCurrentAgent($session);

    expect($agent)->toBeInstanceOf(ChatAgent::class)
        ->and($agent->user_id)->toBe($csUser->id)
        ->and($agent->is_online)->toBeFalse()
        ->and(ChatAgent::where('user_id', $csUser->id)->count())->toBe(1);

    expect(CloseChatSession::make()->getCurrentAgent($session)->id)->toBe($agent->id);
});

test('customer service viewer gets no write access to chat', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $viewer = User::factory()->create(['group_id' => $this->organisation->group_id]);
    setPermissionsTeamId($this->user->group_id);
    $viewer->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_VIEWER->value, $this->shop));

    $this->actingAs($viewer);

    expect(CloseChatSession::make()->getCurrentAgent($session))->toBeNull()
        ->and(ChatAgent::where('user_id', $viewer->id)->exists())->toBeFalse();
});

test('a customer service viewer sees the shop knowledge notes but not the controls to change them', function () {
    actingAs($this->user);
    $parameters = [$this->organisation->slug, $this->shop->slug];
    $note       = \App\Models\Chat\ChatKnowledgeEntry::create(['group_id' => $this->shop->group_id, 'organisation_id' => $this->shop->organisation_id, 'shop_id' => $this->shop->id, 'kind' => 'note', 'title' => 'Shipping', 'body' => 'No shipping to Mars', 'source_type' => 'manual', 'is_manual' => true]);
    $canEdit    = fn () => get(route('grp.org.shops.show.chat.settings', $parameters).'?tab=policies')->viewData('page')['props']['policies']['can_edit'];

    expect($canEdit())->toBeTrue();

    $originalRoles = $this->user->roles->pluck('name')->toArray();
    actingAsUserWithRoles($this->user, [RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_VIEWER->value, $this->shop)]);
    expect($canEdit())->toBeFalse();
    $this->delete(route('grp.org.shops.show.chat.settings.knowledge.delete', [...$parameters, $note->id]))->assertForbidden();
    actingAsUserWithRoles($this->user, $originalRoles);

    $note->delete();
});

test('a departed staff member is never a chat agent', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $leaver = User::factory()->create(['group_id' => $this->organisation->group_id]);
    setPermissionsTeamId($this->user->group_id);
    $leaver->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $leaver->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
    ]);

    $this->actingAs($leaver);
    expect(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull()
        ->and(ChatAgent::available()->whereKey($agent->id)->exists())->toBeTrue();

    $assignment = $session->assignments()->create([
        'chat_agent_id' => $agent->id,
        'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_at'   => now(),
    ]);

    $leaver->update(['status' => false]);

    expect(CloseChatSession::make()->getCurrentAgent($session->fresh()))->toBeNull()
        ->and(ChatAgent::available()->whereKey($agent->id)->exists())->toBeFalse();

    // Deactivating hands the conversations back even though the roles outlive the account.
    $result = \App\Actions\Chat\Agent\RevokeChatAgentAccess::run(ChatAgent::withTrashed()->find($agent->id));

    expect($result['released'])->toBe(1)
        ->and($result['suspended'])->toBeTrue()
        ->and($assignment->fresh()->status)->toBe(ChatAssignmentStatusEnum::RESOLVED)
        ->and($session->fresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and(ChatAgent::find($agent->id))->toBeNull();
});

test('working hours follow the agent contract, then the shop, then a plain weekday', function () {
    $tz = \App\Models\Helpers\Timezone::where('name', 'Europe/London')->first();
    $this->shop->update(['timezone_id' => $tz->id, 'opening_hours' => []]);
    $shop = $this->shop->fresh();

    $employee = Employee::factory()->create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'working_hours'   => ['data' => [
            // Monday to Thursday only: this agent does not work Fridays.
            '1' => ['s' => '08:00', 'e' => '16:00', 'b' => [['s' => '12:00', 'e' => '12:30']]],
            '2' => ['s' => '08:00', 'e' => '16:00', 'b' => []],
            '3' => ['s' => '08:00', 'e' => '16:00', 'b' => []],
            '4' => ['s' => '08:00', 'e' => '16:00', 'b' => []],
        ]],
    ]);

    $at = fn (string $when) => \Illuminate\Support\Carbon::parse($when, 'Europe/London');
    $within = fn (string $when) => IsWithinWorkingHours::run($shop, $at($when), $employee);

    expect($within('2026-09-21 09:00'))->toBeTrue()          // Monday morning
        ->and($within('2026-09-21 07:59'))->toBeFalse()       // before the shift
        ->and($within('2026-09-21 16:00'))->toBeFalse()       // end is exclusive
        ->and($within('2026-09-21 12:15'))->toBeFalse()       // on a break
        ->and($within('2026-09-25 09:00'))->toBeFalse()       // Friday: not contracted
        ->and($within('2026-09-26 09:00'))->toBeFalse();      // Saturday

    // No contract: the plain weekday fallback answers instead.
    expect(IsWithinWorkingHours::run($shop, $at('2026-09-25 09:30')))->toBeTrue()
        ->and(IsWithinWorkingHours::run($shop, $at('2026-09-25 07:30')))->toBeFalse()
        ->and(IsWithinWorkingHours::run($shop, $at('2026-09-25 16:30')))->toBeFalse();

    // A public holiday of the organisation is never working time, contract or not.
    $this->organisation->holidays()->create([
        'group_id' => $this->organisation->group_id,
        'type'     => \App\Enums\HumanResources\Holiday\HolidayTypeEnum::PUBLIC->value,
        'year'     => 2026,
        'label'    => 'Test bank holiday',
        'from'     => '2026-09-21',
        'to'       => '2026-09-21',
    ]);

    expect($within('2026-09-21 09:00'))->toBeFalse();
});

test('losing customer service releases the chats and suspends the agent', function () {
    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    setPermissionsTeamId($this->user->group_id);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 1,
    ]);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $assignment = $session->assignments()->create([
        'chat_agent_id' => $agent->id,
        'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_at'   => now(),
    ]);

    // While the position stands, nothing is taken away.
    expect(\App\Actions\Chat\Agent\RevokeChatAgentAccess::run($agent))
        ->toMatchArray(['released' => 0, 'suspended' => false]);

    $user->removeRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    \App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($user);

    $result = \App\Actions\Chat\Agent\RevokeChatAgentAccess::run($agent->fresh());

    expect($result['released'])->toBe(1)
        ->and($result['suspended'])->toBeTrue()
        // The chat goes back to the shop queue instead of sitting in a name nobody can act on.
        ->and($session->fresh()->status)->toBe(ChatSessionStatusEnum::WAITING)
        ->and($assignment->fresh()->status)->toBe(ChatAssignmentStatusEnum::RESOLVED)
        ->and(ChatAgent::find($agent->id))->toBeNull()
        ->and(ChatAgent::withTrashed()->find($agent->id)->trashed())->toBeTrue();
});

test('a shop administrator manages its chats without being an agent', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    setPermissionsTeamId($this->user->group_id);

    // A shop administrator holds CRM, and used to inherit chat with it.
    $admin = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $admin->assignRole(RolesEnum::getRoleName(RolesEnum::SHOP_ADMIN->value, $this->shop));

    // A shop administrator manages its chats without ever being one of its agents.
    expect($admin->authTo(['chat-m.'.$this->shop->id]))->toBeTrue()
        ->and($admin->authTo(['chat.'.$this->shop->id]))->toBeFalse();

    $this->actingAs($admin);
    expect(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull()
        ->and(ChatAgent::findAvailableAgent(shopId: $this->shop->id)?->user_id)->not->toBe($admin->id);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $this->actingAs($clerk);
    expect($clerk->authTo(['chat.'.$this->shop->id]))->toBeTrue()
        ->and(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();
});

test('a customer service supervisor works chat as an agent and supervises it', function () {
    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    setPermissionsTeamId($this->user->group_id);

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_SUPERVISOR->value, $this->shop));

    expect($supervisor->authTo(['chat-m.'.$this->shop->id]))->toBeTrue()
        ->and($supervisor->authTo(['chat.'.$this->shop->id]))->toBeTrue()
        ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($supervisor)['agent_shops'])->toContain($this->shop->id);

    $this->actingAs($supervisor);

    expect(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();
});

test('an organisation administrator manages chat on every shop, including one opened later', function () {
    setPermissionsTeamId($this->user->group_id);

    $orgAdmin = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $orgAdmin->assignRole(RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $this->organisation));

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $this->actingAs($orgAdmin);

    // Manages without holding a single per shop permission, and without being an agent.
    expect($orgAdmin->authTo(['chat.'.$this->shop->id]))->toBeFalse()
        ->and($orgAdmin->authTo(['chat-m.'.$this->shop->id]))->toBeFalse()
        ->and(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();

    // A shop opened after the fact is covered too: the grant sits on the organisation.
    $newShop = createShop($this->organisation)[2] ?? null;
    if ($newShop) {
        $laterSession = ChatSession::create([
            'ulid'             => (string) \Illuminate\Support\Str::ulid(),
            'shop_id'          => $newShop->id,
            'language_id'      => 68,
            'status'           => ChatSessionStatusEnum::ACTIVE->value,
            'priority'         => ChatPriorityEnum::NORMAL->value,
            'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
        ]);

        expect(CloseChatSession::make()->getCurrentAgent($laterSession))->not->toBeNull();
    }
});

test('a fulfilment shop staffs chat from its own positions', function () {
    $fulfilment     = createFulfilment($this->organisation);
    $fulfilmentShop = $fulfilment->shop;
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $fulfilmentShop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    // Office clerk answers the chats: an agent, routed like any other.
    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::FULFILMENT_SHOP_CLERK->value, $fulfilment));
    $this->actingAs($clerk);
    expect($clerk->authTo(['fulfilment-chat.'.$fulfilment->id]))->toBeTrue()
        ->and(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();

    // Supervisor manages without being routed.
    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::FULFILMENT_SHOP_SUPERVISOR->value, $fulfilment));
    $this->actingAs($supervisor);
    expect($supervisor->authTo(['fulfilment-chat-m.'.$fulfilment->id]))->toBeTrue()
        ->and($supervisor->authTo(['fulfilment-chat.'.$fulfilment->id]))->toBeFalse()
        ->and(CloseChatSession::make()->getCurrentAgent($session))->not->toBeNull();

    // Warehouse staff stay out of it.
    $warehouse = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $warehouse->assignRole(RolesEnum::getRoleName(RolesEnum::FULFILMENT_WAREHOUSE_WORKER->value, $fulfilment));
    $this->actingAs($warehouse);
    expect(CloseChatSession::make()->getCurrentAgent($session))->toBeNull();
});

test('an external shop has no chat permissions at all', function () {
    $external = \App\Models\Catalogue\Shop::factory()->make()->toArray();
    $external['type'] = \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL->value;
    $externalShop     = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, $external);

    $values = \App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::getAllValues($externalShop);

    expect(collect($values)->filter(fn ($name) => str_starts_with($name, 'chat')))->toBeEmpty()
        ->and(collect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::getAllValues($this->shop))
            ->filter(fn ($name) => str_starts_with($name, 'chat')))->not->toBeEmpty();

    // Holding the customer service position on it therefore grants nothing.
    setPermissionsTeamId($this->user->group_id);
    $worker = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $worker->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $externalShop));

    expect($worker->authTo(['chat.'.$externalShop->id]))->toBeFalse();
});

test('an external shop with chat enabled gets chat permissions', function () {
    $external = \App\Models\Catalogue\Shop::factory()->make()->toArray();
    $external['type'] = \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL->value;
    $externalShop     = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, $external);

    $externalShop->update(['settings' => array_merge($externalShop->settings ?? [], ['chat' => ['enabled' => true]])]);
    $externalShop->refresh();

    expect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::shopHasChat($externalShop))->toBeTrue()
        ->and(collect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::getAllValues($externalShop))
            ->filter(fn ($name) => str_starts_with($name, 'chat')))->not->toBeEmpty();

    \App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions::run($externalShop);

    setPermissionsTeamId($this->user->group_id);
    $worker = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $worker->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $externalShop));

    expect($worker->authTo(['chat.'.$externalShop->id]))->toBeTrue();
});

test('an external shop offers offline email replies and a customer mailbox only once its chat is enabled', function () {
    $external = \App\Models\Catalogue\Shop::factory()->make()->toArray();
    $external['type'] = \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL->value;
    $externalShop     = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, $external);

    actingAs($this->user);
    $sectionLabels = fn () => collect(
        get(route('grp.org.shops.show.settings.edit', [$this->organisation->slug, $externalShop->slug]))
            ->assertOk()
            ->viewData('page')['props']['formData']['blueprint']
    )->keyBy('label');

    $sections = $sectionLabels();

    expect($sections->get('Chat widget')['fields'])->not->toHaveKey('chat_email_offline_replies')
        ->and($sections->has('Customer mailbox'))->toBeFalse();

    $externalShop->update(['settings' => array_merge($externalShop->settings ?? [], ['chat' => ['enabled' => true]])]);

    $sections = $sectionLabels();

    expect($sections->get('Chat widget')['fields'])->toHaveKey('chat_email_offline_replies')
        ->and($sections->get('Customer mailbox')['fields'])->toHaveKey('mailbox')
        ->and($sections->has('Chat'))->toBeFalse();
});

test('an external shop can be set up for meta ads and social media', function () {
    $external = \App\Models\Catalogue\Shop::factory()->make()->toArray();
    $external['type'] = \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL->value;
    $externalShop     = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, $external);

    actingAs($this->user);
    $sections = collect(
        get(route('grp.org.shops.show.settings.edit', [$this->organisation->slug, $externalShop->slug]))
            ->assertOk()
            ->viewData('page')['props']['formData']['blueprint']
    )->keyBy('label');

    expect($sections->get('Meta Ads')['fields'])->toHaveKey('meta_ads_ad_account_id')
        ->and($sections->get('Social media (Meta)')['fields'])->toHaveKeys(['meta_social_facebook_page_id', 'meta_social_instagram_account_id'])
        ->and($sections->has('Google Ads'))->toBeFalse();
});

test('a non external shop with chat disabled loses chat permissions', function () {
    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['chat' => ['enabled' => false]])]);
    $this->shop->refresh();

    expect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::shopHasChat($this->shop))->toBeFalse()
        ->and(collect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::getAllValues($this->shop))
            ->filter(fn ($name) => str_starts_with($name, 'chat')))->toBeEmpty();

    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['chat' => ['enabled' => true]])]);
    $this->shop->refresh();
});

test('the meta chat session channel follows the same rule as the rest of chat', function () {
    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp'])->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628111222333',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    makeChatWorker($clerk, $this->shop);

    $stranger = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    $channel = new \App\Broadcasting\MetaChatSessionChannel();

    expect($channel->join($clerk, $metaChatSession->ulid))->toBeTrue()
        ->and($channel->join($stranger, $metaChatSession->ulid))->toBeFalse();
});

test('regaining the position brings a suspended agent profile back', function () {
    setPermissionsTeamId($this->user->group_id);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    // Losing it suspends the profile.
    $user->removeRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    \App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($user);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    expect(\App\Actions\Chat\Agent\RevokeChatAgentAccess::run($agent->fresh())['suspended'])->toBeTrue()
        ->and(ChatAgent::find($agent->id))->toBeNull();

    // Getting it back restores it, rather than waiting for the next inbox visit.
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    \App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($user);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $result = \App\Actions\Chat\Agent\RevokeChatAgentAccess::run(ChatAgent::withTrashed()->find($agent->id));

    expect($result['restored'])->toBeTrue()
        ->and($result['suspended'])->toBeFalse()
        ->and(ChatAgent::find($agent->id))->not->toBeNull()
        // The same profile, not a second one.
        ->and(ChatAgent::withTrashed()->where('user_id', $user->id)->count())->toBe(1);
});

test('giving the login back brings the agent profile with it', function () {
    setPermissionsTeamId($this->user->group_id);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    \App\Actions\SysAdmin\User\UpdateUser::make()->action($user, ['status' => false]);
    expect(ChatAgent::find($agent->id))->toBeNull();

    \App\Actions\SysAdmin\User\UpdateUser::make()->action($user->fresh(), ['status' => true]);

    expect(ChatAgent::find($agent->id))->not->toBeNull()
        ->and(ChatAgent::withTrashed()->where('user_id', $user->id)->count())->toBe(1);
});

test('a widget key resolves only to the shop it belongs to', function () {
    $key = \App\Actions\Chat\Widget\ChatWidgetKey::make()->ensure($this->shop);

    expect($key)->toBeString()->toHaveLength(32)
        ->and(\App\Actions\Chat\Widget\ChatWidgetKey::make()->ensure($this->shop))->toBe($key)
        ->and(\App\Actions\Chat\Widget\ChatWidgetKey::make()->resolveShop($key)?->id)->toBe($this->shop->id)
        ->and(\App\Actions\Chat\Widget\ChatWidgetKey::make()->resolveShop('nope'))->toBeNull()
        ->and(\App\Actions\Chat\Widget\ChatWidgetKey::make()->resolveShop(null))->toBeNull();
});

test('the widget config is served for a shop with chat and hidden for one without', function () {
    $key = \App\Actions\Chat\Widget\ChatWidgetKey::make()->ensure($this->shop);

    getJson('/app/api/chats/widget-config?key='.$key)
        ->assertOk()
        ->assertJsonPath('data.shop_id', $this->shop->id);

    $settings = $this->shop->settings ?? [];
    data_set($settings, 'chat.enabled', false);
    $this->shop->update(['settings' => $settings]);

    getJson('/app/api/chats/widget-config?key='.$key)->assertNotFound();

    data_set($settings, 'chat.enabled', true);
    $this->shop->update(['settings' => $settings]);
});

test('the widget config refuses an unknown or missing key', function () {
    getJson('/app/api/chats/widget-config?key=doesnotexist')->assertNotFound();
    getJson('/app/api/chats/widget-config')->assertNotFound();
});

test('enabling the chat widget seeds the chat permissions for an external shop', function () {
    $external = \App\Models\Catalogue\Shop::factory()->make()->toArray();
    $external['type'] = \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL->value;
    $externalShop     = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, $external);

    expect(collect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::getAllValues($externalShop))
        ->filter(fn ($name) => str_starts_with($name, 'chat')))->toBeEmpty();

    $result = \App\Actions\Chat\Widget\EnableShopChatWidget::make()->handle($externalShop);

    $externalShop->refresh();

    expect($result['enabled'])->toBeTrue()
        ->and($result['key'])->toBeString()
        ->and(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::shopHasChat($externalShop))->toBeTrue()
        ->and(Permission::where('scope_type', 'Shop')->where('scope_id', $externalShop->id)
            ->where('name', 'chat.'.$externalShop->id)->exists())->toBeTrue();

    \App\Actions\Chat\Widget\EnableShopChatWidget::make()->handle($externalShop, false);
    $externalShop->refresh();

    expect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::shopHasChat($externalShop))->toBeFalse()
        ->and(Permission::where('scope_type', 'Shop')->where('scope_id', $externalShop->id)
            ->where('name', 'chat.'.$externalShop->id)->exists())->toBeFalse();
});

test('the storefront chat widget sizes its launcher in pixels so the host root font size cannot shrink it', function () {
    $widgetBundle = file_get_contents(public_path('chat-widget/v1.js'));

    expect($widgetBundle)->toContain('.w-14{width:56px}')
        ->and($widgetBundle)->toContain('.h-14{height:56px}')
        ->and($widgetBundle)->not->toContain('.w-14{width:3.5rem}');
});

test('the shop settings toggle turns chat on and off and seeds the permissions with it', function () {
    $external = \App\Models\Catalogue\Shop::factory()->make()->toArray();
    $external['type'] = \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL->value;
    $externalShop     = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, $external);

    $permissionExists = fn () => Permission::where('scope_type', 'Shop')
        ->where('scope_id', $externalShop->id)
        ->where('name', 'chat.'.$externalShop->id)
        ->exists();

    expect($permissionExists())->toBeFalse();

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($externalShop, ['chat_enabled' => true]);
    $externalShop->refresh();

    $key = \Illuminate\Support\Arr::get($externalShop->settings, 'chat.widget_key');

    expect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::shopHasChat($externalShop))->toBeTrue()
        ->and($key)->toBeString()
        ->and($permissionExists())->toBeTrue();

    \App\Actions\Catalogue\Shop\UpdateShop::make()->action($externalShop, ['chat_enabled' => false]);
    $externalShop->refresh();

    expect(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::shopHasChat($externalShop))->toBeFalse()
        ->and($permissionExists())->toBeFalse()
        /* The key survives a switch off so the storefront embed never has to be edited. */
        ->and(\Illuminate\Support\Arr::get($externalShop->settings, 'chat.widget_key'))->toBe($key);

    getJson('/app/api/chats/widget-config?key='.$key)->assertNotFound();
});

test('a customer listing their own chat history is never offered disposal', function () {
    $webUser = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());
    $session = $this->action->handle([
        'web_user_id' => $webUser->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ]);

    $request = request();
    $request->setUserResolver(fn () => $webUser);

    expect(\App\Http\Resources\CRM\Livechat\ChatSessionListResource::make($session)->resolve($request)['can_dispose'])->toBeFalse();
});

test('a customer cannot read chat history through the staff side panel endpoint', function () {
    $webUser = StoreWebUser::make()->action($this->customer, WebUser::factory()->definition());
    $session = $this->action->handle([
        'web_user_id' => $webUser->id,
        'language_id' => 68,
        'priority'    => ChatPriorityEnum::NORMAL->value,
        'shop_id'     => $this->shop->id,
    ]);

    $this->actingAs($webUser, 'retina')
        ->getJson(route('grp.api.chats.sessions.history', $session->ulid))
        ->assertForbidden();
});

test('unauthenticated request cannot post as system', function () {
    \Illuminate\Support\Facades\Auth::logout();
    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_test_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);

    $senderData = (fn () => $this->determineSenderData(['sender_type' => ChatSenderTypeEnum::SYSTEM->value], $chatSession))
        ->call(SendChatMessage::make());

    expect($senderData['data']['sender_type'] ?? null)->not->toBe(ChatSenderTypeEnum::SYSTEM->value);
});

test('chat read endpoints, agent profiles, export, visitors, agent creation and whatsapp templates follow the chat permissions', function () {
    $shopId         = $this->shop->id;
    $organisationId = $this->organisation->id;
    $shopParameters = [$this->organisation->slug, $this->shop->slug];

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'shop_id'          => $shopId,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.Str::random(8),
    ]);

    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp'])->id,
        'shop_id'         => $shopId,
        'phone_number'    => '+628111'.random_int(100000, 999999),
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    $colleague = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    foreach ([$user, $colleague] as $agentUser) {
        ChatAgent::create([
            'user_id'              => $agentUser->id,
            'max_concurrent_chats' => 5,
            'language_id'          => 68,
            'is_online'            => false,
            'is_available'         => true,
            'current_chat_count'   => 0,
        ]);
    }

    $as = function (array $permissions) use ($user) {
        actingAsUserWithOnlyPermissions($user, $permissions);
        $this->actingAs($user, 'sanctum');
    };

    $sessionRoutes = [
        route('grp.api.chats.sessions.customer_profile', $session->ulid),
        route('grp.api.chats.sessions.customer_timeline', $session->ulid),
        route('grp.api.chats.sessions.activity', $session->ulid),
        route('grp.api.chats.sessions.tickets', $session->ulid),
        route('grp.api.chats.meta.sessions.customer_profile', $metaChatSession->ulid),
        route('grp.api.chats.meta.sessions.messages', $metaChatSession->ulid),
    ];

    $as(["products.$shopId.view"]);
    foreach ($sessionRoutes as $url) {
        getJson($url)->assertForbidden();
    }

    $as(["chat.$shopId.view"]);
    foreach ($sessionRoutes as $url) {
        getJson($url)->assertOk();
    }

    $as(["chat.$shopId"]);
    getJson(route('grp.api.chats.agent.show', $colleague->id))->assertForbidden();
    getJson(route('grp.api.chats.agent.show', $user->id))->assertOk();
    get(route('grp.org.shops.show.chat.conversations.export', $shopParameters))->assertForbidden();
    get(route('grp.org.chat.agents.create', $this->organisation->slug))->assertForbidden();
    get(route('grp.org.shops.show.chat.whatsapp_templates.index', $shopParameters))->assertForbidden();
    get(route('grp.org.shops.show.chat.whatsapp_templates.create', $shopParameters))->assertForbidden();

    $as(["chat-m.$shopId"]);
    getJson(route('grp.api.chats.agent.show', $colleague->id))->assertOk();
    get(route('grp.org.shops.show.chat.conversations.export', $shopParameters))->assertOk();
    get(route('grp.org.chat.agents.create', $this->organisation->slug))->assertOk();

    $as(['goods.view']);
    getJson(route('grp.org.shops.show.chat.dashboard-visitors', $shopParameters))->assertForbidden();
    getJson(route('grp.org.chat.dashboard-visitors', $this->organisation->slug))->assertForbidden();

    $as(["web.$shopId.view"]);
    getJson(route('grp.org.shops.show.chat.dashboard-visitors', $shopParameters))->assertOk();

    $as(["accounting.$organisationId.view"]);
    getJson(route('grp.org.chat.dashboard-visitors', $this->organisation->slug))->assertOk();

    $as(["crm.$shopId.view"]);
    get(route('grp.org.shops.show.chat.whatsapp_templates.index', $shopParameters))->assertOk();

    $as(["crm.$shopId.edit"]);
    get(route('grp.org.shops.show.chat.whatsapp_templates.create', $shopParameters))->assertOk();
});

test('export, create agent and new template buttons show only to who may use them', function () {
    $this->withoutVite();
    $shopId         = $this->shop->id;
    $shopParameters = [$this->organisation->slug, $this->shop->slug];

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => true,
        'current_chat_count'   => 0,
    ]);

    $actionRoutes = function (string $url, string $actionsPath) {
        $routeNames = [];
        get($url)->assertOk()->assertInertia(function ($page) use ($actionsPath, &$routeNames) {
            $routeNames = collect($page->toArray()['props'])->dot()
                ->filter(fn ($value, $key) => str_starts_with($key, $actionsPath.'.') && str_ends_with($key, '.route.name'))
                ->values()
                ->all();
        });

        return $routeNames;
    };

    $pages = fn () => [
        'export'       => $actionRoutes(route('grp.org.shops.show.chat.conversations.show', $shopParameters), 'pageHead.actions'),
        'shop_agents'  => $actionRoutes(route('grp.org.shops.show.chat.agents.show', $shopParameters), 'pageHeading.actions'),
        'settings'     => $actionRoutes(route('grp.org.shops.show.chat.settings', [...$shopParameters, 'tab' => 'agents']), 'pageHead.actions'),
        'templates'    => $actionRoutes(route('grp.org.shops.show.chat.whatsapp_templates.index', $shopParameters), 'pageHead.actions'),
    ];

    actingAsUserWithOnlyPermissions($user, ["chat.$shopId", "crm.$shopId.view"]);
    $agentPages = $pages();
    expect($agentPages['export'])->not->toContain('grp.org.shops.show.chat.conversations.export')
        ->and($agentPages['shop_agents'])->not->toContain('grp.org.chat.agents.create')
        ->and($agentPages['settings'])->not->toContain('grp.org.chat.agents.create')
        ->and($agentPages['templates'])->not->toContain('grp.org.shops.show.chat.whatsapp_templates.create');

    actingAsUserWithOnlyPermissions($user, ["chat-m.$shopId", "crm.$shopId.view"]);
    $supervisorPages = $pages();
    expect($supervisorPages['export'])->toContain('grp.org.shops.show.chat.conversations.export')
        ->and($supervisorPages['shop_agents'])->toContain('grp.org.chat.agents.create')
        ->and($supervisorPages['settings'])->toContain('grp.org.chat.agents.create')
        ->and($supervisorPages['templates'])->toContain('grp.org.shops.show.chat.whatsapp_templates.create');
});

test('the unclaimed queue opens a waiting session to another shop viewer and the organisation export keeps to supervised shops', function () {
    $otherShop = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());
    $longAgo   = now()->subYear();

    $newSession = function (ChatSessionStatusEnum $status) use ($longAgo) {
        $session = ChatSession::create([
            'ulid'                    => (string) Str::ulid(),
            'shop_id'                 => $this->shop->id,
            'channel'                 => \App\Enums\CRM\Livechat\ChatChannelEnum::WEBSITE->value,
            'language_id'             => 68,
            'status'                  => $status->value,
            'priority'                => ChatPriorityEnum::NORMAL->value,
            'guest_identifier'        => 'guest-'.Str::random(8),
            'last_visitor_message_at' => $longAgo,
        ]);
        foreach ([ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::AGENT] as $senderType) {
            \App\Models\Chat\ChatMessage::create([
                'chat_session_id' => $session->id,
                'message_type'    => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT,
                'sender_type'     => $senderType,
                'message_text'    => 'Hello '.Str::random(6),
            ]);
        }
        $session->update(['created_at' => $longAgo]);

        return $session;
    };

    $closedSession    = $newSession(ChatSessionStatusEnum::CLOSED);
    $unclaimedSession = $newSession(ChatSessionStatusEnum::WAITING);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    actingAsUserWithOnlyPermissions($user, ["chat.$otherShop->id.view"]);
    $this->actingAs($user, 'sanctum');
    getJson(route('grp.api.chats.sessions.customer_profile', $closedSession->ulid))->assertForbidden();
    getJson(route('grp.api.chats.sessions.customer_profile', $unclaimedSession->ulid))->assertOk();

    $exportRoute = fn (array $query = []) => route('grp.org.chat.conversations.export', [$this->organisation->slug, ...$query]);

    actingAsUserWithOnlyPermissions($user, ["chat-m.{$this->shop->id}"]);
    expect(get($exportRoute(['shop_id' => $this->shop->id, 'min_turns' => 1]))->assertOk()->streamedContent())->toContain($closedSession->ulid);

    actingAsUserWithOnlyPermissions($user, ["chat-m.$otherShop->id"]);
    get($exportRoute(['shop_id' => $this->shop->id]))->assertForbidden();
    expect(get($exportRoute(['min_turns' => 1]))->assertOk()->streamedContent())->not->toContain($closedSession->ulid);
});

test('staff chat messages are agent messages of the shop chat staff only, and Slack settings and forwards follow the session shop chat rule, INI-076', function () {
    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.Str::random(8),
        'ai_model_version' => 'default',
    ]);
    $message = \App\Models\Chat\ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => 'Hello '.Str::random(6),
    ]);

    $sendUrl    = route('grp.org.chat.agents.messages.send', [$this->organisation->slug, $session->ulid]);
    $slackUrl   = route('grp.org.chat.agents.sessions.slack.update', [$this->organisation->slug, $session->ulid]);
    $forwardUrl = route('grp.org.chat.agents.messages.forward_slack', [$this->organisation->slug, $message->id]);
    $payload    = fn (ChatSenderTypeEnum $senderType) => ['message_text' => 'Hi', 'message_type' => 'text', 'sender_type' => $senderType->value];

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    ChatAgent::firstOrCreate(['user_id' => $user->id], ['is_online' => true, 'max_concurrent_chats' => 100, 'current_chat_count' => 0]);
    $before = \App\Models\Chat\ChatMessage::where('chat_session_id', $session->id)->count();

    $session->assignments()->create([
        'chat_agent_id' => ChatAgent::where('user_id', $user->id)->value('id'),
        'status'        => \App\Enums\CRM\Livechat\ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'   => \App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'   => now(),
    ]);

    actingAsUserWithOnlyPermissions($user, ["human-resources.{$this->organisation->id}.view"]);
    foreach ([ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER, ChatSenderTypeEnum::SYSTEM, ChatSenderTypeEnum::AGENT] as $senderType) {
        $this->postJson($sendUrl, $payload($senderType))->assertForbidden();
    }
    $this->putJson($slackUrl, ['token' => 'xoxb-stolen'])->assertForbidden();
    $this->postJson($forwardUrl, ['destination_keys' => ['channel:C1']])->assertForbidden();
    expect(\App\Models\Chat\ChatMessage::where('chat_session_id', $session->id)->count())->toBe($before);

    actingAsUserWithOnlyPermissions($user, ["chat.{$this->shop->id}"]);
    $this->postJson($sendUrl, $payload(ChatSenderTypeEnum::GUEST))->assertForbidden();
    $this->postJson($sendUrl, $payload(ChatSenderTypeEnum::AGENT))->assertSuccessful();
    expect($this->putJson($slackUrl, [])->status())->not->toBe(403);
});

test('agent profiles are edited by their own agent or a chat supervisor, agents are made by supervisors, and image checks and chat tasks follow the shop chat rule, INI-076', function () {
    $newAgent = function () {
        $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

        return [$user, ChatAgent::firstOrCreate(['user_id' => $user->id], ['is_online' => true, 'max_concurrent_chats' => 100, 'current_chat_count' => 0])];
    };
    [$worker, $workerAgent] = $newAgent();
    [$colleague, $colleagueAgent] = $newAgent();

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.Str::random(8),
        'ai_model_version' => 'default',
    ]);
    $image = \App\Models\Chat\ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::IMAGE,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => 'photo',
    ]);

    $org        = $this->organisation->slug;
    $updateUrl  = fn (ChatAgent $agent) => route('grp.org.chat.agents.update', [$org, $agent->id]);
    $editUrl    = fn (ChatAgent $agent) => route('grp.org.chat.agents.edit', [$org, $agent->id]);
    $storeUrl   = route('grp.org.chat.agents.store', $org);
    $verifyUrl  = route('grp.org.chat.agents.messages.verify_image', [$org, $image->id]);
    $taskUrl    = route('grp.org.chat.agents.sessions.task', [$org, $session->ulid]);
    $whatsappTaskUrl = route('grp.org.chat.agents.whatsapp.sessions.task', [$org, MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp'])->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628111'.random_int(100000, 999999),
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ])->ulid]);
    $newProfile = ['organisation_id' => $this->organisation->id, 'user_id' => $colleague->id];

    actingAsUserWithOnlyPermissions($worker, ["human-resources.{$this->organisation->id}.view"]);
    $this->patchJson($updateUrl($colleagueAgent), ['max_concurrent_chats' => 1])->assertForbidden();
    $this->postJson($storeUrl, $newProfile)->assertForbidden();
    $this->postJson($verifyUrl)->assertForbidden();
    $this->postJson($taskUrl, ['title' => 'x'])->assertForbidden();
    $this->postJson($whatsappTaskUrl, ['title' => 'x'])->assertForbidden();

    actingAsUserWithOnlyPermissions($worker, ["chat.{$this->shop->id}"]);
    $this->patchJson($updateUrl($colleagueAgent), ['max_concurrent_chats' => 1])->assertForbidden();
    $this->patchJson($updateUrl($workerAgent), ['user_id' => $colleague->id])->assertForbidden();
    expect($this->patchJson($updateUrl($workerAgent), ['max_concurrent_chats' => 7])->status())->not->toBe(403)
        ->and($workerAgent->refresh()->max_concurrent_chats)->toBe(7);
    $this->get($editUrl($colleagueAgent))->assertForbidden();
    $this->get($editUrl($workerAgent))->assertOk();
    $this->postJson($storeUrl, $newProfile)->assertForbidden();
    expect($this->postJson($taskUrl, ['title' => 'x'])->status())->not->toBe(403);
    expect($this->postJson($whatsappTaskUrl, ['title' => 'x'])->status())->not->toBe(403);

    actingAsUserWithOnlyPermissions($colleague, ["chat-m.{$this->shop->id}"]);
    expect($this->patchJson($updateUrl($workerAgent), ['max_concurrent_chats' => 9])->status())->not->toBe(403)
        ->and($workerAgent->refresh()->max_concurrent_chats)->toBe(9);
    $this->get($editUrl($workerAgent))->assertOk();
});

test('whatsapp template writes need template editing on the shop, a template of another shop is not found there, and viewers see no edit actions, INI-076', function () {
    $otherShop = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());
    $template  = fn (\App\Models\Catalogue\Shop $shop, string $status) => \App\Models\Chat\MetaMessageTemplate::create([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'meta_channel_id' => MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp'])->id,
        'template_id'     => 'tpl'.Str::random(10),
        'name'            => 'tpl_'.strtolower(Str::random(6)),
        'status'          => $status,
    ]);
    $own     = $template($this->shop, 'APPROVED');
    $foreign = $template($otherShop, \App\Actions\Chat\Whatsapp\Templates\StoreWhatsappTemplateDraft::STATUS);

    $shopParameters = ['organisation' => $this->organisation->slug, 'shop' => $this->shop->slug];
    $at             = fn (string $name, \App\Models\Chat\MetaMessageTemplate $t) => route("grp.org.shops.show.chat.whatsapp_templates.$name", [...$shopParameters, 'metaMessageTemplate' => $t->id]);
    $writes         = [
        ['post', route('grp.org.shops.show.chat.whatsapp_templates.store', $shopParameters)],
        ['post', route('grp.org.shops.show.chat.whatsapp_templates.draft.store', $shopParameters)],
        ['post', route('grp.org.shops.show.chat.whatsapp_templates.sync', $shopParameters)],
        ['patch', $at('update', $own)],
        ['delete', $at('delete', $own)],
        ['post', $at('refresh', $own)],
        ['patch', $at('variables', $own)],
        ['post', $at('header_media', $own)],
        ['post', $at('draft.update', $own)],
        ['post', $at('draft.submit', $own)],
    ];

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    actingAsUserWithOnlyPermissions($user, ["crm.{$this->shop->id}.view"]);
    foreach ($writes as [$method, $url]) {
        $this->json($method, $url)->assertForbidden();
    }
    get(route('grp.org.shops.show.chat.whatsapp_templates.index', $shopParameters))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->where('canEdit', false)
        ->where('pageHead.actions', []));

    actingAsUserWithOnlyPermissions($user, ["crm.{$this->shop->id}.edit"]);
    $this->patchJson($at('update', $own), ['label' => 'Own label'])->assertRedirect();
    expect($own->refresh()->data['label'] ?? null)->toBe('Own label');
    $this->patchJson($at('update', $foreign), ['label' => 'Taken'])->assertNotFound();
    $this->deleteJson($at('delete', $foreign))->assertNotFound();
    $this->postJson($at('draft.update', $foreign))->assertNotFound();
    get($at('edit', $foreign))->assertNotFound();
    get($at('draft.edit', $foreign))->assertNotFound();
    expect($foreign->refresh()->shop_id)->toBe($otherShop->id);
    get(route('grp.org.shops.show.chat.whatsapp_templates.index', $shopParameters))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('canEdit', true));
});

test('chat api writes from staff need the chat rights of the shop, the customer keeps rating and reacting by session, and agent settings are changed by the agent or a supervisor, INI-076', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.Str::random(8),
        'ai_model_version' => 'default',
    ]);
    $message = \App\Models\Chat\ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
        'message_text'    => 'Hola '.Str::random(6),
    ]);
    $metaSession = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp'])->id,
        'shop_id'         => $this->shop->id,
        'phone_number'    => '+628111'.random_int(100000, 999999),
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);
    $otherShop     = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());
    $otherCustomer = createCustomer($otherShop);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $peer = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    foreach ([$user, $peer] as $agentUser) {
        ChatAgent::firstOrCreate(['user_id' => $agentUser->id], ['is_online' => true, 'max_concurrent_chats' => 5, 'current_chat_count' => 0]);
    }

    $staffWrites = [
        ['put', route('grp.api.chats.sessions.update', $session->ulid), ['priority' => ChatPriorityEnum::HIGH->value]],
        ['post', route('grp.api.chats.messages.translate', $message->id), ['target_language_id' => 68]],
        ['post', route('grp.api.chats.sessions.translate', $session->ulid), ['target_language_id' => 68]],
        ['post', route('grp.api.chats.messages.reactions.toggle', $message->id), ['emoji' => '👍', 'reactor' => 'agent']],
        ['post', route('grp.api.chats.sessions.share_to_slack', $session->ulid), []],
        ['put', route('grp.api.chats.sessions.sync_by_email', $session->ulid), []],
        ['post', route('grp.api.chats.meta.sessions.read', $metaSession->ulid), []],
        ['put', route('grp.api.chats.meta.sessions.sync_by_phone', $metaSession->ulid), []],
        ['post', route('grp.api.chats.meta.sessions.store'), ['shop_id' => $this->shop->id, 'phone_number' => '+628111222333']],
        ['put', route('grp.api.chats.agents.update', $peer->id), ['is_available' => false]],
    ];

    actingAsUserWithOnlyPermissions($user, ["human-resources.{$this->organisation->id}.view"]);
    foreach ($staffWrites as [$method, $url, $data]) {
        expect($this->json($method, $url, $data)->status())->toBe(403, $url);
    }
    expect($session->refresh()->priority)->toBe(ChatPriorityEnum::NORMAL)
        ->and($this->putJson(route('grp.api.chats.agents.update', $user->id), ['is_available' => false])->status())->not->toBe(403);

    actingAsUserWithOnlyPermissions($user, ["chat.{$this->shop->id}"]);
    $this->putJson(route('grp.api.chats.sessions.update', $session->ulid), ['priority' => ChatPriorityEnum::HIGH->value, 'user_id' => $peer->id])->assertOk();
    expect($session->refresh()->priority)->toBe(ChatPriorityEnum::HIGH);
    $this->postJson(route('grp.api.chats.meta.sessions.store'), ['shop_id' => $this->shop->id, 'customer_id' => $otherCustomer->id])->assertStatus(422);
    $this->putJson(route('grp.api.chats.agents.update', $peer->id), ['is_available' => false])->assertForbidden();

    actingAsUserWithOnlyPermissions($peer, ["chat-m.{$this->shop->id}"]);
    expect($this->putJson(route('grp.api.chats.agents.update', $user->id), ['is_available' => true])->status())->not->toBe(403);

    \Illuminate\Support\Facades\Auth::forgetGuards();
    $this->putJson(route('grp.api.chats.sessions.update', $session->ulid), ['rating' => 5])->assertOk();
    $this->putJson(route('grp.api.chats.sessions.update', $session->ulid), ['priority' => ChatPriorityEnum::LOW->value])->assertForbidden();
    expect($this->postJson(route('grp.api.chats.messages.reactions.toggle', $message->id), ['emoji' => '👍', 'reactor' => 'customer', 'session_ulid' => $session->ulid])->status())->toBeLessThan(300);
    expect((float) $session->refresh()->rating)->toBe(5.0);
});

test('the files of a website or email conversation come back newest first, without retracted or redacted messages', function () {
    $shopId = $this->shop->id;

    $makeSession = fn () => ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'shop_id'          => $shopId,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.Str::random(8),
    ]);
    $makeMessage = fn (ChatSession $session, ChatSenderTypeEnum $sender, string $when, array $metadata = []) => \App\Models\Chat\ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT,
        'sender_type'     => $sender,
        'message_text'    => 'see file',
        'metadata'        => $metadata,
        'created_at'      => $when,
    ]);

    $session = $makeSession();
    $other   = $makeSession();

    $oldest    = chatFileOnMessage($makeMessage($session, ChatSenderTypeEnum::GUEST, now()->subHours(3)), 'broken.png');
    $invoice   = chatFileOnMessage($makeMessage($session, ChatSenderTypeEnum::AGENT, now()->subHours(2)), 'invoice.pdf', false);
    $retracted = $makeMessage($session, ChatSenderTypeEnum::GUEST, now()->subHour());
    chatFileOnMessage($retracted, 'retracted.png');
    $retracted->delete();
    chatFileOnMessage($makeMessage($session, ChatSenderTypeEnum::GUEST, now()->subMinutes(30), ['attachment_redacted_at' => now()->toISOString()]), 'redacted.png');
    $newest = chatFileOnMessage($makeMessage($session, ChatSenderTypeEnum::GUEST, now()->subMinutes(5)), 'new.png');
    chatFileOnMessage($makeMessage($other, ChatSenderTypeEnum::GUEST, now()), 'elsewhere.png');

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    actingAsUserWithOnlyPermissions($user, ["chat.$shopId.view"]);
    $this->actingAs($user, 'sanctum');

    $data = getJson(route('grp.api.chats.sessions.files', $session->ulid))->assertOk()->json('data');

    expect(array_column($data, 'id'))->toBe([$newest->id, $invoice->id, $oldest->id])
        ->and($data[0])->toMatchArray([
            'from'         => 'customer',
            'is_image'     => true,
            'is_archived'  => false,
            'file_name'    => 'new.png',
            'file_mime'    => 'image/png',
            'download_url' => route('grp.api.chats.chat.attachment.download', ['ulid' => $newest->ulid]),
        ])
        ->and($data[0]['thumbnail'])->not->toBeNull()
        ->and($data[0]['full'])->not->toBeNull()
        ->and($data[1])->toMatchArray(['from' => 'agent', 'is_image' => false, 'file_name' => 'invoice.pdf', 'file_mime' => 'application/pdf'])
        ->and($data[1]['thumbnail'])->toBeNull()
        ->and($data[1]['full'])->toBeNull();

    actingAsUserWithOnlyPermissions($user, ["products.$shopId.view"]);
    $this->actingAs($user, 'sanctum');
    getJson(route('grp.api.chats.sessions.files', $session->ulid))->assertForbidden();
});

test('moving a conversation to another shop is refused for a shop the user cannot act on chat on', function () {
    $otherShop = \App\Models\Catalogue\Shop::where('organisation_id', $this->organisation->id)->where('id', '!=', $this->shop->id)->first()
        ?? \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());
    $originalState = $otherShop->state;
    $otherShop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $session = ChatSession::create([
        'ulid'        => (string) Str::ulid(),
        'shop_id'     => $this->shop->id,
        'language_id' => 68,
        'status'      => ChatSessionStatusEnum::WAITING->value,
        'priority'    => ChatPriorityEnum::NORMAL->value,
    ]);

    $route = route('grp.org.chat.agents.sessions.move_to_shop', [$this->organisation->slug, $session->ulid]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    makeChatWorker($clerk, $this->shop);

    expect(\App\Actions\Chat\ChatSession\MoveChatSessionToShop::targetShopsFor($clerk, $session)->pluck('id')->all())->not->toContain($otherShop->id);

    actingAs($clerk)->patchJson($route, ['shop_id' => $otherShop->id])->assertForbidden();
    expect($session->fresh()->shop_id)->toBe($this->shop->id)
        ->and($session->chatEvents()->where('event_type', \App\Enums\CRM\Livechat\ChatEventTypeEnum::MOVED_TO_SHOP->value)->exists())->toBeFalse();

    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    makeChatWorker($outsider, $otherShop);
    actingAs($outsider)->patchJson($route, ['shop_id' => $otherShop->id])->assertForbidden();
    actingAs($outsider)->getJson(route('grp.org.chat.agents.sessions.move_to_shop.shops', [$this->organisation->slug, $session->ulid]))->assertForbidden();

    $bothShops = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    makeChatWorker($bothShops, $this->shop);
    makeChatWorker($bothShops, $otherShop);

    actingAs($bothShops)->getJson(route('grp.org.chat.agents.sessions.move_to_shop.shops', [$this->organisation->slug, $session->ulid]))
        ->assertOk()
        ->assertJsonFragment(['id' => $otherShop->id]);
    actingAs($bothShops)->patchJson($route, ['shop_id' => $otherShop->id])->assertOk();

    expect($session->fresh()->shop_id)->toBe($otherShop->id)
        ->and($session->fresh()->status)->toBe(ChatSessionStatusEnum::WAITING);

    $otherShop->update(['state' => $originalState]);
});
