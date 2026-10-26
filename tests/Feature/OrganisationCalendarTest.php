<?php

use App\Actions\CRM\Appointment\StoreAppointment;
use App\Actions\CRM\AppointmentBlock\StoreAppointmentBlock;
use App\Actions\CRM\AppointmentType\StoreAppointmentType;
use App\Actions\HumanResources\CalendarEvent\StoreCalendarEvent;
use App\Actions\SysAdmin\Group\Seeders\SeedJobPositionCategories;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions;
use App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationPermissions;
use App\Actions\SysAdmin\User\UpdateUserOrganisationPseudoJobPositions;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\HumanResources\CalendarEvent;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user, $this->shop) = createShop();

    setPermissionsTeamId($this->organisation->group_id);
    SeedOrganisationPermissions::run($this->organisation);
    SeedJobPositionCategories::run($this->organisation->group);
    SeedJobPositions::run($this->organisation);

    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);

    $this->timezone = $this->organisation->timezone?->name ?? 'UTC';
    $this->day      = fn (int $days) => now($this->timezone)->addDays($days)->format('Y-m-d');

    $this->staffWith = function (array $positions): User {
        $user = StoreGuest::make()->action(
            $this->organisation->group,
            array_merge(Guest::factory()->definition(), ['positions' => []])
        )->getUser();

        UpdateUserOrganisationPseudoJobPositions::make()->action(
            $user,
            $this->organisation,
            ['permissions' => array_fill_keys($positions, [])]
        );

        return $user->refresh();
    };

    $this->editor = ($this->staffWith)(['cal']);

    $this->storeRoute = route('grp.models.org.calendar_event.store', ['organisation' => $this->organisation->id]);
});

afterEach(function () {
    CalendarEvent::where('organisation_id', $this->organisation->id)->forceDelete();
});

test('the calendar editor position gives the calendar edit permission of its organisation', function () {
    $reader = ($this->staffWith)(['rep']);

    expect($this->editor->authTo("calendar.{$this->organisation->id}.edit"))->toBeTrue()
        ->and(StoreCalendarEvent::canEditCalendar($this->editor, $this->organisation))->toBeTrue()
        ->and(StoreCalendarEvent::canEditCalendar($reader, $this->organisation))->toBeFalse();
});

