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
use App\Actions\Chat\Staff\Slack\SyncSlackPublicChannels;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Catalogue\Product;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

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

describe('staff messaging', function () {
    beforeEach(function () {
        $this->otherUser = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        if ($this->otherUser->group_id !== $this->user->group_id) {
            $this->otherUser->update(['group_id' => $this->user->group_id]);
        }
    });

    test('dm is created once and reused', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $first  = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        $second = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->otherUser, ['user_ids' => [$this->user->id]]);

        expect($first->id)->toBe($second->id)
            ->and($first->type)->toBe('dm')
            ->and($first->participants()->count())->toBe(2);
    });

    test('message is sent, counted unread for the other participant, then read', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => '<b>hello</b> there']);

        Event::assertDispatched(\App\Events\StaffMessageSent::class);
        expect($conversation->messages()->first()->body)->toBe('hello there');

        $mine   = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id);
        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->otherUser)->firstWhere('id', $conversation->id);
        expect((int) $mine->unread_count)->toBe(0)->and((int) $theirs->unread_count)->toBe(1);

        \App\Actions\Chat\Staff\MarkStaffConversationRead::run($conversation, $this->otherUser);
        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->otherUser)->firstWhere('id', $conversation->id);
        expect((int) $theirs->unread_count)->toBe(0);
    });

    test('a message sent in the same second the conversation was read still counts unread', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $other        = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);

        \Illuminate\Support\Carbon::setTestNow('2026-09-14 10:00:00.100000');
        \App\Actions\Chat\Staff\MarkStaffConversationRead::run($conversation, $other);
        \Illuminate\Support\Carbon::setTestNow('2026-09-14 10:00:00.200000');
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'same second']);
        \Illuminate\Support\Carbon::setTestNow();

        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($other)->firstWhere('id', $conversation->id);
        expect((int) $theirs->unread_count)->toBe(1);
    });

    test('the conversations list loads avatars and contexts once, not per conversation', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'list me']);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $rows = \App\Http\Resources\Chat\StaffConversationResource::collection(
            \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)
        )->resolve();
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query');
        \Illuminate\Support\Facades\DB::disableQueryLog();

        expect(collect($rows)->pluck('ulid'))->toContain($conversation->ulid)
            ->and($queries->filter(fn (string $sql) => str_contains($sql, 'from "media"'))->count())->toBeLessThanOrEqual(1)
            ->and($queries->filter(fn (string $sql) => preg_match('/from "(delivery_notes|orders|picking_sessions)"/', $sql))->count())->toBeLessThanOrEqual(3);
    });

    test('the conversations list sends each avatar once beside the list, with only the links the screens draw', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'avatars apart']);

        $response = actingAs($this->user)->getJson(route('grp.chat.staff.conversations.index'))->assertOk();
        $listed   = collect($response->json('data'))->firstWhere('ulid', $conversation->ulid);
        $sources  = ['original' => 'a', 'original_2x' => 'b', 'avif' => 'c', 'avif_2x' => 'd', 'webp' => 'e', 'webp_2x' => 'f', 'png' => 'g', 'png_2x' => 'h'];

        expect($listed['participants'])->each->not->toHaveKey('avatar')
            ->and($response->getContent())->toContain('"avatars":{')
            ->and(User::chatAvatarOf($sources))->toBe(['original' => 'a', 'original_2x' => 'b', 'avif' => 'c', 'avif_2x' => 'd'])
            ->and(User::chatAvatarOf(null))->toBeNull();
    });

    test('reaction toggles on and off', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'react']);

        \App\Actions\Chat\Staff\ToggleStaffMessageReaction::run($message, $this->otherUser, '👍');
        expect($message->reactions()->count())->toBe(1);
        \App\Actions\Chat\Staff\ToggleStaffMessageReaction::run($message, $this->otherUser, '👍');
        expect($message->reactions()->count())->toBe(0);
    });

    test('image only message stores media', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        \Illuminate\Support\Facades\Storage::fake('public');
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        $response = actingAs($this->user)
            ->post(route('grp.chat.staff.conversations.messages.store', $conversation), [
                'image' => \Illuminate\Http\UploadedFile::fake()->image('screenshot.png', 40, 40),
            ]);
        $response->assertSuccessful();
        expect($response->json('data.image'))->not->toBeNull()
            ->and($response->json('data.body'))->toBe('');
    });

    test('non participant is rejected by the message endpoint', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $stranger     = User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        actingAs($stranger)
            ->postJson(route('grp.chat.staff.conversations.messages.store', $conversation), ['body' => 'intrude'])
            ->assertForbidden();
    });

    test('empty conversation is listed only for its creator until a message is sent', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $newcomer     = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$newcomer->id]]);

        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->not->toBeNull()
            ->and(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($newcomer)->firstWhere('id', $conversation->id))->toBeNull();

        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'now you see me']);

        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($newcomer)->firstWhere('id', $conversation->id))->not->toBeNull();
    });
});

describe('staff messaging mentions', function () {
    test('mention resolves to participant and flags unread for them', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $nickname = 'adamm'.Str::lower(Str::random(6));
        $other    = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'nickname' => $nickname]);

        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => "hey @$nickname check this"]);

        expect($message->mentions)->toBe([$other->id]);

        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($other)->firstWhere('id', $conversation->id);
        $mine   = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id);
        expect($theirs->has_mention)->toBeTruthy()
            ->and($mine->has_mention)->toBeFalsy();
    });
});

describe('staff messaging page', function () {
    beforeEach(function () {
        $this->otherUser = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
    });

    test('index renders the messaging page', function () {
        actingAs($this->user)->get(route('grp.chat.staff.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffMessaging'));
    });

    test('show renders the messaging page for a participant', function () {
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        actingAs($this->user)->get(route('grp.chat.staff.show', $conversation))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffMessaging'));
    });
});

describe('staff messaging context shortcuts', function () {
    test('ask CRM on an order opens one group conversation with the shop CRM role holders', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $product = Product::where("shop_id", $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);

        $crmUser = User::factory()->create(['group_id' => $this->user->group_id]);
        setPermissionsTeamId($this->user->group_id);
        $crmUser->assignRole(\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id'))->toContain($crmUser->id);

        $first  = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $order, 'crm');
        $second = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $order, 'crm');

        expect($first->id)->toBe($second->id)
            ->and($first->type)->toBe('group')
            ->and($first->context_type)->toBe('Order')
            ->and($first->context_id)->toBe($order->id)
            ->and($first->participants()->pluck('users.id'))->toContain($crmUser->id, $this->user->id);

        $resource = (new \App\Http\Resources\Chat\StaffConversationResource($first->load('participants')))->resolve();
        expect($resource['context_label'])->toBe($order->reference)
            ->and($resource['context_url'])->toContain($order->slug);
    });
});

describe('staff messaging audience mapping', function () {
    test('org crm list is ignored, shop crm list wins', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $other   = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);

        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$other->id, now()->timestamp);
        $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->not->toBe([$other->id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$other->id]);
    });

    test('org warehouse list is the fallback when shop has none', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $other   = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);

        $shopSettings = $this->shop->settings ?? [];
        \Illuminate\Support\Arr::set($shopSettings, 'staff_chat.warehouse_user_ids', []);
        $this->shop->update(['settings' => $shopSettings]);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$other->id, now()->timestamp);
        $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['staff_chat' => ['warehouse_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('warehouse', $order)->pluck('id')->all())->toBe([$other->id]);
    });
});

describe('staff messaging routing', function () {
    test('shop primary wins over org', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id]]])]);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$a->id, now()->timestamp);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$a->id]);
    });

    test('inactive primary falls back to active backup', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);
        $b       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id], 'crm_backup_user_ids' => [$b->id]]])]);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$a->id);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$b->id, now()->timestamp);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$b->id]);
    });

    test('nobody active returns primary and backup together', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);
        $b       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id], 'crm_backup_user_ids' => [$b->id]]])]);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$a->id);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$b->id);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
    });
});

