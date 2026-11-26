<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\CRM\WebUser\StoreWebUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Broadcasting\BroadcastManager;
use App\Events\BroadcastUserPermissionsChanged;
use App\Events\BroadcastMobileRecordChanged;
use App\Broadcasting\MobileRecordChannel;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToUnassigned;
use App\Actions\Dispatching\DeliveryNoteItem\DeleteDeliveryNoteItem;
use App\Actions\Dispatching\PickingSession\UpdatePickingSession;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\SysAdmin\User\SetUserAuthorisedModels;
use App\Actions\UI\Grp\GetFirstLoadProps;
use App\Actions\UI\Notification\MarkAllNotificationAsRead;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Exceptions\FcmAccessTokenRefused;
use App\Exceptions\FcmMessageRejected;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Language;
use App\Models\Inventory\Warehouse;
use App\Models\Notifications\FcmToken;
use App\Models\SysAdmin\User;
use App\Notifications\TicketNotification;
use App\Notifications\Work\WorkNotifier;
use App\Notifications\WorkNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\json;

require_once __DIR__.'/../Support/MobileMyWorkHelpers.php';

/**
 * @param array<int, string>|null $permissions
 * @param array<string, mixed> $attributes
 */
function wnWorker(object $ctx, ?array $permissions = null, array $attributes = []): User
{
    $warehouseId = $ctx->warehouse->id;
    setPermissionsTeamId($ctx->group->id);

    $user = createMobileAppUser($ctx->group, $attributes);
    $user->givePermissionTo($permissions ?? [
        "dispatching.$warehouseId.view",
        "dispatching.$warehouseId.edit",
        "returns.$warehouseId",
        "fulfilment.$warehouseId.view",
        "fulfilment.$warehouseId.edit",
    ]);
    SetUserAuthorisedModels::run($user);

    return $user->refresh();
}

function wnActingAs(User $user): User
{
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Sanctum::actingAs($user, ['mobile']);

    return $user;
}

function wnActingAsNobody(): void
{
    app('auth')->forgetGuards();
    Context::forgetHidden('audit_user');
}

function wnName(User $user): string
{
    return $user->contact_name ?: $user->username;
}

/**
 * @return Collection<int, array<string, mixed>>
 */
function wnNotices(User $user): Collection
{
    return DatabaseNotification::query()
        ->where('notifiable_type', $user->getMorphClass())
        ->where('notifiable_id', $user->id)
        ->where('type', WorkNotification::class)
        ->orderBy('created_at')
        ->get()
        ->map(fn (DatabaseNotification $notification) => $notification->data)
        ->values();
}

/**
 * @return array<int, string>
 */
function wnEvents(User $user): array
{
    return wnNotices($user)->map(fn (array $data) => $data['kind'].':'.$data['event'])->sort()->values()->all();
}

function wnForget(User ...$users): void
{
    foreach ($users as $user) {
        DatabaseNotification::query()->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->delete();
    }
}

/**
 * @param array<string, mixed> $attributes
 */
function wnNote(object $ctx, array $attributes): DeliveryNote
{
    return goodsOutDeliveryNote($ctx->order, $ctx->warehouse, $attributes);
}

/**
 * @param array<string, mixed> $data
 */
function wnRawNotice(User $user, string $type, array $data, ?\DateTimeInterface $createdAt = null): DatabaseNotification
{
    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->create([
        'id'   => (string) Str::uuid(),
        'type' => $type,
        'data' => $data,
    ]);
    if ($createdAt) {
        $notification->forceFill(['created_at' => $createdAt])->save();
    }

    return $notification->refresh();
}

/**
 * @param array<string, mixed> $body
 */
function wnMobile(string $method, string $route, mixed $parameters = [], array $body = [], ?string $token = null): TestResponse
{
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key'];
    if ($token !== null) {
        app('auth')->forgetGuards();
        $headers['Authorization'] = 'Bearer '.$token;
    }

    return json($method, route($route, $parameters), $body, $headers);
}

function wnFirebaseCredentials(): string
{
    $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($privateKey, $privateKeyPem);
    config(['services.fcm.credentials' => base64_encode(json_encode([
        'type'         => 'service_account',
        'project_id'   => 'aiku-push-test',
        'private_key'  => $privateKeyPem,
        'client_email' => 'push-'.Str::lower(Str::random(8)).'@aiku-push-test.iam.gserviceaccount.com',
        'token_uri'    => 'https://oauth2.googleapis.com/token',
    ]))]);

    return $privateKeyPem;
}

function wnFcmToken(User $user, string $fcmToken): FcmToken
{
    return FcmToken::create([
        'personal_access_token_id' => $user->createToken('mobile|'.$fcmToken, ['mobile'])->accessToken->id,
        'push_notifiable_type'     => $user->getMorphClass(),
        'push_notifiable_id'       => $user->id,
        'fcm_token'                => $fcmToken,
        'platform'                 => 'android',
    ]);
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();
    $this->group     = $this->organisation->group;
    $this->customer  = createCustomer($this->shop);
    $this->product   = createProduct($this->shop)[1];
    $this->order     = createOrder($this->customer, $this->product);
    $this->warehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());

    config(['services.fcm.credentials' => null, 'services.fcm.project_id' => null]);
    SendPalletReturnNotification::shouldRun()->andReturn();
});

