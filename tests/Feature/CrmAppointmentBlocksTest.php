<?php

use App\Actions\CRM\Appointment\GoogleCalendar\DisconnectShopGoogleCalendar;
use App\Actions\CRM\Appointment\GoogleCalendar\ReconcileShopGoogleCalendar;
use App\Actions\CRM\Appointment\StoreAppointment;
use App\Actions\CRM\AppointmentBlock\StoreAppointmentBlock;
use App\Actions\CRM\AppointmentBlock\SyncAppointmentBlockGoogleCalendarEvent;
use App\Actions\CRM\AppointmentType\GetAppointmentTypeAvailableSlots;
use App\Actions\CRM\AppointmentType\StoreAppointmentType;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\CRM\AppointmentBlock;
use App\Models\CRM\AppointmentType;
use App\Services\GoogleCalendar\GoogleCalendarClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Lorisleiva\Actions\Decorators\JobDecorator;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user, $this->shop) = createShop();

    Http::preventStrayRequests();
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);

    $this->timezone = $this->shop->timezone?->name ?? 'UTC';
    $this->day      = fn (int $days) => now($this->timezone)->addDays($days)->format('Y-m-d');

    $this->appointmentType = fn (): AppointmentType => StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Block test '.Str::random(6),
        'meeting_mode'        => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'location'            => 'Showroom, Sheffield',
        'duration_minutes'    => 60,
        'min_notice_hours'    => 0,
        'booking_window_days' => 14,
        'capacity_per_slot'   => 3,
        'availability'        => ['weekly' => array_fill_keys(range(1, 7), [['from' => '09:00', 'to' => '13:00']])],
    ]);

    $this->storeRoute = route('grp.models.shop.appointment_block.store', ['shop' => $this->shop->id]);
});

afterEach(function () {
    AppointmentBlock::where('shop_id', $this->shop->id)->delete();
    DisconnectShopGoogleCalendar::make()->handle($this->shop->refresh());
});

test('blocked days are not offered to visitors until the block is removed', function () {
    $appointmentType = ($this->appointmentType)();

    $this->post($this->storeRoute, [
        'is_all_day' => true,
        'date_from'  => ($this->day)(3),
        'date_to'    => ($this->day)(4),
        'reason'     => 'Stocktake',
    ])->assertSessionHas('notification.status', 'success');

    $block = AppointmentBlock::where('shop_id', $this->shop->id)->latest('id')->first();
    $slots = GetAppointmentTypeAvailableSlots::run($appointmentType);

    expect($block->is_all_day)->toBeTrue()
        ->and($block->reason)->toBe('Stocktake')
        ->and($block->created_by_user_id)->toBe($this->user->id)
        ->and($block->starts_at->copy()->setTimezone($this->timezone)->format('Y-m-d H:i'))->toBe(($this->day)(3).' 00:00')
        ->and($block->ends_at->copy()->setTimezone($this->timezone)->format('Y-m-d H:i'))->toBe(($this->day)(5).' 00:00')
        ->and($slots)->not->toHaveKey(($this->day)(3))
        ->and($slots)->not->toHaveKey(($this->day)(4))
        ->and($slots[($this->day)(5)])->toBe(['09:00', '10:00', '11:00', '12:00']);

    $this->delete(route('grp.models.shop.appointment_block.delete', ['shop' => $this->shop->id, 'appointmentBlock' => $block->id]))
        ->assertSessionHas('notification.status', 'success');

    expect(AppointmentBlock::find($block->id))->toBeNull()
        ->and(GetAppointmentTypeAvailableSlots::run($appointmentType))->toHaveKey(($this->day)(3));
});

test('a block of hours closes only those hours, and only for its appointment type when it names one', function () {
    $blockedType = ($this->appointmentType)();
    $otherType   = ($this->appointmentType)();

    StoreAppointmentBlock::make()->action($this->shop, [
        'is_all_day'          => false,
        'date_from'           => ($this->day)(2),
        'time_from'           => '10:00',
        'time_to'             => '12:00',
        'appointment_type_id' => $blockedType->id,
    ]);

    expect(GetAppointmentTypeAvailableSlots::run($blockedType)[($this->day)(2)])->toBe(['09:00', '12:00'])
        ->and(GetAppointmentTypeAvailableSlots::run($otherType)[($this->day)(2)])->toBe(['09:00', '10:00', '11:00', '12:00']);
});

test('a block must end after it starts and can only name an appointment type of its shop', function () {
    $this->post($this->storeRoute, [
        'is_all_day' => false,
        'date_from'  => ($this->day)(2),
        'time_from'  => '12:00',
        'time_to'    => '10:00',
    ])->assertSessionHasErrors('time_to');

    $this->post($this->storeRoute, [
        'is_all_day'          => true,
        'date_from'           => ($this->day)(2),
        'appointment_type_id' => (int) AppointmentType::withTrashed()->max('id') + 1000,
    ])->assertSessionHasErrors('appointment_type_id');

    expect(AppointmentBlock::where('shop_id', $this->shop->id)->count())->toBe(0);
});