describe('staff messaging picking session', function () {
    test('ask CRM on a picking session opens one group conversation with the shop CRM role holders', function () {
        $pickingSession = \App\Models\Inventory\PickingSession::create([
            'group_id'        => $this->warehouse->group_id,
            'organisation_id' => $this->warehouse->organisation_id,
            'warehouse_id'    => $this->warehouse->id,
            'user_id'         => $this->user->id,
            'reference'       => 'staff-chat-'.uniqid(),
        ]);

        $first  = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $pickingSession, 'crm');
        $second = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $pickingSession, 'crm');

        expect($first->id)->toBe($second->id)
            ->and($first->context_type)->toBe('PickingSession');

        $resource = (new \App\Http\Resources\Chat\StaffConversationResource($first->load('participants')))->resolve();
        expect($resource['context_url'])->toContain($pickingSession->slug);
    });
});

describe('staff messaging team', function () {
    test('team membership toggles and coworkers endpoint flags it', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        expect(\App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other))->toBeTrue()
            ->and($this->user->teamMembers()->count())->toBe(1);

        $coworkers = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');
        $row = collect($coworkers)->firstWhere('id', $other->id);
        expect($row['in_team'])->toBeTrue()
            ->and($row['organisation_ids'])->toBeArray();

        expect(\App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other))->toBeFalse()
            ->and($this->user->teamMembers()->count())->toBe(0);
    });

    test('the coworkers poll shares the group list between viewers but keeps team and closeness per viewer', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        \Illuminate\Support\Facades\Cache::forget('staff-coworkers:'.$this->user->group_id);
        \App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other);

        actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk();

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $mine = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');
        $queryLog          = collect(\Illuminate\Support\Facades\DB::getQueryLog());
        $queriesOnWarmPoll = $queryLog
            ->filter(fn (array $query) => preg_match('/"(users|user_has_models|employees|media|user_has_team_members)"/', $query['query']))
            ->count();
        expect($queryLog->count())->toBeLessThanOrEqual(6);
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $theirs = actingAs($other)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');

        expect(collect($mine)->firstWhere('id', $other->id)['in_team'])->toBeTrue()
            ->and(collect($mine)->pluck('id'))->not->toContain($this->user->id)
            ->and(collect($theirs)->pluck('id'))->not->toContain($other->id)
            ->and(collect($theirs)->firstWhere('id', $this->user->id)['in_team'])->toBeFalse()
            ->and($queriesOnWarmPoll)->toBeLessThanOrEqual(2);

        $searched = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index', ['q' => $other->chatName()]))->assertOk()->json('data');
        expect(collect($searched)->pluck('id'))->toContain($other->id);

        \App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other);
    });

    test('the presence poll returns only who is active or on the telephone, matching the full list', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$other->id, 1_700_000_000, 60);

        $full     = collect(actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data'))->keyBy('id');
        $presence = collect(actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index', ['presence' => 1]))->assertOk()->json('data'))->keyBy('id');

        expect($presence->keys()->sort()->values()->all())->toBe($full->keys()->sort()->values()->all())
            ->and(array_keys($presence[$other->id]))->toBe(['id', 'last_active_at', 'on_call', 'on_call_since'])
            ->and($presence[$other->id]['last_active_at'])->toBe(1_700_000_000)
            ->and($presence[$other->id])->toBe(\Illuminate\Support\Arr::only($full[$other->id], ['id', 'last_active_at', 'on_call', 'on_call_since']));
    });

    test('json-only grp routes exist and skip building the layout', function () {
        $routeNames = (new ReflectionClassConstant(\App\Http\Middleware\HandleInertiaGrpRequests::class, 'JSON_ONLY_ROUTES'))->getValue();

        foreach ($routeNames as $routeName) {
            expect(\Illuminate\Support\Facades\Route::has($routeName))->toBeTrue("$routeName is not a registered route");

            $request = \Illuminate\Http\Request::create('/');
            $request->setRouteResolver(fn () => \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName));

            expect(app(\App\Http\Middleware\HandleInertiaGrpRequests::class)->share($request))->toBe([]);
        }
    });

    test('a json call to any grp route skips the layout, an inertia visit still gets it', function () {
        $sendRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('grp.org.chat.agents.messages.send');
        $share     = function (array $headers) use ($sendRoute): array {
            $request = \Illuminate\Http\Request::create('/', 'POST', server: $headers);
            $request->setRouteResolver(fn () => $sendRoute);
            $request->setLaravelSession(app('session.store'));

            return app(\App\Http\Middleware\HandleInertiaGrpRequests::class)->share($request);
        };

        expect($share(['HTTP_ACCEPT' => 'application/json, text/plain, */*']))->toBe([])
            ->and($share(['HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_INERTIA' => 'true']))->toHaveKeys(['auth', 'flash', 'ziggy']);
    });
});

describe('staff messaging archive', function () {
    test('closing a conversation hides it until a new message arrives', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $other, ['body' => 'hi']);

        \App\Actions\Chat\Staff\ArchiveStaffConversation::run($conversation, $this->user);
        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->toBeNull();

        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $other, ['body' => 'are you there?']);
        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->not->toBeNull();
    });

    test('the key a browser encrypts its saved chats with is personal, stable and never cached', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        $response = actingAs($this->user)->getJson(route('grp.chat.staff.cache_key'))->assertOk();
        $key      = $response->json('key');

        expect(strlen(base64_decode($key)))->toBe(32)
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and(actingAs($this->user)->getJson(route('grp.chat.staff.cache_key'))->json('key'))->toBe($key)
            ->and(actingAs($other)->getJson(route('grp.chat.staff.cache_key'))->json('key'))->not->toBe($key);
    });

    test('reading a conversation tells the other participants when, for their read ticks', function () {
        Event::fake([\App\Events\StaffMessageSent::class, \App\Events\StaffConversationRead::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'can you check the pallet?']);

        actingAs($other)->postJson(route('grp.chat.staff.conversations.read', $conversation))->assertOk();

        Event::assertDispatched(\App\Events\StaffConversationRead::class, function (\App\Events\StaffConversationRead $event) use ($conversation, $other) {
            $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

            return $event->conversation->is($conversation)
                && $event->broadcastWith()['user_id'] === $other->id
                && $channels === ['private-grp.personal.'.$this->user->id];
        });

        $participants = collect((new \App\Http\Resources\Chat\StaffConversationResource($conversation->fresh()->load('participants')))->resolve()['participants']);

        expect($participants->firstWhere('id', $other->id)['last_read_at'])->not->toBeNull();
    });

    test('a task chat cannot be archived while the task is open and lives on the task page', function () {
        $task         = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Restock the blue mugs', 'department' => 'warehouse']);
        $conversation = $task->conversation;

        $conversationTask = (new \App\Http\Resources\Chat\StaffConversationResource($conversation))->resolve()['task'];
        expect($conversationTask['is_open'])->toBeTrue()
            ->and($conversationTask['status'])->toBe('todo')
            ->and($conversationTask['status_label'])->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::labels()['todo'])
            ->and($conversationTask['status_icon']['icon'])->not->toBeEmpty();

        actingAs($this->user)
            ->get(route('grp.chat.staff.show', $conversation))
            ->assertRedirect(route('grp.tasks.show', $task->reference));

        actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.archive', $conversation))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversation');

        $task->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE, 'closed_at' => now()]);
        expect((new \App\Http\Resources\Chat\StaffConversationResource($conversation->fresh()))->resolve()['task']['status'])->toBe('done');

        actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.archive', $conversation))
            ->assertOk();

        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->toBeNull();
    });

    test('a participant watches and unwatches a task chat, an outsider cannot', function () {
        $task         = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Check the courier labels', 'description' => 'Labels on the blue shelf are smudged', 'department' => 'warehouse']);
        $conversation = $task->conversation;

        $conversationTask = (new \App\Http\Resources\Chat\StaffConversationResource($conversation->fresh()))->resolve()['task'];
        expect($conversationTask['description'])->toBe('Labels on the blue shelf are smudged')
            ->and($conversationTask)->toHaveKeys(['model_label', 'model_url']);

        $isWatching = fn () => (bool) $conversation->participants()->where('users.id', $this->user->id)->first()?->pivot->is_watching;

        expect($isWatching())->toBeFalse();

        actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.watch', $conversation), ['is_watching' => true])
            ->assertOk()
            ->assertJson(['is_watching' => true]);

        expect($isWatching())->toBeTrue();

        $listed = actingAs($this->user)->getJson(route('grp.chat.staff.conversations.index'))->assertOk()->json('data');
        expect(collect($listed)->firstWhere('ulid', $conversation->ulid)['is_watching'] ?? null)->toBeTrue();

        actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.watch', $conversation), ['is_watching' => false])
            ->assertOk()
            ->assertJson(['is_watching' => false]);

        expect($isWatching())->toBeFalse();

        $outsider = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();

        actingAs($outsider)
            ->postJson(route('grp.chat.staff.conversations.watch', $conversation), ['is_watching' => true])
            ->assertForbidden();

        $task->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE, 'closed_at' => now()]);
    });
});