test('a picker change tells the new picker and the one taken off, never the person who made it', function () {
    $picker     = wnWorker($this);
    $newPicker  = wnWorker($this);
    $supervisor = wnWorker($this);
    $note       = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id]);
    wnForget($picker, $newPicker, $supervisor);

    wnActingAs($supervisor);
    UpdateDeliveryNote::make()->action($note, ['picker_user_id' => $newPicker->id]);

    expect(wnEvents($newPicker))->toBe(['delivery_note:assigned'])
        ->and(wnEvents($picker))->toBe(['delivery_note:taken_over'])
        ->and(wnEvents($supervisor))->toBe([])
        ->and(wnNotices($newPicker)->first())->toBe([
            'type'    => 'work',
            'kind'    => 'delivery_note',
            'event'   => 'assigned',
            'record'  => [
                'type'         => 'delivery_note',
                'id'           => $note->id,
                'reference'    => $note->reference,
                'warehouse_id' => $this->warehouse->id,
                'state'        => 'handling',
                'type_group'   => null,
            ],
            'actor'   => ['type' => 'user', 'id' => $supervisor->id, 'name' => wnName($supervisor)],
            'changes' => [],
            'title'   => "$note->reference assigned to you",
            'body'    => wnName($supervisor)." assigned $note->reference to you.",
        ])
        ->and(wnNotices($picker)->first()['title'])->toBe("$note->reference taken over")
        ->and(wnNotices($picker)->first()['body'])->toBe(wnName($newPicker)." has taken over $note->reference, which was assigned to you.");

    wnForget($picker, $newPicker);
    wnActingAs($picker);
    UpdateDeliveryNote::make()->action($note->refresh(), ['picker_user_id' => $picker->id]);

    expect(wnEvents($picker))->toBe([])
        ->and(wnEvents($newPicker))->toBe(['delivery_note:taken_over']);
});

test('picked to packing by someone else is a hand-over, while a packer replaced by another is taken over', function () {
    $picker      = wnWorker($this);
    $packer      = wnWorker($this);
    $otherPacker = wnWorker($this);
    $note        = wnNote($this, ['state' => DeliveryNoteStateEnum::PICKED, 'picker_user_id' => $picker->id]);
    wnForget($picker, $packer, $otherPacker);

    wnActingAs($packer);
    $note->update(['state' => DeliveryNoteStateEnum::PACKING, 'packer_user_id' => $packer->id]);

    expect(wnEvents($picker))->toBe([])
        ->and(wnEvents($packer))->toBe([]);

    wnActingAs($otherPacker);
    $note->update(['packer_user_id' => $otherPacker->id]);
    $note->update(['state' => DeliveryNoteStateEnum::PACKED]);

    expect(wnEvents($packer))->toBe(['delivery_note:taken_over'])
        ->and(wnEvents($picker))->toBe([])
        ->and(wnEvents($otherPacker))->toBe([]);
});

test('a picker taken off with nobody in their place is told they were removed, and a cancel names the reference they knew, in their language', function () {
    $picker     = wnWorker($this, attributes: ['language_id' => Language::where('code', 'es')->value('id')]);
    $supervisor = wnWorker($this);
    $queued     = wnNote($this, ['state' => DeliveryNoteStateEnum::QUEUED, 'picker_user_id' => $picker->id]);
    $handling   = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id]);
    $reference  = $handling->reference;
    wnForget($picker);

    wnActingAs($supervisor);
    UpdateDeliveryNoteStateToUnassigned::make()->action($queued);
    CancelDeliveryNote::make()->action($handling, $supervisor, false);

    $notices = wnNotices($picker)->keyBy('event');
    $spanish = fn (string $text, array $replace) => __($text, $replace, 'es');
    expect(wnEvents($picker))->toBe(['delivery_note:cancelled', 'delivery_note:unassigned'])
        ->and($notices['unassigned']['body'])->toBe($spanish(':actor removed you from :reference.', ['actor' => wnName($supervisor), 'reference' => $queued->reference]))
        ->and($handling->refresh()->reference)->not->toBe($reference)
        ->and($notices['cancelled']['record']['reference'])->toBe($reference)
        ->and($notices['cancelled']['record']['state'])->toBe('cancelled')
        ->and($notices['cancelled']['title'])->toBe($spanish(':reference cancelled', ['reference' => $reference]))
        ->and($notices['cancelled']['title'])->not->toBe("$reference cancelled")
        ->and($notices['cancelled']['body'])->toBe($spanish(':actor cancelled :reference, which is assigned to you.', ['actor' => wnName($supervisor), 'reference' => $reference]));
});

