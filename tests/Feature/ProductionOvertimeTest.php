<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Production\ManufactureOvertime\RecordManufactureToilTaken;
use App\Actions\Production\ManufactureOvertime\RemindManufactureOvertimeDecisions;
use App\Actions\Production\ManufactureOvertime\SetManufactureOvertime;
use App\Actions\Production\ManufactureOvertime\SetManufactureToilRatio;
use App\Actions\Production\ManufactureTaskSession\GetManufacturePayroll;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureArtisanDay;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufactureTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use App\Notifications\ManufactureOvertimeReminderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../Support/ProductionHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
    setPermissionsTeamId($this->group->id);
    $this->guest = createAdminGuest($this->group);

    $this->production = Production::orderBy('id')->first()
        ?? \App\Actions\Production\Production\StoreProduction::make()->action($this->organisation, ['code' => 'CODE', 'name' => 'NAME']);
    $artefact = Artefact::where('production_id', $this->production->id)->orderBy('id')->first()
        ?? \App\Actions\Production\Artefact\StoreArtefact::make()->action($this->production, ['code' => 'CODE', 'name' => 'NAME', 'state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value]);
    $task = ManufactureTask::where('production_id', $this->production->id)->where('code', '!=', 'PROD')->orderBy('id')->first()
        ?? \App\Actions\Production\ManufactureTask\StoreManufactureTask::make()->action($this->production, [
            'code'                            => 'CODE',
            'name'                            => 'name',
            'task_materials_cost'             => 10,
            'task_energy_cost'                => 10,
            'task_other_cost'                 => 10,
            'task_work_cost'                  => 10,
            'task_lower_target'               => 10,
            'task_upper_target'               => 10,
            'operative_reward_terms'          => \App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT->value,
            'operative_reward_allowance_type' => \App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY->value,
            'operative_reward_amount'         => 10,
        ]);
    $artefact->manufactureTasks()->sync([$task->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $line     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 1000]);
    ConfirmJobOrder::make()->action($jobOrder);
    $this->overtimeTask = $line->tasks()->first();

    foreach (['0' => 12.71, '1' => 13.00, '3' => 15.00, 'GURU' => 1.00] as $code => $rate) {
        ManufacturePayBand::query()->firstOrCreate(
            ['production_id' => $this->production->id, 'code' => (string) $code],
            ['group_id' => $this->production->group_id, 'organisation_id' => $this->production->organisation_id, 'name' => 'Band '.$code, 'hourly_rate' => $rate, 'effective_from' => now()->subYears(2)]
        );
    }

    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);
    \Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->guest->getUser());
});

function overtimeArtisan(\App\Models\SysAdmin\Organisation $organisation): Employee
{
    $employee = dayPlanOperator($organisation);
    $schedule = \App\Models\HumanResources\WorkSchedule::create([
        'schedulable_type' => 'Employee',
        'schedulable_id'   => $employee->id,
        'name'             => 'Weekdays',
        'type'             => 'default',
        'is_active'        => true,
    ]);
    foreach (range(1, 7) as $day) {
        $schedule->days()->create(['day_of_week' => $day, 'is_working_day' => $day <= 5, 'start_time' => $day <= 5 ? '08:00:00' : null, 'end_time' => $day <= 5 ? '16:30:00' : null]);
    }

    return $employee;
}

function overtimeSession(JobOrderItemTask $task, User $user, Employee $employee, Carbon $startedAt, float $hours, string $band): ManufactureTaskSession
{
    return ManufactureTaskSession::create([
        'group_id'               => $task->group_id,
        'organisation_id'        => $task->organisation_id,
        'production_id'          => $task->production_id,
        'job_order_item_task_id' => $task->id,
        'manufacture_task_id'    => $task->manufacture_task_id,
        'user_id'                => $user->id,
        'employee_id'            => $employee->id,
        'state'                  => ManufactureTaskSessionStateEnum::CLOSED,
        'started_at'             => $startedAt,
        'ended_at'               => $startedAt->copy()->addMinutes((int) round($hours * 60)),
        'hours'                  => $hours,
        'band_code'              => $band,
        'quantity_made'          => 10,
        'standard_rate'          => 152,
        'activity_type'          => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
    ]);
}