describe('staff messaging gifs', function () {
    test('search returns mapped klipy results', function () {
        \Illuminate\Support\Facades\Http::fake([
            'api.klipy.com/*' => \Illuminate\Support\Facades\Http::response([
                'result' => true,
                'data'   => [
                    'data'     => [
                        [
                            'id'    => '1',
                            'file'  => [
                                'sm' => ['gif' => ['url' => 'https://static.klipy.com/a/sm.gif', 'width' => 100, 'height' => 80]],
                                'md' => ['gif' => ['url' => 'https://static.klipy.com/a/md.gif', 'width' => 200, 'height' => 160]],
                            ],
                        ],
                    ],
                    'has_next'     => true,
                    'current_page' => 1,
                ],
            ]),
        ]);
        config(['services.klipy.key' => 'k']);

        actingAs($this->user)
            ->getJson(route('grp.chat.staff.gifs.search', ['q' => 'cat', 'page' => 1]))
            ->assertOk()
            ->assertJsonPath('data.0.url', 'https://static.klipy.com/a/md.gif')
            ->assertJsonPath('next', 2);
    });
});

describe('staff messaging nickname', function () {
    test('user sets a nickname and it appears in profile update and messages', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'Raulito'])
            ->assertOk();

        expect($this->user->fresh()->nickname)->toBe('Raulito');

        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $other        = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'hi']);

        $resource = (new \App\Http\Resources\Chat\StaffMessageResource($message->load(['user', 'translations', 'reactions', 'conversation'])))->resolve();
        expect($resource['user_name'])->toBe('Raulito');
    });

    test('nickname must be unique', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'SharedNick'])
            ->assertOk();

        actingAs($other)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'SharedNick'])
            ->assertStatus(422);
    });
});

describe('staff messaging chat theme', function () {
    test('user can set chat theme', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'nord'])
            ->assertOk();

        expect(Arr::get($this->user->fresh()->settings, 'chat_theme'))->toBe('nord');
    });

    test('changing chat theme busts the cached layout props', function () {
        config()->set('ui.cache.layout', true);

        $props = new App\Actions\UI\Grp\GetFirstLoadProps()->handle($this->user);
        expect(Arr::get($props, 'layout.chat_theme'))->not->toBe('gruvbox');

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'gruvbox'])
            ->assertOk();

        $props = new App\Actions\UI\Grp\GetFirstLoadProps()->handle($this->user->fresh());
        expect(Arr::get($props, 'layout.chat_theme'))->toBe('gruvbox');
    });

    test('invalid chat theme is rejected', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'not-a-theme'])
            ->assertStatus(422);
    });

    test('each person chooses their own alert sounds, which reach the layout', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_sounds' => ['chat' => 'submarine', 'whatsapp' => 'fart', 'email' => 'voice', 'colleague' => 'silent', 'waiting' => 'silent', 'ticket' => 'genie', 'ticket_new' => 'bells', 'ticket_answered' => 'knock', 'ticket_qa' => 'voice', 'pager' => 'bells']])
            ->assertOk();

        $expected = ['chat' => 'submarine', 'whatsapp' => 'fart', 'email' => 'voice', 'colleague' => 'silent', 'waiting' => 'silent', 'ticket' => 'genie', 'ticket_new' => 'bells', 'ticket_answered' => 'knock', 'ticket_qa' => 'voice'];

        expect(Arr::get($this->user->fresh()->settings, 'alert_sounds'))->toEqual($expected)
            ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($this->user->fresh())['settings']['alert_sounds'])->toEqual($expected);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_sounds' => ['chat' => 'air-horn']])
            ->assertStatus(422);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_preview_seconds' => 10])
            ->assertOk();

        expect(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($this->user->fresh())['settings']['alert_preview_seconds'])->toBe(10);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_preview_seconds' => 99])
            ->assertStatus(422);
    });

    test('each alerting event is switched on or off by way, and an unknown event or way is not kept', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_events' => [
                'customer_message' => ['popup' => false, 'desktop' => true, 'sound' => '0'],
                'waiting'          => ['desktop' => false, 'popup' => false],
                'pager'            => ['sound' => false],
            ]])
            ->assertOk();

        $expected = [
            'customer_message' => ['popup' => false, 'desktop' => true, 'sound' => false],
            'waiting'          => ['desktop' => false],
        ];

        expect(Arr::get($this->user->fresh()->settings, 'alert_events'))->toEqual($expected)
            ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($this->user->fresh())['settings']['alert_events'])->toEqual($expected);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_events' => ['colleague' => ['sound' => 'loud']]])
            ->assertStatus(422);
    });
});

