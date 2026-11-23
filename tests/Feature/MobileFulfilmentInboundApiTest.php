<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Fulfilment\Pallet\BookInPallet;
use App\Actions\Fulfilment\Pallet\SetPalletBackToStoring;
use App\Actions\Fulfilment\Pallet\UpdatePalletLocation;
use App\Actions\Fulfilment\PalletDelivery\ConfirmPalletDelivery;
use App\Actions\Fulfilment\StoredItemAudit\CompleteStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAudit\StoreStoredItemAuditFromPallet;
use App\Actions\Fulfilment\StoredItemAuditDelta\StoreStoredItemAuditDelta;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Actions\Fulfilment\Pallet\SetPalletAsDamaged;
use App\Actions\Fulfilment\Pallet\SetPalletAsNotReceived;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\ReceivePalletDelivery;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\RecurringBillTransaction;
use App\Models\Fulfilment\StoredItemAuditDelta;
use App\Models\Inventory\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

require_once __DIR__.'/../Support/MobileFulfilmentInboundHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->warehouse    = createWarehouse();
    $this->organisation = $this->warehouse->organisation;
    $this->group        = $this->organisation->group;
    $this->fulfilment   = createFulfilment($this->organisation);
    mobileFulInboundOtherWarehouse($this->organisation);
    SendPalletDeliveryNotification::shouldRun()->andReturn();
});

function mobileFulInboundLocationPallets(Location $location): int
{
    return (int) $location->refresh()->stats->number_pallets;
}

test('mobile pallet deliveries list the active ones of this warehouse, filter by state and search, and count per state', function () {
    mobileFulInboundUser($this);
    $baseline = mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id])->assertOk()->json('counts');

    $customer  = mobileFulInboundCustomer($this->fulfilment);
    $confirmed = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, $this->warehouse, [['type' => PalletTypeEnum::PALLET->value], ['type' => PalletTypeEnum::BOX->value]]));
    $received  = mobileFulInboundReceived(mobileFulInboundDelivery($customer, $this->warehouse, [[], ['type' => PalletTypeEnum::OVERSIZE->value]]));
    $bookingIn = mobileFulInboundBookingIn(mobileFulInboundDelivery($customer, $this->warehouse, [[], [], []]));
    mobileFulInboundDelivery($customer, $this->warehouse);
    $elsewhere = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, mobileFulInboundOtherWarehouse($this->organisation)));

    [$booked, $notReceived, $toBook] = mobileFulInboundPallets($bookingIn);
    BookInPallet::make()->action($booked, ['location_id' => mobileFulInboundLocation($this->warehouse)->id]);
    SetPalletAsNotReceived::make()->action($notReceived);

    $confirmed->update(['estimated_delivery_date' => now()->addDays(3)->toDateString(), 'customer_reference' => 'MFI-PO-'.Str::upper(Str::random(5))]);
    $received->update(['estimated_delivery_date' => now()->addDay()->toDateString()]);
    $bookingIn->update(['estimated_delivery_date' => null]);

    $list   = fn (array $query = []) => mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id], ['q' => $customer->customer->name, ...$query])->assertOk();
    $active = $list();

    expect(collect($active->json('data'))->pluck('id')->all())->toBe([$received->id, $confirmed->id, $bookingIn->id])
        ->and($active->json('meta'))->toBe(['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 3])
        ->and($active->json('data.1'))->toBe([
            'id'                      => $confirmed->id,
            'reference'               => $confirmed->reference,
            'customer_reference'      => $confirmed->customer_reference,
            'state'                   => 'confirmed',
            'state_label'             => 'Confirmed',
            'customer'                => ['id' => $customer->id, 'name' => $customer->customer->name],
            'estimated_delivery_date' => now()->addDays(3)->toDateString(),
            'received_at'             => null,
            'booked_in_at'            => null,
            'counts'                  => ['pallets' => 1, 'boxes' => 1, 'oversizes' => 0, 'total' => 2, 'booked_in' => 0, 'not_received' => 0],
        ])
        ->and($active->json('data.2.counts'))->toBe(['pallets' => 3, 'boxes' => 0, 'oversizes' => 0, 'total' => 3, 'booked_in' => 1, 'not_received' => 1])
        ->and($toBook->refresh()->state)->toBe(PalletStateEnum::BOOKING_IN);

    expect(collect($list(['state' => 'received'])->json('data'))->pluck('id')->all())->toBe([$received->id])
        ->and(collect($list(['sort' => '-reference'])->json('data'))->pluck('reference')->all())->toBe(collect([$confirmed, $received, $bookingIn])->pluck('reference')->sortDesc()->values()->all())
        ->and(collect(mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id], ['q' => $confirmed->customer_reference])->json('data'))->pluck('id')->all())->toBe([$confirmed->id])
        ->and(collect(mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id], ['q' => $customer->slug, 'state' => 'confirmed'])->json('data'))->pluck('id')->all())->toBe([$confirmed->id])
        ->and(mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id], ['state' => 'in_process'])->status())->toBe(422);

    $counts = mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id])->json('counts');
    expect(collect($counts)->map(fn (int $count, string $state) => $count - $baseline[$state])->all())->toBe([
        'confirmed'    => 1,
        'received'     => 1,
        'booking_in'   => 1,
        'booked_in'    => 0,
        'not_received' => 0,
        'active'       => 3,
    ]);

    mobileFulInboundGet('pallet_deliveries.show', ['warehouse' => $this->warehouse->id, 'palletDelivery' => $elsewhere->id])->assertNotFound();

    mobileFulInboundUser($this, [], ["incoming.{$this->warehouse->id}.view"]);
    mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id])->assertForbidden()->assertJsonStructure(['message']);
});