test('weekend overtime is paid at 1.5 x band 0, TOIL is owed not paid, refused and undecided overtime stay paid and flagged', function () {
    $monday   = now()->addYears(7)->addWeeks(rand(0, 40))->startOfWeek();
    $timezone = $this->organisation->timezone?->name ?? config('app.timezone');
    $at       = fn (int $weekday, string $time) => Carbon::parse($monday->copy()->addDays($weekday)->toDateString().' '.$time, $timezone)->utc();
    $date     = fn (int $weekday) => $monday->copy()->addDays($weekday)->toDateString();
    $employee = overtimeArtisan($this->organisation);
    $employee->update(['guru_since' => $monday->toDateString()]);
    $user     = stepTestSecondOperator($this->group);
    $manager  = $this->guest->getUser();

    expect((float) ManufacturePayBand::where('production_id', $this->production->id)->where('code', '1')->value('hourly_rate'))->toBe(13.0);

    // Friday: the manager plans Saturday 08:00-12:00 and approves it, paid
    $this->travelTo($at(4, '10:00'));
    post(route('grp.org.productions.show.artisans.daily_hours.overtime', [$this->organisation->slug, $this->production->slug]), [
        'days'          => [['employee_id' => $employee->id, 'date' => $date(5)]],
        'decision'      => 'approved',
        'compensation'  => 'paid',
        'planned_start' => '08:00',
        'planned_end'   => '13:00',
    ])->assertSessionHasNoErrors();
    $saturdayOvertime = \App\Models\Production\ManufactureOvertime::where('employee_id', $employee->id)->where('date', $date(5))->sole();
    expect($saturdayOvertime->decided_clocked_minutes)->toBeNull()
        ->and($saturdayOvertime->planned_start->equalTo($at(5, '08:00')))->toBeTrue();

    $this->travelTo($at(7, '10:00'));
    SetManufactureToilRatio::make()->action($this->production, ['toil_ratio' => 1.5]);

    // shift 08:00-16:30 less a 30 minute break = 8 h a weekday; nothing on Saturday or Sunday
    $clocked = [0 => 420, 1 => 540, 2 => 600, 3 => 540, 4 => 555, 5 => 300, 6 => 240];
    foreach ($clocked as $weekday => $minutes) {
        $timesheet = clockedTimesheet($employee, $date($weekday), $minutes);
        if ($weekday == 5) {
            \App\Models\HumanResources\TimeTracker::create(['timesheet_id' => $timesheet->id, 'subject_type' => 'Employee', 'subject_id' => $employee->id, 'status' => 'closed', 'starts_at' => $at(5, '07:00'), 'ends_at' => $at(5, '12:00'), 'duration' => 300 * 60]);
        }
        if ($weekday == 5) {
            overtimeSession($this->overtimeTask, $user, $employee, $at(5, '07:00'), 3, '1');
            overtimeSession($this->overtimeTask, $user, $employee, $at(5, '10:00'), 2, '3');
        } else {
            overtimeSession($this->overtimeTask, $user, $employee, $at($weekday, '07:00'), $minutes / 60, '1');
        }
    }

    $setOvertime = fn (int $weekday, array $data) => SetManufactureOvertime::make()->action($this->production, $employee, $date($weekday), $manager, $data);
    $setOvertime(1, ['decision' => 'approved', 'compensation' => 'paid']);
    $setOvertime(2, ['decision' => 'approved', 'compensation' => 'toil']);
    $setOvertime(6, ['decision' => 'approved', 'compensation' => 'toil']);
    expect(fn () => $setOvertime(3, ['decision' => 'not_approved']))->toThrow(ValidationException::class);
    $setOvertime(3, ['decision' => 'not_approved', 'reason' => 'Not asked to stay']);

    // Saturday was clocked from 07:00, before the planned 08:00 less the 30 minute tolerance
    get(route('grp.org.productions.show.artisans.daily_hours', [$this->organisation->slug, $this->production->slug, 'from' => $date(0), 'to' => $date(6)]))->assertOk();
    $days = ManufactureArtisanDay::where('employee_id', $employee->id)->get()->keyBy(fn ($day) => $day->date->dayOfWeekIso);
    expect($days[6]->status->value)->toBe('over')
        ->and($days[6]->isOutsidePlanned())->toBeTrue()
        ->and($days[6]->is_warning_open)->toBeTrue()
        ->and($days[7]->expected_minutes)->toBe(0)
        ->and($days[7]->is_warning_open)->toBeFalse()
        ->and($days[3]->is_warning_open)->toBeFalse()
        ->and($days[4]->is_warning_open)->toBeFalse()
        ->and($days[5]->isOvertimeAwaitingDecision())->toBeTrue();

    Notification::fake();
    expect(RemindManufactureOvertimeDecisions::make()->remind($this->production))->toBeGreaterThan(0);
    Notification::assertSentTo($manager, ManufactureOvertimeReminderNotification::class, fn (ManufactureOvertimeReminderNotification $notification) => count(array_filter($notification->days, fn (string $line) => str_contains($line, $employee->contact_name))) === 2
        && collect($notification->days)->contains(fn (string $line) => str_contains($line, $employee->contact_name) && str_contains($line, '+1:15'))
        && collect($notification->days)->contains(fn (string $line) => str_contains($line, $employee->contact_name) && str_contains($line, '+5:00')));

    $setOvertime(5, ['decision' => 'approved', 'compensation' => 'paid', 'planned_start' => '08:00', 'planned_end' => '13:00']);
    expect(ManufactureArtisanDay::find($days[6]->id)->is_warning_open)->toBeFalse();

    $payroll = collect(GetManufacturePayroll::run($this->production, $monday, $monday->copy()->addDays(6)))->firstWhere('user_id', $user->id);

    // weekdays paid as normal hours: Mon 7 + Tue 9 + Wed 8 (10 less 2 TOIL) + Thu 9 + Fri 9.25 = 42.25 h of band 1 with Guru
    // 42.25 x 12.71 = 536.9975; band 1 above band 0: 42.25 x 0.29 = 12.2525; Guru 42.25 x 1.00
    // Saturday 5 h at round(1.5 x 12.71 = 19.065) = 19.07 -> 95.35, no band, no Guru; split 3 h band 1, 2 h band 3
    // TOIL: Wednesday 2 h + Sunday 4 h = 6 h, owed x 1.5 = 9 h
    expect(collect($payroll['lines'])->where('pay', 'weekend_overtime')->pluck('band_rate')->unique()->values()->all())->toBe([19.07])
        ->and(collect($payroll['lines'])->where('pay', 'weekend_overtime')->sum('guru_rate'))->toEqual(0)
        ->and($payroll['working_hours'])->toBe(42.25)
        ->and($payroll['working_amount'])->toBe(537.0)
        ->and($payroll['band_1_hours'])->toBe(42.25)
        ->and($payroll['band_1_amount'])->toBe(12.25)
        ->and($payroll['band_3_hours'])->toBe(0.0)
        ->and($payroll['guru_hours'])->toBe(42.25)
        ->and($payroll['guru_amount'])->toBe(42.25)
        ->and($payroll['weekend_overtime_hours'])->toBe(5.0)
        ->and($payroll['weekend_overtime_amount'])->toBe(95.35)
        ->and($payroll['weekend_overtime_bands'])->toEqual(['1' => 3.0, '3' => 2.0])
        ->and($payroll['toil_hours'])->toBe(6.0)
        ->and($payroll['toil_owed_hours'])->toBe(9.0)
        ->and($payroll['total'])->toBe(686.85);

    RecordManufactureToilTaken::make()->action($this->production, $employee, $manager, ['date' => $date(4), 'hours' => 2]);
    expect(fn () => RecordManufactureToilTaken::make()->action($this->production, $employee, $manager, ['date' => $date(4), 'hours' => 8]))->toThrow(ValidationException::class);

    $period = ['from' => $date(0), 'to' => $date(6)];
    get(route('grp.org.productions.show.artisans.payroll', [$this->organisation->slug, $this->production->slug] + $period))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Production/ManufacturePayroll')
            ->where('data.data', function ($rows) use ($user) {
                $row = collect($rows)->firstWhere('user_id', $user->id);

                return $row['total'] == 686.85
                    && $row['not_authorised_overtime']['minutes'] === 60
                    && $row['awaiting_overtime']['minutes'] === 75
                    && $row['toil_taken_hours'] == 2.0
                    && $row['toil_balance_hours'] == 7.0;
            }));

    $response = get(route('grp.org.productions.show.artisans.payroll.export', [$this->organisation->slug, $this->production->slug] + $period))->assertOk();
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();
    expect($csv)->toContain('"Weekend overtime hours","Weekend overtime £","Weekend overtime rate £ (1.5 x Band 0)","Weekend overtime band split (information)"')
        ->and($csv)->toContain('"5.00","95.35","19.07","Band 1 3.00, Band 3 2.00"')
        ->and($csv)->toContain('"686.85","","6.00","9.00","2.00","7.00","1.00","1.25"');

    get(route('grp.org.productions.show.artisans.daily_hours', [$this->organisation->slug, $this->production->slug] + $period))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Production/ArtisanDays')
            ->where('toil.ratio', 1.5)
            ->where('data.data', fn ($rows) => collect($rows)->where('user_id', $user->id)->firstWhere('date', $date(5))['overtime']['planned_start'] === '08:00'
                && collect($rows)->where('user_id', $user->id)->firstWhere('date', $date(5))['clocked_in_at'] === '07:00'));
});