describe('staff chat audience seeding', function () {
    test('seed fills an empty shop list from role holders and leaves a curated one alone', function () {
        $roleHolder = User::factory()->create(['group_id' => $this->user->group_id]);
        setPermissionsTeamId($this->user->group_id);
        $roleHolder->assignRole(\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

        $settings = $this->shop->settings ?? [];
        \Illuminate\Support\Arr::set($settings, 'staff_chat.crm_user_ids', []);
        $this->shop->update(['settings' => $settings]);

        \App\Actions\Chat\Staff\SeedStaffChatAudiences::run();

        expect(\Illuminate\Support\Arr::get($this->shop->fresh()->settings, 'staff_chat.crm_user_ids'))->toContain($roleHolder->id);

        $curated  = User::factory()->create(['group_id' => $this->user->group_id]);
        $settings = $this->shop->fresh()->settings ?? [];
        \Illuminate\Support\Arr::set($settings, 'staff_chat.crm_user_ids', [$curated->id]);
        $this->shop->update(['settings' => $settings]);

        \App\Actions\Chat\Staff\SeedStaffChatAudiences::run();

        expect(\Illuminate\Support\Arr::get($this->shop->fresh()->settings, 'staff_chat.crm_user_ids'))->toBe([$curated->id]);
    });
});

test('a staff chat on an order opens only for staff who can see that order, INI-076', function () {
    list(, $product) = createProduct($this->shop);
    $order           = createOrder($this->customer, $product);
    $payload         = ['context_type' => 'Order', 'context_id' => $order->id, 'audience' => 'crm'];

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    actingAsUserWithOnlyPermissions($user, ["human-resources.{$this->organisation->id}.view"]);
    $this->postJson(route('grp.chat.staff.context.open'), $payload)->assertForbidden();

    actingAsUserWithOnlyPermissions($user, ["orders.{$this->shop->id}.view"]);
    $this->postJson(route('grp.chat.staff.context.open'), $payload)->assertSuccessful();
});

test('a staff member cannot start a conversation with only themselves', function () {
    actingAs($this->user);
    $before = \App\Models\Chat\StaffConversation::count();

    $this->postJson(route('grp.chat.staff.conversations.store'), ['user_ids' => [$this->user->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_ids');

    expect(\App\Models\Chat\StaffConversation::count())->toBe($before);
});

test('rail clicks are counted per day, user, view and button', function () {
    $row = fn (string $view, string $button) => \Illuminate\Support\Facades\DB::table('rail_usage')
        ->whereRaw('date = CURRENT_DATE')->where(['user_id' => $this->user->id, 'view' => $view, 'button' => $button])->value('clicks');
    $before = (int) $row('micro', 'online');

    actingAs($this->user)
        ->postJson(route('grp.models.profile.rail-click'), ['button' => 'online', 'view' => 'micro'])
        ->assertNoContent();
    actingAs($this->user)
        ->postJson(route('grp.models.profile.rail-click'), ['view' => 'compact'])
        ->assertNoContent();

    expect((int) $row('micro', 'online'))->toBe($before + 1)
        ->and($row('compact', ''))->toBe(0);

    actingAs($this->user)
        ->postJson(route('grp.models.profile.rail-click'), ['button' => 'bad key!', 'view' => 'micro'])
        ->assertUnprocessable();
    actingAs($this->user)
        ->postJson(route('grp.models.profile.rail-click'), ['button' => 'online', 'view' => 'huge'])
        ->assertUnprocessable();
});

test('messages of public Slack channels are kept as they arrive, edits and deletions included, and private ones never', function () {
    Config::set('services.slack.signing_secret', 'shh');
    $slackUserIdBefore = $this->user->slack_user_id;
    $this->user->update(['slack_user_id' => 'UPUBLIC1']);

    $post = function (array $event) {
        $body      = json_encode(['type' => 'event_callback', 'event' => $event]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    $message = ['type' => 'message', 'channel' => 'CPUBLIC1', 'channel_type' => 'channel', 'user' => 'UPUBLIC1', 'text' => 'Pallet 12 is ready', 'ts' => '1789138198.657369'];
    $post($message)->assertOk();
    $post($message)->assertOk();

    $channel = SlackChannel::where('slack_id', 'CPUBLIC1')->firstOrFail();
    $stored  = $channel->messages()->get();
    expect($stored)->toHaveCount(1)
        ->and($stored[0]->text)->toBe('Pallet 12 is ready')
        ->and($stored[0]->user_id)->toBe($this->user->id)
        ->and($stored[0]->sent_at->timestamp)->toBe(1789138198)
        ->and($stored[0]->data['ts'])->toBe('1789138198.657369');

    $post(['type' => 'message', 'subtype' => 'message_changed', 'channel' => 'CPUBLIC1', 'channel_type' => 'channel', 'ts' => '1789138300.000100', 'message' => ['type' => 'message', 'user' => 'UPUBLIC1', 'text' => 'Pallet 13 is ready', 'ts' => '1789138198.657369', 'edited' => ['user' => 'UPUBLIC1', 'ts' => '1789138299.000000']]])->assertOk();
    $post(['type' => 'message', 'channel' => 'CPUBLIC1', 'channel_type' => 'channel', 'user' => 'UUNKNOWN', 'text' => 'On it', 'ts' => '1789138400.000200', 'thread_ts' => '1789138198.657369'])->assertOk();

    $edited = $channel->messages()->where('ts', '1789138198.657369')->firstOrFail();
    $reply  = $channel->messages()->where('ts', '1789138400.000200')->firstOrFail();
    expect($channel->messages()->count())->toBe(2)
        ->and($edited->text)->toBe('Pallet 13 is ready')
        ->and($edited->edited_at->timestamp)->toBe(1789138299)
        ->and($reply->thread_ts)->toBe('1789138198.657369')
        ->and($reply->user_id)->toBeNull()
        ->and($reply->slack_user_id)->toBe('UUNKNOWN');

    $post(['type' => 'message', 'subtype' => 'message_deleted', 'channel' => 'CPUBLIC1', 'channel_type' => 'channel', 'ts' => '1789138500.000300', 'deleted_ts' => '1789138400.000200'])->assertOk();
    expect($channel->messages()->count())->toBe(1)
        ->and(SlackMessage::withTrashed()->where('slack_channel_id', $channel->id)->count())->toBe(2);

    $post(['type' => 'message', 'channel' => 'GPRIVATE1', 'channel_type' => 'group', 'user' => 'UPUBLIC1', 'text' => 'private channel', 'ts' => '1789138600.000400'])->assertOk();
    $post(['type' => 'message', 'channel' => 'DDIRECT1', 'channel_type' => 'im', 'user' => 'UPUBLIC1', 'text' => 'direct message', 'ts' => '1789138700.000500'])->assertOk();
    $post(['type' => 'message', 'channel' => 'GGROUPDM1', 'channel_type' => 'mpim', 'user' => 'UPUBLIC1', 'text' => 'group direct message', 'ts' => '1789138800.000600'])->assertOk();
    $post(['type' => 'message', 'subtype' => 'message_changed', 'channel' => 'CUNKNOWN1', 'message' => ['user' => 'UPUBLIC1', 'text' => 'kind of channel not said', 'ts' => '1789138900.000700']])->assertOk();
    expect(SlackChannel::whereIn('slack_id', ['GPRIVATE1', 'DDIRECT1', 'GGROUPDM1', 'CUNKNOWN1'])->count())->toBe(0)
        ->and(SlackMessage::withTrashed()->whereIn('text', ['private channel', 'direct message', 'group direct message', 'kind of channel not said'])->count())->toBe(0);

    $post(['type' => 'message', 'subtype' => 'message_changed', 'channel' => 'CPUBLIC1', 'message' => ['user' => 'UPUBLIC1', 'text' => 'Pallet 14 is ready', 'ts' => '1789138198.657369']])->assertOk();
    expect($edited->refresh()->text)->toBe('Pallet 14 is ready');

    $this->user->update(['slack_user_id' => $slackUserIdBefore]);
});

test('the Slack sync lists public channels only, joins them only when asked and brings their history with thread replies', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Sleep::fake();
    $group      = $this->organisation->group;
    $joinAllowed = true;
    $newestPage = Http::sequence()
        ->push(['ok' => false, 'error' => 'ratelimited'], 429, ['Retry-After' => '7'])
        ->push(['ok' => true, 'messages' => [['user' => 'U9', 'text' => 'thread start', 'ts' => '1789120000.000100', 'reply_count' => 1]], 'response_metadata' => ['next_cursor' => 'page2']])
        ->whenEmpty(Http::response(['ok' => true, 'messages' => []]));

    Http::fake([
        'slack.com/api/conversations.list*'    => Http::response(['ok' => true, 'channels' => [
            ['id' => 'CSYNCIN1', 'name' => 'warehouse', 'is_member' => true, 'is_archived' => false],
            ['id' => 'CSYNCOUT1', 'name' => 'random', 'is_member' => false, 'is_archived' => false],
        ]]),
        'slack.com/api/conversations.join'     => function () use (&$joinAllowed) {
            return Http::response($joinAllowed ? ['ok' => true] : ['ok' => false, 'error' => 'missing_scope']);
        },
        'slack.com/api/conversations.history*' => function (HttpRequest $request) use ($newestPage) {
            return match (true) {
                $request['channel'] !== 'CSYNCIN1' => Http::response(['ok' => true, 'messages' => [['user' => 'U9', 'text' => 'hello from random', 'ts' => '1789130000.000100']]]),
                isset($request['cursor'])          => Http::response(['ok' => true, 'messages' => [['user' => 'U9', 'text' => 'first ever', 'ts' => '1789100000.000100']]]),
                default                            => $newestPage($request),
            };
        },
        'slack.com/api/conversations.replies*' => Http::response(['ok' => true, 'messages' => [
            ['user' => 'U9', 'text' => 'thread start', 'ts' => '1789120000.000100', 'thread_ts' => '1789120000.000100'],
            ['user' => 'U8', 'text' => 'thread reply', 'ts' => '1789120500.000100', 'thread_ts' => '1789120000.000100'],
        ]]),
    ]);

    $totals = SyncSlackPublicChannels::run($group, history: true);

    $inside  = SlackChannel::where('slack_id', 'CSYNCIN1')->firstOrFail();
    $outside = SlackChannel::where('slack_id', 'CSYNCOUT1')->firstOrFail();
    expect($totals)->toBe(['channels' => 2, 'joined' => 0, 'messages' => 3, 'added' => 3])
        ->and($inside->name)->toBe('warehouse')
        ->and($inside->messages()->orderBy('ts')->pluck('text')->all())->toBe(['first ever', 'thread start', 'thread reply'])
        ->and($inside->history_backfilled_to)->toBe('1789100000.000100')
        ->and($inside->history_synced_at)->not->toBeNull()
        ->and($outside->is_member)->toBeFalse()
        ->and($outside->messages()->count())->toBe(0);
    Sleep::assertSleptTimes(1);
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.join'));
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.list') && $request['types'] === 'public_channel');

    $totals = SyncSlackPublicChannels::run($group, join: true, history: true);

    expect($totals['joined'])->toBe(1)
        ->and($totals['added'])->toBe(1)
        ->and($outside->refresh()->is_member)->toBeTrue()
        ->and($outside->messages()->pluck('text')->all())->toBe(['hello from random'])
        ->and($inside->messages()->count())->toBe(3);

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.join') && $request['channel'] === 'CSYNCOUT1');
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.history') && $request['channel'] === 'CSYNCIN1' && isset($request['oldest']));

    $joinAllowed = false;
    $outside->update(['is_member' => false]);
    expect(fn () => SyncSlackPublicChannels::run($group, join: true))->toThrow(RuntimeException::class, 'missing_scope');
});

test('the Slack people directory keeps everyone, matches aiku users by email without overwriting and gives past messages an author', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $s       = Str::lower(Str::random(6));
    $newUser = fn (string $email, ?string $slackUserId = null) => User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'email' => $email, 'slack_user_id' => $slackUserId]);

    $matching = $newUser("match.$s@example.com");
    $holding  = $newUser("held.$s@example.com", "UOTHER$s");
    $sharedA  = $newUser("Shared.$s@example.com");
    $sharedB  = $newUser("shared.$s@example.com");
    $leaver   = $newUser("gone.$s@example.com");
    $botOwner = $newUser("bot.$s@example.com");

    $channel = SlackChannel::create(['group_id' => $this->organisation->group->id, 'slack_id' => "CPEOPLE$s", 'is_member' => true]);
    foreach (["UMATCH$s", "UHELD$s", "USHARED$s"] as $i => $author) {
        SlackMessage::create(['slack_channel_id' => $channel->id, 'ts' => "178900000$i.000100", 'slack_user_id' => $author, 'text' => 'hello', 'sent_at' => now()]);
    }

    Http::fake([
        'slack.com/api/users.list*' => function (HttpRequest $request) use ($s) {
            return isset($request['cursor'])
                ? Http::response(['ok' => true, 'members' => [
                    ['id' => "UGONE$s", 'name' => 'gone', 'deleted' => true, 'profile' => ['email' => "gone.$s@example.com", 'display_name' => 'Gone']],
                    ['id' => "UBOT$s", 'name' => 'robot', 'is_bot' => true, 'profile' => ['real_name' => 'Robot', 'email' => "bot.$s@example.com"]],
                ]])
                : Http::response(['ok' => true, 'members' => [
                    ['id' => "UMATCH$s", 'name' => 'match', 'real_name' => 'Match Person', 'profile' => ['email' => "Match.$s@Example.com", 'display_name' => 'Matchy', 'image_192' => 'https://avatars.example/m.png']],
                    ['id' => "UHELD$s", 'name' => 'held', 'profile' => ['email' => "held.$s@example.com"]],
                    ['id' => "USHARED$s", 'name' => 'shared', 'profile' => ['email' => "shared.$s@example.com"]],
                ], 'response_metadata' => ['next_cursor' => 'page2']]);
        },
    ]);

    \App\Actions\Chat\Staff\Slack\SyncSlackUsers::run();
    $totals = \App\Actions\Chat\Staff\Slack\SyncSlackUsers::run();

    $people = \App\Models\Chat\SlackUser::where('slack_id', 'like', "%$s")->get()->keyBy('slack_id');
    expect($totals['people'])->toBe(5)
        ->and($people)->toHaveCount(5)
        ->and($people["UMATCH$s"]->user_id)->toBe($matching->id)
        ->and($people["UMATCH$s"]->display_name)->toBe('Matchy')
        ->and($people["UMATCH$s"]->avatar_url)->toBe('https://avatars.example/m.png')
        ->and($people["UMATCH$s"]->email)->toBe("match.$s@example.com")
        ->and($matching->refresh()->slack_user_id)->toBe("UMATCH$s")
        ->and($people["UHELD$s"]->user_id)->toBe($holding->id)
        ->and($holding->refresh()->slack_user_id)->toBe("UOTHER$s")
        ->and($people["USHARED$s"]->user_id)->toBeNull()
        ->and($sharedA->refresh()->slack_user_id)->toBeNull()
        ->and($sharedB->refresh()->slack_user_id)->toBeNull()
        ->and($people["UGONE$s"]->is_deleted)->toBeTrue()
        ->and($people["UGONE$s"]->user_id)->toBe($leaver->id)
        ->and($leaver->refresh()->slack_user_id)->toBeNull()
        ->and($people["UBOT$s"]->is_bot)->toBeTrue()
        ->and($people["UBOT$s"]->user_id)->toBeNull()
        ->and($botOwner->refresh()->slack_user_id)->toBeNull()
        ->and($channel->messages()->orderBy('ts')->pluck('user_id')->all())->toBe([$matching->id, $holding->id, null])
        ->and($channel->messages()->where('slack_user_id', "USHARED$s")->first()->slackUser->name)->toBe('shared');

    \App\Actions\Chat\Staff\Slack\StoreSlackMessage::make()->store($channel, ['user' => "UHELD$s", 'text' => 'edited later', 'ts' => '1789000001.000100']);
    expect($channel->messages()->where('slack_user_id', "UHELD$s")->value('user_id'))->toBe($holding->id);
});

test('Slack people who join, change or leave are kept up to date from Slack events', function () {
    Config::set('services.slack.signing_secret', 'shh');
    $s      = Str::lower(Str::random(6));
    $joiner = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'email' => "joiner.$s@example.com"]);

    $post = function (array $event) {
        $body      = json_encode(['type' => 'event_callback', 'event' => $event]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    $joined = ['type' => 'team_join', 'user' => ['id' => "UJOIN$s", 'name' => 'joiner', 'real_name' => 'New Joiner', 'profile' => ['email' => "Joiner.$s@example.com", 'display_name' => 'Newbie']]];
    $post($joined)->assertOk();
    $post($joined)->assertOk();

    $person = \App\Models\Chat\SlackUser::where('slack_id', "UJOIN$s")->sole();
    expect($person->display_name)->toBe('Newbie')
        ->and($person->user_id)->toBe($joiner->id)
        ->and($joiner->refresh()->slack_user_id)->toBe("UJOIN$s");

    $post(['type' => 'user_change', 'user' => ['id' => "UJOIN$s", 'name' => 'joiner', 'real_name' => 'New Joiner', 'profile' => ['email' => "joiner.$s@example.com", 'display_name' => 'Renamed', 'image_192' => 'https://avatars.example/j.png']]])->assertOk();
    expect($person->refresh()->display_name)->toBe('Renamed')
        ->and($person->avatar_url)->toBe('https://avatars.example/j.png');

    $post(['type' => 'user_change', 'user' => ['id' => "UJOIN$s", 'name' => 'joiner', 'deleted' => true, 'profile' => ['display_name' => 'Renamed']]])->assertOk();
    expect(\App\Models\Chat\SlackUser::where('slack_id', "UJOIN$s")->count())->toBe(1)
        ->and($person->refresh()->is_deleted)->toBeTrue()
        ->and($person->email)->toBe("joiner.$s@example.com")
        ->and($person->user_id)->toBe($joiner->id);
});

test('files of new public Slack messages are copied into aiku once per Slack file, a login page is refused and big or external files are not downloaded, INI-417', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    \Illuminate\Support\Facades\Storage::fake('media');
    $s = Str::lower(Str::random(6));
    Http::fake([
        "files.slack.com/files-pri/T1-FPDF$s/*"   => Http::response('%PDF-1.4 delivery note', 200, ['Content-Type' => 'application/pdf']),
        "files.slack.com/files-pri/T1-FLOGIN$s/*" => Http::response('<!DOCTYPE html><html>Sign in to Slack</html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        "files.slack.com/files-pri/T1-FSTUCK$s/*" => Http::response('stuck', 200, ['Content-Type' => 'text/plain']),
    ]);

    $post = function (array $event) {
        $body      = json_encode(['type' => 'event_callback', 'event' => $event]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };
    $pdf = ['id' => "FPDF$s", 'name' => 'delivery note.pdf', 'mimetype' => 'application/pdf', 'filetype' => 'pdf', 'size' => 22, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FPDF$s/download/delivery_note.pdf"];
    $big = ['id' => "FBIG$s", 'name' => 'video.mov', 'mimetype' => 'video/quicktime', 'size' => 200 * 1024 * 1024, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FBIG$s/download/video.mov"];
    $drive = ['id' => "FDRIVE$s", 'name' => 'Stock sheet', 'mode' => 'external', 'url_private' => 'https://docs.google.com/spreadsheets/d/abc'];

    $post(['type' => 'message', 'channel' => "CFILES$s", 'channel_type' => 'channel', 'text' => 'see attached', 'ts' => '1789200000.000100', 'files' => [$pdf, $big, $drive]])->assertOk();
    $post(['type' => 'message', 'channel' => "CFILES$s", 'channel_type' => 'channel', 'text' => 'same note again', 'ts' => '1789200100.000200', 'files' => [$pdf]])->assertOk();

    $copied = \App\Models\Chat\SlackFile::where('slack_id', "FPDF$s")->sole();
    expect($copied->state)->toBe(\App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED)
        ->and($copied->copy->file_name)->toEndWith('.pdf')
        ->and(file_get_contents($copied->copy->getPath()))->toBe('%PDF-1.4 delivery note')
        ->and($copied->message->text)->toBe('see attached')
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FBIG$s")->sole()->only(['state', 'reason']))->toBe(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::SKIPPED, 'reason' => 'larger than 50 MB'])
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FDRIVE$s")->sole()->state)->toBe(\App\Enums\CRM\Livechat\SlackFileStateEnum::EXTERNAL);
    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Bearer xoxb-test'));

    $message = SlackMessage::where('text', 'see attached')->sole();
    $login   = ['id' => "FLOGIN$s", 'name' => 'photo.jpg', 'mimetype' => 'image/jpeg', 'size' => 10, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FLOGIN$s/download/photo.jpg"];
    expect(fn () => \App\Actions\Chat\Staff\Slack\CopySlackMessageFile::run($message, $login))->toThrow(RuntimeException::class, 'login page');
    $refused = \App\Models\Chat\SlackFile::where('slack_id', "FLOGIN$s")->sole();
    expect($refused->state)->toBe(\App\Enums\CRM\Livechat\SlackFileStateEnum::FAILED)
        ->and($refused->media_id)->toBeNull()
        ->and($refused->getMedia('slack_files'))->toHaveCount(0);

    $stuck     = ['id' => "FSTUCK$s", 'name' => 'notes.txt', 'mimetype' => 'text/plain', 'size' => 5, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FSTUCK$s/download/notes.txt"];
    $claimed   = \App\Models\Chat\SlackFile::create(['group_id' => $message->channel->group_id, 'slack_id' => "FSTUCK$s", 'state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::COPYING, 'data' => $stuck]);
    $sentSoFar = count(Http::recorded());
    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFile::run($message, $stuck)->state)->toBe(\App\Enums\CRM\Livechat\SlackFileStateEnum::COPYING)
        ->and(count(Http::recorded()))->toBe($sentSoFar);
    \App\Models\Chat\SlackFile::whereKey($claimed->id)->update(['updated_at' => now()->subMinutes(20)]);
    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFile::run($message, $stuck)->state)->toBe(\App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED);
});

test('files of Slack messages already stored are copied by a resumable command that skips what is done and records files gone in Slack, INI-418', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    \Illuminate\Support\Facades\Storage::fake('media');
    Sleep::fake();
    $s    = Str::lower(Str::random(6));
    $more = ['id' => "FMORE$s", 'name' => 'more.png', 'mimetype' => 'image/png', 'size' => 4, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FMORE$s/download/more.png"];
    Http::fake([
        "files.slack.com/files-pri/T1-FOLD$s/*"  => Http::response('old photo', 200, ['Content-Type' => 'image/png']),
        "files.slack.com/files-pri/T1-FGONE$s/*" => Http::response('', 404),
        "files.slack.com/files-pri/T1-FMORE$s/*" => Http::response('more', 200, ['Content-Type' => 'image/png']),
        'slack.com/api/files.info*'              => Http::response(['ok' => true, 'file' => $more]),
    ]);

    $channel = SlackChannel::create(['group_id' => $this->organisation->group_id, 'slack_id' => "CHIST$s", 'name' => "history-$s", 'is_member' => true]);
    $private = SlackChannel::create(['group_id' => $this->organisation->group_id, 'slack_id' => "CPRIV$s", 'name' => "went-private-$s", 'is_member' => true, 'is_public' => false]);
    $old     = ['id' => "FOLD$s", 'name' => 'old.png', 'mimetype' => 'image/png', 'size' => 9, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FOLD$s/download/old.png"];
    $gone    = ['id' => "FGONE$s", 'name' => 'lost.pdf', 'mimetype' => 'application/pdf', 'size' => 1000, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FGONE$s/download/lost.pdf"];
    $hidden  = ['id' => "FHIDDEN$s", 'mode' => 'hidden_by_limit'];
    $stub    = ['id' => "FMORE$s", 'file_access' => 'check_file_info'];
    foreach ([['1500000000.000100', [$old]], ['1500000100.000200', [$old, $gone, $hidden, $stub]]] as [$ts, $files]) {
        $channel->messages()->create(['ts' => $ts, 'text' => 'from 2017', 'sent_at' => now()->subYears(9), 'data' => ['ts' => $ts, 'files' => $files]]);
    }
    $private->messages()->create(['ts' => '1500000200.000300', 'text' => 'private now', 'sent_at' => now()->subYears(9), 'data' => ['files' => [['id' => "FPRIV$s", 'size' => 5, 'url_private_download' => "https://files.slack.com/files-pri/T1-FPRIV$s/x.png"]]]]);

    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->dryRun($channel))->toBe([
        'ask Slack: check_file_info'    => ['files' => 1, 'bytes' => 0],
        'not_available: hidden_by_limit' => ['files' => 1, 'bytes' => 0],
        'to_copy'                       => ['files' => 2, 'bytes' => 1009],
    ])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->dryRun($private))->toBe([]);

    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::run($channel, limit: 1))->toBe(['queued' => 1, 'recorded' => 0])
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FOLD$s")->sole()->state)->toBe(\App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED)
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FGONE$s")->exists())->toBeFalse();

    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::run($channel))->toBe(['queued' => 2, 'recorded' => 1])
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FGONE$s")->sole()->only(['state', 'reason']))->toBe(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::NOT_AVAILABLE, 'reason' => 'gone in Slack (http 404)'])
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FHIDDEN$s")->sole()->reason)->toBe('Slack shows it as hidden_by_limit')
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FMORE$s")->sole()->only(['state', 'size']))->toBe(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED, 'size' => 4]);

    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::run($channel))->toBe(['queued' => 0, 'recorded' => 0])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->dryRun($channel))->toBe([])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFile::run($private->messages()->sole(), ['id' => "FPRIV$s", 'size' => 5, 'url_private_download' => "https://files.slack.com/files-pri/T1-FPRIV$s/x.png"]))->toBeNull()
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FPRIV$s")->exists())->toBeFalse();

    \App\Models\Chat\SlackFile::where('slack_id', "FOLD$s")->update(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::FAILED]);
    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::run($channel))->toBe(['queued' => 0, 'recorded' => 0])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::run($channel, retryFailed: true))->toBe(['queued' => 1, 'recorded' => 0])
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FOLD$s")->sole()->state)->toBe(\App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED);
    Http::assertSentCount(5);
    Sleep::assertSleptTimes(1);
});