test('a note that stops waiting can go ahead for its picker, but not while it is still blocked', function () {
    $picker     = wnWorker($this);
    $supervisor = wnWorker($this);
    $note       = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED, 'picker_user_id' => $picker->id]);
    wnForget($picker);

    wnActingAs($supervisor);
    $note->update(['handling_blocked_at' => now()->subMinute()]);
    expect(wnEvents($picker))->toBe([]);

    $note->update(['state' => DeliveryNoteStateEnum::HANDLING]);
    expect(wnNotices($picker)->first())->toMatchArray([
        'event' => 'ready_again',
        'title' => "$note->reference can go ahead",
        'body'  => "$note->reference is no longer waiting and can be done again.",
    ]);

    wnForget($picker);
    $note->update(['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED]);
    wnActingAs($picker);
    $note->update(['state' => DeliveryNoteStateEnum::HANDLING]);
    expect(wnEvents($picker))->toBe([]);
});

test('a note in a waiting picking session is ready again through its session only', function () {
    $picker     = wnWorker($this);
    $supervisor = wnWorker($this);
    $session    = myWorkSession($this->warehouse, $picker, PickingSessionStateEnum::HANDLING_BLOCKED);
    $inSession  = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED, 'picker_user_id' => $picker->id]);
    $alone      = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED, 'picker_user_id' => $picker->id]);
    myWorkSessionNote($session, $inSession);
    wnForget($picker);

    wnActingAs($supervisor);
    DB::transaction(function () use ($inSession, $session) {
        $inSession->update(['state' => DeliveryNoteStateEnum::HANDLING]);
        $session->update(['state' => PickingSessionStateEnum::HANDLING, 'is_waiting_ready' => true]);
    });
    $alone->update(['state' => DeliveryNoteStateEnum::HANDLING]);

    expect(wnEvents($picker))->toBe(['delivery_note:ready_again', 'picking_session:ready_again'])
        ->and(wnNotices($picker)->pluck('record.reference')->sort()->values()->all())->toBe(collect([$session->reference, $alone->reference])->sort()->values()->all());
});

test('a change by someone else is told once per assignment, never to the person who made it', function () {
    $picker     = wnWorker($this);
    $colleague  = wnWorker($this);
    $supervisor = wnWorker($this);
    [$note, $lines] = goodsOutPickingNote($this, [2, 3], $picker);
    wnForget($picker);

    wnActingAs($picker);
    $note->update(['private_warehouse_note' => 'Top shelf']);
    expect(wnEvents($picker))->toBe([]);

    wnActingAs($supervisor);
    $note->update(['shipping_notes' => 'Fragile']);
    DeleteDeliveryNoteItem::run($lines[1]);
    $note->update(['contact_name' => 'Somebody else']);

    expect(wnNotices($picker)->all())->toHaveCount(1)
        ->and(wnNotices($picker)->first())->toMatchArray([
            'event'   => 'modified',
            'changes' => ['notes'],
            'title'   => "$note->reference was changed",
            'body'    => "Heads up: $note->reference, assigned to you, was changed by ".wnName($supervisor).'.',
        ]);

    UpdateDeliveryNote::make()->action($note->refresh(), ['picker_user_id' => $colleague->id]);
    UpdateDeliveryNote::make()->action($note->refresh(), ['picker_user_id' => $picker->id]);
    wnForget($picker);
    WorkNotifier::modified($note, 'lines');
    WorkNotifier::modified($note, 'address');

    expect(wnNotices($picker)->pluck('event')->all())->toBe(['modified'])
        ->and(wnNotices($picker)->first()['changes'])->toBe(['lines']);
});

test('changes by the customer, a marketplace or the system name their source', function () {
    $picker      = wnWorker($this);
    $platform    = Platform::where('type', PlatformTypeEnum::EBAY)->firstOrFail();
    $byCustomer  = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id]);
    $byPlatform  = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id, 'platform_id' => $platform->id]);
    $bySystem    = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id]);
    createWebsite($this->shop);
    $webUser     = StoreWebUser::make()->action($this->customer->refresh(), [
        'username' => 'wn'.Str::lower(Str::random(10)),
        'email'    => Str::lower(Str::random(10)).'@work-notices.test',
        'password' => 'secret',
    ]);
    wnForget($picker);

    actingAs($webUser, 'retina');
    $byCustomer->update(['customer_notes' => 'Ring twice']);

    wnActingAsNobody();
    WorkNotifier::modified($byPlatform, 'lines');
    $bySystem->update(['email' => 'new-address@work-notices.test']);

    $notices = wnNotices($picker)->keyBy('record.reference');
    expect($notices[$byCustomer->reference]['actor'])->toBe(['type' => 'customer', 'id' => $this->customer->id, 'name' => $this->customer->name])
        ->and($notices[$byCustomer->reference]['body'])->toBe("The customer has updated $byCustomer->reference, which is assigned to you.")
        ->and($notices[$byPlatform->reference]['actor'])->toBe(['type' => 'platform', 'id' => $platform->id, 'name' => $platform->name])
        ->and($notices[$byPlatform->reference]['body'])->toBe("$platform->name has updated $byPlatform->reference, which is assigned to you.")
        ->and($notices[$bySystem->reference]['actor'])->toBe(['type' => 'system', 'id' => null, 'name' => 'Aiku'])
        ->and($notices[$bySystem->reference]['changes'])->toBe(['address'])
        ->and($notices[$bySystem->reference]['body'])->toBe("Aiku has updated $bySystem->reference, which is assigned to you.");
});

