<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 02:55:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\SysAdmin\User\StoreUserFromEmployee;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Actions\HumanResources\Employee\DeleteEmployee;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\HumanResources\Workplace\DeleteWorkplace;
use App\Actions\HumanResources\Workplace\StoreWorkplace;
use App\Actions\HumanResources\Workplace\UpdateWorkplace;
use App\Actions\HumanResources\JobPosition\DeleteJobPosition;
use App\Actions\HumanResources\JobPosition\StoreJobPosition;
use App\Actions\HumanResources\JobPosition\UpdateJobPosition;
use App\Actions\HumanResources\Holiday\DeleteHoliday;
use App\Actions\HumanResources\Holiday\StoreHoliday;
use App\Actions\HumanResources\Holiday\UpdateHoliday;
use App\Actions\HumanResources\Leave\DeleteLeaveType;
use App\Actions\HumanResources\Leave\StoreLeaveType;
use App\Actions\HumanResources\HolidayYear\StoreHolidayYear;
use App\Actions\HumanResources\HolidayYear\UpdateHolidayYear;
use App\Actions\HumanResources\EmployeeContract\StoreEmployeeContract;
use App\Actions\HumanResources\EmployeeContract\UpdateEmployeeContract;
use App\Actions\HumanResources\EmployeeContract\DeleteEmployeeContract;
use App\Actions\HumanResources\Clocking\RepairMisattributedClockings;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\HumanResources\ClockingMachine\StoreClockingMachine;
use App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin;
use App\Actions\HumanResources\ClockingMachine\StoreClockingMachineQRCode;
use App\Actions\HumanResources\ClockingMachine\ValidateClockingMachineQrCode;
use App\Actions\HumanResources\Clocking\UpdateClocking;
use App\Actions\HumanResources\Clocking\UpdateClockingNotes;
use App\Actions\HumanResources\Clocking\DeleteClocking;
use App\Actions\HumanResources\Overtime\StoreOvertimeRequest;
use App\Actions\HumanResources\Overtime\StoreOvertimeType;
use App\Actions\HumanResources\Overtime\ApproveOvertimeRequest;
use App\Actions\HumanResources\Overtime\RejectOvertimeRequest;
use App\Actions\HumanResources\Overtime\DeleteOvertimeRequest;
use App\Actions\HumanResources\Overtime\UpdateOvertimeRequest;
use App\Actions\HumanResources\Overtime\UpdateOvertimeType;
use App\Actions\HumanResources\Overtime\DeleteOvertimeType;
use App\Actions\HumanResources\AttendanceAdjustment\StoreAttendanceAdjustment;
use App\Actions\HumanResources\AttendanceAdjustment\ApproveAttendanceAdjustment;
use App\Actions\HumanResources\AttendanceAdjustment\RejectAttendanceAdjustment;
use App\Actions\HumanResources\Employee\GeneratePinEmployee;
use App\Actions\HumanResources\Employee\ValidatePinEmployee;
use App\Actions\HumanResources\Employee\AdjustEmployeeLeaveBalance;
use App\Actions\HumanResources\Employee\SetEmployeePin;
use App\Actions\HumanResources\Holiday\GenerateNextYearHolidays;
use App\Actions\HumanResources\HolidayYear\ActivateHolidayYear;
use App\Actions\HumanResources\JobPosition\StoreJobPositionScopeGroup;
use App\Actions\HumanResources\JobPosition\UpdateJobPositionScopeGroup;
use App\Actions\HumanResources\JobPosition\SyncEmployeeJobPositions;
use App\Actions\HumanResources\Concurrency\StoreLeaveConcurrencyRule;
use App\Actions\HumanResources\Concurrency\UpdateLeaveConcurrencyRule;
use App\Actions\HumanResources\Concurrency\DeleteLeaveConcurrencyRule;
use App\Actions\HumanResources\RestrictedPeriods\StoreRestrictedPeriod;
use App\Actions\HumanResources\RestrictedPeriods\UpdateRestrictedPeriod;
use App\Actions\HumanResources\RestrictedPeriods\DeleteRestrictedPeriod;
use App\Actions\HumanResources\WorkSchedule\StoreWorkSchedule;
use App\Actions\HumanResources\WorkSchedule\UpdateWorkSchedule;
use App\Actions\HumanResources\WorkSchedule\DeleteWorkSchedule;
use App\Actions\HumanResources\TimeTracker\StoreTimeTracker;
use App\Actions\HumanResources\TimeTracker\DeleteTimeTracker;
use App\Actions\HumanResources\TimeTracker\ClockInTimeTracker;
use App\Actions\HumanResources\TimeTracker\AddClockingToTimeTracker;
use App\Actions\HumanResources\TimeTracker\CloseTimeTracker;
use App\Actions\HumanResources\TimeTracker\RepairNegativeTimeTrackers;
use App\Actions\HumanResources\Timesheet\StoreTimesheet;
use App\Actions\HumanResources\Timesheet\DeleteTimesheet;
use App\Actions\HumanResources\Leave\StoreLeave;
use App\Actions\HumanResources\Leave\UpdateLeave;
use App\Actions\HumanResources\Leave\DeleteLeave;
use App\Actions\HumanResources\Leave\ApproveLeave;
use App\Actions\HumanResources\Leave\RejectLeave;
use App\Actions\HumanResources\Leave\StoreLeaveApprover;
use App\Actions\HumanResources\Leave\DeleteLeaveApprover;
use App\Actions\HumanResources\Leave\UpdateLeaveType;
use App\Actions\HumanResources\Leave\GenerateEmployeeLeaveBalance;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Workplace;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\Holiday;
use App\Models\HumanResources\LeaveType;
use App\Models\HumanResources\HolidayYear;
use App\Models\HumanResources\EmployeeContract;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineQRCode;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\HumanResources\OvertimeType;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprover;
use App\Models\HumanResources\LeaveConcurrencyRule;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\HumanResources\WorkSchedule;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\SysAdmin\User;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use Illuminate\Support\Facades\Storage;
use App\Actions\Helpers\Avatars\GetDiceBearAvatar;

use function Pest\Laravel\actingAs;

class CollidingStoreClockingMachineQRCode extends StoreClockingMachineQRCode
{
    protected static array $hashes = [];

    public static function useHashes(string ...$hashes): void
    {
        self::$hashes = $hashes;
    }

    protected static function generateHash(): string
    {
        return array_shift(self::$hashes);
    }
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    GetDiceBearAvatar::mock()
        ->shouldReceive('handle')
        ->andReturn(Storage::disk('art')->get('icons/shapes.svg'));

    $this->organisation = createOrganisation();
    $this->group = $this->organisation->group;
});

test('can store employee', function () {
    $modelData = Employee::factory()->make([
        'organisation_id' => $this->organisation->id,
    ])->toArray();

    $modelData['worker_number'] = 'W' . rand(1000, 9999);
    $modelData['alias'] = 'Alias ' . rand(1000, 9999);
    $modelData['type'] = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
    $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
    $modelData['state'] = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

    $employee = StoreEmployee::make()->action($this->organisation, $modelData);

    expect($employee)->toBeInstanceOf(Employee::class)
        ->and($employee->organisation_id)->toBe($this->organisation->id);

    return $employee;
});

test('can update employee', function (Employee $employee) {
    $updateData = [
        'contact_name' => 'Updated Name',
        'job_title' => 'Updated Title',
        'type' => $employee->type,
        'employment_type' => $employee->employment_type,
        'state' => $employee->state,
    ];

    $updatedEmployee = UpdateEmployee::make()->action($employee, $updateData);

    expect($updatedEmployee->contact_name)->toBe('Updated Name')
        ->and($updatedEmployee->job_title)->toBe('Updated Title');
})->depends('can store employee');

test('can delete employee', function (Employee $employee) {
    $result = DeleteEmployee::make()->action($employee);

    expect($result)->toBeInstanceOf(Employee::class);
    expect(Employee::find($employee->id))->toBeNull();
})->depends('can store employee');