test('appointments already booked in blocked time are kept and marked on the calendar', function () {
    $appointmentType = ($this->appointmentType)();
    $book            = fn (int $days) => StoreAppointment::make()->action($this->shop, [
        'appointment_type_id' => $appointmentType->id,
        'starts_at'           => ($this->day)($days).' 10:00',
        'contact_name'        => 'Blocked visitor '.Str::random(6),
    ]);

    $inBlock    = $book(6);
    $outOfBlock = $book(7);

    $this->post($this->storeRoute, [
        'is_all_day' => true,
        'date_from'  => ($this->day)(6),
    ])->assertSessionHas('notification.status', 'warning');

    expect($inBlock->refresh()->state)->toBe(AppointmentStateEnum::ACCEPTED);

    $this->get(route('grp.org.shops.show.crm.appointments.calendar', [$this->organisation->slug, $this->shop->slug, 'month' => substr(($this->day)(6), 0, 7)]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Shop/CRM/AppointmentsCalendar')
            ->where('canEdit', true)
            ->where('calendar.weeks', function ($weeks) use ($inBlock, $outOfBlock) {
                $days = collect($weeks)->flatten(1)->keyBy('date');
                $row  = fn (string $date, int $id) => collect($days[$date]['appointments'] ?? [])->firstWhere('id', $id);

                return count($days[($this->day)(6)]['blocks']) === 1
                    && $days[($this->day)(6)]['blocks'][0]['is_all_day']
                    && $row(($this->day)(6), $inBlock->id)['is_blocked'] === true
                    && $row(($this->day)(6), $inBlock->id)['can_decline'] === true
                    && (!isset($days[($this->day)(7)]) || $row(($this->day)(7), $outOfBlock->id)['is_blocked'] === false);
            }));
});

test('a block is written into the connected Google calendar, removed with it, and put right by the sync', function () {
    $settings = $this->shop->settings ?? [];
    data_set($settings, GoogleCalendarClient::SETTINGS_KEY, [
        'calendar'      => 'Showroom',
        'refresh_token' => Crypt::encryptString('refresh-token'),
        'connected_at'  => now()->toIso8601String(),
    ]);
    $this->shop->update(['settings' => $settings]);

    Queue::fake();
    $block = StoreAppointmentBlock::make()->action($this->shop->refresh(), [
        'is_all_day' => false,
        'date_from'  => ($this->day)(2),
        'time_from'  => '10:00',
        'time_to'    => '12:00',
        'reason'     => 'Delivery',
    ]);

    Queue::assertPushed(JobDecorator::class, fn ($job) => $job->displayName() === SyncAppointmentBlockGoogleCalendarEvent::class && $job->getParameters() === [$this->shop->id, $block->id]);

    $googleEvents = [];
    Http::fake(function (Request $request) use (&$googleEvents) {
        if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
            return Http::response(['access_token' => 'access-token', 'expires_in' => 3600]);
        }

        return match ($request->method()) {
            'PUT'    => Http::response(['error' => ['code' => 404]], 404),
            'POST'   => Http::response(['id' => Arr::get($request->data(), 'id')]),
            'DELETE' => Http::response(null, 204),
            default  => Http::response(['items' => $googleEvents]),
        };
    });

    SyncAppointmentBlockGoogleCalendarEvent::run($this->shop->id, $block->id);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && Arr::get($request->data(), 'id') === GoogleCalendarClient::blockEventId($block->id)
        && Arr::get($request->data(), 'extendedProperties.private.'.GoogleCalendarClient::BLOCK_EVENT_PROPERTY) === (string) $block->id
        && str_contains(Arr::get($request->data(), 'summary'), 'Delivery'));

    $orphanId     = (int) AppointmentBlock::max('id') + 1000;
    $googleEvents = [[
        'id'                 => GoogleCalendarClient::blockEventId($orphanId),
        'summary'            => 'Blocked',
        'status'             => 'confirmed',
        'start'              => ['dateTime' => now()->addDays(3)->toIso8601String()],
        'end'                => ['dateTime' => now()->addDays(3)->addHour()->toIso8601String()],
        'extendedProperties' => ['private' => [GoogleCalendarClient::BLOCK_EVENT_PROPERTY => (string) $orphanId]],
    ]];

    $result = ReconcileShopGoogleCalendar::make()->handle($this->shop->refresh());

    expect($result['removed'])->toBeGreaterThanOrEqual(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), 'events/'.GoogleCalendarClient::blockEventId($orphanId)));
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_ends_with($request->url(), 'events/'.GoogleCalendarClient::blockEventId($block->id)));

    $block->delete();
    SyncAppointmentBlockGoogleCalendarEvent::run($this->shop->id, $block->id);

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), 'events/'.GoogleCalendarClient::blockEventId($block->id)));
});
