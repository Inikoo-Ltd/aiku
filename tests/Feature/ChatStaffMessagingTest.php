<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use Illuminate\Support\Arr;
use App\Actions\Chat\Staff\Json\GetStaffConversationFilesAndLinks;
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

    test('files and links lists pictures, GIFs and web links, filtered, searched, sorted and paged, never past the moment the person left', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        \Illuminate\Support\Facades\Storage::fake('public');
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['name' => 'Files '.\Illuminate\Support\Str::random(8), 'user_ids' => [$this->otherUser->id]]);
        $send = fn (array $data, ?User $as = null) => actingAs($as ?? $this->user)->post(route('grp.chat.staff.conversations.messages.store', $conversation), $data)->assertSuccessful();

        $send(['body' => 'Spec at https://example.com/spec. Also https://example.com/spec']);
        $send(['image' => \Illuminate\Http\UploadedFile::fake()->image('shelf-photo.png', 40, 40)]);
        $send(['body' => 'https://media.example.com/party.gif']);
        $send(['body' => 'nothing to see']);
        $send(['body' => 'Order https://example.com/order/7'], $this->otherUser);

        $url   = fn (array $query = []) => route('grp.chat.staff.conversations.files-links', ['staffConversation' => $conversation->ulid] + $query);
        $keys  = fn (array $items) => array_map(fn (array $item) => $item['kind'].':'.($item['link']['url'] ?? $item['media']['gif_url'] ?? $item['media']['name']), $items);
        $fetch = fn (array $query = [], ?User $as = null) => actingAs($as ?? $this->user)->getJson($url($query))->assertOk()->json();

        $all = $fetch();
        expect($keys($all['items']))->toBe(['link:https://example.com/order/7', 'media:https://media.example.com/party.gif', 'media:shelf-photo.png', 'link:https://example.com/spec'])
            ->and($all['next_cursor'])->toBeNull()
            ->and($all['items'][0]['shared_by'])->toBe($this->otherUser->chatName())
            ->and($all['items'][2]['media']['image'])->not->toBeNull();

        expect($keys($fetch(['kind' => 'media'])['items']))->toBe(['media:https://media.example.com/party.gif', 'media:shelf-photo.png'])
            ->and($keys($fetch(['kind' => 'links', 'sort' => 'oldest'])['items']))->toBe(['link:https://example.com/spec', 'link:https://example.com/order/7'])
            ->and($keys($fetch(['q' => 'SHELF'])['items']))->toBe(['media:shelf-photo.png']);

        $conversation->messages()->orderBy('id')->first()->update(['created_at' => now()->subHour()]);
        $conversation->participants()->updateExistingPivot($this->otherUser->id, ['left_at' => now()->subMinutes(30)->format(\App\Models\Chat\StaffConversation::PRECISE_DATE_FORMAT)]);
        expect($keys($fetch(['kind' => 'links'], $this->otherUser)['items']))->toBe(['link:https://example.com/spec']);

        foreach (range(1, GetStaffConversationFilesAndLinks::PER_PAGE + 1) as $i) {
            $send(['body' => "Link https://example.com/page/{$i}"]);
        }
        $first = $fetch(['kind' => 'links']);
        $rest  = $fetch(['kind' => 'links', 'cursor' => $first['next_cursor']]);
        expect($first['next_cursor'])->not->toBeNull()
            ->and(count($first['items']) + count($rest['items']))->toBe(GetStaffConversationFilesAndLinks::PER_PAGE + 3)
            ->and($rest['next_cursor'])->toBeNull();

        actingAs($this->user)->getJson($url(['kind' => 'files']))->assertUnprocessable();
        actingAs(User::factory()->create(['group_id' => $this->user->group_id]))->getJson($url())->assertForbidden();
    });

    test('non participant is rejected by the message endpoint', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $stranger     = User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        actingAs($stranger)
            ->postJson(route('grp.chat.staff.conversations.messages.store', $conversation), ['body' => 'intrude'])
            ->assertForbidden();
    });

    test('a message is accepted up to 5,000 characters counted as the chat box counts them, and refused above, INI-508', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        $send         = fn (string $body) => actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.messages.store', $conversation), ['body' => $body]);

        $send(str_repeat('😀', 5000))->assertSuccessful();
        $send('  '.str_repeat('a', 5000)."\n")->assertSuccessful();
        $send(str_repeat('a', 5001))->assertUnprocessable()->assertJsonValidationErrors('body');

        expect($conversation->messages()->count())->toBe(2);
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

describe('staff profile card', function () {
    test('shows a colleague with their organisation and marks yourself', function () {
        $colleague = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'nickname' => 'CardNick'.uniqid()]);

        $card = actingAs($this->user)
            ->getJson(route('grp.chat.staff.people.show', $colleague->id))
            ->assertOk()
            ->json('data');

        expect($card['name'])->toBe($colleague->nickname)
            ->and($card['is_me'])->toBeFalse()
            ->and($card['organisations'])->toBe([]);

        $colleague->update(['employed_in_organisation_id' => $this->organisation->id]);

        actingAs($this->user)
            ->getJson(route('grp.chat.staff.people.show', $colleague->id))
            ->assertOk()
            ->assertJsonPath('data.organisations.0.slug', $this->organisation->slug);

        actingAs($this->user)
            ->getJson(route('grp.chat.staff.people.show', $this->user->id))
            ->assertOk()
            ->assertJsonPath('data.is_me', true);
    });

    test('hides people who are inactive', function () {
        $inactive = User::factory()->create(['group_id' => $this->user->group_id, 'status' => false]);

        actingAs($this->user)
            ->getJson(route('grp.chat.staff.people.show', $inactive->id))
            ->assertNotFound();
    });
});

describe('staff messaging chat theme', function () {
    test('user can set chat theme', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'nord'])
            ->assertOk();

        expect(Arr::get($this->user->fresh()->settings, 'chat_theme'))->toBe('nord');
    });

    test('users without a chat theme get classic and can pick it', function () {
        $this->user->update(['settings' => Arr::except($this->user->settings ?? [], 'chat_theme')]);

        expect(Arr::get(App\Actions\UI\Grp\Layout\GetLayout::run($this->user->fresh()), 'chat_theme'))->toBe('classic');

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'classic'])
            ->assertOk();

        expect(Arr::get($this->user->fresh()->settings, 'chat_theme'))->toBe('classic');
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

    $post(['type' => 'message', 'subtype' => 'message_changed', 'channel' => 'CPUBLIC1', 'message' => ['user' => 'UPUBLIC1', 'text' => 'Pallet 14 is ready', 'ts' => '1789138198.657369', 'edited' => ['user' => 'UPUBLIC1', 'ts' => '1789138500.000000']]])->assertOk();
    $post(['type' => 'message', 'subtype' => 'message_changed', 'channel' => 'CPUBLIC1', 'message' => ['user' => 'UPUBLIC1', 'text' => 'an older wording that arrives late', 'ts' => '1789138198.657369']])->assertOk();
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