test('mobile pallet deliveries filter by the booked in day from and to and refuse a bad range', function () {
    mobileFulInboundUser($this);
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $early    = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, $this->warehouse));
    $late     = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, $this->warehouse));
    $notYet   = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, $this->warehouse));
    DB::table('pallet_deliveries')->where('id', $early->id)->update(['booked_in_at' => '2026-06-01 12:00:00']);
    DB::table('pallet_deliveries')->where('id', $late->id)->update(['booked_in_at' => '2026-06-03 12:00:00']);

    $ids = fn (array $query) => collect(mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id], ['q' => $customer->customer->name, 'sort' => 'booked_in_at', ...$query])->assertOk()->json('data'))->pluck('id')->all();

    expect($ids([]))->toBe([$early->id, $late->id, $notYet->id])
        ->and($ids(['from' => '2026-06-01', 'to' => '2026-06-03']))->toBe([$early->id, $late->id])
        ->and($ids(['from' => '2026-06-02']))->toBe([$late->id])
        ->and($ids(['to' => '2026-06-02']))->toBe([$early->id]);

    mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id], ['from' => '2026/06/01', 'sort' => 'customer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from', 'sort']);

    mobileFulInboundGet('pallet_deliveries.index', ['warehouse' => $this->warehouse->id], ['from' => '2026-06-03', 'to' => '2026-06-01'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

test('mobile pallet delivery detail sends no money, orders pallets to book in first and lists actions by state and right', function () {
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $delivery = mobileFulInboundDelivery($customer, $this->warehouse, [['customer_reference' => 'MFI-C-'.Str::random(4)], ['type' => PalletTypeEnum::BOX->value], ['notes' => 'Fragile']]);
    $storedItem = mobileFulInboundStoredItem($delivery->pallets()->orderBy('id')->firstOrFail(), 12);
    $delivery = mobileFulInboundBookingIn($delivery);
    $delivery->update(['public_notes' => 'Back door', 'internal_notes' => 'Check seals', 'estimated_delivery_date' => now()->toDateString()]);

    [$withItems, $box, $fragile] = mobileFulInboundPallets($delivery);
    $location = mobileFulInboundLocation($this->warehouse);
    BookInPallet::make()->action($box, ['location_id' => $location->id]);
    SetPalletAsNotReceived::make()->action($fragile);

    $show = fn () => mobileFulInboundGet('pallet_deliveries.show', ['warehouse' => $this->warehouse->id, 'palletDelivery' => $delivery->id])->assertOk()->json('data');

    mobileFulInboundUser($this);
    $clerkView = $show();

    expect(collect($clerkView['pallets'])->pluck('id')->all())->toBe([$withItems->id, $box->id, $fragile->id])
        ->and(collect(mobileFulInboundKeys($clerkView))->filter(fn (string $key) => Str::contains($key, ['price', 'amount', 'invoice', 'bill', 'currency', 'discount']))->all())->toBe([])
        ->and(array_keys($clerkView))->toBe(['id', 'reference', 'customer_reference', 'state', 'state_label', 'customer', 'estimated_delivery_date', 'handing_type', 'customer_notes', 'public_notes', 'internal_notes', 'timeline', 'counts', 'pallets', 'services', 'physical_goods', 'finish_booking_blocked', 'available_actions'])
        ->and($clerkView['state'])->toBe('booking_in')
        ->and($clerkView['public_notes'])->toBe('Back door')
        ->and($clerkView['internal_notes'])->toBe('Check seals')
        ->and($clerkView['estimated_delivery_date'])->toBe(now()->toDateString())
        ->and(collect($clerkView['timeline'])->pluck('state')->all())->toBe(['confirmed', 'received', 'booking_in', 'booked_in'])
        ->and($clerkView['timeline'][2]['at'])->not->toBeNull()
        ->and($clerkView['timeline'][3]['at'])->toBeNull()
        ->and($clerkView['counts'])->toBe(['pallets' => 2, 'boxes' => 1, 'oversizes' => 0, 'total' => 3, 'received' => 0, 'booking_in' => 1, 'booked_in' => 1, 'not_received' => 1])
        ->and($clerkView['pallets'][0]['stored_items'])->toBe([['id' => $storedItem->id, 'reference' => $storedItem->reference, 'name' => $storedItem->name, 'quantity' => 12]])
        ->and($clerkView['pallets'][0]['number_stored_items'])->toBe(1)
        ->and($clerkView['pallets'][0]['has_rental'])->toBeTrue()
        ->and($clerkView['pallets'][0]['customer'])->toBe(['id' => $customer->id, 'name' => $customer->customer->name])
        ->and($clerkView['pallets'][1]['location'])->toBe(['id' => $location->id, 'code' => $location->code])
        ->and($clerkView['pallets'][1]['type_label'])->toBe('Box')
        ->and($clerkView['pallets'][2]['notes'])->toBe('Fragile')
        ->and(collect($clerkView['pallets'])->pluck('available_actions')->all())->toBe([
            [['key' => 'book_in', 'label' => 'Book in'], ['key' => 'not_received', 'label' => 'Not received']],
            [['key' => 'book_in', 'label' => 'Change location']],
            [],
        ])
        ->and($clerkView['available_actions'])->toBe([])
        ->and($clerkView['finish_booking_blocked'])->toBeNull();

    mobileFulInboundShopClerk($this);
    $shopView = $show();
    expect(collect($shopView['pallets'])->pluck('available_actions')->flatten()->all())->toBe([])
        ->and($shopView['available_actions'])->toBe([])
        ->and($shopView['finish_booking_blocked'])->toBe('1 pallet still to book in');

    mobileFulInboundClerkWithShopRight($this);
    BookInPallet::make()->action($withItems, ['location_id' => $location->id]);
    $bothView = $show();
    expect($bothView['available_actions'])->toBe([['key' => 'finish_booking', 'label' => 'Set as booked in']])
        ->and($bothView['finish_booking_blocked'])->toBeNull()
        ->and(collect($bothView['pallets'])->pluck('id')->all())->toBe(collect([$withItems, $box])->sortBy('reference')->pluck('id')->push($fragile->id)->values()->all());

    $box->update(['rental_id' => null]);
    $withoutRental = $show();
    expect($withoutRental['available_actions'])->toBe([])
        ->and($withoutRental['finish_booking_blocked'])->toBe('1 pallet has no rental; set it on the web');

    $confirmed = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, $this->warehouse));
    $received  = mobileFulInboundReceived(mobileFulInboundDelivery($customer, $this->warehouse, [[], []]));
    $actionsOf = fn ($palletDelivery) => mobileFulInboundGet('pallet_deliveries.show', ['warehouse' => $this->warehouse->id, 'palletDelivery' => $palletDelivery->id])->assertOk()->json('data');

    expect($actionsOf($confirmed)['available_actions'])->toBe([['key' => 'receive', 'label' => 'Mark as received']])
        ->and($actionsOf($confirmed)['pallets'][0]['available_actions'])->toBe([])
        ->and($actionsOf($received)['available_actions'])->toBe([['key' => 'start_booking', 'label' => 'Start booking in']])
        ->and($actionsOf($received)['pallets'][0]['available_actions'])->toBe([['key' => 'not_received', 'label' => 'Not received']]);

    SetPalletAsNotReceived::make()->action(mobileFulInboundPallets($received)[0]);
    expect($actionsOf($received)['pallets'][1]['available_actions'])->toBe([['key' => 'undo_not_received', 'label' => 'Undo not received']]);
});