test('weekend overtime approved after the pay week locked is recorded as an adjustment with its pay difference', function () {
    $monday   = now()->addYears(8)->addWeeks(rand(0, 40))->startOfWeek();
    $timezone = $this->organisation->timezone?->name ?? config('app.timezone');
    $at       = fn (int $weekday, string $time) => Carbon::parse($monday->copy()->addDays($weekday)->toDateString().' '.$time, $timezone)->utc();
    $saturday = $monday->copy()->addDays(5)->toDateString();
    $employee = overtimeArtisan($this->organisation);
    $employee->update(['guru_since' => $monday->toDateString()]);
    $user = stepTestSecondOperator($this->group);

    $this->travelTo($at(8, '10:00'));
    clockedTimesheet($employee, $saturday, 300);
    overtimeSession($this->overtimeTask, $user, $employee, $at(5, '07:00'), 3, '1');
    overtimeSession($this->overtimeTask, $user, $employee, $at(5, '10:00'), 2, '3');

    $set = fn (array $data) => SetManufactureOvertime::make()->action($this->production, $employee, $saturday, $this->guest->getUser(), $data);
    expect(fn () => $set(['decision' => 'approved', 'compensation' => 'paid']))->toThrow(ValidationException::class);
    $set(['decision' => 'approved', 'compensation' => 'paid', 'lock_reason' => 'Approved late']);

    // before: 5 h x 12.71 = 63.55, band 1 3 h x 0.29 = 0.87, band 3 2 h x 2.29 = 4.58, Guru 5 h x 1.00 = 5.00 -> 74.00
    // after: 5 h x 19.07 = 95.35 -> +21.35, and the same 5 hours
    $adjustment = \App\Models\Production\ManufacturePayrollAdjustment::where('user_id', $user->id)->sole();
    expect($adjustment->change)->toBe('overtime')
        ->and((float) $adjustment->amount_delta)->toBe(21.35)
        ->and((float) $adjustment->hours_delta)->toBe(0.0)
        ->and($adjustment->reason)->toBe('Approved late');
});

