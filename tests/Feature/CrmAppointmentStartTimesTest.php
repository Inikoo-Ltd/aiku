<?php

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Appointment\StoreAppointment;
use App\Actions\CRM\AppointmentBlock\StoreAppointmentBlock;
use App\Actions\CRM\AppointmentType\GetAppointmentTypeAvailableSlots;
use App\Actions\CRM\AppointmentType\StoreAppointmentType;
use App\Actions\CRM\AppointmentType\UpdateAppointmentType;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\CRM\AppointmentBlock;
use App\Models\CRM\AppointmentType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user, $this->shop) = createShop();

    Http::preventStrayRequests();
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);

    $timezone     = $this->shop->timezone?->name ?? 'UTC';
    $this->monday = now($timezone)->next(CarbonInterface::MONDAY);
    $this->day    = fn (int $daysAfterMonday) => $this->monday->copy()->addDays($daysAfterMonday)->format('Y-m-d');

    $this->startTimes = ['08:30', '09:30', '10:30', '13:30', '14:30'];

    $this->appointmentType = fn (AppointmentTypeMeetingModeEnum $meetingMode = AppointmentTypeMeetingModeEnum::STORE_VISIT, array $startTimes = []): AppointmentType => StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Start times test '.Str::random(6),
        'meeting_mode'        => $meetingMode->value,
        'duration_minutes'    => 60,
        'buffer_minutes'      => 0,
        'min_notice_hours'    => 0,
        'booking_window_days' => 21,
        'capacity_per_slot'   => 1,
        'start_times'         => $startTimes,
        'availability'        => ['weekly' => [
            1 => [['from' => '08:30', 'to' => '17:00']],
            2 => [['from' => '08:30', 'to' => '17:00']],
            3 => [['from' => '08:30', 'to' => '17:00']],
            4 => [['from' => '08:30', 'to' => '17:00']],
            5 => [['from' => '08:30', 'to' => '17:00']],
        ]],
    ]);
});

afterEach(function () {
    AppointmentBlock::where('shop_id', $this->shop->id)->delete();
    UpdateShop::make()->action($this->shop->refresh(), ['appointment_one_at_a_time' => false]);
});

test('appointments start only at the set start times, on open days and inside the opening hours', function () {
    $showroom = ($this->appointmentType)(startTimes: ['14:30', '08:30', '09:30', '10:30', '13:30']);

    $showroom->dates()->create(['date' => ($this->day)(2), 'hours' => [['from' => '08:30', 'to' => '12:00']]]);

    $slots = GetAppointmentTypeAvailableSlots::run($showroom);

    expect($showroom->startTimes())->toBe($this->startTimes)
        ->and($slots[($this->day)(0)])->toBe($this->startTimes)
        ->and($slots[($this->day)(4)])->toBe($this->startTimes)
        ->and($slots[($this->day)(2)])->toBe(['08:30', '09:30', '10:30'])
        ->and($slots)->not->toHaveKey(($this->day)(5))
        ->and($slots)->not->toHaveKey(($this->day)(6));
});

test('clearing the start times goes back to appointments following one another', function () {
    $showroom = ($this->appointmentType)(startTimes: $this->startTimes);

    $showroom = UpdateAppointmentType::make()->action($showroom, ['start_times' => []]);

    expect($showroom->startTimes())->toBe([])
        ->and(GetAppointmentTypeAvailableSlots::run($showroom)[($this->day)(0)])->toBe(['08:30', '09:30', '10:30', '11:30', '12:30', '13:30', '14:30', '15:30']);
});

test('start times save on their own from the edit page and keep the video call media', function () {
    $videoCall = ($this->appointmentType)(AppointmentTypeMeetingModeEnum::VIDEO_CALL);

    $this->patch(route('grp.models.appointment_type.update', ['appointmentType' => $videoCall->id]), ['start_times' => $this->startTimes])
        ->assertSessionHasNoErrors();

    $videoCall->refresh();

    expect($videoCall->startTimes())->toBe($this->startTimes)
        ->and($videoCall->media())->not->toBeNull();

    $this->get(route('grp.org.shops.show.crm.appointments.types.edit', [$this->organisation->slug, $this->shop->slug, $videoCall->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('formData.blueprint.2.fields.start_times.value', $this->startTimes));
});

test('the break does not take the next start time when start times are set', function () {
    $showroom = ($this->appointmentType)(startTimes: $this->startTimes);
    $showroom = UpdateAppointmentType::make()->action($showroom, ['duration_minutes' => 45, 'buffer_minutes' => 30]);
    $day      = ($this->day)(3);

    StoreAppointment::make()->action($this->shop, [
        'appointment_type_id' => $showroom->id,
        'starts_at'           => $day.' 08:30',
        'contact_name'        => 'Early visitor '.Str::random(6),
    ]);

    $nextVisitor = StoreAppointment::make()->action($this->shop, [
        'appointment_type_id' => $showroom->id,
        'starts_at'           => $day.' 09:30',
        'contact_name'        => 'Next visitor '.Str::random(6),
    ]);

    expect($showroom->breakMinutes())->toBe(0)
        ->and($nextVisitor->appointment_type_id)->toBe($showroom->id)
        ->and(GetAppointmentTypeAvailableSlots::run($showroom->fresh())[$day])->toBe(['10:30', '13:30', '14:30']);
});

test('start times must be times of the day without repeats', function (array $startTimes) {
    expect(fn () => ($this->appointmentType)(startTimes: $startTimes))->toThrow(ValidationException::class);
})->with([
    'not a time' => [['8.30']],
    'repeated'   => [['08:30', '08:30']],
]);

test('a showroom visit and a video call on the same start times never overlap when the shop takes one appointment at a time', function () {
    $showroom  = ($this->appointmentType)(startTimes: $this->startTimes);
    $videoCall = ($this->appointmentType)(AppointmentTypeMeetingModeEnum::VIDEO_CALL, $this->startTimes);
    $day       = ($this->day)(1);

    UpdateShop::make()->action($this->shop, ['appointment_one_at_a_time' => true]);

    StoreAppointment::make()->action($this->shop, [
        'appointment_type_id' => $showroom->id,
        'starts_at'           => $day.' 09:30',
        'contact_name'        => 'Showroom visitor '.Str::random(6),
    ]);

    expect(GetAppointmentTypeAvailableSlots::run($videoCall->fresh())[$day])->toBe(['08:30', '10:30', '13:30', '14:30']);
});

test('a bank holiday or Christmas closure takes the days out for every appointment type', function () {
    $showroom  = ($this->appointmentType)(startTimes: $this->startTimes);
    $videoCall = ($this->appointmentType)(AppointmentTypeMeetingModeEnum::VIDEO_CALL, $this->startTimes);

    StoreAppointmentBlock::make()->action($this->shop, [
        'is_all_day' => true,
        'date_from'  => ($this->day)(7),
        'date_to'    => ($this->day)(9),
        'reason'     => 'Christmas closure',
    ]);

    foreach ([$showroom, $videoCall] as $appointmentType) {
        $slots = GetAppointmentTypeAvailableSlots::run($appointmentType);

        expect($slots)->not->toHaveKeys([($this->day)(7), ($this->day)(8), ($this->day)(9)])
            ->and($slots[($this->day)(10)])->toBe($this->startTimes);
    }
});