test('a Slack file too large for the media library is kept on the large files disk, up to 1 GB, and only when that disk is set up, INI-491', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Config::set('media-library.max_file_size', 10);
    \Illuminate\Support\Facades\Storage::fake('staff-chat-r2');
    $channel = SlackChannel::create(['group_id' => \App\Models\SysAdmin\Group::firstOrFail()->id, 'slack_id' => 'CLARGE1', 'name' => 'training', 'is_member' => true]);
    $message = SlackMessage::create(['slack_channel_id' => $channel->id, 'ts' => '1789600000.000100', 'text' => 'video', 'sent_at' => now(), 'data' => []]);
    $file    = fn (string $id, int $size) => ['id' => $id, 'name' => 'Can You Sell This.mp4', 'mimetype' => 'video/mp4', 'filetype' => 'mp4', 'size' => $size, 'url_private_download' => "https://files.slack.com/files-pri/T1-$id/video.mp4"];
    Http::fake(['files.slack.com/*' => Http::response(str_repeat('v', 20), 200, ['Content-Type' => 'video/mp4'])]);

    $copy = \App\Actions\Chat\Staff\Slack\CopySlackMessageFile::class;
    expect($copy::run($message, $file('FLARGE0', 20))->only(['state', 'reason', 'disk']))->toBe(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::SKIPPED, 'reason' => 'larger than 50 MB', 'disk' => null]);

    Config::set('filesystems.disks.staff-chat-r2.bucket', 'aiku-staff-chat');
    $kept = $copy::run($message, $file('FLARGE1', 20));
    expect($kept->only(['state', 'disk', 'path', 'size', 'media_id']))->toBe(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED, 'disk' => 'staff-chat-r2', 'path' => 'slack-files/FLARGE1/can-you-sell-this.mp4', 'size' => 20, 'media_id' => null]);
    \Illuminate\Support\Facades\Storage::disk('staff-chat-r2')->assertExists('slack-files/FLARGE1/can-you-sell-this.mp4');
    expect(\Illuminate\Support\Facades\Storage::disk('staff-chat-r2')->get('slack-files/FLARGE1/can-you-sell-this.mp4'))->toBe(str_repeat('v', 20))
        ->and($copy::run($message, $file('FLARGE2', $copy::MAX_LARGE_BYTES + 1))->only(['state', 'reason']))->toBe(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::SKIPPED, 'reason' => 'larger than 1 GB']);
});

test('nothing is kept of an ignored Slack channel: no messages, reactions, files or history, and it is never joined', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Config::set('services.slack.signing_secret', 'shh');
    Queue::fake();
    $group   = \App\Models\SysAdmin\Group::firstOrFail();
    $ignored = SlackChannel::create(['group_id' => $group->id, 'slack_id' => 'CIGNORE1', 'name' => 'ticket-mirror', 'is_member' => false, 'is_ignored' => true]);
    $earlier = SlackMessage::create(['slack_channel_id' => $ignored->id, 'ts' => '1789700000.000100', 'text' => 'kept before', 'sent_at' => now(), 'data' => ['files' => [['id' => 'FIGNORE1', 'size' => 5, 'url_private_download' => 'https://files.slack.com/files-pri/T1-FIGNORE1/x.png']], 'reactions' => [['name' => 'eyes', 'users' => ['UA'], 'count' => 1]]]]);
    $post    = function (array $event) {
        $body      = json_encode(['type' => 'event_callback', 'event' => $event]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    expect(\App\Actions\Chat\Staff\Slack\StoreSlackMessage::run(['type' => 'message', 'channel' => 'CIGNORE1', 'channel_type' => 'channel', 'user' => 'U1', 'text' => 'HELP-1 is now Done', 'ts' => '1789700001.000100']))->toBeNull();
    $post(['type' => 'reaction_added', 'user' => 'UB', 'reaction' => 'tada', 'item' => ['type' => 'message', 'channel' => 'CIGNORE1', 'ts' => $earlier->ts]])->assertOk();
    expect($ignored->messages()->count())->toBe(1)
        ->and($earlier->reactions()->count())->toBe(0)
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFile::run($earlier, $earlier->data['files'][0]))->toBeNull()
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->dryRun($ignored))->toBe([])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::run($ignored))->toBe(['queued' => 0, 'recorded' => 0]);

    Http::fake(['slack.com/api/conversations.list*' => Http::response(['ok' => true, 'channels' => [['id' => 'CIGNORE1', 'name' => 'ticket-mirror', 'is_member' => false, 'is_archived' => false]]])]);
    expect(SyncSlackPublicChannels::run($group, join: true, history: true))->toMatchArray(['joined' => 0, 'messages' => 0])
        ->and($ignored->refresh()->is_ignored)->toBeTrue();
    Http::assertNotSent(fn (HttpRequest $request) => !str_contains($request->url(), 'conversations.list'));
});

test('who is in each kept Slack channel comes from a paged member list and from join and leave events, the bot included, INI-428', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $s     = Str::upper(Str::random(6));
    $group = \App\Models\SysAdmin\Group::firstOrFail();
    $post  = function (array $event) {
        $body      = json_encode(['type' => 'event_callback', 'authorizations' => [['user_id' => 'UAIKUBOT', 'is_bot' => true]], 'event' => $event]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };
    $kept    = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CMEM$s", 'name' => 'general', 'is_member' => true]);
    $ignored = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CMEMIGN$s", 'is_member' => true, 'is_ignored' => true]);
    $private = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CMEMPRIV$s", 'is_member' => true, 'is_public' => false]);
    $ignored->members()->create(['slack_user_id' => "UOLD$s"]);
    $kept->members()->create(['slack_user_id' => "UGONE$s"]);
    $stayed = $kept->members()->create(['slack_user_id' => "UA$s", 'joined_at' => now()->subDay()]);

    Http::fake([
        'slack.com/api/conversations.members*' => function (HttpRequest $request) use ($s) {
            if ($request['channel'] !== "CMEM$s") {
                return Http::response(['ok' => true, 'members' => []]);
            }

            return isset($request['cursor'])
                ? Http::response(['ok' => true, 'members' => ["UB$s", 'UAIKUBOT']])
                : Http::response(['ok' => true, 'members' => ["UA$s"], 'response_metadata' => ['next_cursor' => 'page2']]);
        },
    ]);
    $totals = \App\Actions\Chat\Staff\Slack\SyncSlackChannelMembers::run();

    expect($kept->members()->pluck('slack_user_id')->sort()->values()->all())->toBe(collect(['UAIKUBOT', "UA$s", "UB$s"])->sort()->values()->all())
        ->and($stayed->refresh()->joined_at)->not->toBeNull()
        ->and($ignored->members()->count())->toBe(0)
        ->and($totals['failed'])->toBe([]);
    Http::assertNotSent(fn (HttpRequest $request) => in_array($request['channel'] ?? null, ["CMEMIGN$s", "CMEMPRIV$s"], true));

    $post(['type' => 'member_joined_channel', 'user' => "UC$s", 'channel' => "CMEM$s", 'channel_type' => 'C'])->assertOk();
    $post(['type' => 'member_joined_channel', 'user' => "UC$s", 'channel' => "CMEM$s", 'channel_type' => 'C'])->assertOk();
    expect($kept->members()->where('slack_user_id', "UC$s")->sole()->joined_at)->not->toBeNull()
        ->and($kept->refresh()->is_member)->toBeTrue();
    $post(['type' => 'member_left_channel', 'user' => "UA$s", 'channel' => "CMEM$s", 'channel_type' => 'C'])->assertOk();
    expect($kept->members()->where('slack_user_id', "UA$s")->exists())->toBeFalse();

    $post(['type' => 'member_joined_channel', 'user' => "UC$s", 'channel' => "CMEMIGN$s", 'channel_type' => 'C'])->assertOk();
    $post(['type' => 'member_joined_channel', 'user' => "UC$s", 'channel' => "CMEMPRIV$s", 'channel_type' => 'C'])->assertOk();
    $post(['type' => 'member_joined_channel', 'user' => "UC$s", 'channel' => "GMEM$s", 'channel_type' => 'G'])->assertOk();
    $post(['type' => 'member_joined_channel', 'user' => "UC$s", 'channel' => "CMEMNEW$s", 'channel_type' => 'C'])->assertOk();
    expect(\App\Models\Chat\SlackChannelMember::where('slack_user_id', "UC$s")->count())->toBe(1)
        ->and(SlackChannel::whereIn('slack_id', ["GMEM$s", "CMEMNEW$s"])->exists())->toBeFalse();

    $post(['type' => 'member_left_channel', 'user' => 'UAIKUBOT', 'channel' => "CMEM$s", 'channel_type' => 'C'])->assertOk();
    expect($kept->refresh()->is_member)->toBeFalse()
        ->and($kept->members()->where('slack_user_id', 'UAIKUBOT')->exists())->toBeFalse();
});