test('weekend overtime approved ahead pays 1.5 x band 0 only for the planned hours, and a later schedule change does not move it', function () {
    $monday   = now()->addYears(9)->addWeeks(rand(0, 40))->startOfWeek();
    $timezone = $this->organisation->timezone?->name ?? config('app.timezone');
    $at       = fn (int $weekday, string $time) => Carbon::parse($monday->copy()->addDays($weekday)->toDateString().' '.$time, $timezone)->utc();
    $saturday = $monday->copy()->addDays(5)->toDateString();
    $employee = overtimeArtisan($this->organisation);
    $user     = stepTestSecondOperator($this->group);

    $this->travelTo($at(4, '10:00'));
    SetManufactureOvertime::make()->action($this->production, $employee, $saturday, $this->guest->getUser(), ['decision' => 'approved', 'compensation' => 'paid', 'planned_start' => '08:00', 'planned_end' => '12:00']);

    $this->travelTo($at(7, '10:00'));
    clockedTimesheet($employee, $saturday, 600);
    overtimeSession($this->overtimeTask, $user, $employee, $at(5, '08:00'), 10, '1');

    // 4 planned hours at 19.07 = 76.28; the other 6 h as normal band 1 hours: 6 x 12.71 = 76.26 + 6 x 0.29 = 1.74
    $pay = fn () => collect(GetManufacturePayroll::run($this->production, $monday, $monday->copy()->addDays(6)))->firstWhere('user_id', $user->id);
    expect($pay()['weekend_overtime_hours'])->toBe(4.0)
        ->and($pay()['weekend_overtime_amount'])->toBe(76.28)
        ->and($pay()['working_hours'])->toBe(6.0)
        ->and($pay()['total'])->toBe(154.28);

    get(route('grp.org.productions.show.artisans.daily_hours', [$this->organisation->slug, $this->production->slug, 'from' => $saturday, 'to' => $saturday]))->assertOk();
    expect(ManufactureArtisanDay::where('employee_id', $employee->id)->where('date', $saturday)->sole()->isOvertimeAwaitingDecision())->toBeTrue();

    $employee->getEffectiveWorkSchedule()->days()->where('day_of_week', 6)->update(['is_working_day' => true, 'start_time' => '08:00:00', 'end_time' => '16:30:00']);
    expect($pay()['total'])->toBe(154.28);
});