test('an editor adds an event with its participants and everyone in the organisation sees it in the month', function () {
    $colleague = ($this->staffWith)(['rep']);
    actingAs($this->editor);

    $this->post($this->storeRoute, [
        'title'        => 'Supplier visit',
        'description'  => 'Review the autumn range',
        'location'     => 'Showroom',
        'starts_at'    => ($this->day)(2).' 10:00',
        'ends_at'      => ($this->day)(2).' 11:30',
        'participants' => [$this->editor->id, $colleague->id],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $event = CalendarEvent::where('organisation_id', $this->organisation->id)->latest('id')->first();

    expect($event->title)->toBe('Supplier visit')
        ->and($event->created_by_user_id)->toBe($this->editor->id)
        ->and($event->starts_at->copy()->setTimezone($this->timezone)->format('Y-m-d H:i'))->toBe(($this->day)(2).' 10:00')
        ->and($event->ends_at->copy()->setTimezone($this->timezone)->format('Y-m-d H:i'))->toBe(($this->day)(2).' 11:30')
        ->and($event->participants->pluck('id')->sort()->values()->all())->toBe(collect([$this->editor->id, $colleague->id])->sort()->values()->all());

    actingAs($colleague);
    $month = now($this->timezone)->addDays(2)->format('Y-m');

    $this->get(route('grp.org.calendar.index', ['organisation' => $this->organisation->slug, 'month' => $month]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Calendar/OrganisationCalendar')
            ->where('can_edit', false)
            ->where('calendar.month', $month)
            ->where('calendar.weeks', function ($weeks) use ($event) {
                $day = collect($weeks)->flatten(1)->firstWhere('date', ($this->day)(2));

                return collect($day['events'])->contains(fn ($item) => $item['id'] === $event->id && $item['time'] === '10:00' && $item['title'] === 'Supplier visit');
            })
            ->etc());
});

test('staff without the calendar permission can not add, change or delete events', function () {
    $reader = ($this->staffWith)(['rep']);
    $event  = StoreCalendarEvent::make()->action($this->organisation, [
        'title'     => 'Stocktake',
        'starts_at' => ($this->day)(3).' 09:00',
        'ends_at'   => ($this->day)(3).' 17:00',
    ]);
    actingAs($reader);

    $this->post($this->storeRoute, [
        'title'     => 'Not allowed',
        'starts_at' => ($this->day)(3).' 09:00',
        'ends_at'   => ($this->day)(3).' 10:00',
    ])->assertForbidden();

    $this->patch(route('grp.models.calendar_event.update', ['calendarEvent' => $event->id]), [
        'title'     => 'Renamed',
        'starts_at' => ($this->day)(3).' 09:00',
        'ends_at'   => ($this->day)(3).' 10:00',
    ])->assertForbidden();

    $this->delete(route('grp.models.calendar_event.delete', ['calendarEvent' => $event->id]))->assertForbidden();

    expect($event->refresh()->title)->toBe('Stocktake')
        ->and($event->trashed())->toBeFalse();
});

test('an event clashing with a participant is refused with what they are doing, and saved when the editor allows the overlap', function () {
    actingAs($this->editor);
    StoreCalendarEvent::make()->action($this->organisation, [
        'title'        => 'Team meeting',
        'starts_at'    => ($this->day)(4).' 10:00',
        'ends_at'      => ($this->day)(4).' 11:00',
        'participants' => [$this->editor->id],
    ]);

    $clashing = [
        'title'        => 'Trade fair call',
        'starts_at'    => ($this->day)(4).' 10:30',
        'ends_at'      => ($this->day)(4).' 11:30',
        'participants' => [$this->editor->id],
    ];

    $this->post($this->storeRoute, $clashing)->assertSessionHasErrors('overlap');
    expect(session('errors')->first('overlap'))->toContain('Team meeting');

    $this->post($this->storeRoute, array_merge($clashing, ['allow_overlap' => true]))->assertSessionHasNoErrors();

    $this->post($this->storeRoute, array_merge($clashing, ['starts_at' => ($this->day)(4).' 11:00', 'ends_at' => ($this->day)(4).' 12:00', 'title' => 'Right after']))
        ->assertSessionHasErrors('overlap');

    $this->post($this->storeRoute, array_merge($clashing, ['starts_at' => ($this->day)(4).' 12:00', 'ends_at' => ($this->day)(4).' 13:00', 'title' => 'Free time']))
        ->assertSessionHasNoErrors();

    expect(CalendarEvent::where('organisation_id', $this->organisation->id)->pluck('title')->sort()->values()->all())
        ->toBe(['Free time', 'Team meeting', 'Trade fair call']);
});

test('an event must end after it starts, and an all-day event takes its whole days', function () {
    actingAs($this->editor);

    $this->post($this->storeRoute, [
        'title'     => 'Backwards',
        'starts_at' => ($this->day)(5).' 15:00',
        'ends_at'   => ($this->day)(5).' 14:00',
    ])->assertSessionHasErrors('ends_at');

    $this->post($this->storeRoute, [
        'title'      => 'Bank holiday',
        'is_all_day' => true,
        'starts_at'  => ($this->day)(5).' 09:00',
        'ends_at'    => ($this->day)(6).' 09:00',
    ])->assertSessionHasNoErrors();

    $event = CalendarEvent::where('organisation_id', $this->organisation->id)->where('title', 'Bank holiday')->first();

    expect($event->is_all_day)->toBeTrue()
        ->and($event->starts_at->copy()->setTimezone($this->timezone)->format('Y-m-d H:i'))->toBe(($this->day)(5).' 00:00')
        ->and($event->ends_at->copy()->setTimezone($this->timezone)->format('Y-m-d H:i'))->toBe(($this->day)(7).' 00:00');
});

test('an editor changes and deletes an event', function () {
    actingAs($this->editor);
    $event = StoreCalendarEvent::make()->action($this->organisation, [
        'title'        => 'Draft',
        'starts_at'    => ($this->day)(6).' 09:00',
        'ends_at'      => ($this->day)(6).' 10:00',
        'participants' => [$this->editor->id],
    ]);

    $this->patch(route('grp.models.calendar_event.update', ['calendarEvent' => $event->id]), [
        'title'        => 'Packaging review',
        'location'     => 'Warehouse',
        'starts_at'    => ($this->day)(6).' 09:30',
        'ends_at'      => ($this->day)(6).' 10:30',
        'participants' => [],
    ])->assertSessionHasNoErrors();

    $event->refresh();
    expect($event->title)->toBe('Packaging review')
        ->and($event->location)->toBe('Warehouse')
        ->and($event->starts_at->copy()->setTimezone($this->timezone)->format('H:i'))->toBe('09:30')
        ->and($event->participants)->toHaveCount(0);

    $this->delete(route('grp.models.calendar_event.delete', ['calendarEvent' => $event->id]))->assertRedirect();

    expect(CalendarEvent::find($event->id))->toBeNull();
});

test('the calendar shows the open appointments of every shop of the organisation, naming the customer only to staff who see that shop\'s customers, and they clash with their staff', function () {
    Queue::fake();
    actingAs($this->editor);

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'              => 'Org calendar '.Str::random(6),
        'meeting_mode'      => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes'  => 60,
        'capacity_per_slot' => 5,
    ]);

    $appointmentType->attendees()->syncWithoutDetaching([$this->editor->id]);

    $appointment = StoreAppointment::make()->action($this->shop, [
        'appointment_type_id' => $appointmentType->id,
        'starts_at'           => now($this->timezone)->addDays(8)->format('Y-m-d').' 14:00',
        'contact_name'        => 'Calendar Visitor',
        'user_id'             => $this->editor->id,
    ]);
    $visitStart = $appointment->starts_at->copy()->setTimezone($this->timezone);

    $month      = $visitStart->format('Y-m');
    $contactFor = fn (User $viewer) => collect(
        collect(
            actingAs($viewer)->get(route('grp.org.calendar.index', ['organisation' => $this->organisation->slug, 'month' => $month]))
                ->assertOk()
                ->viewData('page')['props']['calendar']['weeks']
        )->flatten(1)->firstWhere('date', $visitStart->toDateString())['appointments']
    )->firstWhere('id', $appointment->id);

    expect($this->editor->authTo("crm.{$this->shop->id}.view"))->toBeFalse()
        ->and($contactFor($this->editor))->toMatchArray(['contact_name' => null, 'url' => null, 'shop_code' => $this->shop->code])
        ->and($contactFor($this->user)['contact_name'])->toBe('Calendar Visitor');

    $clash = [
        'title'        => 'Clashes with the visit',
        'starts_at'    => $visitStart->copy()->addMinutes(30)->format('Y-m-d H:i'),
        'ends_at'      => $visitStart->copy()->addMinutes(90)->format('Y-m-d H:i'),
        'participants' => [$this->editor->id],
    ];

    actingAs($this->editor)->post($this->storeRoute, $clash)->assertSessionHasErrors('overlap');

    expect(session('errors')->first('overlap'))->toContain('appointment in '.$this->shop->code)
        ->not->toContain('Calendar Visitor');

    actingAs($this->user)->post($this->storeRoute, $clash)->assertSessionHasErrors('overlap');

    expect(session('errors')->first('overlap'))->toContain('Calendar Visitor');
});

test('the calendar gives the time of the organisation for new events and shows the times its shops blocked for bookings', function () {
    Queue::fake();
    $this->freezeTime();
    actingAs($this->editor);

    $blockDay = now($this->timezone)->addDays(9)->format('Y-m-d');
    $reason   = 'Stocktake '.Str::random(6);

    $block = StoreAppointmentBlock::make()->action($this->shop, [
        'is_all_day' => false,
        'date_from'  => $blockDay,
        'time_from'  => '10:00',
        'time_to'    => '12:00',
        'reason'     => $reason,
    ]);
    $blockStart = $block->starts_at->copy()->setTimezone($this->timezone);

    $this->get(route('grp.org.calendar.index', ['organisation' => $this->organisation->slug, 'month' => $blockStart->format('Y-m')]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.now', now($this->timezone)->format('Y-m-d H:i'))
            ->where('calendar.weeks', function ($weeks) use ($block, $blockStart, $reason) {
                $day = collect($weeks)->flatten(1)->firstWhere('date', $blockStart->toDateString());

                return collect($day['blocks'])->contains(fn ($item) => $item['id'] === $block->id
                    && $item['reason'] === $reason
                    && $item['shop_code'] === $this->shop->code
                    && $item['time'] === $blockStart->format('H:i')
                    && $item['end_time'] === $block->ends_at->copy()->setTimezone($this->timezone)->format('H:i'));
            })
            ->etc());

    $block->delete();
});

test('one calendar item opens on the offers calendar, with the offers and appointments calendars in its top bar', function () {
    $navigation = GetOrganisationNavigation::run($this->user, $this->organisation);

    expect($navigation)->not->toHaveKey('calendar_offers')
        ->and($navigation['calendar']['route']['name'])->toBe('grp.org.offer.calendar')
        ->and($navigation['calendar']['roots'])->toBe(['grp.org.offer.calendar', 'grp.org.calendar.'])
        ->and(collect($navigation['calendar']['topMenu']['subSections'])->pluck('route.name')->all())
        ->toBe(['grp.org.offer.calendar', 'grp.org.calendar.index'])
        ->and(collect($navigation['shops_index']['topMenu']['subSections'])->pluck('label'))->not->toContain(__('Calendar'));
});

test('staff who cannot see the shops only get the appointments calendar', function () {
    $navigation = GetOrganisationNavigation::run(($this->staffWith)([]), $this->organisation);

    expect($navigation['calendar']['route']['name'])->toBe('grp.org.calendar.index')
        ->and(collect($navigation['calendar']['topMenu']['subSections'])->pluck('route.name')->all())
        ->toBe(['grp.org.calendar.index']);
});