test('a kept public Slack channel is visible only to active aiku staff linked to an active Slack person, and an ignored, private or deleted one to nobody, INI-428', function () {
    $s       = Str::upper(Str::random(6));
    $group   = \App\Models\SysAdmin\Group::firstOrFail();
    $newUser = fn (array $attributes = []) => User::factory()->create(array_merge(['group_id' => $group->id, 'language_id' => $this->user->language_id], $attributes));
    $link    = fn (User $user, string $slackId, array $attributes = []) => \App\Models\Chat\SlackUser::create(array_merge(['slack_id' => $slackId, 'user_id' => $user->id], $attributes));

    $linked      = $newUser();
    $unlinked    = $newUser();
    $leaver      = $newUser();
    $agent       = $newUser();
    $agentOrg    = \App\Actions\SupplyChain\Agent\StoreAgent::make()->action($group, \App\Models\SupplyChain\Agent::factory()->definition())->organisation_id;
    $agent->authorisedAgentsOrganisations()->attach($agentOrg, ['org_id' => $agentOrg]);
    $inactive    = $newUser(['status' => false]);
    $guest       = $newUser();
    $borrowed    = $newUser();
    $link($linked, "UL$s", ['email' => strtoupper($linked->email)]);
    $link($guest, "UGUEST$s", ['email' => $guest->email, 'data' => ['is_restricted' => true]]);
    $link($borrowed, "UBORROW$s", ['email' => 'someone.else@example.com']);
    $link($leaver, "ULEFT$s", ['is_deleted' => true, 'email' => $leaver->email]);
    $link($agent, "UAGENT$s", ['email' => $agent->email]);
    $link($inactive, "UOFF$s", ['email' => $inactive->email]);

    $public   = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CVIS$s", 'is_member' => false, 'is_archived' => true]);
    $ignored  = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CVISIGN$s", 'is_ignored' => true]);
    $private  = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CVISPRIV$s", 'is_public' => false]);
    $deleted  = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CVISDEL$s", 'deleted_in_slack_at' => now()]);
    $channels = [$public, $ignored, $private, $deleted];
    $visible  = fn (User $user) => SlackChannel::visibleTo($user)->whereIn('id', collect($channels)->pluck('id'))->pluck('id')->all();

    expect($visible($linked))->toBe([$public->id])
        ->and($public->isVisibleTo($linked))->toBeTrue();
    foreach ([$ignored, $private, $deleted] as $hidden) {
        expect($hidden->isVisibleTo($linked))->toBeFalse();
    }
    foreach ([$unlinked, $leaver, $agent, $inactive, $guest, $borrowed] as $refused) {
        expect($visible($refused))->toBe([])
            ->and($public->isVisibleTo($refused))->toBeFalse();
    }
});

test('Slack markup in a message becomes display nodes: styles, code, quotes, lists, links, mentions, entities and emoji, INI-432', function () {
    $format = fn (?string $text, array $data = []) => \App\Actions\Chat\Staff\Slack\FormatSlackMessages::make()->handle([new SlackMessage(['id' => 1, 'text' => $text, 'data' => $data])])[1];
    $plain  = fn (array $nodes) => \App\Actions\Chat\Staff\Slack\FormatSlackMessages::plainText($nodes);

    expect($format("*Bold* and _italic_ and ~gone~ with `a*b*c` and snake_case_name\nsecond line"))->toEqual([
        ['type' => 'paragraph', 'children' => [
            ['type' => 'bold', 'children' => [['type' => 'text', 'text' => 'Bold']]],
            ['type' => 'text', 'text' => ' and '],
            ['type' => 'italic', 'children' => [['type' => 'text', 'text' => 'italic']]],
            ['type' => 'text', 'text' => ' and '],
            ['type' => 'strike', 'children' => [['type' => 'text', 'text' => 'gone']]],
            ['type' => 'text', 'text' => ' with '],
            ['type' => 'code', 'text' => 'a*b*c'],
            ['type' => 'text', 'text' => ' and snake_case_name'],
            ['type' => 'br'],
            ['type' => 'text', 'text' => 'second line'],
        ]],
    ]);

    expect($format("Look:\n```if (a &lt; b &amp;&amp; *c*) {}\n<https://x.test|kept as text>```\n&gt; quoted *line*\n&gt; second\n• one\n• two _x_\n3. three\n4. four"))->toEqual([
        ['type' => 'paragraph', 'children' => [['type' => 'text', 'text' => 'Look:']]],
        ['type' => 'pre', 'text' => "if (a < b && *c*) {}\nkept as text"],
        ['type' => 'quote', 'children' => [['type' => 'paragraph', 'children' => [
            ['type' => 'text', 'text' => 'quoted '],
            ['type' => 'bold', 'children' => [['type' => 'text', 'text' => 'line']]],
            ['type' => 'br'],
            ['type' => 'text', 'text' => 'second'],
        ]]]],
        ['type' => 'list', 'ordered' => false, 'start' => 1, 'items' => [
            [['type' => 'text', 'text' => 'one']],
            [['type' => 'text', 'text' => 'two '], ['type' => 'italic', 'children' => [['type' => 'text', 'text' => 'x']]]],
        ]],
        ['type' => 'list', 'ordered' => true, 'start' => 3, 'items' => [
            [['type' => 'text', 'text' => 'three']],
            [['type' => 'text', 'text' => 'four']],
        ]],
    ]);

    expect($format("&gt;&gt;&gt;all\nof this"))->toEqual([
        ['type' => 'quote', 'children' => [['type' => 'paragraph', 'children' => [['type' => 'text', 'text' => 'all'], ['type' => 'br'], ['type' => 'text', 'text' => 'of this']]]]],
    ]);

    expect($format('See *<https://shop.test/a_b_c?x=1&amp;y=2|the _order_>* or <https://shop.test/plain> or <mailto:team@example.test|mail us> or <mailto:a@example.test>')[0]['children'])->toEqual([
        ['type' => 'text', 'text' => 'See '],
        ['type' => 'bold', 'children' => [['type' => 'link', 'url' => 'https://shop.test/a_b_c?x=1&y=2', 'text' => 'the _order_']]],
        ['type' => 'text', 'text' => ' or '],
        ['type' => 'link', 'url' => 'https://shop.test/plain', 'text' => 'https://shop.test/plain'],
        ['type' => 'text', 'text' => ' or '],
        ['type' => 'link', 'url' => 'mailto:team@example.test', 'text' => 'mail us'],
        ['type' => 'text', 'text' => ' or '],
        ['type' => 'link', 'url' => 'mailto:a@example.test', 'text' => 'a@example.test'],
    ]);

    expect($format('<!here> <!channel> <!everyone> <!subteam^S0TEAM1> <!subteam^S0TEAM2|@buyers> <!date^1700000000^{date}|Nov 14> <tel:+441234|call us> Tom &amp; Jerry &amp;lt;b&amp;gt;')[0]['children'])->toEqual([
        ['type' => 'broadcast', 'range' => 'here'],
        ['type' => 'text', 'text' => ' '],
        ['type' => 'broadcast', 'range' => 'channel'],
        ['type' => 'text', 'text' => ' '],
        ['type' => 'broadcast', 'range' => 'everyone'],
        ['type' => 'text', 'text' => ' '],
        ['type' => 'group', 'id' => 'S0TEAM1', 'label' => null],
        ['type' => 'text', 'text' => ' '],
        ['type' => 'group', 'id' => 'S0TEAM2', 'label' => '@buyers'],
        ['type' => 'text', 'text' => ' '],
        ['type' => 'text', 'text' => 'Nov 14'],
        ['type' => 'text', 'text' => ' '],
        ['type' => 'text', 'text' => 'call us'],
        ['type' => 'text', 'text' => ' Tom & Jerry &lt;b&gt;'],
    ]);

    $withEmoji = $format('Done :white_check_mark: :+1::skin-tone-3: :our_logo: at 10:30:45', ['blocks' => [['type' => 'rich_text', 'elements' => [['type' => 'rich_text_section', 'elements' => [
        ['type' => 'emoji', 'name' => 'white_check_mark', 'unicode' => '2705'],
        ['type' => 'emoji', 'name' => '+1', 'unicode' => '1f44d-1f3fc', 'skin_tone' => 3],
    ]]]]]]);
    expect($withEmoji[0]['children'])->toEqual([
        ['type' => 'text', 'text' => 'Done '],
        ['type' => 'emoji', 'name' => 'white_check_mark', 'skin_tone' => null, 'unicode' => '✅'],
        ['type' => 'text', 'text' => ' '],
        ['type' => 'emoji', 'name' => '+1', 'skin_tone' => 'skin-tone-3', 'unicode' => '👍🏼'],
        ['type' => 'text', 'text' => ' :our_logo: at 10:30:45'],
    ])->and($plain($withEmoji))->toBe("Done ✅ 👍🏼 :our_logo: at 10:30:45\n");
});