test('mobile receive needs the shop right, a confirmed delivery and a rental agreement, and records the mobile user', function () {
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $delivery = mobileFulInboundDelivery($customer, $this->warehouse, [[], []]);
    $storedItem = mobileFulInboundStoredItem($delivery->pallets()->orderBy('id')->firstOrFail(), 5);
    $receive  = fn ($palletDelivery) => mobileFulInboundPost('pallet_deliveries.receive', ['warehouse' => $this->warehouse->id, 'palletDelivery' => $palletDelivery->id]);

    mobileFulInboundUser($this);
    $receive(mobileFulInboundConfirmed($delivery))->assertForbidden()->assertJsonPath('message', 'You can not receive or book in the deliveries of this customer.');

    $user = mobileFulInboundShopClerk($this);
    $response = $receive($delivery)->assertOk();
    expect($response->json('result'))->toBe(['status' => 'done', 'message' => 'Delivery marked as received.'])
        ->and($response->json('data.state'))->toBe('received')
        ->and($response->json('data.counts.received'))->toBe(2)
        ->and($delivery->refresh()->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and(StoredItemAuditDelta::where('stored_item_id', $storedItem->id)->sole()->user_id)->toBe($user->id);

    $receive($delivery)->assertUnprocessable()->assertJsonPath('errors.state.0', 'This can not be done while the delivery is Received.');

    $withoutAgreement = mobileFulInboundConfirmed(mobileFulInboundDelivery(mobileFulInboundCustomer($this->fulfilment, withRentalAgreement: false), $this->warehouse));
    $receive($withoutAgreement)->assertUnprocessable()->assertJsonPath('errors.state.0', 'This customer has no rental agreement; set it up on the web first.');
    expect($withoutAgreement->refresh()->state)->toBe(PalletDeliveryStateEnum::CONFIRMED);

    $elsewhere = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, mobileFulInboundOtherWarehouse($this->organisation)));
    $receive($elsewhere)->assertNotFound();
});

test('receiving refuses pallets without a rental up front and never leaves a half received delivery', function () {
    $autoAssignedTypes = $this->organisation->rentals()->where('auto_assign_asset', 'Pallet')->pluck('auto_assign_asset_type')->all();
    $typeWithoutAutoRental = collect(PalletTypeEnum::cases())->first(fn (PalletTypeEnum $type) => !in_array($type->value, $autoAssignedTypes));
    expect($typeWithoutAutoRental)->not->toBeNull();

    $customer = mobileFulInboundCustomer($this->fulfilment);
    $delivery = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, $this->warehouse, [[], ['type' => $typeWithoutAutoRental->value]]));
    [$withRental, $withoutRental] = mobileFulInboundPallets($delivery);
    $withoutRental->update(['rental_id' => null]);
    $billLines = fn () => RecurringBillTransaction::where('fulfilment_customer_id', $customer->id)->count();

    $assertUnreceived = function () use ($delivery, $customer, $billLines) {
        expect($delivery->refresh()->state)->toBe(PalletDeliveryStateEnum::CONFIRMED)
            ->and($delivery->received_at)->toBeNull()
            ->and($delivery->recurring_bill_id)->toBeNull()
            ->and($delivery->pallets()->where('state', '!=', PalletStateEnum::CONFIRMED)->count())->toBe(0)
            ->and($customer->refresh()->current_recurring_bill_id)->toBeNull()
            ->and($billLines())->toBe(0);
    };

    expect(fn () => ReceivePalletDelivery::make()->action($delivery))
        ->toThrow(ValidationException::class, $withoutRental->reference);
    $assertUnreceived();

    mobileFulInboundShopClerk($this);
    mobileFulInboundPost('pallet_deliveries.receive', ['warehouse' => $this->warehouse->id, 'palletDelivery' => $delivery->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.pallets.0', fn (string $message) => str_contains($message, $withoutRental->reference) && !str_contains($message, $withRental->reference));
    $assertUnreceived();

    $withoutRental->update(['rental_id' => $withRental->rental_id]);
    StoreRecurringBillTransaction::mock()->shouldReceive('action')->andThrow(new RuntimeException('billing failed'));
    expect(fn () => ReceivePalletDelivery::make()->action($delivery->refresh()))->toThrow(RuntimeException::class, 'billing failed');
    $assertUnreceived();
});