test('somebody who may not open the record gets nothing, even when it is assigned to them', function () {
    $outsider   = wnWorker($this, ["locations.{$this->warehouse->id}.view"]);
    $handler    = wnWorker($this);
    $supervisor = wnWorker($this);
    $note       = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING]);
    $return     = myWorkReturn($this, null);
    wnForget($outsider, $handler);

    wnActingAs($supervisor);
    UpdateDeliveryNote::make()->action($note, ['picker_user_id' => $outsider->id]);
    $return->update(['handler_user_id' => $outsider->id]);
    $return->update(['handler_user_id' => $handler->id]);

    expect(wnEvents($outsider))->toBe([])
        ->and(wnEvents($handler))->toBe(['return:assigned']);
});

test('a picking session handed to another picker is one notice each, the notes it moves along stay quiet', function () {
    $picker     = wnWorker($this);
    $newPicker  = wnWorker($this);
    $supervisor = wnWorker($this);
    $session    = myWorkSession($this->warehouse, $picker, PickingSessionStateEnum::HANDLING);
    $note       = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id]);
    myWorkSessionNote($session, $note);
    wnForget($picker, $newPicker);

    wnActingAs($supervisor);
    UpdatePickingSession::make()->action($session, ['user_id' => $newPicker->id]);

    expect($note->refresh()->picker_user_id)->toBe($newPicker->id)
        ->and(wnEvents($picker))->toBe(['picking_session:taken_over'])
        ->and(wnEvents($newPicker))->toBe(['picking_session:assigned'])
        ->and(wnNotices($newPicker)->first()['record']['reference'])->toBe($session->reference);

    wnForget($newPicker);
    $session->refresh()->update(['state' => PickingSessionStateEnum::HANDLING_BLOCKED]);
    $session->update(['state' => PickingSessionStateEnum::HANDLING, 'is_waiting_ready' => true]);

    expect(wnEvents($newPicker))->toBe(['picking_session:ready_again']);
});

test('unlocking a note on the phone counts as taken over for its picker, who keeps the note', function () {
    $picker    = wnWorker($this);
    $colleague = wnWorker($this);
    $note      = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id]);
    wnForget($picker, $colleague);

    wnActingAs($colleague);
    postGoodsOut('grp.api.mobile.delivery_notes.take_over', $note->id)->assertOk();

    expect($note->refresh()->picker_user_id)->toBe($picker->id)
        ->and(wnEvents($picker))->toBe(['delivery_note:taken_over'])
        ->and(wnNotices($picker)->first()['body'])->toBe(wnName($colleague)." has taken over $note->reference, which was assigned to you.")
        ->and(wnEvents($colleague))->toBe([]);
});

test('customer returns and pallet returns tell their assignees when assigned, taken over, ready again or cancelled', function () {
    $handler    = wnWorker($this);
    $other      = wnWorker($this);
    $supervisor = wnWorker($this);
    $return     = myWorkReturn($this, $handler, ReturnDeliveryNoteStateEnum::RECEIVED);
    $pallets    = myWorkPalletReturn($this);
    wnForget($handler, $other);

    wnActingAs($supervisor);
    $return->update(['handler_user_id' => $other->id]);
    $return->update(['state' => ReturnDeliveryNoteStateEnum::CANCELLED]);

    expect(wnEvents($handler))->toBe(['return:taken_over'])
        ->and(wnEvents($other))->toBe(['return:assigned', 'return:cancelled']);

    wnForget($handler, $other);
    myWorkPalletReturnState($pallets, PalletReturnStateEnum::PICKING, $handler, null, $this->warehouse);
    $pallets->update(['number_items_waiting_crm' => 2]);
    $pallets->update(['number_items_waiting_crm' => 0]);
    $pallets->delete();

    expect(wnEvents($handler))->toBe(['pallet_return:assigned', 'pallet_return:cancelled', 'pallet_return:ready_again'])
        ->and(wnNotices($handler)->first()['record']['type_group'])->toBe('pallets');
});