test('Slack mentions and channel references are named from slack_users and slack_channels in one query each for a page of messages, INI-432', function () {
    $s     = strtoupper(Str::random(6));
    $group = $this->organisation->group;
    \App\Models\Chat\SlackUser::create(['slack_id' => "UFMTA$s", 'name' => 'ann.handle', 'real_name' => 'Ann Example', 'display_name' => 'Annie']);
    \App\Models\Chat\SlackUser::create(['slack_id' => "UFMTB$s", 'name' => 'bob.handle', 'real_name' => 'Bob Example']);
    SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CFMT$s", 'name' => 'warehouse-news', 'is_member' => true]);
    SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CPRIV$s", 'name' => 'private-pay-talk', 'is_member' => true, 'is_public' => false]);

    $messages = [
        new SlackMessage(['id' => 1, 'text' => "<@UFMTA$s> please ask <@UFMTB$s> in <#CFMT$s>", 'data' => []]),
        new SlackMessage(['id' => 2, 'text' => "<@UNOBODY$s> wrote in <#CGONE$s|old-name> and <#CGONE2$s> and <#CPRIV$s>", 'data' => []]),
        new SlackMessage(['id' => 3, 'text' => '', 'data' => ['blocks' => [['type' => 'rich_text', 'elements' => [['type' => 'rich_text_section', 'elements' => [
            ['type' => 'user', 'user_id' => "UFMTA$s"], ['type' => 'text', 'text' => ' see '], ['type' => 'channel', 'channel_id' => "CFMT$s"],
            ['type' => 'usergroup', 'usergroup_id' => 'S0TEAM1'], ['type' => 'broadcast', 'range' => 'here'],
        ]]]]]]]),
    ];

    \Illuminate\Support\Facades\DB::enableQueryLog();
    \Illuminate\Support\Facades\DB::flushQueryLog();
    $formatted = \App\Actions\Chat\Staff\Slack\FormatSlackMessages::make()->handle($messages);
    $queries   = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::disableQueryLog();

    $plain = fn (int $id) => \App\Actions\Chat\Staff\Slack\FormatSlackMessages::plainText($formatted[$id]);
    expect($queries)->toBe(2)
        ->and($plain(1))->toBe("@Annie please ask @Bob Example in #warehouse-news\n")
        ->and($plain(2))->toBe("@unknown wrote in #unknown and #unknown and #unknown\n")
        ->and($formatted[2][0]['children'][0])->toEqual(['type' => 'user', 'id' => "UNOBODY$s", 'name' => null])
        ->and($plain(3))->toBe("@Annie see #warehouse-news@group@here\n");
});

test('nothing written in Slack becomes markup or an unsafe link: script tags, javascript and data links, colours and broken markup, INI-432', function () {
    $format = fn (?string $text, array $data = []) => \App\Actions\Chat\Staff\Slack\FormatSlackMessages::make()->handle([new SlackMessage(['id' => 1, 'text' => $text, 'data' => $data])])[1];
    $urls   = function (array $nodes) {
        $found = [];
        array_walk_recursive($nodes, function ($value, $key) use (&$found) {
            if (in_array($key, ['url', 'title_url', 'color'], true) && $value !== null) {
                $found[] = $value;
            }
        });

        return $found;
    };

    $hostile = $format('&lt;script&gt;alert(1)&lt;/script&gt; <javascript:alert(1)|click me> <JaVaScRiPt:alert(2)> <data:text/html;base64,PHNjcmlwdD4=|data> < https://x.test|spaced> <https://x.test/"onmouseover="alert(1)|quoted> &lt;@UFAKE1&gt; <@UFAKE2|<b>name</b>> *bold _crossed* tail_ *unclosed `code` ~~ **');

    expect(\App\Actions\Chat\Staff\Slack\FormatSlackMessages::plainText($hostile))->toBe(
        "<script>alert(1)</script> click me JaVaScRiPt:alert(2) data spaced quoted <@UFAKE1> <@UFAKE2|bname/b> bold _crossed tail_ *unclosed code ~~ **\n"
    )->and($urls($hostile))->toBe([])
        ->and(collect($hostile[0]['children'])->where('type', 'user')->count())->toBe(0);

    $attached = $format('', ['attachments' => [[
        'color'      => 'red;background:url(javascript:alert(1))',
        'title'      => '<img src=x onerror=alert(1)>',
        'title_link' => 'javascript:alert(1)',
        'image_url'  => 'data:image/svg+xml,<svg onload=alert(1)>',
        'text'       => '<vbscript:msgbox|vb> ok',
    ], [
        'color'      => '36a64f',
        'title'      => 'Safe',
        'title_link' => 'https://x.test/t',
    ]], 'blocks' => [['type' => 'image', 'image_url' => 'javascript:alert(1)', 'alt_text' => 'pic']]]);

    expect($urls($attached))->toBe(['#36a64f', 'https://x.test/t'])
        ->and($attached[0]['title'])->toBe('img src=x onerror=alert(1)')
        ->and(\App\Actions\Chat\Staff\Slack\FormatSlackMessages::plainText($attached))->toBe("img src=x onerror=alert(1)\nvb ok\nSafe\n");

    $deep = $format(str_repeat('&gt;', 5000).' deep '.str_repeat('*_~', 2000).'x'.str_repeat('~_*', 2000)."\n".str_repeat("&gt;&gt;&gt;\n", 3000).'end');
    expect(json_encode($deep))->not->toBeFalse()
        ->and(\App\Actions\Chat\Staff\Slack\FormatSlackMessages::plainText($deep))->toContain('deep')->toContain('end');

    $started = microtime(true);
    $huge    = $format(str_repeat('*a* _b_ ~c~ ', 4000));
    expect(microtime(true) - $started)->toBeLessThan(5.0)
        ->and(collect($huge[0]['children'])->where('type', 'bold')->count())->toBe(4000);
});