test('mobile start booking resets not received pallets and says so', function () {
    $delivery = mobileFulInboundReceived(mobileFulInboundDelivery(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, [[], []]));
    [$kept, $missing] = mobileFulInboundPallets($delivery);
    SetPalletAsNotReceived::make()->action($missing);
    $startBooking = fn () => mobileFulInboundPost('pallet_deliveries.start_booking', ['warehouse' => $this->warehouse->id, 'palletDelivery' => $delivery->id]);

    mobileFulInboundUser($this);
    $startBooking()->assertForbidden();

    mobileFulInboundShopClerk($this);
    $startBooking()->assertOk()
        ->assertJsonPath('result.message', 'Booking in started. 1 pallet marked not received was reset to booking in.')
        ->assertJsonPath('data.state', 'booking_in')
        ->assertJsonPath('data.counts.booking_in', 2);

    expect($kept->refresh()->state)->toBe(PalletStateEnum::BOOKING_IN)
        ->and($missing->refresh()->state)->toBe(PalletStateEnum::BOOKING_IN);

    $startBooking()->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile finish booking refuses with the blocking reason instead of the web 400', function () {
    $delivery = mobileFulInboundBookingIn(mobileFulInboundDelivery(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, [[], [], []]));
    [$first, $second, $missing] = mobileFulInboundPallets($delivery);
    $location = mobileFulInboundLocation($this->warehouse);
    BookInPallet::make()->action($first, ['location_id' => $location->id]);
    SetPalletAsNotReceived::make()->action($missing);
    $finish = fn () => mobileFulInboundPost('pallet_deliveries.finish_booking', ['warehouse' => $this->warehouse->id, 'palletDelivery' => $delivery->id]);

    mobileFulInboundClerkWithShopRight($this);
    $finish()->assertUnprocessable()->assertJsonPath('errors.state.0', '1 pallet still to book in');

    BookInPallet::make()->action($second, ['location_id' => $location->id]);
    $second->update(['rental_id' => null]);
    $first->update(['rental_id' => null]);
    $finish()->assertUnprocessable()->assertJsonPath('errors.state.0', '2 pallets have no rental; set it on the web');

    $rentalId = mobileFulInboundRental($this->fulfilment)->id;
    $first->update(['rental_id' => $rentalId]);
    $second->update(['rental_id' => $rentalId]);

    $response = $finish()->assertOk();
    expect($response->json('data.state'))->toBe('booked_in')
        ->and($response->json('data.counts.booked_in'))->toBe(2)
        ->and($response->json('data.available_actions'))->toBe([])
        ->and($response->json('result.message'))->toBe('Delivery booked in.')
        ->and($first->refresh()->status)->toBe(PalletStatusEnum::STORING)
        ->and($missing->refresh()->state)->toBe(PalletStateEnum::NOT_RECEIVED);

    $finish()->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile book in takes a pallet location by id, code or barcode, rehydrates both locations and refuses bad ones', function () {
    $delivery = mobileFulInboundBookingIn(mobileFulInboundDelivery(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, [[], []]));
    [$pallet] = mobileFulInboundPallets($delivery);
    $first  = mobileFulInboundLocation($this->warehouse);
    $second = mobileFulInboundLocation($this->warehouse, ['barcode' => 'MFB'.Str::upper(Str::random(8))]);
    $bookIn = fn (array $body, ?Pallet $target = null) => mobileFulInboundPost('pallets.book_in', ['warehouse' => $this->warehouse->id, 'pallet' => ($target ?? $pallet)->id], $body);

    mobileFulInboundShopClerk($this);
    $bookIn(['location_id' => $first->id])->assertForbidden()->assertJsonPath('message', 'You can not book in pallets in this warehouse.');

    mobileFulInboundUser($this);
    $bookIn([])->assertUnprocessable()->assertJsonValidationErrors(['location_id', 'location_code']);
    $bookIn(['location_id' => mobileFulInboundLocation(mobileFulInboundOtherWarehouse($this->organisation))->id])->assertUnprocessable()->assertJsonPath('errors.location_id.0', 'That location is not in this warehouse.');
    $bookIn(['location_id' => mobileFulInboundLocation($this->warehouse, ['status' => LocationStatusEnum::BROKEN])->id])->assertUnprocessable()->assertJsonPath('errors.location_id.0', 'This location is not operational.');
    $bookIn(['location_code' => mobileFulInboundLocation($this->warehouse, ['allow_fulfilment' => false])->code])->assertUnprocessable()->assertJsonPath('errors.location_code.0', 'This location does not take pallets.');
    $bookIn(['location_code' => 'NOWHERE-'.Str::random(6)])->assertUnprocessable()->assertJsonPath('errors.location_code.0', 'That location is not in this warehouse.');

    $booked = $bookIn(['location_code' => strtolower($first->code)])->assertOk();
    expect($booked->json('result'))->toBe(['status' => 'done', 'message' => 'Pallet booked in at '.$first->code.'.'])
        ->and($booked->json('data.pallets.1.id'))->toBe($pallet->id)
        ->and($booked->json('data.pallets.1.available_actions'))->toBe([['key' => 'book_in', 'label' => 'Change location']])
        ->and($pallet->refresh()->state)->toBe(PalletStateEnum::BOOKED_IN)
        ->and($pallet->location_id)->toBe($first->id)
        ->and(mobileFulInboundLocationPallets($first))->toBe(1);

    $bookIn(['location_id' => $first->id])->assertOk()->assertJsonPath('result.status', 'nothing_changed');

    $bookIn(['location_code' => strtolower($second->barcode)])->assertOk()->assertJsonPath('result', ['status' => 'done', 'message' => 'Pallet moved to '.$second->code.'.']);
    expect($pallet->refresh()->location_id)->toBe($second->id)
        ->and(mobileFulInboundLocationPallets($first))->toBe(0)
        ->and(mobileFulInboundLocationPallets($second))->toBe(1);

    $received = mobileFulInboundReceived(mobileFulInboundDelivery(mobileFulInboundCustomer($this->fulfilment), $this->warehouse));
    $bookIn(['location_id' => $first->id], mobileFulInboundPallets($received)[0])->assertUnprocessable()->assertJsonPath('errors.state.0', 'This pallet can not be booked in now.');

    $stored = mobileFulInboundStoredPallet(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, $first);
    $bookIn(['location_id' => $first->id], $stored)->assertNotFound();
});

test('mobile not received and its undo follow the delivery and pallet states', function () {
    $delivery = mobileFulInboundReceived(mobileFulInboundDelivery(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, [[], []]));
    [$pallet] = mobileFulInboundPallets($delivery);
    $post = fn (string $route, Pallet $target) => mobileFulInboundPost('pallets.'.$route, ['warehouse' => $this->warehouse->id, 'pallet' => $target->id]);

    mobileFulInboundUser($this);
    $post('not_received', $pallet)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Pallet set as not received.'])
        ->assertJsonPath('data.pallets.1.id', $pallet->id)
        ->assertJsonPath('data.pallets.1.available_actions', [['key' => 'undo_not_received', 'label' => 'Undo not received']]);
    expect($pallet->refresh()->state)->toBe(PalletStateEnum::NOT_RECEIVED);

    $post('not_received', $pallet)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    $post('undo_not_received', $pallet)->assertOk()->assertJsonPath('result.status', 'done');
    expect($pallet->refresh()->state)->toBe(PalletStateEnum::RECEIVED);
    $post('undo_not_received', $pallet)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    $bookingIn = mobileFulInboundBookingIn(mobileFulInboundDelivery(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, [[], []]));
    [$booked, $missing] = mobileFulInboundPallets($bookingIn);
    BookInPallet::make()->action($booked, ['location_id' => mobileFulInboundLocation($this->warehouse)->id]);
    $post('not_received', $booked)->assertUnprocessable()->assertJsonPath('errors.state.0', 'This pallet can not be set as not received now.');
    $post('not_received', $missing)->assertOk();
    $post('undo_not_received', $missing)->assertUnprocessable()->assertJsonPath('errors.state.0', 'This pallet can not be set back to received now.');
    expect($missing->refresh()->state)->toBe(PalletStateEnum::NOT_RECEIVED);
});