test('the mobile inbox lists only work notifications, checks the target again, and marks read, unread and all read', function () {
    $me             = wnWorker($this);
    $colleague      = wnWorker($this);
    $supervisor     = wnWorker($this);
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $note           = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING]);
    $farNote        = goodsOutDeliveryNote($this->order, $otherWarehouse, ['state' => DeliveryNoteStateEnum::HANDLING]);
    wnForget($me, $colleague);

    $ticket      = wnRawNotice($me, TicketNotification::class, ['title' => 'A ticket', 'body' => 'Web only', 'type' => 'ticket'], now()->subMinutes(10));
    $unreachable = wnRawNotice($me, WorkNotification::class, [
        'type'    => 'work',
        'kind'    => 'delivery_note',
        'event'   => 'assigned',
        'record'  => ['type' => 'delivery_note', 'id' => $farNote->id, 'reference' => $farNote->reference, 'warehouse_id' => $otherWarehouse->id, 'state' => 'handling', 'type_group' => null],
        'actor'   => ['type' => 'system', 'id' => null, 'name' => 'Aiku'],
        'changes' => [],
        'title'   => "$farNote->reference assigned to you",
        'body'    => "Aiku assigned $farNote->reference to you.",
    ], now()->subMinutes(5));
    $colleaguesOwn = wnRawNotice($colleague, WorkNotification::class, ['type' => 'work', 'kind' => 'delivery_note', 'event' => 'assigned', 'record' => ['id' => $note->id], 'title' => 'x', 'body' => 'y']);

    wnActingAs($supervisor);
    UpdateDeliveryNote::make()->action($note, ['picker_user_id' => $me->id]);
    $assigned = DatabaseNotification::where('notifiable_id', $me->id)->where('type', WorkNotification::class)->whereKeyNot($unreachable->id)->sole();

    wnActingAs($me);
    $inbox = getGoodsOut('grp.api.mobile.inbox.index', [])->assertOk();
    expect($inbox->json('data.*.id'))->toBe([$assigned->id, $unreachable->id])
        ->and($inbox->json('data.0'))->toBe([
            'id'         => $assigned->id,
            'kind'       => 'delivery_note',
            'event'      => 'assigned',
            'title'      => "$note->reference assigned to you",
            'body'       => wnName($supervisor)." assigned $note->reference to you.",
            'created_at' => $assigned->created_at->toIso8601String(),
            'read_at'    => null,
            'target'     => [
                'screen'       => 'delivery_note',
                'id'           => $note->id,
                'reference'    => $note->reference,
                'state'        => 'handling',
                'warehouse_id' => $this->warehouse->id,
                'type_group'   => null,
            ],
        ])
        ->and($inbox->json('data.1.target'))->toBeNull()
        ->and($inbox->json('meta'))->toBe(['current_page' => 1, 'last_page' => 1, 'per_page' => 30, 'total' => 2])
        ->and($inbox->json('unread'))->toBe(2)
        ->and(getGoodsOut('grp.api.mobile.inbox.unread_count', [])->assertOk()->json('data'))->toBe(['unread' => 2])
        ->and(myWorkBadges($this->warehouse)->assertOk()->json('data.inbox_unread'))->toBe(2);

    $read = postGoodsOut('grp.api.mobile.inbox.read', $assigned->id)->assertOk();
    expect($read->json('data.id'))->toBe($assigned->id)
        ->and($read->json('data.read_at'))->not->toBeNull()
        ->and($read->json('data.target.id'))->toBe($note->id)
        ->and(getGoodsOut('grp.api.mobile.inbox.unread_count', [])->json('data.unread'))->toBe(1)
        ->and(getGoodsOut('grp.api.mobile.inbox.index', [], ['filter' => 'unread'])->assertOk()->json('data.*.id'))->toBe([$unreachable->id]);

    expect(postGoodsOut('grp.api.mobile.inbox.unread', $assigned->id)->assertOk()->json('data.read_at'))->toBeNull();
    postGoodsOut('grp.api.mobile.inbox.read', $colleaguesOwn->id)->assertForbidden();
    postGoodsOut('grp.api.mobile.inbox.read', $ticket->id)->assertNotFound();
    getGoodsOut('grp.api.mobile.inbox.index', [], ['per_page' => 101])->assertUnprocessable();

    postGoodsOut('grp.api.mobile.inbox.read_all', [])->assertOk()->assertExactJson(['data' => ['unread' => 0]]);
    expect(getGoodsOut('grp.api.mobile.inbox.unread_count', [])->json('data.unread'))->toBe(0)
        ->and($ticket->refresh()->read_at)->toBeNull()
        ->and($colleaguesOwn->refresh()->read_at)->toBeNull();
});

test('the web bell, notifications page and mark all read leave the work notifications to the app', function () {
    $me     = wnWorker($this);
    $ticket = wnRawNotice($me, TicketNotification::class, ['title' => 'A ticket', 'body' => 'Web only', 'type' => 'ticket']);
    $work   = wnRawNotice($me, WorkNotification::class, ['type' => 'work', 'kind' => 'delivery_note', 'event' => 'assigned', 'record' => ['id' => 1], 'title' => 'AR1 assigned to you', 'body' => 'x']);

    $bell = collect(GetFirstLoadProps::make()->badges($me)['notifications'])->map(fn ($resource) => $resource->resource->id)->all();
    expect($bell)->toContain($ticket->id)->not->toContain($work->id);

    actingAs($me);
    $page = getJson(route('grp.profile.notifications.index'))->assertOk();
    expect(collect($page->json('data'))->pluck('id')->all())->toBe([$ticket->id]);

    MarkAllNotificationAsRead::make()->handle($me);
    expect($ticket->refresh()->read_at)->not->toBeNull()
        ->and($work->refresh()->read_at)->toBeNull();
});