test('can store workplace', function () {
    $modelData = [
        'name' => 'Test Workplace ' . rand(1000, 9999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ];

    $workplace = StoreWorkplace::make()->action($this->organisation, $modelData);

    expect($workplace)->toBeInstanceOf(Workplace::class)
        ->and($workplace->organisation_id)->toBe($this->organisation->id);

    return $workplace;
});

test('can update workplace', function (Workplace $workplace) {
    $updateData = [
        'name' => 'Updated Workplace Name',
        'type' => $workplace->type,
    ];

    $updatedWorkplace = UpdateWorkplace::make()->action($workplace, $updateData);

    expect($updatedWorkplace->name)->toBe('Updated Workplace Name');
})->depends('can store workplace');

test('can delete workplace', function (Workplace $workplace) {
    $result = DeleteWorkplace::make()->action($workplace);

    expect($result)->toBeInstanceOf(Workplace::class);
    expect(Workplace::find($workplace->id))->toBeNull();
})->depends('can store workplace');

test('can store job position', function () {
    $modelData = [
        'code' => 'JP' . rand(1000, 9999),
        'name' => 'Test Job Position',
        'scope' => \App\Enums\HumanResources\JobPosition\JobPositionScopeEnum::ORGANISATION,
    ];

    $jobPosition = StoreJobPosition::make()->action($this->organisation, $modelData);

    expect($jobPosition)->toBeInstanceOf(JobPosition::class)
        ->and($jobPosition->organisation_id)->toBe($this->organisation->id);

    return $jobPosition;
});

test('can update job position', function (JobPosition $jobPosition) {
    $updateData = [
        'name' => 'Updated Job Position Name',
        'scope' => $jobPosition->scope,
    ];

    $updatedJobPosition = UpdateJobPosition::make()->action($jobPosition, $updateData);

    expect($updatedJobPosition->name)->toBe('Updated Job Position Name');
})->depends('can store job position');

test('can delete job position', function (JobPosition $jobPosition) {
    $result = DeleteJobPosition::make()->action($jobPosition);

    expect($result)->toBeInstanceOf(JobPosition::class);
    expect(JobPosition::find($jobPosition->id))->toBeNull();
})->depends('can store job position');

test('can store holiday', function () {
    $modelData = [
        'type' => \App\Enums\HumanResources\Holiday\HolidayTypeEnum::PUBLIC,
        'from' => '2026-12-25',
        'to' => '2026-12-25',
        'label' => 'Christmas',
    ];

    $holiday = StoreHoliday::make()->action($this->organisation, $modelData);

    expect($holiday)->toBeInstanceOf(Holiday::class)
        ->and($holiday->organisation_id)->toBe($this->organisation->id);

    return $holiday;
});

test('can update holiday', function (Holiday $holiday) {
    $updateData = [
        'label' => 'Updated Christmas',
        'type' => $holiday->type,
        'from' => $holiday->from->toDateString(),
        'to' => $holiday->to->toDateString(),
    ];

    $updatedHoliday = UpdateHoliday::make()->action($holiday, $updateData);

    expect($updatedHoliday->label)->toBe('Updated Christmas');
})->depends('can store holiday');

test('can delete holiday', function (Holiday $holiday) {
    $result = DeleteHoliday::make()->action($holiday);

    expect($result)->toBeTrue();
    expect(Holiday::find($holiday->id))->toBeNull();
})->depends('can store holiday');

test('can store leave type', function () {
    $modelData = [
        'code' => 'LT' . rand(1000, 9999),
        'name' => 'Sick Leave',
        'category' => \App\Enums\HumanResources\Leave\LeaveCategoryEnum::MEDICAL,
    ];

    $leaveType = StoreLeaveType::make()->action($this->organisation, $modelData);

    expect($leaveType)->toBeInstanceOf(LeaveType::class)
        ->and($leaveType->organisation_id)->toBe($this->organisation->id);

    return $leaveType;
});

test('can delete leave type', function (LeaveType $leaveType) {
    $result = DeleteLeaveType::make()->action($leaveType);

    expect($result)->toBeTrue();
    expect(LeaveType::find($leaveType->id))->toBeNull();
})->depends('can store leave type');

test('can store holiday year', function () {
    $modelData = [
        'label' => 'Year 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ];

    $holidayYear = StoreHolidayYear::make()->action($this->organisation, $modelData);

    expect($holidayYear)->toBeInstanceOf(HolidayYear::class)
        ->and($holidayYear->organisation_id)->toBe($this->organisation->id);

    return $holidayYear;
});

test('can update holiday year', function (HolidayYear $holidayYear) {
    $updateData = [
        'label' => 'Updated Year 2026',
        'start_date' => $holidayYear->start_date->toDateString(),
        'end_date' => $holidayYear->end_date->toDateString(),
    ];

    $updatedHolidayYear = UpdateHolidayYear::make()->action($holidayYear, $updateData);

    expect($updatedHolidayYear->label)->toBe('Updated Year 2026');
})->depends('can store holiday year');

test('can store employee contract', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $modelData = [
        'start_date' => '2026-01-01',
        'annual_leave_days' => 20,
    ];

    $contract = StoreEmployeeContract::make()->action($employee, $modelData);

    expect($contract)->toBeInstanceOf(EmployeeContract::class)
        ->and($contract->employee_id)->toBe($employee->id);

    return $contract;
});

test('can update employee contract', function (EmployeeContract $contract) {
    $updateData = [
        'start_date' => $contract->start_date->toDateString(),
        'annual_leave_days' => 25,
    ];

    $updatedContract = UpdateEmployeeContract::make()->action($contract, $updateData);

    expect($updatedContract->annual_leave_days)->toBe(25.0);
})->depends('can store employee contract');

test('can delete employee contract', function (EmployeeContract $contract) {
    $result = DeleteEmployeeContract::make()->action($contract);

    expect($result)->toBeInstanceOf(EmployeeContract::class);
    expect(EmployeeContract::find($contract->id))->toBeNull();
})->depends('can store employee contract');

test('can store overtime type', function () {
    $modelData = [
        'code' => 'OT' . rand(1000, 9999),
        'name' => 'Test Overtime Type',
        'category' => \App\Enums\HumanResources\Overtime\OvertimeCategoryEnum::OVERTIME,
        'compensation_type' => \App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum::PAID,
    ];

    $overtimeType = StoreOvertimeType::make()->action($this->organisation, $modelData);

    expect($overtimeType)->toBeInstanceOf(OvertimeType::class)
        ->and($overtimeType->organisation_id)->toBe($this->organisation->id);

    return $overtimeType;
});

test('can store overtime request', function (OvertimeType $overtimeType) {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $modelData = [
        'employee_id' => $employee->id,
        'overtime_type_id' => $overtimeType->id,
        'requested_date' => '2026-06-30',
        'requested_duration_minutes' => 60,
        'status' => \App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum::PENDING,
    ];

    $overtimeRequest = StoreOvertimeRequest::make()->action($this->organisation, $modelData);

    expect($overtimeRequest)->toBeInstanceOf(OvertimeRequest::class)
        ->and($overtimeRequest->employee_id)->toBe($employee->id);
})->depends('can store overtime type');

test('can store clocking', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Clocking Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $modelData = [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ];

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, $modelData, 0, true);

    expect($clocking)->toBeInstanceOf(Clocking::class)
        ->and($clocking->subject_id)->toBe($employee->id);
});

test('clocking is bucketed into the day of the workplace timezone not the organisation one', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $aucklandTimezone = \App\Models\Helpers\Timezone::where('name', 'Pacific/Auckland')->firstOrFail();

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name'        => 'Auckland Workplace',
        'type'        => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
        'timezone_id' => $aucklandTimezone->id,
    ]);

    $clockedAt = \Illuminate\Support\Carbon::parse('2026-08-03 22:00:00', 'UTC');

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type'       => 'in',
        'clocked_at' => $clockedAt,
    ], 0, true);

    $expectedDate = $clockedAt->copy()->setTimezone('Pacific/Auckland')->toDateString();

    expect($expectedDate)->toBe('2026-08-04')
        ->and($clocking->timesheet->date->toDateString())->toBe($expectedDate);
});

test('can store clocking machine QR code', function () {
    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'QR Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'QR Clocking Machine',
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
    ]);

    $qrCode = StoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'label' => 'Main entrance',
    ]);

    expect($qrCode)->toBeInstanceOf(ClockingMachineQRCode::class)
        ->and($qrCode->exists)->toBeTrue()
        ->and($qrCode->clocking_machine_id)->toBe($clockingMachine->id)
        ->and($qrCode->clockingMachine->id)->toBe($clockingMachine->id)
        ->and($qrCode->label)->toBe('Main entrance')
        ->and($qrCode->active)->toBeTrue()
        ->and($qrCode->hash)->toMatch('/^[a-f0-9]{8}$/');

    return $clockingMachine;
});

test('clocking machine QR code gets a generated label when none is provided', function (ClockingMachine $clockingMachine) {
    $qrCode = StoreClockingMachineQRCode::make()->handle($clockingMachine, []);

    expect($qrCode->label)->toMatch('/^[a-z]+-[a-z]+$/');
})->depends('can store clocking machine QR code');

test('clocking machine QR code hash is regenerated on collision', function (ClockingMachine $clockingMachine) {
    $clockingMachine->clockingMachineQrCodes()->create([
        'label' => 'Existing QR code',
        'hash'  => 'aaaaaaaa',
    ]);

    CollidingStoreClockingMachineQRCode::useHashes('aaaaaaaa', 'bbbbbbbb');

    $qrCode = CollidingStoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'label' => 'New QR code',
    ]);

    expect($qrCode->hash)->toBe('bbbbbbbb');
})->depends('can store clocking machine QR code');

test('can store attendance adjustment', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $modelData = [
        'date' => '2026-06-30',
        'reason' => 'Forgot to clock in',
        'requested_start_at' => '08:00',
        'requested_end_at' => '17:00',
    ];

    $adjustment = StoreAttendanceAdjustment::make()->action($employee, $modelData);

    expect($adjustment)->toBeInstanceOf(AttendanceAdjustment::class)
        ->and($adjustment->employee_id)->toBe($employee->id);
});

test('can approve attendance adjustment', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $adjustment = StoreAttendanceAdjustment::make()->action($employee, [
        'date' => '2026-06-30',
        'reason' => 'Forgot to clock in',
        'requested_start_at' => '08:00',
        'requested_end_at' => '17:00',
    ]);

    $approved = ApproveAttendanceAdjustment::make()->handle($adjustment);

    expect($approved->status)->toBe(\App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum::APPROVED)
        ->and($approved->approved_at)->not->toBeNull();
});