test('bot messages are read from their Block Kit blocks and attachments, unknown blocks fall back to the text and a file-only message stays empty, INI-432', function () {
    $format = fn (?string $text, array $data = []) => \App\Actions\Chat\Staff\Slack\FormatSlackMessages::make()->handle([new SlackMessage(['id' => 1, 'text' => $text, 'data' => $data])])[1];
    $plain  = fn (array $nodes) => \App\Actions\Chat\Staff\Slack\FormatSlackMessages::plainText($nodes);

    $bot = $format('*New Master Product Created *Code:*\nAB-01 View button', ['blocks' => [
        ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => '*New Master Product Created']],
        ['type' => 'section', 'fields' => [['type' => 'mrkdwn', 'text' => "*Code:*\nAB-01"], ['type' => 'mrkdwn', 'text' => "*Name:*\nTest Lamp &amp; Shade"]]],
        ['type' => 'divider'],
        ['type' => 'actions', 'elements' => [['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'View'], 'url' => 'https://app.test/p/ab-01']]],
        ['type' => 'context', 'elements' => [['type' => 'plain_text', 'text' => 'Created on 2026-11-20'], ['type' => 'image', 'image_url' => 'https://x.test/i.png', 'alt_text' => 'x'], ['type' => 'mrkdwn', 'text' => 'by *robot*']]],
        ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => 'Ticket closed by <https://t.test/1|Test Co>'], 'accessory' => ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Open']]],
    ]]);

    expect(collect($bot)->pluck('type')->all())->toBe(['header', 'fields', 'divider', 'buttons', 'context', 'paragraph', 'buttons'])
        ->and($bot[0]['children'])->toEqual([['type' => 'text', 'text' => '*New Master Product Created']])
        ->and($plain($bot))->toBe("*New Master Product Created\nCode:\nAB-01\nName:\nTest Lamp & Shade\n---\n[View]\nCreated on 2026-11-20  by robot\nTicket closed by Test Co\n[Open]\n");

    $unknown = $format('The *real* text', ['blocks' => [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => 'section']], ['type' => 'table', 'rows' => []]]]);
    expect($plain($unknown))->toBe("The real text\n")
        ->and($plain($format('', ['blocks' => [['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => 'Stock alert']], ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '_3_ lines low']]]])))->toBe("Stock alert\n3 lines low\n")
        ->and($plain($format(null, ['blocks' => [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => 'only known part']], ['type' => 'video', 'title' => ['type' => 'plain_text', 'text' => 'clip']]]])))->toBe("only known part\n");

    $onlyBlocks = $format('', ['blocks' => [['type' => 'rich_text', 'elements' => [
        ['type' => 'rich_text_section', 'elements' => [['type' => 'text', 'text' => 'Plan', 'style' => ['bold' => true]], ['type' => 'text', 'text' => "\n"], ['type' => 'link', 'url' => 'https://x.test/a'], ['type' => 'date', 'timestamp' => 1, 'fallback' => 'Monday']]],
        ['type' => 'rich_text_list', 'style' => 'ordered', 'offset' => 1, 'elements' => [['type' => 'rich_text_section', 'elements' => [['type' => 'text', 'text' => 'b', 'style' => ['code' => true]]]]]],
        ['type' => 'rich_text_quote', 'elements' => [['type' => 'text', 'text' => 'said']]],
        ['type' => 'rich_text_preformatted', 'elements' => [['type' => 'text', 'text' => 'x = 1']]],
        ['type' => 'canvas_thing', 'elements' => []],
    ]]]]);
    expect(collect($onlyBlocks)->pluck('type')->all())->toBe(['paragraph', 'list', 'quote', 'pre'])
        ->and($onlyBlocks[0]['children'][0])->toEqual(['type' => 'bold', 'children' => [['type' => 'text', 'text' => 'Plan']]])
        ->and($onlyBlocks[1]['start'])->toBe(2)
        ->and($plain($onlyBlocks))->toBe("Plan\nhttps://x.test/aMonday\n• b\nsaid\nx = 1\n");

    $attachments = $format('', ['attachments' => [
        ['color' => 'D0D0D0', 'author_name' => 'Ann Example', 'is_share' => true, 'message_blocks' => [['message' => ['blocks' => [['type' => 'rich_text', 'elements' => [['type' => 'rich_text_section', 'elements' => [['type' => 'text', 'text' => 'forwarded words']]]]]]]]]],
        ['color' => '#2684FF', 'pretext' => 'New ticket', 'title' => 'HELP-1 broken &amp; slow', 'title_link' => 'https://t.test/HELP-1', 'fields' => [['title' => 'Status', 'value' => '*Open*']], 'actions' => [['type' => 'button', 'text' => 'Close Ticket']], 'footer' => 'via <https://t.test|Tracker>'],
        ['fallback' => 'giphy: dancing'],
        ['id' => 1],
    ]]);
    expect($attachments)->toHaveCount(3)
        ->and($attachments[1]['color'])->toBe('#2684FF')
        ->and($attachments[1]['title'])->toBe('HELP-1 broken & slow')
        ->and($attachments[1]['footer'][1])->toEqual(['type' => 'link', 'url' => 'https://t.test', 'text' => 'Tracker'])
        ->and($plain($attachments))->toBe("Ann Example\nforwarded words\nHELP-1 broken & slow\nNew ticket\nStatus\nOpen\n[Close Ticket]\nvia Tracker\ngiphy: dancing\n");

    expect($format('', ['files' => [['id' => 'F1']]]))->toBe([])
        ->and($format(null))->toBe([]);
});

test('an edited Slack message keeps its earlier wordings in order, and link previews, repeated events and the nightly re-read keep none, INI-422', function () {
    Config::set('services.slack.signing_secret', 'shh');
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
    $channelId = 'CEDIT'.Str::upper(Str::random(6));
    $ts        = '1789600000.000100';
    $edit      = fn (string $text, ?string $editedTs = null, array $extra = []) => $post(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $channelId, 'channel_type' => 'channel', 'ts' => '1789600999.000900', 'message' => array_filter(['type' => 'message', 'user' => 'U1', 'text' => $text, 'ts' => $ts, 'edited' => $editedTs ? ['user' => 'U1', 'ts' => $editedTs] : null]) + $extra])->assertOk();

    $post(['type' => 'message', 'channel' => $channelId, 'channel_type' => 'channel', 'user' => 'U1', 'text' => 'Price list on https://aw.example', 'ts' => $ts])->assertOk();
    $message = SlackMessage::where('ts', $ts)->whereHas('channel', fn ($channel) => $channel->where('slack_id', $channelId))->sole();

    $edit('Price list on https://aw.example', null, ['attachments' => [['title' => 'AW', 'from_url' => 'https://aw.example']]]);
    $edit('Price list on https://aw.example', null, ['reply_count' => 2]);
    expect($message->versions()->count())->toBe(0);

    $edit('New price list on https://aw.example', '1789600100.000000');
    $edit('New price list on https://aw.example', '1789600100.000000');
    \App\Actions\Chat\Staff\Slack\StoreSlackMessage::make()->store($message->channel, ['type' => 'message', 'user' => 'U1', 'text' => 'New price list on https://aw.example', 'ts' => $ts, 'edited' => ['user' => 'U1', 'ts' => '1789600100.000000']]);
    expect($message->versions()->pluck('text')->all())->toBe(['Price list on https://aw.example']);

    $edit('Final price list', '1789600200.000000');
    $versions = $message->versions()->get();
    expect($versions->pluck('text')->all())->toBe(['Price list on https://aw.example', 'New price list on https://aw.example'])
        ->and($versions[0]->replaced_at->timestamp)->toBe(1789600100)
        ->and($versions[1]->replaced_at->timestamp)->toBe(1789600200)
        ->and($versions[1]->data['edited']['ts'])->toBe('1789600100.000000')
        ->and($message->refresh()->text)->toBe('Final price list');
});

test('custom Slack emoji are kept with a copy of each image, aliases without one, and added, removed and renamed ones follow Slack; names resolve to Unicode or the copy, INI-425', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    \Illuminate\Support\Facades\Storage::fake('media');
    $s    = Str::lower(Str::random(6));
    $png  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
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
    Http::fake([
        'slack.com/api/emoji.list*' => Http::sequence()
            ->push(['ok' => true, 'emoji' => [
                "aw-logo-$s" => "https://emoji.slack-edge.com/T1/aw-logo-$s/abc.png",
                "shipit-$s"  => "alias:aw-logo-$s",
                "yes-$s"     => 'alias:+1',
                "odd-$s"     => 'https://evil.example.com/odd.png',
            ]])
            ->push(['ok' => false, 'error' => 'missing_scope', 'needed' => 'emoji:read']),
        'emoji.slack-edge.com/*'    => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);
    $gone = \App\Models\Chat\SlackEmoji::create(['name' => "gone-$s", 'url' => 'https://emoji.slack-edge.com/T1/gone/x.png']);

    $this->artisan('slack:sync-emojis')->assertSuccessful();
    $logo = \App\Models\Chat\SlackEmoji::where('name', "aw-logo-$s")->sole();
    $alias = \App\Models\Chat\SlackEmoji::where('name', "shipit-$s")->sole();
    expect($logo->copy)->not->toBeNull()
        ->and(file_get_contents($logo->copy->getPath()))->toBe($png)
        ->and($alias->only(['alias_for', 'url', 'media_id']))->toBe(['alias_for' => "aw-logo-$s", 'url' => null, 'media_id' => null])
        ->and(\App\Models\Chat\SlackEmoji::where('name', "odd-$s")->sole()->media_id)->toBeNull()
        ->and($gone->refresh()->removed_in_slack_at)->not->toBeNull();
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'evil.example.com'));
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'slack-edge.com') && $request->hasHeader('Authorization'));

    $resolve = fn (string $name) => \App\Actions\Chat\Staff\Slack\ResolveSlackEmoji::run($name);
    expect($resolve(":shipit-$s:"))->toBe(['url' => $logo->copy->fullResolutionUrls()['original_url'], 'name' => "aw-logo-$s"])
        ->and($resolve("yes-$s"))->toBe(['unicode' => '👍'])
        ->and($resolve(':wave::skin-tone-3:'))->toBe(['unicode' => "👋\u{1F3FC}"])
        ->and($resolve('+1::skin-tone-2'))->toBe(['unicode' => "👍\u{1F3FB}"])
        ->and($resolve('v::skin-tone-5'))->toBe(['unicode' => "✌\u{1F3FE}"])
        ->and($resolve('white_check_mark'))->toBe(['unicode' => '✅'])
        ->and($resolve('rocket'))->toBe(['unicode' => '🚀'])
        ->and($resolve('pig'))->toBeNull()
        ->and($resolve("no-such-emoji-$s"))->toBeNull();

    $post(['type' => 'emoji_changed', 'subtype' => 'add', 'name' => "parrot-$s", 'value' => "https://emoji.slack-edge.com/T1/parrot-$s/def.png", 'event_ts' => '1789700000.000100'])->assertOk();
    $parrot = \App\Models\Chat\SlackEmoji::where('name', "parrot-$s")->sole();
    expect($parrot->media_id)->not->toBeNull();

    $post(['type' => 'emoji_changed', 'subtype' => 'remove', 'names' => ["parrot-$s"], 'event_ts' => '1789700100.000100'])->assertOk();
    expect($parrot->refresh()->removed_in_slack_at)->not->toBeNull()
        ->and($parrot->copy)->not->toBeNull();

    $post(['type' => 'emoji_changed', 'subtype' => 'rename', 'old_name' => "aw-logo-$s", 'new_name' => "aw-$s", 'value' => "https://emoji.slack-edge.com/T1/aw-logo-$s/abc.png", 'event_ts' => '1789700200.000100'])->assertOk();
    expect($logo->refresh()->name)->toBe("aw-$s")
        ->and($logo->media_id)->not->toBeNull()
        ->and($alias->refresh()->alias_for)->toBe("aw-$s")
        ->and($resolve("shipit-$s")['name'])->toBe("aw-$s");

    expect(fn () => \App\Actions\Chat\Staff\Slack\SyncSlackEmojis::run())->toThrow(RuntimeException::class, 'missing_scope');

    config(['app.master' => true]);
    $schedule = new \Illuminate\Console\Scheduling\Schedule();
    new ReflectionMethod(\App\Console\Kernel::class, 'schedule')->invoke(app(\App\Console\Kernel::class), $schedule);
    $nightly = collect($schedule->events())->filter(fn ($event) => str_contains((string) $event->command, 'slack:sync-emojis') || str_contains((string) $event->command, 'slack:sync-pins-and-bookmarks'));
    expect($nightly)->toHaveCount(2)
        ->and($nightly->every(fn ($event) => $event->onOneServer && $event->withoutOverlapping))->toBeTrue();
});