test('a push token belongs to the access token, moves off a shared handheld, and goes with the sign out', function () {
    $user      = createMobileAppUser($this->group);
    $phone     = $user->createToken('mobile|Pixel 8', ['mobile']);
    $colleague = createMobileAppUser($this->group);
    $shared    = $colleague->createToken('mobile|Shared TC52', ['mobile']);
    FcmToken::create([
        'personal_access_token_id' => $shared->accessToken->id,
        'push_notifiable_type'     => $colleague->getMorphClass(),
        'push_notifiable_id'       => $colleague->id,
        'fcm_token'                => 'handheld-fcm-token',
        'platform'                 => 'android',
    ]);

    wnMobile('PUT', 'grp.api.mobile.push_token.update', [], ['token' => 'handheld-fcm-token', 'platform' => 'android', 'app_version' => '1.4.0'], $phone->plainTextToken)
        ->assertOk()
        ->assertExactJson(['data' => ['registered' => true]]);

    expect(Context::getHidden('audit_user'))->toBe([User::class, $user->id])
        ->and(FcmToken::where('fcm_token', 'handheld-fcm-token')->get(['personal_access_token_id', 'push_notifiable_id', 'platform', 'app_version'])->toArray())->toBe([[
            'personal_access_token_id' => $phone->accessToken->id,
            'push_notifiable_id'       => $user->id,
            'platform'                 => 'android',
            'app_version'              => '1.4.0',
        ]]);

    wnMobile('PUT', 'grp.api.mobile.push_token.update', [], ['token' => 'refreshed-fcm-token', 'platform' => 'android'], $phone->plainTextToken)->assertOk();
    wnMobile('PUT', 'grp.api.mobile.push_token.update', [], ['token' => 'x', 'platform' => 'windows'], $phone->plainTextToken)->assertUnprocessable();
    expect(FcmToken::where('personal_access_token_id', $phone->accessToken->id)->pluck('fcm_token')->all())->toBe(['refreshed-fcm-token']);

    wnMobile('DELETE', 'grp.api.mobile.push_token.delete', [], [], $phone->plainTextToken)
        ->assertOk()
        ->assertExactJson(['data' => ['registered' => false]]);
    expect(FcmToken::where('personal_access_token_id', $phone->accessToken->id)->exists())->toBeFalse();

    wnMobile('PUT', 'grp.api.mobile.push_token.update', [], ['token' => 'again-fcm-token', 'platform' => 'ios'], $phone->plainTextToken)->assertOk();
    wnMobile('POST', 'grp.api.mobile.logout', [], [], $phone->plainTextToken)->assertNoContent();
    expect(FcmToken::where('fcm_token', 'again-fcm-token')->exists())->toBeFalse();
});

test('a work notification is pushed to every device of the recipient, and a token Firebase no longer knows is dropped', function () {
    $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($privateKey, $privateKeyPem);
    config(['services.fcm.credentials' => base64_encode(json_encode([
        'type'         => 'service_account',
        'project_id'   => 'aiku-push-test',
        'private_key'  => $privateKeyPem,
        'client_email' => 'push@aiku-push-test.iam.gserviceaccount.com',
        'token_uri'    => 'https://oauth2.googleapis.com/token',
    ]))]);

    $fcmCalls = 0;
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fcm-test-access-token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*'    => function (Request $request) use (&$fcmCalls) {
            $fcmCalls++;
            if ($fcmCalls === 1) {
                return Http::response(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED']], 401);
            }

            return $request['message']['token'] === 'dead-fcm-token'
                ? Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404)
                : Http::response(['name' => 'projects/aiku-push-test/messages/1']);
        },
    ]);

    $picker     = wnWorker($this);
    $supervisor = wnWorker($this);
    foreach (['alive-fcm-token', 'dead-fcm-token'] as $fcmToken) {
        FcmToken::create([
            'personal_access_token_id' => $picker->createToken('mobile|'.$fcmToken, ['mobile'])->accessToken->id,
            'push_notifiable_type'     => $picker->getMorphClass(),
            'push_notifiable_id'       => $picker->id,
            'fcm_token'                => $fcmToken,
            'platform'                 => 'android',
        ]);
    }
    $note = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING]);

    wnActingAs($supervisor);
    UpdateDeliveryNote::make()->action($note, ['picker_user_id' => $picker->id]);
    $notification = DatabaseNotification::where('notifiable_id', $picker->id)->where('type', WorkNotification::class)->sole();

    Http::assertSent(function (Request $request) use ($privateKeyPem) {
        if ($request->url() !== 'https://oauth2.googleapis.com/token') {
            return false;
        }
        [$header, $claims, $signature] = explode('.', $request['assertion']);
        $decode = fn (string $segment) => base64_decode(strtr($segment, '-_', '+/'));

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && json_decode($decode($claims), true)['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
            && openssl_verify("$header.$claims", $decode($signature), openssl_pkey_get_details(openssl_pkey_get_private($privateKeyPem))['key'], OPENSSL_ALGO_SHA256) === 1;
    });
    Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/aiku-push-test/messages:send'
        && $request->hasHeader('Authorization', 'Bearer fcm-test-access-token')
        && $request['message'] === [
            'notification' => ['title' => "$note->reference assigned to you", 'body' => wnName($supervisor)." assigned $note->reference to you."],
            'data'         => [
                'notification_id' => $notification->id,
                'kind'            => 'delivery_note',
                'event'           => 'assigned',
                'record_type'     => 'delivery_note',
                'record_id'       => (string) $note->id,
                'reference'       => $note->reference,
                'warehouse_id'    => (string) $this->warehouse->id,
                'state'           => 'handling',
                'type_group'      => '',
            ],
            'android'      => ['priority' => 'high', 'notification' => ['tag' => "delivery_note:$note->id", 'channel_id' => 'work']],
            'token'        => 'alive-fcm-token',
        ]);

    expect($fcmCalls)->toBe(3)
        ->and(Http::recorded(fn (Request $request) => str_contains($request->url(), 'oauth2.googleapis.com'))->count())->toBe(2)
        ->and(FcmToken::where('push_notifiable_id', $picker->id)->pluck('fcm_token')->all())->toBe(['alive-fcm-token']);
});