test('approving an adjustment recomputes the hours the employee is paid from', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $timesheet = \App\Models\HumanResources\Timesheet::create([
        'group_id'                  => $this->group->id,
        'organisation_id'           => $this->organisation->id,
        'date'                      => '2026-06-30',
        'subject_type'              => 'Employee',
        'subject_id'                => $employee->id,
        'subject_name'              => $employee->contact_name,
        'start_at'                  => '2026-06-30 08:00:00',
        'working_duration'          => 0,
        'breaks_duration'           => 0,
        'total_duration'            => 0,
        'number_time_trackers'      => 1,
        'number_open_time_trackers' => 1,
    ]);

    \App\Models\HumanResources\TimeTracker::create([
        'timesheet_id'    => $timesheet->id,
        'subject_type'    => 'Employee',
        'subject_id'      => $employee->id,
        'status'          => \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::OPEN,
        'starts_at'       => '2026-06-30 08:00:00',
    ]);

    $adjustment = StoreAttendanceAdjustment::make()->action($employee, [
        'date'               => '2026-06-30',
        'reason'             => 'Forgot to clock out',
        'requested_start_at' => '08:00',
        'requested_end_at'   => '17:00',
    ]);
    $adjustment->update(['timesheet_id' => $timesheet->id]);

    ApproveAttendanceAdjustment::make()->handle($adjustment->refresh());

    $timesheet->refresh();

    expect($timesheet->working_duration)->toBe(9 * 3600)
        ->and($timesheet->number_open_time_trackers)->toBe(0);
});

test('an adjustment that ends before it starts never reaches the paid hours', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $timesheet = \App\Models\HumanResources\Timesheet::create([
        'group_id'                  => $this->group->id,
        'organisation_id'           => $this->organisation->id,
        'date'                      => '2026-06-30',
        'subject_type'              => 'Employee',
        'subject_id'                => $employee->id,
        'subject_name'              => $employee->contact_name,
        'working_duration'          => 28800,
        'breaks_duration'           => 0,
        'total_duration'            => 28800,
        'number_time_trackers'      => 1,
        'number_open_time_trackers' => 0,
    ]);

    \App\Models\HumanResources\TimeTracker::create([
        'timesheet_id'    => $timesheet->id,
        'subject_type'    => 'Employee',
        'subject_id'      => $employee->id,
        'status'          => \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::CLOSED,
        'starts_at'       => '2026-06-30 09:00:00',
        'ends_at'         => '2026-06-30 17:00:00',
        'duration'        => 28800,
    ]);

    $adjustment = AttendanceAdjustment::create([
        'group_id'           => $this->group->id,
        'organisation_id'    => $this->organisation->id,
        'employee_id'        => $employee->id,
        'employee_name'      => $employee->contact_name,
        'timesheet_id'       => $timesheet->id,
        'date'               => '2026-06-30',
        'reason'             => 'Typo in the end time',
        'requested_start_at' => '2026-06-30 09:00:00',
        'requested_end_at'   => '2026-06-30 07:00:00',
        'status'             => \App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum::PENDING,
    ]);

    ApproveAttendanceAdjustment::make()->handle($adjustment);

    $timesheet->refresh();

    expect($timesheet->working_duration)->toBe(28800);
});

function makeOvertimeDay(mixed $context, string $compensationType, string $multiplier): array
{
    $employee = Employee::factory()->create([
        'organisation_id' => $context->organisation->id,
        'group_id'        => $context->group->id,
    ]);

    $timesheet = \App\Models\HumanResources\Timesheet::create([
        'group_id'                  => $context->group->id,
        'organisation_id'           => $context->organisation->id,
        'date'                      => '2026-06-27',
        'subject_type'              => 'Employee',
        'subject_id'                => $employee->id,
        'subject_name'              => $employee->contact_name,
        'start_at'                  => '2026-06-27 09:00:00',
        'end_at'                    => '2026-06-27 11:00:00',
        'working_duration'          => 7200,
        'breaks_duration'           => 0,
        'total_duration'            => 7200,
        'number_time_trackers'      => 1,
        'number_open_time_trackers' => 0,
    ]);

    \App\Models\HumanResources\TimeTracker::create([
        'timesheet_id' => $timesheet->id,
        'subject_type' => 'Employee',
        'subject_id'   => $employee->id,
        'status'       => \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::CLOSED,
        'starts_at'    => '2026-06-27 09:00:00',
        'ends_at'      => '2026-06-27 11:00:00',
        'duration'     => 7200,
    ]);

    $overtimeType = \App\Models\HumanResources\OvertimeType::create([
        'group_id'          => $context->group->id,
        'organisation_id'   => $context->organisation->id,
        'code'              => 'OT'.$employee->id,
        'name'              => 'Overtime '.$employee->id,
        'category'          => \App\Enums\HumanResources\Overtime\OvertimeCategoryEnum::OVERTIME,
        'compensation_type' => $compensationType,
        'multiplier'        => $multiplier,
        'is_active'         => true,
    ]);

    \App\Models\HumanResources\OvertimeRequest::create([
        'group_id'                   => $context->group->id,
        'organisation_id'            => $context->organisation->id,
        'employee_id'                => $employee->id,
        'overtime_type_id'           => $overtimeType->id,
        'requested_date'             => '2026-06-27',
        'requested_duration_minutes' => 120,
        'status'                     => \App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum::APPROVED,
    ]);

    return [$timesheet, \App\Actions\HumanResources\Timesheet\CalculateTimesheetOvertime::run($timesheet->refresh())];
}

test('paid overtime is weighted by its type multiplier without inflating hours worked', function () {
    [, $split] = makeOvertimeDay($this, 'paid', '1.50');

    expect($split['paid_overtime_duration'])->toBe(7200)
        ->and($split['payable_overtime_equivalent_duration'])->toBe(10800)
        ->and($split['unpaid_overtime_duration'])->toBe(0);
});

test('time in lieu overtime is never treated as payable', function () {
    [, $split] = makeOvertimeDay($this, 'time_in_lieu', '1.50');

    expect($split['paid_overtime_duration'])->toBe(0)
        ->and($split['payable_overtime_equivalent_duration'])->toBe(0)
        ->and($split['unpaid_overtime_duration'])->toBe(7200);
});

test('can reject attendance adjustment', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $adjustment = StoreAttendanceAdjustment::make()->action($employee, [
        'date' => '2026-06-30',
        'reason' => 'Forgot to clock in',
        'requested_start_at' => '08:00',
        'requested_end_at' => '17:00',
    ]);

    $rejected = RejectAttendanceAdjustment::make()->handle($adjustment, 'Not enough evidence');

    expect($rejected->status)->toBe(\App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum::REJECTED)
        ->and($rejected->approval_comment)->toBe('Not enough evidence');
});


// OVERTIME ACTIONS

test('can update overtime type', function () {
    $overtimeType = StoreOvertimeType::make()->action($this->organisation, [
        'code' => 'OTU' . rand(1000, 9999),
        'name' => 'Update Overtime Type',
        'category' => \App\Enums\HumanResources\Overtime\OvertimeCategoryEnum::OVERTIME,
        'compensation_type' => \App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum::PAID,
    ]);

    $updated = UpdateOvertimeType::make()->action($overtimeType, [
        'name' => 'Updated Overtime Type Name',
    ]);

    expect($updated->name)->toBe('Updated Overtime Type Name');
});

test('can delete overtime type', function () {
    $overtimeType = StoreOvertimeType::make()->action($this->organisation, [
        'code' => 'OTD' . rand(1000, 9999),
        'name' => 'Delete Overtime Type',
        'category' => \App\Enums\HumanResources\Overtime\OvertimeCategoryEnum::OVERTIME,
        'compensation_type' => \App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum::PAID,
    ]);

    $result = DeleteOvertimeType::make()->action($overtimeType);

    expect($result)->toBeTrue();
    expect(OvertimeType::find($overtimeType->id))->toBeNull();
});

function createOvertimeRequestFixture($organisation, $group): OvertimeRequest
{
    $employee = Employee::factory()->create([
        'organisation_id' => $organisation->id,
        'group_id' => $group->id,
    ]);

    $overtimeType = StoreOvertimeType::make()->action($organisation, [
        'code' => 'OT' . rand(10000, 99999),
        'name' => 'Fixture Overtime Type',
        'category' => \App\Enums\HumanResources\Overtime\OvertimeCategoryEnum::OVERTIME,
        'compensation_type' => \App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum::PAID,
    ]);

    return StoreOvertimeRequest::make()->action($organisation, [
        'employee_id' => $employee->id,
        'overtime_type_id' => $overtimeType->id,
        'requested_date' => '2026-06-30',
        'requested_duration_minutes' => 60,
        'status' => OvertimeRequestStatusEnum::PENDING,
    ]);
}

test('can approve overtime request', function () {
    $overtimeRequest = createOvertimeRequestFixture($this->organisation, $this->group);

    $approved = ApproveOvertimeRequest::make()->action($overtimeRequest);

    expect($approved->status)->toBe(OvertimeRequestStatusEnum::APPROVED)
        ->and($approved->approved_at)->not->toBeNull();
});

test('can reject overtime request', function () {
    $overtimeRequest = createOvertimeRequestFixture($this->organisation, $this->group);

    $rejected = RejectOvertimeRequest::make()->action($overtimeRequest);

    expect($rejected->status)->toBe(OvertimeRequestStatusEnum::REJECTED)
        ->and($rejected->rejected_at)->not->toBeNull();
});