test('mobile pallets list filters by bucket, incident state, search, location, customer and type with live counts', function () {
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $location = mobileFulInboundLocation($this->warehouse);
    $reporter = mobileFulInboundUser($this);

    $stored    = mobileFulInboundStoredPallet($customer, $this->warehouse, $location);
    $box       = mobileFulInboundStoredPallet($customer, $this->warehouse, null, ['type' => PalletTypeEnum::BOX->value, 'customer_reference' => 'MFI-BLUE-'.Str::random(4)]);
    $returning = mobileFulInboundStoredPallet($customer, $this->warehouse, null, ['state' => PalletStateEnum::PICKING, 'status' => PalletStatusEnum::RETURNING]);
    $returned  = mobileFulInboundStoredPallet($customer, $this->warehouse, null, ['state' => PalletStateEnum::DISPATCHED, 'status' => PalletStatusEnum::RETURNED]);
    $receiving = mobileFulInboundStoredPallet($customer, $this->warehouse, null, ['state' => PalletStateEnum::BOOKING_IN, 'status' => PalletStatusEnum::RECEIVING]);
    $damaged   = mobileFulInboundStoredPallet($customer, $this->warehouse);
    $other     = mobileFulInboundStoredPallet($customer, $this->warehouse, null, ['state' => PalletStateEnum::OTHER_INCIDENT, 'status' => PalletStatusEnum::INCIDENT]);
    $other->update(['incident_report' => ['notes' => 'Not on the shelf']]);
    SetPalletAsDamaged::make()->action($damaged, ['message' => 'Crushed corner', 'reporter_id' => $reporter->id]);
    mobileFulInboundStoredPallet($customer, mobileFulInboundOtherWarehouse($this->organisation));

    $list = fn (array $query = []) => mobileFulInboundGet('pallets.index', ['warehouse' => $this->warehouse->id], ['fulfilment_customer_id' => $customer->id, ...$query])->assertOk();
    $ids  = fn (array $query = []) => collect($list($query)->json('data'))->pluck('id')->all();

    $storing = $list();
    expect(collect($storing->json('data'))->pluck('id')->all())->toBe(collect([$stored, $box])->sortBy('reference')->pluck('id')->values()->all())
        ->and($storing->json('counts'))->toBe([
            'storing'         => 2,
            'receiving'       => 1,
            'returning'       => 1,
            'incidents'       => 2,
            'returned'        => 1,
            'incident_states' => ['damaged' => 1, 'lost' => 0, 'other_incident' => 1],
        ])
        ->and(collect($storing->json('data'))->firstWhere('id', $stored->id))->toBe([
            'id'                  => $stored->id,
            'reference'           => $stored->reference,
            'customer_reference'  => $stored->customer_reference,
            'type'                => 'pallet',
            'type_label'          => 'Pallet',
            'state'               => 'storing',
            'state_label'         => 'Storing',
            'status'              => 'storing',
            'status_label'        => 'Storing',
            'customer'            => ['id' => $customer->id, 'name' => $customer->customer->name],
            'location'            => ['id' => $location->id, 'code' => $location->code],
            'notes'               => null,
            'number_stored_items' => 0,
            'incident'            => null,
        ])
        ->and($ids(['filter' => 'receiving']))->toBe([$receiving->id])
        ->and($ids(['filter' => 'returning']))->toBe([$returning->id])
        ->and($ids(['filter' => 'returned']))->toBe([$returned->id])
        ->and($ids(['filter' => 'incidents', 'incident_state' => 'other_incident']))->toBe([$other->id])
        ->and($ids(['q' => strtolower($location->code)]))->toBe([$stored->id])
        ->and($ids(['q' => 'blue']))->toBe([$box->id])
        ->and($ids(['q' => $box->reference]))->toBe([$box->id])
        ->and($ids(['location_id' => $location->id]))->toBe([$stored->id])
        ->and($ids(['type' => 'box']))->toBe([$box->id])
        ->and($ids(['sort' => '-reference']))->toBe(collect([$stored, $box])->sortByDesc('reference')->pluck('id')->values()->all())
        ->and($list(['type' => 'box'])->json('counts.storing'))->toBe(1);

    $incidents = collect($list(['filter' => 'incidents', 'sort' => 'incident_date'])->json('data'))->keyBy('id');
    expect($incidents[$damaged->id]['incident'])->toMatchArray([
        'state'       => 'damaged',
        'state_label' => 'Damaged',
        'message'     => 'Crushed corner',
        'reporter'    => ['id' => $reporter->id, 'name' => $reporter->contact_name ?? $reporter->username],
        'return'      => null,
    ])
        ->and($incidents[$damaged->id]['incident']['date'])->not->toBeNull()
        ->and($incidents[$damaged->id]['location'])->toBeNull()
        ->and($incidents[$other->id]['incident']['message'])->toBe('Not on the shelf')
        ->and($incidents[$other->id]['incident']['reporter'])->toBeNull();

    mobileFulInboundGet('pallets.index', ['warehouse' => $this->warehouse->id], ['filter' => 'lost'])->assertUnprocessable()->assertJsonValidationErrors(['filter']);
});