test('pins and bookmarks of public Slack channels are kept from Slack lists and pin events, a pin of something not kept is skipped and a missing scope fails loudly, INI-424', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
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
    $s       = Str::upper(Str::random(6));
    $group   = \App\Models\SysAdmin\Group::firstOrFail();
    $channel = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CPINS$s", 'name' => 'procedures', 'is_member' => true]);
    $private = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CPRIV$s", 'name' => 'secret', 'is_member' => true, 'is_public' => false]);
    $store   = fn (SlackChannel $in, string $ts, string $text, array $extra = []) => SlackMessage::create(['slack_channel_id' => $in->id, 'ts' => $ts, 'text' => $text, 'sent_at' => now(), 'data' => []] + $extra);
    $howTo   = $store($channel, '1789800000.000100', 'How to pack');
    $prices  = $store($channel, '1789800100.000100', 'Price list');
    $old     = $store($channel, '1789800200.000100', 'Old procedure', ['pinned_at' => now()->subYear(), 'pinned_by_slack_user_id' => 'U0']);
    $secret  = $store($private, '1789800300.000100', 'Secret');
    \App\Models\Chat\SlackFile::create(['group_id' => $group->id, 'slack_id' => "FPIN$s", 'slack_message_id' => $prices->id, 'state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED]);
    $gone = $channel->bookmarks()->create(['slack_id' => "BkGONE$s", 'title' => 'Old drive']);

    $pinsList = Http::sequence()
        ->push(['ok' => true, 'items' => [
            ['type' => 'message', 'channel' => "CPINS$s", 'created' => 1789900000, 'created_by' => 'U1', 'message' => ['ts' => $howTo->ts, 'text' => 'How to pack']],
            ['type' => 'file', 'created' => 1789900100, 'created_by' => 'U2', 'file' => ['id' => "FPIN$s"]],
            ['type' => 'message', 'channel' => "CPINS$s", 'created' => 1789900200, 'created_by' => 'U1', 'message' => ['ts' => '1700000000.000001', 'text' => 'never kept']],
            ['type' => 'file', 'created' => 1789900300, 'created_by' => 'U2', 'file' => ['id' => 'FUNKNOWN']],
        ]])
        ->push(['ok' => false, 'error' => 'missing_scope', 'needed' => 'pins:read']);
    Http::fake(function (HttpRequest $request) use ($s, $pinsList) {
        $isOurs = str_contains($request->url().$request->body(), "CPINS$s");
        if (str_contains($request->url(), 'pins.list')) {
            return $isOurs ? $pinsList->__invoke($request) : Http::response(['ok' => true, 'items' => []]);
        }
        if (str_contains($request->url(), 'bookmarks.list')) {
            return Http::response(['ok' => true, 'bookmarks' => $isOurs ? [
                ['id' => "BkA$s", 'channel_id' => "CPINS$s", 'title' => 'Stock sheet', 'link' => 'https://docs.example/sheet', 'emoji' => ':bar_chart:', 'type' => 'link', 'rank' => 'b'],
                ['id' => "BkB$s", 'channel_id' => "CPINS$s", 'title' => 'Rota', 'link' => 'https://docs.example/rota', 'emoji' => null, 'type' => 'link', 'rank' => 'a'],
            ] : []]);
        }

        return Http::response(['ok' => true]);
    });

    $this->artisan('slack:sync-pins-and-bookmarks')->assertSuccessful();
    expect($howTo->refresh()->pinned_at->timestamp)->toBe(1789900000)
        ->and($howTo->pinned_by_slack_user_id)->toBe('U1')
        ->and($prices->refresh()->pinned_by_slack_user_id)->toBe('U2')
        ->and($old->refresh()->pinned_at)->toBeNull()
        ->and(SlackMessage::where('ts', '1700000000.000001')->exists())->toBeFalse()
        ->and($channel->bookmarks()->whereNull('removed_in_slack_at')->orderBy('position')->pluck('title')->all())->toBe(['Rota', 'Stock sheet'])
        ->and($gone->refresh()->removed_in_slack_at)->not->toBeNull();
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'bookmarks.list') && $request->method() === 'POST' && $request['channel_id'] === "CPINS$s");
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url().$request->body(), "CPRIV$s"));

    $post(['type' => 'pin_added', 'user' => 'U3', 'channel_id' => "CPINS$s", 'item' => ['type' => 'message', 'channel' => "CPINS$s", 'message' => ['ts' => $old->ts]], 'event_ts' => '1789950000.000100'])->assertOk();
    $post(['type' => 'pin_removed', 'user' => 'U3', 'channel_id' => "CPINS$s", 'item' => ['type' => 'message', 'channel' => "CPINS$s", 'message' => ['ts' => $howTo->ts]], 'has_pins' => true, 'event_ts' => '1789950100.000100'])->assertOk();
    $post(['type' => 'pin_added', 'user' => 'U3', 'channel_id' => "CPINS$s", 'item' => ['type' => 'message', 'channel' => "CPINS$s", 'message' => ['ts' => '1700000000.000002', 'text' => 'not kept']], 'event_ts' => '1789950200.000100'])->assertOk();
    $post(['type' => 'pin_added', 'user' => 'U3', 'channel_id' => "CPRIV$s", 'item' => ['type' => 'message', 'channel' => "CPRIV$s", 'message' => ['ts' => $secret->ts]], 'event_ts' => '1789950300.000100'])->assertOk();
    expect($old->refresh()->pinned_by_slack_user_id)->toBe('U3')
        ->and($old->pinned_at)->not->toBeNull()
        ->and($howTo->refresh()->pinned_at)->toBeNull()
        ->and(SlackMessage::where('ts', '1700000000.000002')->exists())->toBeFalse()
        ->and($secret->refresh()->pinned_at)->toBeNull();

    expect(fn () => $this->artisan('slack:sync-pins-and-bookmarks')->run())->toThrow(RuntimeException::class, 'missing_scope');
});