test('can update overtime request', function () {
    $overtimeRequest = createOvertimeRequestFixture($this->organisation, $this->group);

    $updated = UpdateOvertimeRequest::make()->action($this->organisation, $overtimeRequest, [
        'employee_id' => $overtimeRequest->employee_id,
        'overtime_type_id' => $overtimeRequest->overtime_type_id,
        'requested_date' => '2026-07-01',
        'requested_duration_minutes' => 90,
        'status' => OvertimeRequestStatusEnum::PENDING,
    ]);

    expect($updated->requested_duration_minutes)->toBe(90);
});

test('can delete overtime request', function () {
    $overtimeRequest = createOvertimeRequestFixture($this->organisation, $this->group);

    $result = DeleteOvertimeRequest::make()->action($overtimeRequest);

    expect($result)->toBeTrue();
    expect(OvertimeRequest::find($overtimeRequest->id))->toBeNull();
});


// CLOCKING ACTIONS

test('can update clocking last_fetched_at', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Update Clocking Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ], 0, true);

    $fetchedAt = now()->toDateTimeString();
    $updated = UpdateClocking::make()->action($clocking, [
        'last_fetched_at' => $fetchedAt,
    ], 0, false);

    expect($updated->last_fetched_at->toDateTimeString())->toBe($fetchedAt);
});

test('can update clocking notes', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Notes Clocking Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ], 0, true);

    $updated = UpdateClockingNotes::make()->handle($clocking, 'Forgot badge', null);

    expect($updated->notes)->toBe('Forgot badge');
});

test('can delete clocking', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Delete Clocking Workplace ' . rand(100000, 999999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ], 0, true);

    $timeTracker = $clocking->timesheet->timeTrackers()->first();

    DeleteClocking::make()->handle($clocking);

    $this->assertDatabaseMissing('clockings', ['id' => $clocking->id]);
    $this->assertDatabaseHas('time_trackers', [
        'id'                => $timeTracker->id,
        'start_clocking_id' => null,
        'starts_at'         => null,
    ]);
});

test('can add clock in to repair a time tracker missing its start', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Repair Clock In Workplace ' . rand(100000, 999999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockIn = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ], 0, true);

    $timeTracker = $clockIn->timesheet->timeTrackers()->first();

    DeleteClocking::make()->handle($clockIn);

    $timeTracker->refresh();
    expect($timeTracker->start_clocking_id)->toBeNull();

    $repaired = ClockInTimeTracker::make()->handle($timeTracker, now(), $employee->id, 'Employee');

    expect($repaired->start_clocking_id)->not->toBeNull()
        ->and($repaired->starts_at)->not->toBeNull();

    $this->assertDatabaseHas('clockings', [
        'time_tracker_id' => $timeTracker->id,
        'type' => \App\Enums\HumanResources\Clocking\ClockingTypeEnum::MANUAL->value,
    ]);
});

test('deleting the clock out clocking reopens its time tracker', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Delete Clock Out Workplace ' . rand(100000, 999999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockIn = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ], 0, true);

    $clockOut = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'out',
        'at' => now()->addHours(8)->toDateTimeString(),
    ], 0, true);

    $timeTracker = $clockIn->timesheet->timeTrackers()->first();

    expect($timeTracker->status)->toBe(\App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::CLOSED);

    DeleteClocking::make()->handle($clockOut);

    $this->assertDatabaseMissing('clockings', ['id' => $clockOut->id]);
    $this->assertDatabaseHas('clockings', ['id' => $clockIn->id]);
    $this->assertDatabaseHas('time_trackers', [
        'id'              => $timeTracker->id,
        'end_clocking_id' => null,
        'ends_at'         => null,
        'duration'        => null,
        'status'          => \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::OPEN->value,
    ]);
});

test('deleting the clock in of an already closed tracker does not crash the timesheet rehydration', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Delete Clock In After Close Workplace ' . rand(100000, 999999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockIn = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ], 0, true);

    $clockOut = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'out',
        'at' => now()->addHours(8)->toDateTimeString(),
    ], 0, true);

    $timesheet = $clockIn->timesheet;

    DeleteClocking::make()->handle($clockIn);

    $this->assertDatabaseMissing('clockings', ['id' => $clockIn->id]);
    $this->assertDatabaseHas('clockings', ['id' => $clockOut->id]);
    $this->assertDatabaseHas('timesheets', ['id' => $timesheet->id, 'start_at' => null]);
});

test('can delete timesheet and its clockings and time trackers', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Delete Timesheet Workplace ' . rand(100000, 999999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockIn = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ], 0, true);

    $clockOut = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'out',
        'at' => now()->addHours(8)->toDateTimeString(),
    ], 0, true);

    $timesheet = $clockIn->timesheet;
    $timeTracker = $timesheet->timeTrackers()->first();

    DeleteTimesheet::make()->handle($timesheet);

    $this->assertDatabaseMissing('timesheets', ['id' => $timesheet->id]);
    $this->assertDatabaseMissing('clockings', ['id' => $clockIn->id]);
    $this->assertDatabaseMissing('clockings', ['id' => $clockOut->id]);
    $this->assertDatabaseMissing('time_trackers', ['id' => $timeTracker->id]);
});


// EMPLOYEE PIN AND LEAVE BALANCE ACTIONS

test('can generate employee pin', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $pin = GeneratePinEmployee::make()->handle($employee);

    expect($pin)->toBeString()
        ->and($pin)->toContain((string) $this->organisation->id.':');
});

test('can validate employee pin', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
        'state' => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
    ]);
    $employee->update(['pin' => 'TEST-PIN-1234']);

    $found = ValidatePinEmployee::make()->handle($this->organisation, ['pin' => 'TEST-PIN-1234']);

    expect($found->id)->toBe($employee->id);
});

// CLOCKING KIOSK PIN

test('can clock in via kiosk pin', function () {
    $suffix = 'A'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk PIN Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::PIN->value,
    ]);
    $clockingMachine->update([
        'kiosk_token' => 'kiosk-token-'.$clockingMachine->id.'-'.$suffix,
        'config'      => ['pin' => ['enable' => true]],
    ]);

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);
    $employee->update(['pin' => $this->organisation->id.':AB'.rand(10, 99)]);
    $employee->workplaces()->attach($workplace->id);

    $clocking = \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin::make()
        ->handle($clockingMachine, substr($employee->fresh()->pin, strlen((string) $this->organisation->id) + 1))['clocking'];

    expect($clocking)->toBeInstanceOf(Clocking::class)
        ->and($clocking->subject_id)->toBe($employee->id)
        ->and($clocking->clocking_machine_id)->toBe($clockingMachine->id)
        ->and($clocking->type)->toBe(\App\Enums\HumanResources\Clocking\ClockingTypeEnum::CLOCKING_MACHINE);

    return [$clockingMachine, $employee];
});

test('wrong kiosk pin is rejected', function (array $context) {
    [$clockingMachine] = $context;

    \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin::make()->handle($clockingMachine, 'WRONG');
})->depends('can clock in via kiosk pin')->throws(\Exception::class, 'Invalid PIN.');

test('kiosk pin typed with the leading organisation digits still clocks in', function () {
    $suffix = 'B'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk PIN Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::PIN->value,
    ]);
    $clockingMachine->update(['config' => ['pin' => ['enable' => true]]]);

    $code = 'EF'.rand(10, 99);
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);
    $employee->update(['pin' => $this->organisation->id.':'.$code]);
    $employee->workplaces()->attach($workplace->id);

    // Employees only ever see the full pin (e.g. "5:EF56"); since the kiosk keypad has no
    // ':' key, typing the leading organisation digits without the colon must still resolve.
    $clocking = \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin::make()
        ->handle($clockingMachine, $this->organisation->id.$code)['clocking'];

    expect($clocking->subject_id)->toBe($employee->id);
});

test('kiosk pin does not require the employee to belong to the machine workplace', function () {
    $suffix = 'C'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk PIN Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::PIN->value,
    ]);
    $clockingMachine->update(['config' => ['pin' => ['enable' => true]]]);

    $code = 'CD'.rand(10, 99);

    // The employee is never attached to any workplace, matching real employees whose
    // employee_workplace pivot is empty - the kiosk PIN check no longer depends on it.
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);
    $employee->update(['pin' => $this->organisation->id.':'.$code]);

    $clocking = \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin::make()
        ->handle($clockingMachine, $code)['clocking'];

    expect($clocking->subject_id)->toBe($employee->id);
});

test('kiosk pin for a non working employee is rejected', function () {
    $suffix = 'D'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk PIN Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::PIN->value,
    ]);
    $clockingMachine->update(['config' => ['pin' => ['enable' => true]]]);

    $code = 'GH'.rand(10, 99);
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
        'state' => \App\Enums\HumanResources\Employee\EmployeeStateEnum::HIRED,
    ]);
    $employee->update(['pin' => $this->organisation->id.':'.$code]);

    \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin::make()->handle($clockingMachine, $code);
})->throws(\Exception::class, 'Invalid PIN.');

test('kiosk endpoint 404s when pin mode is disabled', function () {
    $suffix = 'E'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk Disabled Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::PIN->value,
    ]);
    $clockingMachine->update(['kiosk_token' => 'kiosk-token-disabled-'.$clockingMachine->id.'-'.$suffix]);

    \Pest\Laravel\postJson(route('grp.kiosk.pin.submit', ['kioskToken' => $clockingMachine->kiosk_token]), [
        'pin' => 'AB12',
    ])->assertNotFound();
});