test('mobile pallet page lists stored items with items storage and actions by status and right', function () {
    $location = mobileFulInboundLocation($this->warehouse);
    $pallet   = mobileFulInboundStoredPallet(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, $location);
    $item     = mobileFulInboundStoredItem($pallet, 40);
    $show     = fn (Pallet $target) => mobileFulInboundGet('pallets.show', ['warehouse' => $this->warehouse->id, 'pallet' => $target->id]);

    mobileFulInboundUser($this);
    $detail           = $show($pallet)->assertOk()->json('data');
    $palletStoredItem = PalletStoredItem::where('pallet_id', $pallet->id)->sole();
    expect(array_keys($detail))->toBe(['id', 'reference', 'customer_reference', 'type', 'type_label', 'state', 'state_label', 'status', 'status_label', 'customer', 'location', 'notes', 'number_stored_items', 'incident', 'delivery', 'return', 'received_at', 'booked_in_at', 'storing_at', 'items_storage', 'stored_items', 'open_audit_id', 'available_actions'])
        ->and($detail['items_storage'])->toBeTrue()
        ->and($detail['stored_items'])->toBe([[
            'id'          => $item->id,
            'reference'   => $item->reference,
            'name'        => $item->name,
            'barcode'     => $item->barcode,
            'quantity'    => 40,
            'state'       => $palletStoredItem->state->value,
            'state_label' => PalletStoredItemStateEnum::labels()[$palletStoredItem->state->value],
        ]])
        ->and($detail['open_audit_id'])->toBeNull()
        ->and($detail['delivery'])->toBeNull()
        ->and(collect($detail['available_actions'])->pluck('key')->all())->toBe(['move', 'damaged', 'lost', 'audit'])
        ->and(collect($detail['available_actions'])->firstWhere('key', 'audit')['label'])->toBe('Audit stored items');

    mobileFulInboundShopClerk($this);
    expect($show($pallet)->assertOk()->json('data.available_actions'))->toBe([]);

    mobileFulInboundUser($this, [], ["fulfilment.{$this->warehouse->id}.view", "locations.{$this->warehouse->id}.edit"]);
    expect($show($pallet)->assertOk()->json('data.available_actions'))->toBe([['key' => 'move', 'label' => 'Move']]);

    mobileFulInboundUser($this);
    $noItemsStorage = mobileFulInboundStoredPallet(mobileFulInboundCustomer($this->fulfilment, itemsStorage: false), $this->warehouse, $location);
    mobileFulInboundStoredItem($noItemsStorage, 3);
    $plain = $show($noItemsStorage)->assertOk()->json('data');
    expect($plain['items_storage'])->toBeFalse()
        ->and($plain['stored_items'])->toBe([])
        ->and($plain['number_stored_items'])->toBe(1)
        ->and(collect($plain['available_actions'])->pluck('key')->all())->toBe(['move', 'damaged', 'lost']);

    SetPalletAsDamaged::make()->action($noItemsStorage, ['message' => null]);
    expect($show($noItemsStorage)->json('data.available_actions'))->toBe([]);

    $show(mobileFulInboundStoredPallet(mobileFulInboundCustomer($this->fulfilment), mobileFulInboundOtherWarehouse($this->organisation)))->assertNotFound();

    mobileFulInboundUser($this, [], ["locations.{$this->warehouse->id}.edit"]);
    $show($pallet)->assertForbidden();
});

test('mobile move needs a stored pallet and a pallet location, and locations edit alone may move', function () {
    $from   = mobileFulInboundLocation($this->warehouse);
    $to     = mobileFulInboundLocation($this->warehouse);
    $pallet = mobileFulInboundStoredPallet(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, $from);
    LocationHydratePallets::run($from);
    Pallet::enableAuditing();
    $move = fn (array $body, ?Pallet $target = null) => mobileFulInboundPost('pallets.move', ['warehouse' => $this->warehouse->id, 'pallet' => ($target ?? $pallet)->id], $body);

    mobileFulInboundUser($this, [], ["fulfilment.{$this->warehouse->id}.view"]);
    $move(['location_id' => $to->id])->assertForbidden()->assertJsonPath('message', 'You can not move pallets in this warehouse.');

    mobileFulInboundUser($this, [], ["locations.{$this->warehouse->id}.edit"]);
    $move(['location_id' => mobileFulInboundLocation($this->warehouse, ['allow_fulfilment' => false])->id])->assertUnprocessable()->assertJsonPath('errors.location_id.0', 'This location does not take pallets.');

    expect(mobileFulInboundLocationPallets($from))->toBe(1);
    $moved = $move(['location_code' => $to->code])->assertOk();
    expect($moved->json('result'))->toBe(['status' => 'done', 'message' => 'Pallet moved to '.$to->code.'.'])
        ->and($moved->json('data.location'))->toBe(['id' => $to->id, 'code' => $to->code])
        ->and($moved->json('data.available_actions'))->toBe([['key' => 'move', 'label' => 'Move']])
        ->and($pallet->refresh()->location_id)->toBe($to->id)
        ->and(mobileFulInboundLocationPallets($from))->toBe(0)
        ->and(mobileFulInboundLocationPallets($to))->toBe(1)
        ->and($pallet->audits()->latest('id')->first()?->new_values)->toBe(['location' => $to->code]);

    $move(['location_id' => $to->id])->assertOk()->assertJsonPath('result.status', 'nothing_changed');

    $incident = mobileFulInboundStoredPallet(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, null, ['state' => PalletStateEnum::LOST, 'status' => PalletStatusEnum::INCIDENT]);
    $move(['location_id' => $from->id], $incident)->assertUnprocessable()->assertJsonPath('errors.state.0', 'Only a stored pallet can be moved.');
});