test('Slack channels are read in the staff chat by linked staff only: the list, a channel stream newest page first, older pages by cursor, thread replies and deleted messages left out, INI-430', function () {
    $s        = Str::upper(Str::random(6));
    $group    = $this->organisation->group;
    $newUser  = fn () => User::factory()->create(['group_id' => $group->id, 'language_id' => $this->user->language_id]);
    $viewer   = $newUser();
    $stranger = $newUser();
    \App\Models\Chat\SlackUser::create(['slack_id' => "UVIEW$s", 'user_id' => $viewer->id, 'email' => $viewer->email, 'real_name' => 'Viewer Person']);
    \App\Models\Chat\SlackUser::create(['slack_id' => "UANN$s", 'real_name' => 'Ann Author', 'avatar_url' => 'https://avatars.slack-edge.com/ann.png']);

    $channel = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CSTREAM$s", 'name' => "stream-$s", 'is_member' => true, 'data' => ['topic' => ['value' => 'Where things happen']]]);
    $ignored = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CSTRIGN$s", 'name' => "ignored-$s", 'is_ignored' => true]);
    $private = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CSTRPRIV$s", 'name' => "private-$s", 'is_public' => false]);

    $start = now()->subDays(2)->startOfMinute();
    $ts    = fn (int $i) => sprintf('%d.%06d', $start->timestamp + $i * 60, $i);
    $store = fn (int $i, string $text, array $attributes = []) => SlackMessage::create(array_merge([
        'slack_channel_id' => $channel->id,
        'ts'               => $ts($i),
        'slack_user_id'    => "UANN$s",
        'text'             => $text,
        'sent_at'          => $start->copy()->addMinutes($i),
        'data'             => [],
    ], $attributes));
    for ($i = 1; $i <= 52; $i++) {
        $store($i, "Message $i :+1:", match ($i) {
            52      => ['thread_ts' => $ts(52), 'data' => ['reply_count' => 2, 'latest_reply' => $ts(61)]],
            50      => ['text' => '', 'data' => ['files' => [['name' => 'sheet.xlsx']]]],
            49      => ['slack_user_id' => "UVIEW$s"],
            default => [],
        });
    }
    $store(60, 'Hidden thread reply', ['thread_ts' => $ts(52)]);
    $store(61, 'Shared thread reply', ['thread_ts' => $ts(52), 'subtype' => 'thread_broadcast']);
    $store(62, 'Deleted message')->delete();
    $reacted = SlackMessage::where('slack_channel_id', $channel->id)->where('ts', $ts(51))->sole();
    \App\Models\Chat\SlackMessageReaction::create(['slack_message_id' => $reacted->id, 'slack_user_id' => "UANN$s", 'name' => 'tada']);
    \App\Models\Chat\SlackMessageReaction::create(['slack_message_id' => $reacted->id, 'slack_user_id' => "UVIEW$s", 'name' => 'tada']);

    $texts = fn (array $page) => array_map(fn (array $message) => trim(\App\Actions\Chat\Staff\Slack\FormatSlackMessages::plainText($message['nodes'])), $page['data']);

    actingAs($viewer)->get(route('grp.chat.staff.slack.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffMessaging')
            ->where('slack_channels', function ($channels) use ($channel, $ignored, $private) {
                $ids = collect($channels)->pluck('id');

                return $ids->contains($channel->id) && !$ids->contains($ignored->id) && !$ids->contains($private->id)
                    && collect($channels)->firstWhere('id', $channel->id)['topic'] === 'Where things happen';
            })
            ->where('slack_messages', null));

    $firstPage = null;
    actingAs($viewer)->get(route('grp.chat.staff.slack.show', $channel->id))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$firstPage, $s) {
            $page->component('Chat/StaffMessaging')
                ->where('slack_channel.name', "stream-$s")
                ->missing('slack_messages')
                ->loadDeferredProps(function (AssertableInertia $reload) use (&$firstPage) {
                    $firstPage = $reload->toArray()['props']['slack_messages'];
                });
        });

    $byText = collect($firstPage['data'])->keyBy(fn (array $message) => trim(\App\Actions\Chat\Staff\Slack\FormatSlackMessages::plainText($message['nodes'])));
    expect($firstPage['data'])->toHaveCount(50)
        ->and($texts($firstPage)[0])->toBe('Shared thread reply')
        ->and($texts($firstPage)[1])->toBe('Message 52 👍')
        ->and($texts($firstPage))->not->toContain('Hidden thread reply')->not->toContain('Deleted message')
        ->and($byText['Message 52 👍']['reply_count'])->toBe(2)
        ->and($byText['Message 52 👍']['latest_reply_at'])->not->toBeNull()
        ->and($byText['Message 52 👍']['author'])->toBe(['key' => "UANN$s", 'user_id' => null, 'name' => 'Ann Author', 'avatar' => null, 'is_bot' => false])
        ->and($byText['Message 49 👍']['author'])->toBe([
            'key'     => 'user-'.$viewer->id,
            'user_id' => $viewer->id,
            'name'    => $viewer->chatName(),
            'avatar'  => $viewer->image_id ? User::chatAvatarOf($viewer->imageSources(0, 48)) : null,
            'is_bot'  => false,
        ])
        ->and(json_encode($firstPage))->not->toContain('slack-edge')
        ->and($byText['Message 51 👍']['reactions'])->toBe([['name' => 'tada', 'unicode' => '🎉', 'url' => null, 'count' => 2, 'people' => ['Ann Author', $viewer->chatName()]]])
        ->and($byText['']['files'])->toHaveCount(1)
        ->and($byText['']['files'][0])->toMatchArray(['name' => 'sheet.xlsx', 'type' => 'XLSX', 'state' => 'pending', 'url' => null])
        ->and($firstPage['next_cursor'])->not->toBeNull();

    $countQueries = function (int $perPage) use ($channel) {
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::make()->handle($channel, $perPage);
        $count = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        return $count;
    };
    expect($countQueries(50))->toBe($countQueries(5));

    $olderPage = null;
    actingAs($viewer)->get(route('grp.chat.staff.slack.show', [$channel->id, 'cursor' => $firstPage['next_cursor']]))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$olderPage) {
            $page->loadDeferredProps(function (AssertableInertia $reload) use (&$olderPage) {
                $olderPage = $reload->toArray()['props']['slack_messages'];
            });
        });
    expect($texts($olderPage))->toBe(['Message 3 👍', 'Message 2 👍', 'Message 1 👍'])
        ->and($olderPage['next_cursor'])->toBeNull();

    actingAs($viewer)->get(route('grp.chat.staff.slack.show', $ignored->id))->assertForbidden();
    actingAs($viewer)->get(route('grp.chat.staff.slack.show', $private->id))->assertForbidden();

    actingAs($stranger)->get(route('grp.chat.staff.slack.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channels', []));
    actingAs($stranger)->get(route('grp.chat.staff.slack.show', $channel->id))->assertForbidden();
});