// CLOCKING KIOSK BARCODE

test('can clock in via kiosk barcode', function () {
    $suffix = 'F'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk Barcode Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::BARCODE_SCANNER->value,
    ]);
    $clockingMachine->update([
        'kiosk_token' => 'kiosk-token-'.$clockingMachine->id.'-'.$suffix,
        'config'      => ['barcode' => ['enable' => true]],
    ]);

    $code = 'BC'.rand(10, 99);
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);
    $employee->update(['pin' => $this->organisation->id.':'.$code]);

    // The barcode encodes the employee's own pin verbatim (no human typing involved), so an
    // exact scanned match is the common case.
    $clocking = \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskBarcode::make()
        ->handle($clockingMachine, $code)['clocking'];

    expect($clocking)->toBeInstanceOf(Clocking::class)
        ->and($clocking->subject_id)->toBe($employee->id)
        ->and($clocking->clocking_machine_id)->toBe($clockingMachine->id);

    return [$clockingMachine, $employee];
});

test('wrong kiosk barcode is rejected', function (array $context) {
    [$clockingMachine] = $context;

    \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskBarcode::make()->handle($clockingMachine, 'WRONG');
})->depends('can clock in via kiosk barcode')->throws(\Exception::class, 'Invalid barcode.');

test('kiosk endpoint 404s when barcode mode is disabled', function () {
    $suffix = 'G'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk Disabled Barcode Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::BARCODE_SCANNER->value,
    ]);
    $clockingMachine->update(['kiosk_token' => 'kiosk-token-disabled-'.$clockingMachine->id.'-'.$suffix]);

    \Pest\Laravel\postJson(route('grp.kiosk.barcode.submit', ['kioskToken' => $clockingMachine->kiosk_token]), [
        'barcode' => 'BC12',
    ])->assertNotFound();
});

test('kiosk endpoint 404s when using the wrong mode for the machine', function () {
    $suffix = 'H'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk PIN Only Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::PIN->value,
    ]);
    $clockingMachine->update([
        'kiosk_token' => 'kiosk-token-'.$clockingMachine->id.'-'.$suffix,
        'config'      => ['pin' => ['enable' => true]],
    ]);

    // pin.enable is true but barcode.enable is not, so the barcode endpoint must still 404.
    \Pest\Laravel\postJson(route('grp.kiosk.barcode.submit', ['kioskToken' => $clockingMachine->kiosk_token]), [
        'barcode' => 'AB12',
    ])->assertNotFound();
});

// CLOCKING KIOSK CAMERA QR

test('can clock in via kiosk camera qr', function () {
    $suffix = 'I'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk Camera QR Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::CAMERA_QR->value,
    ]);
    $clockingMachine->update([
        'kiosk_token' => 'kiosk-token-'.$clockingMachine->id.'-'.$suffix,
        'config'      => ['camera_qr' => ['enable' => true]],
    ]);

    $code = 'IJ'.rand(10, 99);
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);
    $employee->update(['pin' => $this->organisation->id.':'.$code]);
    $employee->workplaces()->attach($workplace->id);

    // The QR encodes the employee's own pin verbatim, same as the barcode flow.
    $clocking = \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskCameraQr::make()
        ->handle($clockingMachine, $code)['clocking'];

    expect($clocking)->toBeInstanceOf(Clocking::class)
        ->and($clocking->subject_id)->toBe($employee->id)
        ->and($clocking->clocking_machine_id)->toBe($clockingMachine->id);

    return [$clockingMachine, $employee];
});

test('wrong kiosk camera qr code is rejected', function (array $context) {
    [$clockingMachine] = $context;

    \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskCameraQr::make()->handle($clockingMachine, 'WRONG');
})->depends('can clock in via kiosk camera qr')->throws(\Exception::class, 'Invalid QR code.');

test('kiosk camera qr snapshot is stored as the clocking image', function () {
    $suffix = 'J'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk Camera QR Snapshot Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::CAMERA_QR->value,
    ]);
    $clockingMachine->update(['config' => ['camera_qr' => ['enable' => true]]]);

    $code = 'KL'.rand(10, 99);
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);
    $employee->update(['pin' => $this->organisation->id.':'.$code]);

    $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    $snapshot = 'data:image/png;base64,'.base64_encode($pixel);

    $clocking = \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskCameraQr::make()
        ->handle($clockingMachine, $code, $snapshot)['clocking'];

    expect($clocking->fresh()->image_id)->not->toBeNull();
});

test('kiosk endpoint 404s when camera qr mode is disabled', function () {
    $suffix = 'K'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk Disabled Camera QR Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::CAMERA_QR->value,
    ]);
    $clockingMachine->update(['kiosk_token' => 'kiosk-token-disabled-'.$clockingMachine->id.'-'.$suffix]);

    \Pest\Laravel\postJson(route('grp.kiosk.camera-qr.submit', ['kioskToken' => $clockingMachine->kiosk_token]), [
        'code' => 'AB12',
    ])->assertNotFound();
});

test('generated employee pins are unique within the organisation', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $taken = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $taken->updateQuietly(['pin' => $this->organisation->id.':ABC123']);

    for ($i = 0; $i < 30; $i++) {
        SetEmployeePin::make()->action($employee, updateQuietly: true);
        expect($employee->fresh()->pin)->not->toBe($taken->pin);
    }
});

test('repairing pins leaves a valid pin untouched and replaces an invalid one', function () {
    $valid = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $valid->updateQuietly(['pin' => $this->organisation->id.':XYZ987']);

    $invalid = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $invalid->updateQuietly(['pin' => null]);

    $this->artisan('employees:repair_pins')->assertExitCode(0);

    expect($valid->fresh()->pin)->toBe($this->organisation->id.':XYZ987')
        ->and($invalid->fresh()->pin)->toMatch('/^'.$this->organisation->id.':[A-GX-Z]{3}\d{3}$/');
});

test('a repeated kiosk scan within the duplicate window does not create a second clocking', function () {
    $suffix = 'L'.rand(10000, 99999);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Kiosk Workplace '.$suffix,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk Double Scan Machine '.$suffix,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::PIN->value,
    ]);
    $clockingMachine->update(['config' => ['pin' => ['enable' => true]]]);

    $code     = 'GX'.rand(10, 99);
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $employee->updateQuietly(['pin' => $this->organisation->id.':'.$code]);

    $first = \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin::make()
        ->handle($clockingMachine, $code)['clocking'];

    $second = \App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin::make()
        ->handle($clockingMachine, $code)['clocking'];

    expect($second->id)->toBe($first->id)
        ->and(Clocking::where('subject_id', $employee->id)->where('subject_type', 'Employee')->count())->toBe(1);
});

test('can adjust employee leave balance creating a new balance', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $balance = AdjustEmployeeLeaveBalance::make()->handle($employee, []);

    expect($balance)->toBeInstanceOf(EmployeeLeaveBalance::class)
        ->and($balance->employee_id)->toBe($employee->id)
        ->and($balance->employee_contract_id)->toBeNull();
});

test('can generate employee leave balance for a contract', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $contract = StoreEmployeeContract::make()->action($employee, [
        'start_date' => '2026-01-01',
        'annual_leave_days' => 20,
    ]);

    $balance = GenerateEmployeeLeaveBalance::make()->handle($contract);

    expect($balance)->toBeInstanceOf(EmployeeLeaveBalance::class)
        ->and($balance->employee_contract_id)->toBe($contract->id);
});


// HOLIDAY / HOLIDAY YEAR ACTIONS

test('can generate next year holidays from recurring holidays', function () {
    StoreHoliday::make()->action($this->organisation, [
        'type' => \App\Enums\HumanResources\Holiday\HolidayTypeEnum::PUBLIC,
        'from' => '2026-12-25',
        'to' => '2026-12-25',
        'label' => 'Recurring Christmas',
        'data' => ['is_recurring' => true],
    ]);

    $created = GenerateNextYearHolidays::make()->handle($this->organisation, 2027);

    expect($created)->toBe(1);
    expect(Holiday::where('organisation_id', $this->organisation->id)->where('year', 2027)->count())->toBe(1);
});