test('mobile incident sets a stored pallet damaged or lost with the user as reporter', function () {
    $location = mobileFulInboundLocation($this->warehouse);
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $pallet   = mobileFulInboundStoredPallet($customer, $this->warehouse, $location);
    $lost     = mobileFulInboundStoredPallet($customer, $this->warehouse, $location);
    $report   = fn (Pallet $target, array $body) => mobileFulInboundPost('pallets.incident', ['warehouse' => $this->warehouse->id, 'pallet' => $target->id], $body);

    mobileFulInboundShopClerk($this);
    $report($pallet, ['state' => 'damaged'])->assertForbidden();

    $user = mobileFulInboundUser($this);
    $report($pallet, ['state' => 'other_incident'])->assertUnprocessable()->assertJsonValidationErrors(['state']);
    $report($pallet, ['state' => 'damaged', 'message' => str_repeat('x', 1001)])->assertUnprocessable()->assertJsonValidationErrors(['message']);

    $damaged = $report($pallet, ['state' => 'damaged', 'message' => 'Forklift'])->assertOk();
    expect($damaged->json('result'))->toBe(['status' => 'done', 'message' => 'Pallet set as damaged.'])
        ->and($damaged->json('data.status'))->toBe('incident')
        ->and($damaged->json('data.location'))->toBeNull()
        ->and($damaged->json('data.available_actions'))->toBe([])
        ->and($damaged->json('data.incident'))->toMatchArray(['state' => 'damaged', 'message' => 'Forklift', 'reporter' => ['id' => $user->id, 'name' => $user->contact_name ?? $user->username]])
        ->and($pallet->refresh()->incident_report->reporter_id)->toBe($user->id)
        ->and(mobileFulInboundLocationPallets($location))->toBe(1);

    $report($pallet, ['state' => 'lost'])->assertUnprocessable()->assertJsonPath('errors.state.0', 'Only a stored pallet can be set as damaged or lost.');
    $report($lost, ['state' => 'lost'])->assertOk()->assertJsonPath('result.message', 'Pallet set as lost.')->assertJsonPath('data.incident.state', 'lost');
});

test('mobile fulfilment locations list only pallet locations of this warehouse with live pallet counts', function () {
    $prefix  = 'MFQ'.Str::upper(Str::random(4));
    $busy    = mobileFulInboundLocation($this->warehouse, ['code' => $prefix.'-A']);
    $empty   = mobileFulInboundLocation($this->warehouse, ['code' => $prefix.'-B']);
    mobileFulInboundLocation($this->warehouse, ['code' => $prefix.'-C', 'allow_fulfilment' => false]);
    mobileFulInboundLocation($this->warehouse, ['code' => $prefix.'-D', 'status' => LocationStatusEnum::BROKEN]);
    mobileFulInboundLocation(mobileFulInboundOtherWarehouse($this->organisation), ['code' => $prefix.'-E']);
    $trashed = mobileFulInboundLocation($this->warehouse, ['code' => $prefix.'-F']);
    $trashed->delete();
    $customer = mobileFulInboundCustomer($this->fulfilment);
    mobileFulInboundStoredPallet($customer, $this->warehouse, $busy);
    mobileFulInboundStoredPallet($customer, $this->warehouse, $busy, ['state' => PalletStateEnum::DISPATCHED, 'status' => PalletStatusEnum::RETURNED]);
    DB::table('location_stats')->where('location_id', $busy->id)->update(['number_pallets' => 9]);

    $list = fn () => mobileFulInboundGet('fulfilment_locations.index', ['warehouse' => $this->warehouse->id], ['q' => strtolower($prefix)]);

    mobileFulInboundUser($this);
    expect($list()->assertOk()->json('data'))->toBe([
        ['id' => $busy->id, 'code' => $busy->code, 'number_pallets' => 1],
        ['id' => $empty->id, 'code' => $empty->code, 'number_pallets' => 0],
    ]);

    mobileFulInboundUser($this, [], ["locations.{$this->warehouse->id}.edit"]);
    $list()->assertOk();

    mobileFulInboundShopClerk($this);
    $list()->assertForbidden();
});

test('mobile scan finds pallets and pallet deliveries and lets a fulfilment only user scan', function () {
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $location = mobileFulInboundLocation($this->warehouse);
    $pallet   = mobileFulInboundStoredPallet($customer, $this->warehouse, $location, ['customer_reference' => 'MFI-UNIQUE-'.Str::upper(Str::random(5))]);
    $slugged  = mobileFulInboundStoredPallet($customer, $this->warehouse);
    $slugged->update(['slug' => 'mfi-slug-'.Str::lower(Str::random(8))]);
    $shared   = 'MFI-SHARED-'.Str::upper(Str::random(5));
    mobileFulInboundStoredPallet($customer, $this->warehouse, null, ['customer_reference' => $shared]);
    mobileFulInboundStoredPallet(mobileFulInboundCustomer($this->fulfilment), $this->warehouse, null, ['customer_reference' => $shared]);
    $delivery = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, $this->warehouse));
    $elsewhere = mobileFulInboundStoredPallet($customer, mobileFulInboundOtherWarehouse($this->organisation));

    $scan = fn (string $code) => mobileFulInboundGet('scan', ['warehouse' => $this->warehouse->id], ['code' => $code])->assertOk();

    mobileFulInboundUser($this, [], ["supervisor-fulfilment.{$this->warehouse->id}"]);
    $scan(strtolower($pallet->reference))
        ->assertJsonPath('data.type', 'pallet')
        ->assertJsonPath('data.sko', null)
        ->assertJsonPath('data.pallet_delivery', null)
        ->assertJsonPath('data.pallet', [
            'id'                 => $pallet->id,
            'reference'          => $pallet->reference,
            'customer_reference' => $pallet->customer_reference,
            'state'              => 'storing',
            'state_label'        => 'Storing',
            'status'             => 'storing',
            'location_code'      => $location->code,
        ]);
    $scan(strtoupper($slugged->slug))->assertJsonPath('data.pallet.id', $slugged->id);
    $scan(strtolower($pallet->customer_reference))->assertJsonPath('data.pallet.id', $pallet->id);
    $scan($shared)->assertJsonPath('data.type', 'unknown')->assertJsonPath('data.pallet', null);
    $scan($elsewhere->reference)->assertJsonPath('data.type', 'unknown');

    $scan(strtoupper($delivery->reference))
        ->assertJsonPath('data.type', 'pallet_delivery')
        ->assertJsonPath('data.pallet_delivery', ['id' => $delivery->id, 'reference' => $delivery->reference, 'state' => 'confirmed', 'state_label' => 'Confirmed']);
    $scan('pad-'.$delivery->slug)->assertJsonPath('data.pallet_delivery.id', $delivery->id);

    mobileFulInboundUser($this, [], ["locations.{$this->warehouse->id}.view"]);
    $scan($pallet->reference)->assertJsonPath('data.type', 'unknown');
    $scan('pad-'.$delivery->slug)->assertJsonPath('data.type', 'unknown');
});