test('a push Firebase refuses for another reason is reported without the token, and the other devices still get theirs', function () {
    $refusedToken = 'refused-fcm-token-'.Str::random(12);
    $aliveToken   = 'alive-fcm-token-'.Str::random(12);
    wnFirebaseCredentials();
    Exceptions::fake();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fcm-test-access-token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*'    => fn (Request $request) => $request['message']['token'] === $refusedToken
            ? Http::response(['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'SENDER_ID_MISMATCH']]]], 403)
            : Http::response(['name' => 'projects/aiku-push-test/messages/1']),
    ]);

    $picker     = wnWorker($this);
    $supervisor = wnWorker($this);
    $refused    = wnFcmToken($picker, $refusedToken);
    wnFcmToken($picker, $aliveToken);
    $note = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING]);

    wnActingAs($supervisor);
    UpdateDeliveryNote::make()->action($note, ['picker_user_id' => $picker->id]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com') && $request['message']['token'] === $aliveToken);
    Exceptions::assertReported(fn (FcmMessageRejected $exception) => $exception->status === 403
        && $exception->errorCode === 'SENDER_ID_MISMATCH'
        && $exception->fcmTokenId === $refused->id
        && $exception->context() === ['status' => 403, 'error_code' => 'SENDER_ID_MISMATCH', 'fcm_token_id' => $refused->id]
        && !str_contains($exception->getMessage(), $refusedToken));
    expect(wnEvents($picker))->toBe(['delivery_note:assigned'])
        ->and(FcmToken::where('push_notifiable_id', $picker->id)->pluck('fcm_token')->sort()->values()->all())->toBe([$aliveToken, $refusedToken]);
});

test('an access token Firebase refuses is reported once, not per device, and nothing is pushed', function () {
    wnFirebaseCredentials();
    Exceptions::fake();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'], 400),
        'fcm.googleapis.com/*'    => Http::response(['name' => 'projects/aiku-push-test/messages/1']),
    ]);

    $picker     = wnWorker($this);
    $supervisor = wnWorker($this);
    wnFcmToken($picker, 'first-fcm-token-'.Str::random(12));
    wnFcmToken($picker, 'second-fcm-token-'.Str::random(12));
    $note = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING]);

    wnActingAs($supervisor);
    UpdateDeliveryNote::make()->action($note, ['picker_user_id' => $picker->id]);

    $refusals = collect(Exceptions::reported())->filter(fn ($exception) => $exception instanceof FcmAccessTokenRefused);
    expect($refusals)->toHaveCount(1)
        ->and($refusals->first()->context())->toBe(['status' => 400, 'error_code' => 'invalid_grant', 'is_temporary' => false])
        ->and(Http::recorded(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com'))->count())->toBe(0)
        ->and(wnEvents($picker))->toBe(['delivery_note:assigned'])
        ->and(FcmToken::where('push_notifiable_id', $picker->id)->count())->toBe(2);
});

test('without Firebase credentials nothing is pushed and the inbox still gets the notification', function () {
    Http::fake();
    $picker     = wnWorker($this);
    $supervisor = wnWorker($this);
    FcmToken::create([
        'personal_access_token_id' => $picker->createToken('mobile|Pixel 8', ['mobile'])->accessToken->id,
        'push_notifiable_type'     => $picker->getMorphClass(),
        'push_notifiable_id'       => $picker->id,
        'fcm_token'                => 'unused-fcm-token',
        'platform'                 => 'android',
    ]);
    $note = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING]);

    wnActingAs($supervisor);
    UpdateDeliveryNote::make()->action($note, ['picker_user_id' => $picker->id]);

    Http::assertNothingSent();
    expect(wnEvents($picker))->toBe(['delivery_note:assigned']);
});

test('the delivery note, stock movement and low stock audit channels are for staff who may see them, never a customer', function () {
    $note    = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING]);
    $viewer  = wnWorker($this);
    $nobody  = wnWorker($this, []);
    createWebsite($this->shop);
    $webUser = StoreWebUser::make()->action($this->customer->refresh(), [
        'username' => 'wn'.Str::lower(Str::random(10)),
        'email'    => Str::lower(Str::random(10)).'@work-notices.test',
        'password' => 'secret',
    ]);
    $channel = fn (string $name) => Broadcast::driver()->getChannels()->get($name);
    $org     = $this->organisation->slug;

    expect($channel('grp.dn.{deliveryNoteId}')($viewer, $note->id))->toBeTrue()
        ->and($channel('grp.dn.{deliveryNoteId}')($nobody, $note->id))->toBeFalse()
        ->and($channel('grp.dn.{deliveryNoteId}')($webUser, $note->id))->toBeFalse()
        ->and($channel('grp.dn.{deliveryNoteId}')($viewer, 999999999))->toBeFalse()
        ->and($channel('grp.{organisation}.stock_movement')($viewer, $org))->toBeTrue()
        ->and($channel('grp.{organisation}.stock_movement')($viewer, 'no-such-organisation'))->toBeFalse()
        ->and($channel('grp.{organisation}.stock_movement')($webUser, $org))->toBeFalse()
        ->and($channel('grp.{organisation}.warehouse.{warehouse}.low_stock_audit')($viewer, $org, $this->warehouse->slug))->toBeTrue()
        ->and($channel('grp.{organisation}.warehouse.{warehouse}.low_stock_audit')($nobody, $org, $this->warehouse->slug))->toBeFalse()
        ->and($channel('grp.{organisation}.warehouse.{warehouse}.low_stock_audit')($webUser, $org, $this->warehouse->slug))->toBeFalse()
        ->and($channel('grp.{organisation}.warehouse.{warehouse}.low_stock_audit')($viewer, 'no-such-organisation', $this->warehouse->slug))->toBeFalse();
});