test('can activate holiday year deactivating the others', function () {
    $holidayYearOne = StoreHolidayYear::make()->action($this->organisation, [
        'label' => 'Year One',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $holidayYearTwo = StoreHolidayYear::make()->action($this->organisation, [
        'label' => 'Year Two',
        'start_date' => '2027-01-01',
        'end_date' => '2027-12-31',
        'is_active' => false,
    ]);

    ActivateHolidayYear::make()->handle($this->organisation, $holidayYearTwo);

    expect($holidayYearTwo->refresh()->is_active)->toBeTrue()
        ->and($holidayYearOne->refresh()->is_active)->toBeFalse();
});


// JOB POSITION ACTIONS

test('can store job position scope group', function () {
    $jobPosition = StoreJobPositionScopeGroup::make()->action($this->group, [
        'code' => 'JPG' . rand(1000, 9999),
        'name' => 'Group Job Position',
        'scope' => \App\Enums\HumanResources\JobPosition\JobPositionScopeEnum::GROUP,
    ]);

    expect($jobPosition)->toBeInstanceOf(JobPosition::class)
        ->and($jobPosition->group_id)->toBe($this->group->id);
});

test('can update job position scope group', function () {
    $jobPosition = StoreJobPositionScopeGroup::make()->action($this->group, [
        'code' => 'JPGU' . rand(1000, 9999),
        'name' => 'Group Job Position To Update',
        'scope' => \App\Enums\HumanResources\JobPosition\JobPositionScopeEnum::GROUP,
    ]);

    $updated = UpdateJobPositionScopeGroup::make()->action($jobPosition, [
        'name' => 'Updated Group Job Position',
        'scope' => $jobPosition->scope,
    ]);

    expect($updated->name)->toBe('Updated Group Job Position');
});

test('can sync employee job positions', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $jobPosition = StoreJobPosition::make()->action($this->organisation, [
        'code' => 'JPS' . rand(1000, 9999),
        'name' => 'Sync Job Position',
        'scope' => \App\Enums\HumanResources\JobPosition\JobPositionScopeEnum::ORGANISATION,
    ]);

    SyncEmployeeJobPositions::make()->handle($employee, [
        $jobPosition->id => [],
    ]);

    expect($employee->jobPositions()->where('job_positions.id', $jobPosition->id)->exists())->toBeTrue();

    SyncEmployeeJobPositions::make()->handle($employee, []);

    expect($employee->jobPositions()->where('job_positions.id', $jobPosition->id)->exists())->toBeFalse();
});


// LEAVE CONCURRENCY RULE ACTIONS

function createLeaveConcurrencyRuleFixture($organisation, array $modelData): LeaveConcurrencyRule
{
    // StoreLeaveConcurrencyRule::afterValidator() reads the global request() helper
    // instead of the action's own validated data, so it must be primed manually
    // when invoking the action outside of a real HTTP request cycle.
    if (isset($modelData['rule_type']) && $modelData['rule_type'] instanceof \App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum) {
        $modelData['rule_type'] = $modelData['rule_type']->value;
    }
    request()->merge($modelData);

    return StoreLeaveConcurrencyRule::make()->action($organisation, $modelData);
}

test('can store leave concurrency rule', function () {
    $rule = createLeaveConcurrencyRuleFixture($this->organisation, [
        'name' => 'Max two on leave',
        'rule_type' => \App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum::QUOTA,
        'limit' => 2,
    ]);

    expect($rule)->toBeInstanceOf(LeaveConcurrencyRule::class)
        ->and($rule->organisation_id)->toBe($this->organisation->id)
        ->and($rule->limit)->toBe(2);
});

test('can update leave concurrency rule', function () {
    $rule = createLeaveConcurrencyRuleFixture($this->organisation, [
        'name' => 'Rule to update',
        'rule_type' => \App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum::QUOTA,
        'limit' => 2,
    ]);

    $updated = UpdateLeaveConcurrencyRule::make()->action($rule, [
        'limit' => 5,
    ]);

    expect($updated->limit)->toBe(5);
});

test('can delete leave concurrency rule', function () {
    $rule = createLeaveConcurrencyRuleFixture($this->organisation, [
        'name' => 'Rule to delete',
        'rule_type' => \App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum::QUOTA,
        'limit' => 2,
    ]);

    $result = DeleteLeaveConcurrencyRule::make()->action($rule);

    expect($result)->toBeTrue();
    expect(LeaveConcurrencyRule::find($rule->id))->toBeNull();
});


// RESTRICTED PERIODS ACTIONS

test('can store restricted period', function () {
    $restrictedPeriod = StoreRestrictedPeriod::make()->action($this->organisation, [
        'label' => 'Year End Freeze',
        'start_date' => '2026-12-20',
        'end_date' => '2026-12-31',
        'strictness' => 'block',
    ]);

    expect($restrictedPeriod)->toBeInstanceOf(RestrictedPeriod::class)
        ->and($restrictedPeriod->organisation_id)->toBe($this->organisation->id);
});

test('can update restricted period', function () {
    $restrictedPeriod = StoreRestrictedPeriod::make()->action($this->organisation, [
        'label' => 'Period to update',
        'start_date' => '2026-12-20',
        'end_date' => '2026-12-31',
        'strictness' => 'block',
    ]);

    $updated = UpdateRestrictedPeriod::make()->action($restrictedPeriod, [
        'label' => 'Updated Period Label',
    ]);

    expect($updated->label)->toBe('Updated Period Label');
});

test('can delete restricted period', function () {
    $restrictedPeriod = StoreRestrictedPeriod::make()->action($this->organisation, [
        'label' => 'Period to delete',
        'start_date' => '2026-12-20',
        'end_date' => '2026-12-31',
        'strictness' => 'block',
    ]);

    $result = DeleteRestrictedPeriod::make()->action($restrictedPeriod);

    expect($result)->toBeTrue();
    expect(RestrictedPeriod::find($restrictedPeriod->id))->toBeNull();
});


// WORK SCHEDULE ACTIONS

test('can store work schedule', function () {
    $workSchedule = StoreWorkSchedule::make()->action($this->organisation, [
        'name' => 'Standard Schedule',
        'type' => 'default',
    ]);

    expect($workSchedule)->toBeInstanceOf(WorkSchedule::class)
        ->and($workSchedule->name)->toBe('Standard Schedule');
});

test('can update work schedule working hours', function () {
    $workSchedule = StoreWorkSchedule::make()->action($this->organisation, [
        'name' => 'Schedule To Update',
        'type' => 'default',
    ]);

    $updated = UpdateWorkSchedule::make()->action($this->organisation, $workSchedule, [
        'working_hours' => [
            'data' => [
                1 => ['s' => '09:00', 'e' => '17:00'],
            ],
        ],
    ]);

    expect($updated->days()->where('day_of_week', 1)->where('is_working_day', true)->exists())->toBeTrue();
});

test('can delete work schedule', function () {
    $workSchedule = StoreWorkSchedule::make()->action($this->organisation, [
        'name' => 'Schedule To Delete',
        'type' => 'default',
    ]);

    $result = DeleteWorkSchedule::make()->action($workSchedule);

    expect($result)->toBeTrue();
    expect(WorkSchedule::find($workSchedule->id))->toBeNull();
});


// TIME TRACKER ACTIONS

function createRawClockingFixture($organisation, $workplace, $employee, $clockedAt): Clocking
{
    // Bypasses StoreClocking on purpose: that action already creates/closes a
    // TimeTracker as a side effect, which would interfere with directly
    // unit-testing StoreTimeTracker/CloseTimeTracker/AddClockingToTimeTracker.
    return Clocking::create([
        'group_id' => $organisation->group_id,
        'organisation_id' => $organisation->id,
        'workplace_id' => $workplace->id,
        'subject_type' => 'Employee',
        'subject_id' => $employee->id,
        'type' => \App\Enums\HumanResources\Clocking\ClockingTypeEnum::MANUAL,
        'clocked_at' => $clockedAt,
    ]);
}

test('can store time tracker from a clocking', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $timesheet = StoreTimesheet::make()->action($employee, ['date' => '2026-06-30']);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Time Tracker Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clocking = createRawClockingFixture($this->organisation, $workplace, $employee, now());

    $timeTracker = StoreTimeTracker::make()->action($timesheet, $clocking, []);

    expect($timeTracker)->toBeInstanceOf(TimeTracker::class)
        ->and($timeTracker->status)->toBe(\App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::OPEN)
        ->and($timeTracker->start_clocking_id)->toBe($clocking->id);
});

test('can close a time tracker', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $timesheet = StoreTimesheet::make()->action($employee, ['date' => '2026-06-30']);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Close Tracker Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockIn = createRawClockingFixture($this->organisation, $workplace, $employee, now());

    $timeTracker = StoreTimeTracker::make()->action($timesheet, $clockIn, []);

    $clockOut = createRawClockingFixture($this->organisation, $workplace, $employee, now()->addHours(8));

    $closed = CloseTimeTracker::make()->action($timeTracker, $clockOut, []);

    expect($closed->status)->toBe(\App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::CLOSED)
        ->and($closed->duration)->toBeGreaterThan(0);
});

test('can delete time tracker and its related clockings', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Delete Time Tracker Workplace ' . rand(100000, 999999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockIn = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->toDateTimeString(),
    ], 0, true);

    $clockOut = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'out',
        'at' => now()->addHours(8)->toDateTimeString(),
    ], 0, true);

    $timeTracker = $clockIn->timesheet->timeTrackers()->first();

    DeleteTimeTracker::make()->handle($timeTracker);

    $this->assertDatabaseMissing('time_trackers', ['id' => $timeTracker->id]);
    $this->assertDatabaseMissing('clockings', ['id' => $clockIn->id]);
    $this->assertDatabaseMissing('clockings', ['id' => $clockOut->id]);
});

test('AddClockingToTimeTracker opens a tracker then closes it on the next clocking', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $timesheet = StoreTimesheet::make()->action($employee, ['date' => '2026-06-30']);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Add Clocking Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockIn = createRawClockingFixture($this->organisation, $workplace, $employee, now());

    $openTracker = AddClockingToTimeTracker::run($timesheet, $clockIn);

    expect($openTracker->status)->toBe(\App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::OPEN);

    $clockOut = createRawClockingFixture($this->organisation, $workplace, $employee, now()->addHours(8));

    $closedTracker = AddClockingToTimeTracker::run($timesheet, $clockOut);

    expect($closedTracker->id)->toBe($openTracker->id)
        ->and($closedTracker->refresh()->status)->toBe(\App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::CLOSED);
});