test('moving a pallet refuses a non stored pallet and a location of another warehouse, closed, not operational or not for pallets', function () {
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $from     = mobileFulInboundLocation($this->warehouse);
    $pallet   = mobileFulInboundStoredPallet($customer, $this->warehouse, $from);
    $moveTo   = fn (Location $location, ?Pallet $target = null) => fn () => UpdatePalletLocation::make()->action($location, $target ?? $pallet);

    $refusals = [
        'another warehouse' => mobileFulInboundLocation(mobileFulInboundOtherWarehouse($this->organisation)),
        'not operational'   => mobileFulInboundLocation($this->warehouse, ['status' => LocationStatusEnum::BROKEN]),
        'no pallets'        => mobileFulInboundLocation($this->warehouse, ['allow_fulfilment' => false]),
    ];
    foreach ($refusals as $location) {
        expect($moveTo($location))->toThrow(ValidationException::class);
        expect($pallet->refresh()->location_id)->toBe($from->id);
    }

    $deleted = mobileFulInboundLocation($this->warehouse);
    $deleted->delete();
    expect(fn () => UpdatePalletLocation::make()->action($deleted->refresh(), $pallet))->toThrow(ValidationException::class);

    $incident = mobileFulInboundStoredPallet($customer, $this->warehouse, null, ['state' => PalletStateEnum::LOST, 'status' => PalletStatusEnum::INCIDENT]);
    expect($moveTo(mobileFulInboundLocation($this->warehouse), $incident))->toThrow(ValidationException::class, 'Only a stored pallet can be moved.');

    $to = mobileFulInboundLocation($this->warehouse);
    UpdatePalletLocation::make()->action($to, $pallet);
    expect($pallet->refresh()->location_id)->toBe($to->id);
});

test('a damaged or lost pallet on no return goes back to storing, and a pallet without an incident is refused', function () {
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $location = mobileFulInboundLocation($this->warehouse);
    $pallet   = mobileFulInboundStoredPallet($customer, $this->warehouse, $location);

    expect(fn () => SetPalletBackToStoring::make()->action($pallet, []))->toThrow(ValidationException::class, 'Only a pallet with an incident can be set as usable again.');

    SetPalletAsDamaged::make()->action($pallet, ['message' => 'Forklift']);
    expect($pallet->refresh()->status)->toBe(PalletStatusEnum::INCIDENT)
        ->and($pallet->palletReturn)->toBeNull();

    $restored = SetPalletBackToStoring::make()->action($pallet, []);
    expect($restored->state)->toBe(PalletStateEnum::STORING)
        ->and($restored->status)->toBe(PalletStatusEnum::STORING)
        ->and($restored->set_as_incident_at)->toBeNull()
        ->and($restored->incident_report)->toBeEmpty();

    expect(fn () => SetPalletBackToStoring::make()->action($restored, []))->toThrow(ValidationException::class);
});

test('a repeated audit count updates the existing delta and foreign pallets or items are rejected, and a completed audit can not be completed again', function () {
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $other    = mobileFulInboundCustomer($this->fulfilment);
    $pallet   = mobileFulInboundStoredPallet($customer, $this->warehouse, mobileFulInboundLocation($this->warehouse));
    $item     = mobileFulInboundStoredItem($pallet, 10);
    $audit    = StoreStoredItemAuditFromPallet::make()->action($pallet, ['warehouse_id' => $this->warehouse->id]);
    $count    = fn (int $quantity) => StoreStoredItemAuditDelta::make()->action($audit, ['pallet_id' => $pallet->id, 'stored_item_id' => $item->id, 'audited_quantity' => $quantity]);

    $first  = $count(7);
    $second = $count(12);
    expect($second->id)->toBe($first->id)
        ->and($audit->deltas()->count())->toBe(1)
        ->and((float) $first->refresh()->audited_quantity)->toBe(12.0);

    $foreignPallet = mobileFulInboundStoredPallet($other, $this->warehouse);
    $foreignItem   = mobileFulInboundStoredItem($foreignPallet, 1);
    expect(fn () => StoreStoredItemAuditDelta::make()->action($audit, ['pallet_id' => $foreignPallet->id, 'stored_item_id' => $item->id, 'audited_quantity' => 1]))->toThrow(ValidationException::class)
        ->and(fn () => StoreStoredItemAuditDelta::make()->action($audit, ['pallet_id' => $pallet->id, 'stored_item_id' => $foreignItem->id, 'audited_quantity' => 1]))->toThrow(ValidationException::class)
        ->and($audit->deltas()->count())->toBe(1);

    CompleteStoredItemAudit::make()->action($audit->refresh(), []);
    expect(fn () => CompleteStoredItemAudit::make()->action($audit->refresh(), []))->toThrow(ValidationException::class, 'This audit is already completed.');
});

test('reverting a received delivery to confirmed keeps the pallet references and takes the billing receiving added off the current recurring bill', function () {
    $customer = mobileFulInboundCustomer($this->fulfilment);
    $delivery = mobileFulInboundConfirmed(mobileFulInboundDelivery($customer, $this->warehouse, [[], []]));
    $palletIds = $delivery->pallets()->pluck('id');
    $references = $delivery->pallets()->orderBy('id')->pluck('reference')->all();
    $billing = fn () => RecurringBillTransaction::where('fulfilment_customer_id', $customer->id)
        ->where(fn ($query) => $query->where('pallet_delivery_id', $delivery->id)->orWhere(fn ($query) => $query->where('item_type', 'Pallet')->whereIn('item_id', $palletIds)))
        ->count();

    expect($billing())->toBe(0);
    $delivery = ReceivePalletDelivery::make()->action($delivery);
    expect($delivery->refresh()->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and($billing())->toBeGreaterThan(0);

    $delivery = ConfirmPalletDelivery::make()->action($delivery);
    expect($delivery->refresh()->state)->toBe(PalletDeliveryStateEnum::CONFIRMED)
        ->and($delivery->recurring_bill_id)->toBeNull()
        ->and($delivery->pallets()->orderBy('id')->pluck('reference')->all())->toBe($references)
        ->and($billing())->toBe(0);

    $received = ReceivePalletDelivery::make()->action($delivery);
    $received->recurringBill->update(['status' => RecurringBillStatusEnum::FORMER]);
    expect(fn () => ConfirmPalletDelivery::make()->action($received->refresh()))->toThrow(ValidationException::class, 'already been invoiced');
});