test('an open record pings the staff app on its own channel, for those who may open it in the app', function () {
    $picker    = wnWorker($this);
    $newPicker = wnWorker($this);
    $nobody    = wnWorker($this, []);
    $note      = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id]);
    $pinged    = fn (string $type, int $id) => Event::dispatched(BroadcastMobileRecordChanged::class, fn (BroadcastMobileRecordChanged $event) => $event->type === $type && $event->id === $id)->count();

    Event::fake([BroadcastMobileRecordChanged::class]);
    UpdateDeliveryNote::make()->action($note, ['picker_user_id' => $newPicker->id]);
    expect($pinged('delivery_note', $note->id))->toBeGreaterThan(0);

    Event::fake([BroadcastMobileRecordChanged::class]);
    WorkNotifier::modified($note, 'lines');
    BroadcastMobileRecordChanged::dispatchFor(new DeliveryNoteItem(['delivery_note_id' => $note->id]));
    BroadcastMobileRecordChanged::dispatchFor(new StockDeliveryItem(['stock_delivery_id' => 77]));
    BroadcastMobileRecordChanged::dispatchFor(new Warehouse());
    expect($pinged('delivery_note', $note->id))->toBe(2)
        ->and($pinged('stock_delivery', 77))->toBe(1)
        ->and(Event::dispatched(BroadcastMobileRecordChanged::class)->count())->toBe(3);

    $event = new BroadcastMobileRecordChanged('delivery_note', $note->id);
    expect($event->broadcastOn()[0]->name)->toBe('private-grp.mobile.delivery_note.'.$note->id)
        ->and($event->broadcastAs())->toBe('record-changed')
        ->and($event->uniqueId())->toBe('delivery_note:'.$note->id);

    $channel = new MobileRecordChannel();
    expect($channel->join($picker, 'delivery_note', (string) $note->id))->toBeTrue()
        ->and($channel->join($nobody, 'delivery_note', (string) $note->id))->toBeFalse()
        ->and($channel->join($picker, 'invoice', (string) $note->id))->toBeFalse()
        ->and($channel->join($picker, 'delivery_note', '1 or 1'))->toBeFalse();
});

test('a change of rights tells the user\'s open app to fetch its permissions again', function () {
    $worker = wnWorker($this);
    Event::fake([BroadcastUserPermissionsChanged::class]);

    SetUserAuthorisedModels::run($worker);

    Event::assertDispatched(BroadcastUserPermissionsChanged::class, fn (BroadcastUserPermissionsChanged $event) => $event->userId === $worker->id
        && $event->broadcastOn()[0]->name === 'private-grp.personal.'.$worker->id
        && $event->broadcastAs() === 'permissions-changed');
});

test('the app gets the websocket server and signs only its own channels with its token', function () {
    $worker = wnWorker($this);
    $other  = wnWorker($this);
    $note   = wnNote($this, ['state' => DeliveryNoteStateEnum::HANDLING]);
    config([
        'broadcasting.default'                   => 'pusher',
        'broadcasting.connections.pusher.key'    => 'aiku-test-key',
        'broadcasting.connections.pusher.secret' => 'aiku-test-secret',
        'broadcasting.connections.pusher.app_id' => '1',
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');
    wnActingAs($worker);
    $auth   = fn (string $channel) => wnMobile('POST', 'grp.api.mobile.broadcasting.auth', [], ['socket_id' => '1234.5678', 'channel_name' => $channel]);

    wnMobile('GET', 'grp.api.mobile.realtime')->assertOk()->assertJsonPath('data.key', 'aiku-test-key');

    expect($auth('private-grp.personal.'.$worker->id)->assertOk()->json('auth'))->toStartWith('aiku-test-key:')
        ->and($auth('private-grp.mobile.delivery_note.'.$note->id)->assertOk()->json('auth'))->toStartWith('aiku-test-key:');
    $auth('private-grp.personal.'.$other->id)->assertForbidden();
    $auth('private-grp.dn.'.$note->id)->assertUnprocessable()->assertJsonValidationErrors('channel_name');

    config(['broadcasting.connections.pusher.key' => null]);
    wnMobile('GET', 'grp.api.mobile.realtime')->assertOk()->assertJsonPath('data', null);
});