// LEAVE ACTIONS

function createLeaveApproverFixture($organisation, $sequence = 1): LeaveApprover
{
    $user = User::factory()->create(['group_id' => $organisation->group_id]);

    return LeaveApprover::query()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'name' => $user->contact_name ?? 'Approver',
        'sequence_number' => $sequence,
        'is_active' => true,
    ]);
}

test('can store leave approver', function () {
    $user = User::factory()->create([
        'group_id' => $this->organisation->group_id,
        'contact_name' => 'Approver Name',
    ]);

    $leaveApprover = StoreLeaveApprover::make()->action($this->organisation, [
        'user_id' => $user->id,
        'sequence_number' => 1,
    ]);

    expect($leaveApprover)->toBeInstanceOf(LeaveApprover::class)
        ->and($leaveApprover->organisation_id)->toBe($this->organisation->id)
        ->and($leaveApprover->sequence_number)->toBe(1);
});

test('can delete leave approver', function () {
    $leaveApprover = createLeaveApproverFixture($this->organisation);

    $result = DeleteLeaveApprover::make()->action($leaveApprover);

    expect($result)->toBeTrue();
    expect(LeaveApprover::find($leaveApprover->id))->toBeNull();
});

test('can update leave type', function () {
    $leaveType = StoreLeaveType::make()->action($this->organisation, [
        'code' => 'LTU' . rand(1000, 9999),
        'name' => 'Leave Type To Update',
        'category' => \App\Enums\HumanResources\Leave\LeaveCategoryEnum::MEDICAL,
    ]);

    $updated = UpdateLeaveType::make()->action($leaveType, [
        'name' => 'Updated Leave Type Name',
    ]);

    expect($updated->name)->toBe('Updated Leave Type Name');
});

function createPendingLeaveFixture($organisation, $group): Leave
{
    $employee = Employee::factory()->create([
        'organisation_id' => $organisation->id,
        'group_id' => $group->id,
    ]);

    return Leave::create([
        'group_id' => $employee->group_id,
        'organisation_id' => $employee->organisation_id,
        'employee_id' => $employee->id,
        'employee_name' => $employee->contact_name,
        'type' => 'annual',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'duration_days' => 2,
        'is_half_day' => false,
        'session' => 'Full',
        'reason' => 'Family trip',
        'status' => LeaveStatusEnum::PENDING,
    ]);
}

test('can update leave attachments path does not error without attachments', function () {
    $leave = createPendingLeaveFixture($this->organisation, $this->group);

    $updated = UpdateLeave::make()->handle($leave, []);

    expect($updated->id)->toBe($leave->id)
        ->and($updated->status)->toBe(LeaveStatusEnum::PENDING);
});

test('can delete leave', function () {
    $leave = createPendingLeaveFixture($this->organisation, $this->group);

    $result = DeleteLeave::make()->action($leave);

    expect($result)->toBeTrue();
    expect(Leave::find($leave->id))->toBeNull();
});

test('can approve leave and increments employee leave balance', function () {
    $leave = createPendingLeaveFixture($this->organisation, $this->group);
    $approver = createLeaveApproverFixture($this->organisation, 1);

    actingAs($approver->user);

    $approved = ApproveLeave::make()->handle($leave);

    expect($approved->status)->toBe(LeaveStatusEnum::APPROVED)
        ->and($approved->approved_at)->not->toBeNull();
});

test('can reject leave', function () {
    $leave = createPendingLeaveFixture($this->organisation, $this->group);
    $approver = createLeaveApproverFixture($this->organisation, 1);

    actingAs($approver->user);

    $rejected = RejectLeave::make()->handle($leave, 'Insufficient cover');

    expect($rejected->status)->toBe(LeaveStatusEnum::REJECTED)
        ->and($rejected->rejection_reason)->toBe('Insufficient cover');
});

test('StoreLeave handle creates a leave with pending approval records', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    createLeaveApproverFixture($this->organisation, 1);

    $leave = StoreLeave::make()->handle($employee, [
        'type' => 'annual',
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'reason' => 'Personal time',
    ]);

    expect($leave)->toBeInstanceOf(Leave::class)
        ->and($leave->employee_id)->toBe($employee->id)
        ->and($leave->status)->toBe(LeaveStatusEnum::PENDING);

    expect(\App\Models\HumanResources\LeaveApprovalRecord::where('leave_id', $leave->id)->count())->toBeGreaterThanOrEqual(1);
});

test('StoreUserFromEmployee authorisation comes from the human resources trait', function () {
    expect(class_uses_recursive(StoreUserFromEmployee::class))
        ->toContain(WithHumanResourcesEditAuthorisation::class)
        ->and(method_exists(StoreUserFromEmployee::class, 'authorize'))->toBeTrue();
});

test('can create a user from an employee', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'employee-' . $employee->id,
        'password' => 'secret123',
    ]);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->status)->toBeTrue()
        ->and($employee->refresh()->user_id)->toBe($user->id);

    return $employee;
});

test('an employee that already has a user can not get a second one', function (Employee $employee) {
    expect(fn () => StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'employee-dup-' . $employee->id,
        'password' => 'secret123',
    ]))->toThrow(HttpException::class);
})->depends('can create a user from an employee');

test('a user can scan a QR code of a clocking machine in another organisation', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'cross-org-' . $employee->id,
        'password' => 'secret123',
    ]);

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, \App\Models\SysAdmin\Organisation::factory()->definition());

    $workplace = StoreWorkplace::make()->action($otherOrganisation, [
        'name' => 'Cross Org Workplace ' . $employee->id,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Cross Org QR Machine ' . $employee->id,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
    ]);

    $qrCode = StoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'label' => 'Cross org entrance',
    ]);

    actingAs($user);

    $result = ValidateClockingMachineQrCode::make()->handle($qrCode->hash);

    expect($result['clocking']->subject_id)->toBe($employee->id)
        ->and($result['clocking']->organisation_id)->toBe($otherOrganisation->id)
        ->and($result['clocking']->clocking_machine_id)->toBe($clockingMachine->id);
});

test('a user without any employee record can not clock in by QR code', function () {
    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'No Employee Workplace ' . uniqid(),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'No Employee QR Machine ' . uniqid(),
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
    ]);

    $qrCode = StoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'label' => 'No employee entrance',
    ]);

    expect(fn () => ValidateClockingMachineQrCode::make()->handle($qrCode->hash))
        ->toThrow(Exception::class, 'User is not associated with an employee record.');
});

test('a remote employee skips coordinate validation on a machine of another organisation', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'remote-cross-org-' . $employee->id,
        'password' => 'secret123',
    ]);

    \App\Models\HumanResources\ClockingMachineCoordinatePolicy::create([
        'organisation_id' => $this->organisation->id,
        'scope_type'      => 'employee',
        'scope_id'        => $employee->id,
        'mode'            => \App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum::REMOTE,
        'is_active'       => true,
    ]);

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, \App\Models\SysAdmin\Organisation::factory()->definition());

    $workplace = StoreWorkplace::make()->action($otherOrganisation, [
        'name' => 'Remote Policy Workplace ' . $employee->id,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Remote Policy QR Machine ' . $employee->id,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
    ]);

    $clockingMachine->update([
        'config' => [
            'qr' => [
                'enable'            => true,
                'allow_coordinates' => true,
                'coordinates'       => '53.401268237762125, -1.40775203704834',
                'radius'            => 100,
            ],
        ],
    ]);

    $qrCode = StoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'label' => 'Remote policy entrance',
    ]);

    actingAs($user);

    $result = ValidateClockingMachineQrCode::make()->handle($qrCode->hash);

    expect($result['effective_mode'])->toBe(\App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum::REMOTE->value)
        ->and($result['clocking']->subject_id)->toBe($employee->id);
});

test('an employee without a remote policy still gets coordinate validation on another organisation machine', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'onsite-cross-org-' . $employee->id,
        'password' => 'secret123',
    ]);

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, \App\Models\SysAdmin\Organisation::factory()->definition());

    $workplace = StoreWorkplace::make()->action($otherOrganisation, [
        'name' => 'Onsite Policy Workplace ' . $employee->id,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Onsite Policy QR Machine ' . $employee->id,
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
    ]);

    $clockingMachine->update([
        'config' => [
            'qr' => [
                'enable'            => true,
                'allow_coordinates' => true,
                'coordinates'       => '53.401268237762125, -1.40775203704834',
                'radius'            => 100,
            ],
        ],
    ]);

    $qrCode = StoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'label' => 'Onsite policy entrance',
    ]);

    actingAs($user);

    expect(fn () => ValidateClockingMachineQrCode::make()->handle($qrCode->hash, 51.5, -0.12))
        ->toThrow(Exception::class, 'Device is too far from the designated clocking location.');
});