test('Verba lives at /vx, /chat/staff redirects there permanently keeping the path and query, and the menus are Channels, DM, Reports, INI-559', function () {
    expect(route('grp.chat.staff.index', [], false))->toBe('/vx')
        ->and(route('grp.chat.staff.slack.index', [], false))->toBe('/vx/slack');

    actingAs($this->user)->get(route('grp.chat.staff.index'))->assertOk();

    $ulid = (string) Str::ulid();
    $base = route('grp.chat.staff.index');
    actingAs($this->user)->get(str_replace('/vx', '/chat/staff', $base).'/c/'.$ulid.'?x=1')->assertStatus(301)->assertRedirect($base.'/c/'.$ulid.'?x=1');
    actingAs($this->user)->get(str_replace('/vx', '/chat/staff', $base))->assertStatus(301)->assertRedirect($base);

    $navigation = App\Actions\UI\Grp\Layout\GetGroupNavigation::make()->handle($this->user);
    $verba      = collect($navigation['verba']['topMenu']['subSections'])->pluck('label')->all();
    $chat       = collect($navigation['chat']['topMenu']['subSections'])->pluck('label')->all();

    expect($verba)->toBe(['V·Desks', 'Verbrations', 'V·Ds', __('Stats')])
        ->and($chat)->not->toContain(__('Verba'));
});

test('a named group is a Verbration: it is never merged into the DM with the same person, lives on its own tab, and its plain link moves there', function () {
    $other = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'status' => true]);
    $dm    = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);

    $response = actingAs($this->user)->postJson(route('grp.chat.staff.conversations.store'), ['user_ids' => [$other->id], 'name' => 'Buying team'])->assertSuccessful();
    $group    = \App\Models\Chat\StaffConversation::where('ulid', $response->json('data.ulid'))->firstOrFail();

    expect($group->id)->not->toBe($dm->id)
        ->and($group->type)->toBe('group')
        ->and($group->isVerbration())->toBeTrue()
        ->and($dm->isVerbration())->toBeFalse()
        ->and($group->participants()->pluck('users.id')->sort()->values()->all())->toBe(collect([$this->user->id, $other->id])->sort()->values()->all());

    actingAs($this->user)->get(route('grp.chat.staff.groups.index'))->assertOk();
    actingAs($this->user)->get(route('grp.chat.staff.show', $group->ulid))->assertRedirect(route('grp.chat.staff.groups.show', $group->ulid));
    actingAs($this->user)->get(route('grp.chat.staff.groups.show', $group->ulid))->assertOk();
    actingAs($this->user)->get(route('grp.chat.staff.groups.show', $dm->ulid))->assertRedirect(route('grp.chat.staff.show', $dm->ulid));
});

test('Verba Home: Jev picks the news, Haiku writes it within seconds as a quick story, the 30-minute editor gets only what Jev passed and rewrites or drops quick stories; DMs never go to the AI and a story is shown only to members, INI-570', function () {
    Event::fake([\App\Events\StaffMessageSent::class]);
    Config::set('services.openrouter.api_key', 'test-key');
    $member   = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'status' => true]);
    $outsider = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'status' => true]);
    $group    = \App\Models\Chat\StaffConversation::where('ulid', actingAs($this->user)->postJson(route('grp.chat.staff.conversations.store'), ['user_ids' => [$member->id], 'name' => 'Product team'])->json('data.ulid'))->firstOrFail();
    $dm       = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$outsider->id]]);
    \Illuminate\Support\Facades\Cache::forget('staff-stories-cursor:'.$this->user->group_id);

    $editorSaw = null;
    $sentToAi  = [];
    Http::fake(function (HttpRequest $request) use (&$editorSaw, &$sentToAi) {
        $sentToAi[] = json_encode($request->data());
        if (str_contains($request->url(), 'decisions')) {
            $state   = $request->data()['state'];
            $answers = isset($state['messages'])
                ? collect($state['messages'])->mapWithKeys(fn ($line) => [$line['id'] => ['noul' => str_contains($line['text'], 'geode') ? 0.95 : 0.05]])->all()
                : ['answer' => ['noul' => str_contains($state['text'], 'geode') ? 0.95 : 0.05]];

            return Http::response(['answers' => $answers]);
        }
        $prompt = $request->data()['messages'][0]['content'];
        if (str_contains($prompt, 'editor-in-chief')) {
            $editorSaw = $prompt;
            preg_match('/"id":"(s\d+)"/', $prompt, $match);

            return Http::response(['choices' => [['message' => ['content' => json_encode(['stories' => [
                ['id' => $match[1], 'category' => 'product', 'importance' => 4, 'headline' => 'Edited: geode lamps are here', 'blurb' => 'Samples arrived today.'],
            ]])]]]]);
        }

        return Http::response(['choices' => [['message' => ['content' => json_encode(['category' => 'product', 'importance' => 3, 'headline' => 'Quick: geode lamps', 'blurb' => 'Samples arrived.'])]]]]);
    });

    $news    = \App\Actions\Chat\Staff\SendStaffMessage::run($group, $this->user, ['body' => 'The new amethyst geode lamps arrived today, samples on my desk']);
    $chatter = \App\Actions\Chat\Staff\SendStaffMessage::run($group, $member, ['body' => 'ok thanks, I will have a look after lunch today']);
    \App\Actions\Chat\Staff\SendStaffMessage::run($dm, $this->user, ['body' => 'This private message must never reach the editor at all']);

    $quick = \App\Models\Chat\StaffMessageStory::where('staff_message_id', $news->id)->first();
    expect($quick?->source)->toBe('quick')
        ->and($quick?->headline)->toBe('Quick: geode lamps')
        ->and(\App\Models\Chat\StaffMessageStory::where('staff_message_id', $chatter->id)->exists())->toBeFalse();

    expect(\App\Actions\Chat\Staff\Home\EditStaffMessageStories::run($this->user->group))->toBe(1)
        ->and($editorSaw)->toContain('geode')
        ->and($editorSaw)->not->toContain('after lunch')
        ->and(collect($sentToAi)->contains(fn ($body) => str_contains($body, 'private message')))->toBeFalse()
        ->and($quick->fresh()->source)->toBe('editor')
        ->and($quick->fresh()->headline)->toBe('Edited: geode lamps are here');

    $headlines = fn (User $user) => collect(actingAs($user)->getJson(route('grp.chat.staff.home'))->assertOk()->json('stories'))->pluck('headline')->all();

    expect($headlines($member))->toContain('Edited: geode lamps are here')
        ->and($headlines($outsider))->not->toContain('Edited: geode lamps are here')
        ->and(collect(actingAs($this->user)->getJson(route('grp.chat.staff.home'))->json('people'))->pluck('conversation_ulid'))->toContain($dm->ulid);

    $news->delete();
    expect($headlines($member))->not->toContain('Edited: geode lamps are here');

    Config::set('services.openrouter.api_key', null);
    expect(\App\Actions\Chat\Staff\Home\EditStaffMessageStories::run($this->user->group))->toBe(0);
});