test('reactions to kept Slack messages are stored and removed as Slack sends them, once each, and the ticket reaction still raises tickets, INI-419', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.ticket_reaction', 'ticket');
    $post = function (array $event) {
        $body      = json_encode(['type' => 'event_callback', 'event' => $event]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    $post(['type' => 'message', 'channel' => 'CREACT1', 'channel_type' => 'channel', 'user' => 'UREACT1', 'text' => 'Truck is here', 'ts' => '1789200000.000100'])->assertOk();
    $message  = SlackMessage::where('ts', '1789200000.000100')->whereHas('channel', fn ($channel) => $channel->where('slack_id', 'CREACT1'))->firstOrFail();
    $reaction = ['type' => 'reaction_added', 'user' => 'UREACT2', 'reaction' => 'white_check_mark', 'item' => ['type' => 'message', 'channel' => 'CREACT1', 'ts' => '1789200000.000100'], 'event_ts' => '1789200060.000200'];

    $post($reaction)->assertOk();
    $post($reaction)->assertOk();
    $post(['reaction' => 'eyes'] + $reaction)->assertOk();
    expect($message->reactions()->orderBy('name')->pluck('name')->all())->toBe(['eyes', 'white_check_mark'])
        ->and($message->reactions()->where('name', 'white_check_mark')->value('slack_user_id'))->toBe('UREACT2')
        ->and($message->reactions()->where('name', 'white_check_mark')->first()->reacted_at->timestamp)->toBe(1789200060);

    $post(['type' => 'reaction_removed'] + $reaction)->assertOk();
    $post(['type' => 'reaction_removed'] + $reaction)->assertOk();
    expect($message->reactions()->pluck('name')->all())->toBe(['eyes']);

    $before = \App\Models\Chat\SlackMessageReaction::count();
    $post(['item' => ['type' => 'message', 'channel' => 'CREACT1', 'ts' => '1789299999.000000']] + $reaction)->assertOk();
    $post(['item' => ['type' => 'message', 'channel' => 'CNOTKEPT1', 'ts' => '1789200000.000100']] + $reaction)->assertOk();
    expect(\App\Models\Chat\SlackMessageReaction::count())->toBe($before);

    Http::fake(['slack.com/*' => Http::response(['ok' => true, 'messages' => []])]);
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $post(['reaction' => 'ticket'] + $reaction)->assertOk();
    expect($message->reactions()->where('name', 'ticket')->exists())->toBeTrue();
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.history') && $request['channel'] === 'CREACT1' && $request['latest'] === '1789200000.000100');

    $message->channel->update(['is_public' => false]);
    $post(['reaction' => 'tada'] + $reaction)->assertOk();
    expect($message->reactions()->where('name', 'tada')->exists())->toBeFalse();
    $message->channel->update(['is_public' => true]);

    $old = $message->channel->messages()->create([
        'ts'      => '1789100000.000300',
        'sent_at' => now()->subYear(),
        'data'    => ['ts' => '1789100000.000300', 'reactions' => [['name' => 'pray', 'users' => ['UREACT1', 'UREACT2'], 'count' => 2], ['name' => '+1', 'users' => ['UREACT3'], 'count' => 1]]],
    ]);
    $old->reactions()->create(['slack_user_id' => 'UREACT4', 'name' => 'fire', 'reacted_at' => now()]);

    $this->artisan('slack:fill-message-reactions')->assertSuccessful();
    $this->artisan('slack:fill-message-reactions')->assertSuccessful();
    expect($old->reactions()->orderBy('name')->orderBy('slack_user_id')->get(['name', 'slack_user_id'])->map(fn ($row) => "$row->name:$row->slack_user_id")->all())
        ->toBe(['+1:UREACT3', 'fire:UREACT4', 'pray:UREACT1', 'pray:UREACT2'])
        ->and($old->reactions()->where('name', 'pray')->value('reacted_at'))->toBeNull();

    \App\Actions\Chat\Staff\Slack\StoreSlackMessage::make()->store($message->channel, ['ts' => '1789200000.000100', 'text' => 'Truck is here', 'reactions' => [['name' => 'truck', 'users' => ['UREACT5']]]]);
    expect($message->reactions()->where('name', 'truck')->value('slack_user_id'))->toBe('UREACT5')
        ->and($message->reactions()->where('name', 'eyes')->exists())->toBeTrue();
});

test('Slack channel events keep the channel list current, and a channel turned private is never stored again, INI-420', function () {
    Config::set('services.slack.signing_secret', 'shh');
    $post = function (array $event) {
        $body      = json_encode(['type' => 'event_callback', 'authorizations' => [['user_id' => 'UAIKUBOT', 'is_bot' => true]], 'event' => $event]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };
    Http::fake(['slack.com/api/conversations.join' => Http::response(['ok' => true])]);

    $post(['type' => 'channel_created', 'channel' => ['id' => 'CEVENT1', 'name' => 'new-orders', 'created' => 1789300000, 'creator' => 'U1']])->assertOk();
    $channel = SlackChannel::where('slack_id', 'CEVENT1')->firstOrFail();
    expect($channel->name)->toBe('new-orders')
        ->and($channel->is_public)->toBeTrue()
        ->and($channel->is_member)->toBeFalse()
        ->and($channel->is_archived)->toBeFalse();
    Http::assertNothingSent();

    $post(['type' => 'channel_rename', 'channel' => ['id' => 'CEVENT1', 'name' => 'orders-eu', 'created' => 1789300000]])->assertOk();
    expect($channel->refresh()->name)->toBe('orders-eu');

    $post(['type' => 'member_joined_channel', 'user' => 'USOMEONE', 'channel' => 'CEVENT1', 'channel_type' => 'C'])->assertOk();
    expect($channel->refresh()->is_member)->toBeFalse();
    $post(['type' => 'member_joined_channel', 'user' => 'UAIKUBOT', 'channel' => 'CEVENT1', 'channel_type' => 'C'])->assertOk();
    expect($channel->refresh()->is_member)->toBeTrue();
    $post(['type' => 'member_left_channel', 'user' => 'UAIKUBOT', 'channel' => 'CEVENT1', 'channel_type' => 'C'])->assertOk();
    expect($channel->refresh()->is_member)->toBeFalse();
    $post(['type' => 'member_joined_channel', 'user' => 'UAIKUBOT', 'channel' => 'CEVENT1', 'channel_type' => 'C'])->assertOk();
    $post(['type' => 'channel_left', 'channel' => 'CEVENT1', 'actor_id' => 'U1'])->assertOk();
    expect($channel->refresh()->is_member)->toBeFalse();
    $post(['type' => 'member_joined_channel', 'user' => 'UAIKUBOT', 'channel' => 'GEVENTPRIV1', 'channel_type' => 'G'])->assertOk();
    expect(SlackChannel::where('slack_id', 'GEVENTPRIV1')->exists())->toBeFalse();

    $post(['type' => 'channel_archive', 'channel' => 'CEVENT1', 'user' => 'U1'])->assertOk();
    expect($channel->refresh()->is_archived)->toBeTrue();
    $post(['type' => 'channel_unarchive', 'channel' => 'CEVENT1', 'user' => 'U1'])->assertOk();
    expect($channel->refresh()->is_archived)->toBeFalse();

    $post(['type' => 'message', 'channel' => 'CEVENT1', 'channel_type' => 'channel', 'user' => 'U1', 'text' => 'kept', 'ts' => '1789300100.000100'])->assertOk();
    $post(['type' => 'channel_deleted', 'channel' => 'CEVENT1'])->assertOk();
    expect($channel->refresh()->deleted_in_slack_at)->not->toBeNull()
        ->and($channel->messages()->pluck('text')->all())->toBe(['kept']);
    $post(['type' => 'channel_deleted', 'channel' => 'CEVENTNEVERSEEN'])->assertOk();
    expect(SlackChannel::where('slack_id', 'CEVENTNEVERSEEN')->exists())->toBeFalse();

    $post(['type' => 'channel_created', 'channel' => ['id' => 'CEVENT2', 'name' => 'going-private']])->assertOk();
    $post(['type' => 'message', 'channel' => 'CEVENT2', 'channel_type' => 'channel', 'user' => 'U1', 'text' => 'said in public', 'ts' => '1789300200.000100'])->assertOk();
    $post(['type' => 'message', 'subtype' => 'channel_convert_to_private', 'channel' => 'CEVENT2', 'channel_type' => 'channel', 'user' => 'U1', 'text' => 'made this channel private', 'ts' => '1789300300.000100'])->assertOk();
    $private = SlackChannel::where('slack_id', 'CEVENT2')->firstOrFail();
    expect($private->is_public)->toBeFalse();

    $post(['type' => 'message', 'channel' => 'CEVENT2', 'channel_type' => 'channel', 'user' => 'U1', 'text' => 'said in private 1', 'ts' => '1789300400.000100'])->assertOk();
    $post(['type' => 'message', 'channel' => 'CEVENT2', 'user' => 'U1', 'text' => 'said in private 2', 'ts' => '1789300500.000100'])->assertOk();
    $post(['type' => 'reaction_added', 'user' => 'U2', 'reaction' => 'eyes', 'item' => ['type' => 'message', 'channel' => 'CEVENT2', 'ts' => '1789300200.000100']])->assertOk();
    expect($private->messages()->pluck('text')->all())->toBe(['said in public'])
        ->and(\App\Models\Chat\SlackMessageReaction::whereIn('slack_message_id', $private->messages()->pluck('id'))->count())->toBe(0);

    $post(['type' => 'channel_created', 'channel' => ['id' => 'CEVENT3', 'name' => 'also-private']])->assertOk();
    $post(['type' => 'message', 'channel' => 'CEVENT3', 'channel_type' => 'group', 'user' => 'U1', 'text' => 'private now', 'ts' => '1789300600.000100'])->assertOk();
    expect(SlackChannel::where('slack_id', 'CEVENT3')->value('is_public'))->toBeFalse();
    $post(['type' => 'message', 'subtype' => 'channel_convert_to_private', 'channel' => 'CEVENTUNSEEN', 'channel_type' => 'channel', 'user' => 'U1', 'text' => 'made this channel private', 'ts' => '1789300700.000100'])->assertOk();
    expect(SlackChannel::where('slack_id', 'CEVENTUNSEEN')->exists())->toBeFalse();

    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $new = SlackChannel::where('slack_id', 'CEVENT1')->firstOrFail();
    $new->update(['deleted_in_slack_at' => null]);
    \App\Actions\Chat\Staff\Slack\UpdateSlackChannelFromEvent::make()->joinNewPublicChannel($private);
    Http::assertNothingSent();
    \App\Actions\Chat\Staff\Slack\UpdateSlackChannelFromEvent::make()->joinNewPublicChannel($new);
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.join') && $request['channel'] === 'CEVENT1');
    expect($new->refresh()->is_member)->toBeTrue();
});

test('the Slack catch-up runs nightly without joining, one run at a time, records each run and keeps the error of a failed one, INI-421', function () {
    config(['app.master' => true]);
    $schedule   = new \Illuminate\Console\Scheduling\Schedule();
    $reflection = new ReflectionMethod(\App\Console\Kernel::class, 'schedule');
    $reflection->invoke(app(\App\Console\Kernel::class), $schedule);
    $events = collect($schedule->events())->filter(fn ($event) => str_contains((string) $event->command, 'slack:sync-public-channels'));

    expect($events)->toHaveCount(1);
    $event = $events->first();
    expect($event->command)->toContain('--history')->not->toContain('--join')
        ->and($event->expression)->toBe('40 2 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();

    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake([
        'slack.com/api/conversations.list*'    => Http::sequence()
            ->push(['ok' => true, 'channels' => [['id' => 'CNIGHTLY1', 'name' => 'nightly', 'is_member' => true, 'is_archived' => false]]])
            ->push(['ok' => false, 'error' => 'invalid_auth']),
        'slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [['user' => 'U9', 'text' => 'missed while down', 'ts' => '1789400000.000100']]]),
    ]);

    $this->artisan('slack:sync-public-channels --history')->assertSuccessful();
    $run = \App\Models\Chat\SlackSyncRun::latest('id')->firstOrFail();
    expect($run->finished_at)->not->toBeNull()
        ->and($run->channels)->toBe(1)
        ->and($run->messages_read)->toBe(1)
        ->and($run->messages_added)->toBe(1)
        ->and($run->error)->toBeNull();

    expect(fn () => $this->artisan('slack:sync-public-channels --history')->run())->toThrow(RuntimeException::class, 'invalid_auth');
    $failed = \App\Models\Chat\SlackSyncRun::latest('id')->firstOrFail();
    expect($failed->id)->not->toBe($run->id)
        ->and($failed->finished_at)->not->toBeNull()
        ->and($failed->error)->toContain('invalid_auth');
});

test('the Slack catch-up leaves unfinished history alone, a channel Slack stops listing as public is closed, and only a new channel makes a row', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Config::set('services.slack.signing_secret', 'shh');
    Sleep::fake();
    $group    = \App\Models\SysAdmin\Group::firstOrFail();
    $done     = SlackChannel::create(['group_id' => $group->id, 'slack_id' => 'CWHOLE1', 'name' => 'whole', 'is_member' => true, 'history_synced_at' => now()->subHour()]);
    $pending  = SlackChannel::create(['group_id' => $group->id, 'slack_id' => 'CHALF1', 'name' => 'half', 'is_member' => true]);
    $gone     = SlackChannel::create(['group_id' => $group->id, 'slack_id' => 'CWENT1', 'name' => 'went', 'is_member' => true, 'history_synced_at' => now()->subHour()]);
    $listed   = fn (string $id, string $name) => ['id' => $id, 'name' => $name, 'is_member' => true, 'is_archived' => false];
    Http::fake([
        'slack.com/api/conversations.list*'    => Http::response(['ok' => true, 'channels' => [$listed('CWHOLE1', 'whole'), $listed('CHALF1', 'half')]]),
        'slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [['user' => 'U9', 'text' => 'caught up', 'ts' => '1789500000.000100']]]),
    ]);

    $totals = SyncSlackPublicChannels::run($group, history: true, catchUpOnly: true);
    expect($totals['messages'])->toBe(1)
        ->and($done->messages()->count())->toBe(1)
        ->and($pending->messages()->count())->toBe(0)
        ->and($pending->refresh()->history_synced_at)->toBeNull()
        ->and($pending->is_public)->toBeTrue()
        ->and($gone->refresh()->is_public)->toBeFalse();
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.history') && $request['channel'] === 'CWHOLE1');
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.history') && $request['channel'] !== 'CWHOLE1');

    $post = function (array $event) {
        $body      = json_encode(['type' => 'event_callback', 'event' => $event, 'authorizations' => [['user_id' => 'UBOT1']]]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    $post(['type' => 'channel_rename', 'channel' => ['id' => 'CNEVERSEEN1', 'name' => 'secret-plans']])->assertOk();
    $post(['type' => 'channel_archive', 'channel' => 'CNEVERSEEN2'])->assertOk();
    $post(['type' => 'channel_rename', 'channel' => ['id' => 'CWENT1', 'name' => 'renamed-in-private']])->assertOk();
    $post(['type' => 'channel_rename', 'channel' => ['id' => 'CWHOLE1', 'name' => 'whole-renamed']])->assertOk();
    expect(SlackChannel::whereIn('slack_id', ['CNEVERSEEN1', 'CNEVERSEEN2'])->exists())->toBeFalse()
        ->and($gone->refresh()->name)->toBe('went')
        ->and($done->refresh()->name)->toBe('whole-renamed');

    $post(['type' => 'member_joined_channel', 'user' => 'UBOT1', 'channel' => 'CHALF1', 'channel_type' => 'G'])->assertOk();
    expect($pending->refresh()->is_public)->toBeFalse();

    $message = $done->messages()->firstOrFail();
    $message->update(['data' => ['reactions' => [['name' => 'eyes', 'users' => ['UA', 'UB'], 'count' => 2], ['name' => 'tada', 'users' => ['UA'], 'count' => 1]]] + $message->data]);
    \App\Actions\Chat\Staff\Slack\StoreSlackMessageReaction::make()->fromStoredMessage($message);
    $removed = fn (string $name) => ['type' => 'reaction_removed', 'user' => 'UA', 'reaction' => $name, 'item' => ['type' => 'message', 'channel' => 'CWHOLE1', 'ts' => $message->ts]];
    $post($removed('eyes'))->assertOk();
    $post($removed('tada'))->assertOk();
    expect($message->refresh()->data['reactions'])->toEqual([['name' => 'eyes', 'users' => ['UB'], 'count' => 1]]);
    \App\Actions\Chat\Staff\Slack\StoreSlackMessageReaction::make()->fromStoredMessage($message);
    expect($message->reactions()->get()->map(fn ($reaction) => $reaction->name.':'.$reaction->slack_user_id)->all())->toBe(['eyes:UB']);
});