test('get user current employee prefers active record over newer left record', function () {
    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->organisation->group_id]);

    $makeEmployee = function (\App\Enums\HumanResources\Employee\EmployeeStateEnum $state) {
        $modelData = Employee::factory()->make([
            'organisation_id' => $this->organisation->id,
        ])->toArray();
        $modelData['worker_number'] = 'W' . rand(10000, 99999);
        $modelData['alias'] = 'Alias ' . rand(10000, 99999);
        $modelData['type'] = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state'] = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

        $employee = StoreEmployee::make()->action($this->organisation, $modelData);
        if ($state !== \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING) {
            $employee->update(['state' => $state]);
        }

        return $employee;
    };

    $activeEmployee = $makeEmployee(\App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING);
    $leftEmployee = $makeEmployee(\App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT);
    $user->employees()->attach([
        $activeEmployee->id => ['group_id' => $this->organisation->group_id, 'organisation_id' => $this->organisation->id],
        $leftEmployee->id   => ['group_id' => $this->organisation->group_id, 'organisation_id' => $this->organisation->id],
    ]);

    expect($leftEmployee->id)->toBeGreaterThan($activeEmployee->id)
        ->and(\App\Actions\SysAdmin\User\GetUserCurrentEmployee::run($user)->id)->toBe($activeEmployee->id)
        ->and(\App\Actions\SysAdmin\User\GetUserCurrentEmployee::run($user, $this->organisation->id)->id)->toBe($activeEmployee->id)
        ->and(\App\Actions\SysAdmin\User\GetUserCurrentEmployee::run($user, $this->organisation->slug)->id)->toBe($activeEmployee->id);

    $activeEmployee->update(['state' => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT]);
    expect(\App\Actions\SysAdmin\User\GetUserCurrentEmployee::run($user))->not->toBeNull();
});

describe('repair misattributed clockings', function () {
    test('detects machine clockings left on a past employment and ignores legitimate ones', function () {
        $pastEmployee = Employee::factory()->create([
            'organisation_id'   => $this->organisation->id,
            'group_id'          => $this->group->id,
            'state'             => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT,
            'employment_end_at' => now()->subYear(),
        ]);

        $user = StoreUserFromEmployee::make()->handle($pastEmployee, [
            'username' => 'repair-' . $pastEmployee->id,
            'password' => 'secret123',
        ]);

        $currentEmployee = Employee::factory()->create([
            'organisation_id' => $this->organisation->id,
            'group_id'        => $this->group->id,
            'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        ]);
        \App\Actions\SysAdmin\User\AttachEmployeeToUser::make()->action($user, $currentEmployee, ['status' => true]);

        $workplace = StoreWorkplace::make()->action($this->organisation, [
            'name' => 'Repair Workplace ' . rand(100000, 999999),
            'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
        ]);

        $clockingMachine = StoreClockingMachine::make()->action($workplace, [
            'name' => 'Repair Machine ' . rand(100000, 999999),
            'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
        ]);

        $stray = StoreClocking::make()->action($pastEmployee, $clockingMachine, $pastEmployee, [
            'clocked_at' => now()->toDateTimeString(),
        ], 0, true);

        StoreClocking::make()->action($currentEmployee, $clockingMachine, $currentEmployee, [
            'clocked_at' => now()->toDateTimeString(),
        ], 0, true);

        $pairs = collect(RepairMisattributedClockings::make()->handle());

        $pair = $pairs->firstWhere('wrong_employee_id', $pastEmployee->id);

        expect($pair)->not->toBeNull()
            ->and($pair['correct_employee_id'])->toBe($currentEmployee->id)
            ->and($pair['clocking_ids'])->toContain($stray->id)
            ->and($pairs->firstWhere('wrong_employee_id', $currentEmployee->id))->toBeNull();
    });
});

describe('shared group clocking machines', function () {
    test('a kiosk resolves a pin typed bare or organisation-prefixed, and refuses a closed employment', function () {
        $employee = Employee::factory()->create([
            'organisation_id' => $this->organisation->id,
            'group_id'        => $this->group->id,
            'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        ]);
        $employee->update(['pin' => $this->organisation->id . ':ZZQ421']);

        $workplace = StoreWorkplace::make()->action($this->organisation, [
            'name' => 'Shared Workplace ' . rand(100000, 999999),
            'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
        ]);

        $clockingMachine = StoreClockingMachine::make()->action($workplace, [
            'name' => 'Shared Machine ' . rand(100000, 999999),
            'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
        ]);

        $bare = ValidateClockingKioskPin::make()->handle($clockingMachine, 'ZZQ421');
        $prefixed = ValidateClockingKioskPin::make()->handle($clockingMachine, $this->organisation->id . ':ZZQ421');

        expect($bare['employee']->id)->toBe($employee->id)
            ->and($prefixed['employee']->id)->toBe($employee->id);

        $employee->update(['state' => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT]);

        expect(fn () => ValidateClockingKioskPin::make()->handle($clockingMachine, 'ZZQ421'))
            ->toThrow(Exception::class);
    });
});

describe('stranded empty timesheets', function () {
    test('only days with no clockings, no worked time and no real time trackers are listed', function () {
        $pastEmployee = Employee::factory()->create([
            'organisation_id'   => $this->organisation->id,
            'group_id'          => $this->group->id,
            'state'             => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT,
            'employment_end_at' => now()->subYear(),
        ]);

        $empty = StoreTimesheet::make()->action($pastEmployee, ['date' => now()->subDays(2)]);
        $withHusk = StoreTimesheet::make()->action($pastEmployee, ['date' => now()->subDays(3)]);
        $withWork = StoreTimesheet::make()->action($pastEmployee, ['date' => now()->subDays(4)]);

        $withHusk->timeTrackers()->create([
            'subject_type' => 'Employee',
            'subject_id'   => $pastEmployee->id,
            'status'       => \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::OPEN,
        ]);

        $withWork->timeTrackers()->create([
            'subject_type' => 'Employee',
            'subject_id'   => $pastEmployee->id,
            'status'       => \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::OPEN,
            'starts_at'    => now()->subDays(4),
        ]);

        $action = RepairMisattributedClockings::make();
        $action->handle(6);
        $stranded = $action->strandedEmptyTimesheetIds();

        expect($stranded)->toContain($empty->id)
            ->and($stranded)->toContain($withHusk->id)
            ->and($stranded)->not->toContain($withWork->id);

        $husk = $withHusk->timeTrackers()->first();
        $husk->delete();

        $action->deleteStrandedTimesheets($stranded);

        expect(\App\Models\HumanResources\Timesheet::find($empty->id))->toBeNull()
            ->and(\App\Models\HumanResources\Timesheet::find($withHusk->id))->toBeNull()
            ->and(\App\Models\HumanResources\TimeTracker::withTrashed()->find($husk->id))->toBeNull()
            ->and(\App\Models\HumanResources\Timesheet::find($withWork->id))->not->toBeNull();
    });
});

describe('time tracker interval guard', function () {
    test('a tracker closed with an earlier clocking orders its endpoints instead of going negative', function () {
        $employee = Employee::factory()->create([
            'organisation_id' => $this->organisation->id,
            'group_id'        => $this->group->id,
            'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        ]);

        $timesheet = StoreTimesheet::make()->action($employee, ['date' => now()]);

        $tracker = $timesheet->timeTrackers()->create([
            'subject_type' => 'Employee',
            'subject_id'   => $employee->id,
            'status'       => \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::CLOSED,
            'starts_at'    => now()->setTime(16, 0),
            'ends_at'      => now()->setTime(8, 0),
            'duration'     => -28800,
        ]);

        $tracker->normaliseInterval();

        expect($tracker->duration)->toBe(28800)
            ->and($tracker->starts_at->format('H:i'))->toBe('08:00')
            ->and($tracker->ends_at->format('H:i'))->toBe('16:00');

        $negatives = RepairNegativeTimeTrackers::make()->handle();

        expect($negatives->pluck('id'))->not->toContain($tracker->id);

        $timesheet->update([
            'start_at' => now()->setTime(16, 0),
            'end_at'   => now()->setTime(8, 0),
        ]);

        $action = RepairNegativeTimeTrackers::make();
        $inverted = $action->invertedTimesheets();

        expect($inverted->pluck('id'))->toContain($timesheet->id);

        $action->realignTimesheets($inverted);
        $timesheet->refresh();

        expect($timesheet->start_at->format('H:i'))->toBe('08:00')
            ->and($timesheet->end_at->format('H:i'))->toBe('16:00')
            ->and($timesheet->total_duration)->toBeGreaterThan(0);
    });
});

describe('current employee across organisations', function () {
    test('a closed employment in the asked-for organisation never beats an active one elsewhere', function () {
        $closed = Employee::factory()->create([
            'organisation_id' => $this->organisation->id,
            'group_id'        => $this->group->id,
            'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT,
        ]);

        $user = StoreUserFromEmployee::make()->handle($closed, [
            'username' => 'visitor-' . $closed->id,
            'password' => 'secret123',
        ]);

        $active = Employee::factory()->create([
            'organisation_id' => $this->organisation->id,
            'group_id'        => $this->group->id,
            'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        ]);
        \App\Actions\SysAdmin\User\AttachEmployeeToUser::make()->action($user, $active, ['status' => true]);
        $user->refresh();

        $unreachableOrganisationId = $this->organisation->id + 9999;

        expect(GetUserCurrentEmployee::run($user, $this->organisation->id)?->id)->toBe($active->id)
            ->and(GetUserCurrentEmployee::run($user, $unreachableOrganisationId)?->id)->toBe($active->id)
            ->and(GetUserCurrentEmployee::run($user)?->id)->toBe($active->id);
    });
});
