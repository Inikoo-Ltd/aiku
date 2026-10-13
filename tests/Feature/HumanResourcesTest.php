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
use App\Actions\HumanResources\Clocking\StoreManualClocking;
use App\Actions\HumanResources\Clocking\UpdateClockingNotes;
use App\Actions\HumanResources\Clocking\DeleteClocking;
use App\Actions\HumanResources\Overtime\StoreOvertimeRequest;
use App\Actions\HumanResources\Overtime\StoreOvertimeType;
use App\Actions\HumanResources\Overtime\ApproveOvertimeRequest;
use App\Actions\HumanResources\Overtime\RejectOvertimeRequest;
use App\Actions\HumanResources\Overtime\DeleteOvertimeRequest;
use App\Actions\HumanResources\Overtime\UpdateOvertimeRequest;
use App\Actions\HumanResources\Overtime\UpdateOvertimeType;
use App\Actions\HumanResources\Overtime\UI\IndexOvertime;
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
use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;
use App\Actions\HumanResources\Timesheet\StoreTimesheet;
use App\Actions\HumanResources\Timesheet\DeleteTimesheet;
use App\Actions\HumanResources\Leave\StoreLeave;
use App\Actions\HumanResources\Leave\UpdateLeave;
use App\Actions\HumanResources\Leave\DeleteLeave;
use App\Actions\HumanResources\Leave\ApproveLeave;
use App\Actions\HumanResources\Leave\StoreEmployeeLeave;
use App\Actions\HumanResources\Leave\RejectLeave;
use App\Actions\HumanResources\Leave\StoreLeaveApprover;
use App\Actions\HumanResources\Leave\DeleteLeaveApprover;
use App\Actions\HumanResources\Leave\UpdateLeaveType;
use App\Actions\HumanResources\Leave\GenerateEmployeeLeaveBalance;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Workplace;
use App\Models\HumanResources\JobPosition;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Actions\Helpers\Avatars\GetDiceBearAvatar;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

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

    $withPhoto = UpdateEmployee::make()->action($employee, [
        'image' => \Illuminate\Http\UploadedFile::fake()->image('photo.jpg', 200, 200),
    ]);
    expect($withPhoto->image_id)->not->toBeNull();
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

function makeAdjustmentTimesheet(mixed $context, Employee $employee, string $date): \App\Models\HumanResources\Timesheet
{
    $timesheet = \App\Models\HumanResources\Timesheet::create([
        'group_id'                  => $context->group->id,
        'organisation_id'           => $context->organisation->id,
        'date'                      => $date,
        'subject_type'              => 'Employee',
        'subject_id'                => $employee->id,
        'subject_name'              => $employee->contact_name,
        'start_at'                  => $date.' 08:00:00',
        'working_duration'          => 0,
        'breaks_duration'           => 0,
        'total_duration'            => 0,
        'number_time_trackers'      => 1,
        'number_open_time_trackers' => 1,
    ]);

    \App\Models\HumanResources\TimeTracker::create([
        'timesheet_id' => $timesheet->id,
        'subject_type' => 'Employee',
        'subject_id'   => $employee->id,
        'status'       => \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::OPEN,
        'starts_at'    => $date.' 08:00:00',
    ]);

    return $timesheet;
}

test('a correction requested from a timesheet keeps what was recorded and reads the time in the organisation timezone', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $timesheet = makeAdjustmentTimesheet($this, $employee, '2026-06-29');
    $timezone  = $this->organisation->timezone->name ?? 'UTC';

    $adjustment = StoreAttendanceAdjustment::make()->action($employee, [
        'timesheet_id'     => $timesheet->id,
        'date'             => '2026-06-29',
        'reason'           => 'Forgot to clock out',
        'requested_end_at' => '17:00',
    ]);

    expect($adjustment->timesheet_id)->toBe($timesheet->id)
        ->and($adjustment->original_start_at->equalTo($timesheet->start_at))->toBeTrue()
        ->and($adjustment->original_end_at)->toBeNull()
        ->and($adjustment->requested_start_at)->toBeNull()
        ->and($adjustment->requested_end_at->equalTo(\Illuminate\Support\Carbon::parse('2026-06-29 17:00', $timezone)))->toBeTrue();
});

test('approving a check-out only correction closes the day and leaves the recorded check-in alone', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $timesheet = makeAdjustmentTimesheet($this, $employee, '2026-06-26');

    $adjustment = AttendanceAdjustment::create([
        'group_id'         => $this->group->id,
        'organisation_id'  => $this->organisation->id,
        'employee_id'      => $employee->id,
        'employee_name'    => $employee->contact_name,
        'timesheet_id'     => $timesheet->id,
        'date'             => '2026-06-26',
        'reason'           => 'Forgot to clock out',
        'requested_end_at' => '2026-06-26 16:00:00',
        'status'           => \App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum::PENDING,
    ]);

    ApproveAttendanceAdjustment::make()->handle($adjustment);

    $timesheet->refresh();

    expect($timesheet->start_at->format('H:i'))->toBe('08:00')
        ->and($timesheet->end_at->format('H:i'))->toBe('16:00')
        ->and($timesheet->working_duration)->toBe(8 * 3600)
        ->and($timesheet->number_open_time_trackers)->toBe(0);
});

test('approving a request for a day with no clockings creates the clockings and the timesheet', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $adjustment = StoreAttendanceAdjustment::make()->action($employee, [
        'date'               => '2026-06-25',
        'reason'             => 'Clocking machine was down',
        'requested_start_at' => '08:00',
        'requested_end_at'   => '17:00',
    ]);

    expect($adjustment->timesheet_id)->toBeNull()
        ->and($adjustment->original_start_at)->toBeNull();

    ApproveAttendanceAdjustment::make()->handle($adjustment);

    $adjustment->refresh();
    $clockings = \App\Models\HumanResources\Clocking::where('timesheet_id', $adjustment->timesheet_id)->get();

    expect($adjustment->timesheet_id)->not->toBeNull()
        ->and($clockings)->toHaveCount(2)
        ->and($clockings->every(fn ($clocking) => $clocking->type === \App\Enums\HumanResources\Clocking\ClockingTypeEnum::MANUAL))->toBeTrue()
        ->and($adjustment->timesheet->working_duration)->toBe(9 * 3600);
});

test('a request needs a check-in or a check-out, and the check-out must come after the check-in', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    expect(fn () => StoreAttendanceAdjustment::make()->action($employee, [
        'date'   => '2026-06-24',
        'reason' => 'Nothing to change',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(fn () => StoreAttendanceAdjustment::make()->action($employee, [
        'date'               => '2026-06-24',
        'reason'             => 'Typo',
        'requested_start_at' => '17:00',
        'requested_end_at'   => '08:00',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('staff without the human resources position cannot approve or reject a request', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $adjustment = StoreAttendanceAdjustment::make()->action($employee, [
        'date'               => '2026-06-23',
        'reason'             => 'Forgot to clock in',
        'requested_start_at' => '08:00',
    ]);

    actingAs(User::factory()->create(['group_id' => $this->group->id]));

    $this->post(route('grp.org.hr.adjustments.approve', [$this->organisation->slug, $adjustment->id]))->assertForbidden();
    $this->post(route('grp.org.hr.adjustments.reject', [$this->organisation->slug, $adjustment->id]), ['approval_comment' => 'No'])->assertForbidden();

    expect($adjustment->refresh()->status)->toBe(\App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum::PENDING);
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

test('every page of the holidays, overtime and leave families keeps its top menu item active', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('authTo')->andReturn(true);

    $navigationBuilder = new class () {
        use \App\Actions\UI\Grp\Layout\WithLayoutNavigation;
    };

    $subSections = collect($navigationBuilder->getHumanResourcesNavs($user, $this->organisation, [])['hr']['topMenu']['subSections']);

    $activeMenuItems = fn (string $routeName) => $subSections
        ->filter(fn (array $subSection) => collect([$subSection['root'], ...($subSection['roots'] ?? [])])->contains(fn (string $root) => str_contains($routeName, $root)))
        ->pluck('route.name')
        ->values()
        ->all();

    $families = [
        'grp.org.hr.holidays.index' => ['grp.org.hr.calendars.', 'grp.org.hr.holidays.', 'grp.org.hr.holiday_years.', 'grp.org.hr.restricted_periods.'],
        'grp.org.hr.overtime.index' => ['grp.org.hr.overtime.', 'grp.org.hr.overtime_types.', 'grp.org.hr.overtime_requests.'],
        'grp.org.hr.leaves.index'   => ['grp.org.hr.leaves.', 'grp.org.hr.leave_approvers.', 'grp.org.hr.leave_concurrency_rules.'],
    ];

    $routeNames = collect(Route::getRoutes()->getRoutesByName())->keys();

    foreach ($families as $menuItemRoute => $prefixes) {
        $familyRoutes = $routeNames->filter(fn (string $routeName) => \Illuminate\Support\Str::startsWith($routeName, $prefixes));

        foreach ($prefixes as $prefix) {
            expect($familyRoutes->contains(fn (string $routeName) => str_starts_with($routeName, $prefix)))->toBeTrue();
        }

        foreach ($familyRoutes as $routeName) {
            expect($activeMenuItems($routeName))->toBe([$menuItemRoute]);
        }
    }

    foreach (['grp.org.hr.dashboard', 'grp.org.hr.employees.index', 'grp.org.hr.adjustments.dashboard', 'grp.org.hr.workplaces.show.clocking_machines.index'] as $routeName) {
        expect($activeMenuItems($routeName))->toHaveCount(1);
    }
});

test('overtime index filters by status and requested date range', function () {
    $index   = fn (array $filters) => IndexOvertime::make()->handle($this->organisation, null, $filters)->total();
    $inMarch = ['from' => '2031-03-01', 'to' => '2031-03-31'];

    $baseline = [
        'march'          => $index($inMarch),
        'march approved' => $index([...$inMarch, 'status' => 'approved']),
        'march pending'  => $index([...$inMarch, 'status' => 'pending']),
        'from april'     => $index(['from' => '2031-04-01']),
        'until february' => $index(['to' => '2031-02-28']),
    ];

    $pendingInMarch  = createOvertimeRequestFixture($this->organisation, $this->group);
    $approvedInMarch = createOvertimeRequestFixture($this->organisation, $this->group);
    $pendingInApril  = createOvertimeRequestFixture($this->organisation, $this->group);

    $pendingInMarch->update(['requested_date' => '2031-03-01']);
    $approvedInMarch->update(['requested_date' => '2031-03-31']);
    $pendingInApril->update(['requested_date' => '2031-04-01']);
    ApproveOvertimeRequest::make()->action($approvedInMarch);

    expect($index($inMarch))->toBe($baseline['march'] + 2)
        ->and($index([...$inMarch, 'status' => 'approved']))->toBe($baseline['march approved'] + 1)
        ->and($index([...$inMarch, 'status' => 'pending']))->toBe($baseline['march pending'] + 1)
        ->and($index(['from' => '2031-04-01']))->toBe($baseline['from april'] + 1)
        ->and($index(['to' => '2031-02-28']))->toBe($baseline['until february']);

    $rules = IndexOvertime::make()->rules();

    expect(Validator::make(['status' => 'approved', 'from' => '2031-03-01', 'to' => '2031-03-31'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['status' => 'not-a-status'], $rules)->errors()->keys())->toBe(['status'])
        ->and(Validator::make(['from' => '01/03/2031', 'to' => 'tomorrow'], $rules)->errors()->keys())->toBe(['from', 'to']);
});

test('overtime request with a verdict cannot be approved, rejected or edited again', function () {
    $approvedRequest = ApproveOvertimeRequest::make()->action(createOvertimeRequestFixture($this->organisation, $this->group));
    $rejectedRequest = RejectOvertimeRequest::make()->action(createOvertimeRequestFixture($this->organisation, $this->group));

    $editPayload = fn (OvertimeRequest $overtimeRequest) => [
        'employee_id' => $overtimeRequest->employee_id,
        'overtime_type_id' => $overtimeRequest->overtime_type_id,
        'requested_date' => '2026-07-02',
        'requested_duration_minutes' => 120,
        'status' => OvertimeRequestStatusEnum::PENDING,
    ];

    foreach ([$approvedRequest, $rejectedRequest] as $processedRequest) {
        $verdict = $processedRequest->status;

        expect(fn () => ApproveOvertimeRequest::make()->action($processedRequest))
            ->toThrow(\Illuminate\Validation\ValidationException::class, 'Only pending overtime requests can be approved.')
            ->and(fn () => RejectOvertimeRequest::make()->action($processedRequest))
            ->toThrow(\Illuminate\Validation\ValidationException::class, 'Only pending overtime requests can be rejected.')
            ->and(fn () => UpdateOvertimeRequest::make()->action($this->organisation, $processedRequest, $editPayload($processedRequest)))
            ->toThrow(\Illuminate\Validation\ValidationException::class, 'Only pending overtime requests can be edited.');

        $processedRequest->refresh();

        expect($processedRequest->status)->toBe($verdict)
            ->and($processedRequest->requested_duration_minutes)->toBe(60);
    }

    expect(DeleteOvertimeRequest::make()->action($approvedRequest))->toBeTrue();
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

test('manual and edited clockings stay on the timesheet date', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id' => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Timesheet Date Clocking Workplace ' . rand(100000, 999999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at' => now()->subDays(12)->setTime(8, 0)->toDateTimeString(),
    ], 0, true);

    $timesheet = $clocking->timesheet;
    $timezone  = $timesheet->organisation->timezone->name ?? 'UTC';
    $laterDay  = now($timezone)->setTime(16, 30)->toIso8601String();

    $manual = StoreManualClocking::make()->action($timesheet, ['clocked_at' => $laterDay]);
    $edited = UpdateClockingNotes::make()->handle($clocking, null, $laterDay);

    foreach ([$manual, $edited] as $anchored) {
        $local = $anchored->clocked_at->copy()->setTimezone($timezone);
        expect($local->toDateString())->toBe($timesheet->date->toDateString())
            ->and($local->format('H:i'))->toBe('16:30');
    }
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

test('holidays index filters by year and month from one page level filter', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $user->forgetWildcardPermissionIndex();
    actingAs($user);

    foreach (['Filter Fixture March' => '2033-03-14', 'Filter Fixture July' => '2033-07-21'] as $label => $date) {
        StoreHoliday::make()->action($this->organisation, [
            'type'  => \App\Enums\HumanResources\Holiday\HolidayTypeEnum::COMPANY,
            'from'  => $date,
            'to'    => $date,
            'label' => $label,
        ]);
    }

    $labels = fn ($rows) => collect($rows)->pluck('label');

    get(route('grp.org.hr.holidays.index', [$this->organisation->slug, 'filter' => ['year' => 2033, 'month' => 3]]))
        ->assertOk()
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('filters.year', '2033')
                ->where('filters.month', '3')
                ->where('yearOptions', fn ($options) => collect($options)->pluck('value')->contains('2033')
                    && collect($options)->pluck('value')->contains((string) now()->year))
                ->where('data.data', fn ($rows) => $labels($rows)->contains('Filter Fixture March')
                    && !$labels($rows)->contains('Filter Fixture July'))
                ->has('queryBuilderProps.default.filters', 0)
                ->etc()
        );

    get(route('grp.org.hr.holidays.index', [$this->organisation->slug, 'filter' => ['year' => 2033]]))
        ->assertOk()
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('filters.month', null)
                ->where('data.data', fn ($rows) => $labels($rows)->contains('Filter Fixture March')
                    && $labels($rows)->contains('Filter Fixture July'))
                ->etc()
        );

    get(route('grp.org.hr.holidays.index', $this->organisation->slug))
        ->assertOk()
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('filters.year', (string) now()->year)
                ->where('data.data', fn ($rows) => !$labels($rows)->contains('Filter Fixture March'))
                ->etc()
        );
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

test('shift schedule form payload keeps its name and working hours through validation', function () {
    $payload = [
        'name' => 'Renamed Shift',
        'working_hours' => [
            'data' => [
                1 => ['s' => '08:00', 'e' => '17:00', 'b' => [['s' => '12:00', 'e' => '13:00', 'n' => 'Lunch', 'p' => false]]],
                2 => ['s' => null, 'e' => null, 'b' => []],
            ],
        ],
    ];

    $validated = \Illuminate\Support\Facades\Validator::make($payload, UpdateWorkSchedule::make()->rules())->validated();

    expect($validated)->toBe($payload)
        ->and(\Illuminate\Support\Facades\Validator::make(['name' => ''], UpdateWorkSchedule::make()->rules())->fails())->toBeTrue();
});

test('shift schedule can be created with its working hours and edited without losing breaks', function () {
    $schedule = StoreWorkSchedule::make()->action($this->organisation, [
        'name' => 'Shift Created With Hours',
        'type' => 'shift',
        'working_hours' => [
            'data' => [
                1 => ['s' => '22:00', 'e' => '06:00', 'b' => [['s' => '01:00', 'e' => '01:30', 'n' => 'Rest', 'p' => true]]],
                2 => ['s' => null, 'e' => null, 'b' => []],
            ],
        ],
    ]);

    $loaded = \App\Actions\HumanResources\WorkSchedule\EditWorkSchedule::make()->handle($this->organisation, $schedule->fresh());
    $monday = $loaded->days->firstWhere('day_of_week', 1);
    $tuesday = $loaded->days->firstWhere('day_of_week', 2);

    expect($loaded->type)->toBe('shift')
        ->and($monday->is_working_day)->toBeTrue()
        ->and(substr($monday->start_time, 0, 5))->toBe('22:00')
        ->and(substr($monday->end_time, 0, 5))->toBe('06:00')
        ->and($monday->relationLoaded('breaks'))->toBeTrue()
        ->and($monday->breaks)->toHaveCount(1)
        ->and($monday->breaks->first()->break_name)->toBe('Rest')
        ->and($tuesday->is_working_day)->toBeFalse()
        ->and($loaded->days->firstWhere('day_of_week', 3))->toBeNull();

    $renamed = UpdateWorkSchedule::make()->action($this->organisation, $schedule, ['name' => 'Shift Renamed']);

    expect($renamed->name)->toBe('Shift Renamed');
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
        'email'           => 'qr-' . uniqid() . '@example.com',
        'worker_number'   => 'QR' . uniqid(),
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'cross-org-' . $employee->id,
        'password' => 'secret123',
    ]);

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o' . substr(uniqid(), -5),
                'name'  => 'Other Org ' . uniqid(),
                'email' => 'org-' . uniqid() . '@example.com',
            ]
        ));

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
        'email'           => 'qr-' . uniqid() . '@example.com',
        'worker_number'   => 'QR' . uniqid(),
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
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o' . substr(uniqid(), -5),
                'name'  => 'Other Org ' . uniqid(),
                'email' => 'org-' . uniqid() . '@example.com',
            ]
        ));

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
        'email'           => 'qr-' . uniqid() . '@example.com',
        'worker_number'   => 'QR' . uniqid(),
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'onsite-cross-org-' . $employee->id,
        'password' => 'secret123',
    ]);

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o' . substr(uniqid(), -5),
                'name'  => 'Other Org ' . uniqid(),
                'email' => 'org-' . uniqid() . '@example.com',
            ]
        ));

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
        ->toThrow(Exception::class, 'This is the Onsite Policy QR Machine ' . $employee->id . ' QR code');
});

test('a phone without location policy clocks in on a geofenced machine when the handset sends no coordinates', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'email'           => 'qr-' . uniqid() . '@example.com',
        'worker_number'   => 'QR' . uniqid(),
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'no-location-' . $employee->id,
        'password' => 'secret123',
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'No Location Workplace ' . $employee->id,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'No Location QR Machine ' . $employee->id,
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
        'label' => 'No location entrance',
    ]);

    actingAs($user);

    expect(fn () => ValidateClockingMachineQrCode::make()->handle($qrCode->hash))
        ->toThrow(Exception::class, 'Location access is required to validate this QR code.');

    \App\Models\HumanResources\ClockingMachineCoordinatePolicy::create([
        'organisation_id' => $this->organisation->id,
        'scope_type'      => 'employee',
        'scope_id'        => $employee->id,
        'mode'            => \App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum::NO_LOCATION,
        'is_active'       => true,
    ]);

    $result = ValidateClockingMachineQrCode::make()->handle($qrCode->hash);

    expect($result['effective_mode'])->toBe(\App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum::NO_LOCATION->value)
        ->and($result['clocking']->subject_id)->toBe($employee->id);
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

test('a QR scan log records the resolved employee, not the scanning user', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'email'           => 'qr-' . uniqid() . '@example.com',
        'worker_number'   => 'QR' . uniqid(),
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'scan-log-' . $employee->id,
        'password' => 'secret123',
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Scan Log Workplace ' . $employee->id,
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Scan Log QR Machine ' . $employee->id,
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
        'label' => 'Scan log entrance',
    ]);

    actingAs($user);

    expect(fn () => ValidateClockingMachineQrCode::make()->handle($qrCode->hash, 51.5, -0.12))
        ->toThrow(Exception::class);

    $log = \App\Models\HumanResources\QrScanLog::where('clocking_machine_id', $clockingMachine->id)
        ->latest('id')
        ->first();

    expect($log->employee_id)->toBe($employee->id);

    expect(fn () => ValidateClockingMachineQrCode::make()->handle('no-such-hash'))
        ->toThrow(Exception::class, 'Invalid QR Code.');

    $unresolvedLog = \App\Models\HumanResources\QrScanLog::latest('id')->first();

    expect($unresolvedLog->employee_id)->toBeNull();
});

describe('hr records leave on behalf of employee', function () {
    test('leave is created approved, past dates allowed, balance deducted', function () {
        $employee = Employee::factory()->create([
            'organisation_id' => $this->organisation->id,
            'group_id' => $this->group->id,
        ]);
        $leaveType = StoreLeaveType::make()->action($this->organisation, [
            'code' => 'SICKHR' . rand(1000, 9999),
            'name' => 'Sick leave',
            'category' => \App\Enums\HumanResources\Leave\LeaveCategoryEnum::MEDICAL,
        ]);

        $leave = StoreEmployeeLeave::make()->handle($employee, [
            'type' => $leaveType->code,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'reason' => 'Called in sick',
        ]);

        expect($leave->status)->toBe(LeaveStatusEnum::APPROVED)
            ->and($leave->approved_at)->not->toBeNull()
            ->and($leave->employee_id)->toBe($employee->id)
            ->and($leave->leave_type_id)->toBe($leaveType->id)
            ->and($leave->duration_days)->toBeGreaterThan(0);

        $balance = \App\Models\HumanResources\EmployeeLeaveBalance::where('employee_id', $employee->id)->first();
        expect((float) $balance->medical_used)->toBe((float) $leave->duration_days);
    });
});

test('public holidays are generated with the right movable feasts and weekend substitutions', function () {
    $generator = \App\Actions\HumanResources\Holiday\GeneratePublicHolidays::make();

    $uk2026 = collect($generator->holidaysFor('gb', 2026))->pluck('date', 'label');
    expect($uk2026['Good Friday'])->toBe('2026-04-03')
        ->and($uk2026['Easter Monday'])->toBe('2026-04-06')
        ->and($uk2026['Early May bank holiday'])->toBe('2026-05-04')
        ->and($uk2026['Spring bank holiday'])->toBe('2026-05-25')
        ->and($uk2026['Summer bank holiday'])->toBe('2026-08-31')
        // Boxing Day 2026 is a Saturday, so England and Wales take the Monday.
        ->and($uk2026['Boxing Day'])->toBe('2026-12-28');

    // 2027 pushes both Christmas and Boxing Day off the weekend, onto consecutive days.
    $uk2027 = collect($generator->holidaysFor('gb', 2027))->pluck('date', 'label');
    expect($uk2027['Christmas Day'])->toBe('2027-12-27')
        ->and($uk2027['Boxing Day'])->toBe('2027-12-28');

    // Slovakia does not substitute: Christmas Day 2027 stays on the Saturday.
    $sk2027 = collect($generator->holidaysFor('sk', 2027))->pluck('date', 'label');
    expect($sk2027['Prvý sviatok vianočný'])->toBe('2027-12-25')
        ->and($sk2027['Veľký piatok'])->toBe('2027-03-26');

    $es2026 = collect($generator->holidaysFor('es', 2026))->pluck('date', 'label');
    expect($es2026['Viernes Santo'])->toBe('2026-04-03')
        ->and($es2026['Fiesta Nacional de España'])->toBe('2026-10-12');
});

test('an employee marked as left gets an end date and their user is deactivated', function () {
    $employee = Employee::factory()->create([
        'organisation_id'     => $this->organisation->id,
        'group_id'            => $this->group->id,
        'state'               => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'employment_end_at'   => null,
        'email'               => 'leaver-' . uniqid() . '@example.com',
        'worker_number'       => 'LV' . uniqid(),
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'leaver-' . $employee->id,
        'password' => 'secret123',
    ]);

    $employee = UpdateEmployee::make()->action($employee->refresh(), [
        'state'             => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT,
        'employment_end_at' => null,
    ]);

    expect($employee->employment_end_at->format('Y-m-d'))->toBe(now()->format('Y-m-d'))
        ->and($user->refresh()->status)->toBeFalse();
});

test('an employee marked as left keeps the end date that was given', function () {
    $employee = Employee::factory()->create([
        'organisation_id'   => $this->organisation->id,
        'group_id'          => $this->group->id,
        'state'             => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'employment_end_at' => null,
        'email'             => 'leaver-' . uniqid() . '@example.com',
        'worker_number'     => 'LV' . uniqid(),
    ]);

    $employee = UpdateEmployee::make()->action($employee, [
        'state'             => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT,
        'employment_end_at' => now()->subMonth()->format('Y-m-d'),
    ]);

    expect($employee->employment_end_at->format('Y-m-d'))->toBe(now()->subMonth()->format('Y-m-d'));
});

test('giving the login back to somebody who left needs a reason, and it lands in the history', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT,
        'email'           => 'reinstated-' . uniqid() . '@example.com',
        'worker_number'   => 'RE' . uniqid(),
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'reinstated-' . $employee->id,
        'password' => 'secret123',
    ]);

    \App\Actions\SysAdmin\User\UpdateUser::make()->action($user, ['status' => false]);

    // Past the window where an audit folds back into the one before it
    $this->travel(5)->seconds();

    expect(fn () => \App\Actions\SysAdmin\User\UpdateUser::make()->action($user->refresh(), ['status' => true]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $user = \App\Actions\SysAdmin\User\UpdateUser::make()->action($user->refresh(), [
        'status' => true,
        'reason' => 'Covering the handover until the end of the month',
    ]);

    expect($user->status)->toBeTrue();

    $audit = \App\Models\Helpers\Audit::where('auditable_type', 'User')
        ->where('auditable_id', $user->id)
        ->latest('id')
        ->first();

    expect($audit->comments)->toBe('Covering the handover until the end of the month')
        ->and($audit->new_values['status'])->toBeTrue();
});

test('profile timesheets tab returns timesheets beyond today', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'email'           => 'profile-timesheets-' . uniqid() . '@example.com',
        'worker_number'   => 'PT' . uniqid(),
    ]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'profile-timesheets-' . $employee->id,
        'password' => 'secret123',
    ]);

    $this->travelTo(now()->startOfMonth()->addDays(14));

    StoreTimesheet::make()->action($employee, ['date' => now()]);
    StoreTimesheet::make()->action($employee, ['date' => now()->subDays(3)]);

    actingAs($user);

    $response = get(route('grp.profile.timesheets.index'), ['Accept' => 'application/json']);
    $response->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('an employee can be given a shorter week than the organisation works', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'email'           => 'four-day-'.uniqid().'@example.com',
        'worker_number'   => 'FD'.uniqid(),
    ]);

    // Monday to Friday, nine to five, as the organisation works
    $organisationHours = collect(range(1, 5))
        ->mapWithKeys(fn (int $day) => [$day => ['s' => '09:00', 'e' => '17:00']])
        ->all();

    $organisationSchedule = StoreWorkSchedule::make()->action($this->organisation, [
        'name'          => 'Org hours',
        'type'          => 'default',
        'working_hours' => ['data' => $organisationHours],
    ]);

    // this one does not work Fridays: the day is kept, and says nobody is expected in
    UpdateEmployee::make()->action($employee, [
        'working_hours' => [
            'data' => array_replace($organisationHours, [5 => ['s' => null, 'e' => null, 'w' => false, 'b' => []]]),
        ],
    ]);

    $schedule = $employee->refresh()->getDefaultWorkSchedule();

    expect($schedule)->not->toBeNull();

    $friday = $schedule->days()->where('day_of_week', 5)->first();
    $thursday = $schedule->days()->where('day_of_week', 4)->first();

    expect($friday)->not->toBeNull()
        ->and($friday->is_working_day)->toBeFalse()
        ->and($friday->start_time)->toBeNull()
        ->and($thursday->is_working_day)->toBeTrue()
        ->and($thursday->start_time)->toBe('09:00:00')
        ->and($schedule->days()->where('is_working_day', true)->count())->toBe(4);

    // and the organisation still works its Friday
    expect($organisationSchedule->days()->where('day_of_week', 5)->value('is_working_day'))->toBeTrue();
});

test('a day switched back on keeps its hours and a day switched off drops its breaks', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'email'           => 'breaks-'.uniqid().'@example.com',
        'worker_number'   => 'BR'.uniqid(),
    ]);

    UpdateEmployee::make()->action($employee, [
        'working_hours' => [
            'data' => [
                3 => ['s' => '08:00', 'e' => '16:00', 'b' => [['s' => '12:00', 'e' => '12:30', 'n' => 'Lunch', 'p' => false]]],
            ],
        ],
    ]);

    $schedule = $employee->refresh()->getDefaultWorkSchedule();
    expect($schedule->days()->where('day_of_week', 3)->first()->breaks()->count())->toBe(1);

    UpdateEmployee::make()->action($employee, [
        'working_hours' => [
            'data' => [
                3 => ['s' => '08:00', 'e' => '16:00', 'w' => false, 'b' => [['s' => '12:00', 'e' => '12:30', 'n' => 'Lunch', 'p' => false]]],
            ],
        ],
    ]);

    $wednesday = $schedule->refresh()->days()->where('day_of_week', 3)->first();

    expect($wednesday->is_working_day)->toBeFalse()
        ->and($wednesday->start_time)->toBeNull()
        ->and($wednesday->breaks()->count())->toBe(0);

    // switched on but with no hours is still not a day anybody works
    UpdateEmployee::make()->action($employee, [
        'working_hours' => [
            'data' => [
                3 => ['s' => null, 'e' => null, 'w' => true, 'b' => []],
                4 => ['s' => '08:00', 'e' => null, 'w' => true, 'b' => []],
            ],
        ],
    ]);

    $schedule->refresh();

    expect($schedule->days()->where('day_of_week', 3)->value('is_working_day'))->toBeFalse()
        ->and($schedule->days()->where('day_of_week', 4)->value('is_working_day'))->toBeFalse()
        ->and($schedule->days()->where('day_of_week', 4)->value('start_time'))->toBeNull();
});

test('a schedule saved the old way, without the working day flag, is unchanged', function () {
    $schedule = StoreWorkSchedule::make()->action($this->organisation, [
        'name' => 'Legacy payload',
        'type' => 'shift',
    ]);

    // what the organisation's own hours editor sends: no 'w' anywhere
    UpdateWorkSchedule::make()->action($this->organisation, $schedule, [
        'working_hours' => [
            'data' => [
                1 => ['s' => '09:00', 'e' => '17:00', 'b' => [['s' => '13:00', 'e' => '13:30', 'n' => 'Lunch']]],
                6 => ['s' => null, 'e' => null, 'b' => []],
            ],
        ],
    ]);

    $monday   = $schedule->days()->where('day_of_week', 1)->first();
    $saturday = $schedule->days()->where('day_of_week', 6)->first();

    expect($monday->is_working_day)->toBeTrue()
        ->and($monday->start_time)->toBe('09:00:00')
        ->and($monday->breaks()->count())->toBe(1)
        ->and($saturday->is_working_day)->toBeFalse()
        ->and($schedule->days()->count())->toBe(2);
});

test('attendance is judged against the employee week, not the organisation one', function () {
    $employee = Employee::factory()->create([
        'organisation_id'  => $this->organisation->id,
        'group_id'         => $this->group->id,
        'state'            => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'employment_type'  => \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME,
        'email'            => 'late-'.uniqid().'@example.com',
        'worker_number'    => 'LT'.uniqid(),
    ]);

    $everyDayNineToFive = collect(range(1, 7))
        ->mapWithKeys(fn (int $day) => [$day => ['s' => '09:00', 'e' => '17:00']])
        ->all();

    // the organisation works every day of the week
    StoreWorkSchedule::make()->action($this->organisation, [
        'name'          => 'Org every day '.uniqid(),
        'type'          => 'default',
        'working_hours' => ['data' => $everyDayNineToFive],
    ]);

    $lateClocking = new class () {
        use \App\Actions\HumanResources\Clocking\Traits\DeterminesClockingResult;

        public function check(Employee $employee, \Illuminate\Support\Carbon $at): bool
        {
            return $this->calculateLateClocking($employee, $at);
        }
    };

    // in the organisation's timezone, since that is the clock the check works against
    $timezone     = $this->organisation->timezone->name ?? config('app.timezone');
    $todayIso     = \Illuminate\Support\Carbon::today($timezone)->dayOfWeekIso;
    $twoHoursLate = \Illuminate\Support\Carbon::today($timezone)->setTimeFromTimeString('09:00')->addHours(2);

    // this employee does not work today, whatever the organisation says
    UpdateEmployee::make()->action($employee, [
        'working_hours' => [
            'data' => array_replace($everyDayNineToFive, [$todayIso => ['s' => null, 'e' => null, 'w' => false, 'b' => []]]),
        ],
    ]);

    expect($employee->refresh()->getEffectiveWorkSchedule()->days()->where('day_of_week', $todayIso)->value('is_working_day'))->toBeFalse()
        ->and($lateClocking->check($employee->refresh(), $twoHoursLate))->toBeFalse();

    // and once today is a day they do work, the same clocking is late
    UpdateEmployee::make()->action($employee, [
        'working_hours' => ['data' => $everyDayNineToFive],
    ]);

    expect($lateClocking->check($employee->refresh(), $twoHoursLate))->toBeTrue();
});

test('the clocking screen counts attendance against each employee own week', function () {
    $organisation = $this->organisation;

    $everyDayNineToFive = collect(range(1, 7))
        ->mapWithKeys(fn (int $day) => [$day => ['s' => '09:00', 'e' => '17:00']])
        ->all();

    // the organisation's own default schedule, not a second one beside it: only the first is
    // ever read back
    $organisationSchedule = $organisation->getDefaultWorkSchedule();

    if ($organisationSchedule) {
        UpdateWorkSchedule::make()->action($organisation, $organisationSchedule, [
            'working_hours' => ['data' => $everyDayNineToFive],
        ]);
    } else {
        StoreWorkSchedule::make()->action($organisation, [
            'name'          => 'Org every day '.uniqid(),
            'type'          => 'default',
            'working_hours' => ['data' => $everyDayNineToFive],
        ]);
    }

    $timezone = $organisation->timezone->name ?? config('app.timezone');
    $today    = \Illuminate\Support\Carbon::today($timezone);
    $todayIso = $today->dayOfWeekIso;

    $employee = Employee::factory()->create([
        'organisation_id' => $organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'email'           => 'stats-'.uniqid().'@example.com',
        'worker_number'   => 'ST'.uniqid(),
    ]);

    // clocked in two hours after the organisation's start
    $timesheet = StoreTimesheet::make()->action($employee, ['date' => $today->toDateString()]);
    // written with an explicit offset so the instant is unambiguous whatever the app and the
    // organisation are set to: two hours after the day starts, and away on time
    \Illuminate\Support\Facades\DB::table('timesheets')->where('id', $timesheet->id)->update([
        'start_at' => $today->copy()->setTimeFromTimeString('11:00')->utc()->format('Y-m-d H:i:sP'),
        'end_at'   => $today->copy()->setTimeFromTimeString('17:00')->utc()->format('Y-m-d H:i:sP'),
    ]);

    expect($timesheet->refresh()->start_at->copy()->setTimezone($timezone)->format('H:i'))->toBe('11:00');

    $lateCount = function () use ($employee, $timezone) {
        $action = IndexClockingEmployees::make();
        $statsQuery = \App\Models\HumanResources\Timesheet::where('subject_type', 'Employee')
            ->where('subject_id', $employee->id);

        $statistics = (new ReflectionMethod($action, 'getStatistics'))
            ->invoke($action, $employee->refresh(), $statsQuery, $timezone, null, null);

        return $statistics['late_clock_in'] ?? null;
    };

    expect($lateCount())->toBe(1);

    // the same clocking on a day this employee does not work is not counted
    UpdateEmployee::make()->action($employee, [
        'working_hours' => [
            'data' => array_replace($everyDayNineToFive, [$todayIso => ['s' => null, 'e' => null, 'w' => false, 'b' => []]]),
        ],
    ]);

    expect($lateCount())->toBe(0);
});

test('a human resources supervisor can create a holiday from the holidays page', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $user->forgetWildcardPermissionIndex();

    expect($user->authTo(["human-resources.{$this->organisation->id}.edit"]))->toBeTrue();

    actingAs($user);

    $response = post(route('grp.org.hr.holidays.store', $this->organisation->slug), [
        'type'         => \App\Enums\HumanResources\Holiday\HolidayTypeEnum::PUBLIC->value,
        'label'        => 'Día de la Hispanidad',
        'from'         => '2026-10-12',
        'to'           => '2026-10-12',
        'is_recurring' => true,
    ]);

    $response->assertRedirect();

    $holiday = \App\Models\HumanResources\Holiday::where('organisation_id', $this->organisation->id)
        ->where('label', 'Día de la Hispanidad')
        ->first();

    expect($holiday)->not->toBeNull()
        ->and($holiday->year)->toBe(2026)
        ->and(data_get($holiday->data, 'is_recurring'))->toBeTrue();
});

test('clocking photo does not appear in the group uploaded images gallery', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Photo Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at'   => now()->toDateTimeString(),
    ], 0, true);

    $imagePath = tempnam(sys_get_temp_dir(), 'clocking-photo-') . '.png';
    file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

    \App\Actions\HumanResources\Clocking\Traits\SetClockingPhotoFromImage::run($clocking, $imagePath, 'selfie.png', 'png');
    unlink($imagePath);

    $clocking->refresh();

    expect($clocking->image_id)->not->toBeNull()
        ->and($clocking->image->collection_name)->toBe('clocking_photo')
        ->and(
            \App\Models\Helpers\Media::where('group_id', $this->group->id)
                ->where('collection_name', 'image')
                ->pluck('id')
                ->all()
        )->not->toContain($clocking->image_id);
});

test('a staff avatar is not offered in the group image gallery', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $imagePath = tempnam(sys_get_temp_dir(), 'avatar-') . '.png';
    file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

    \App\Actions\Helpers\Media\SaveModelImage::run(
        model: $employee,
        imageData: ['path' => $imagePath, 'originalName' => 'me.png', 'extension' => 'png'],
        scope: 'avatar'
    );
    unlink($imagePath);

    $employee->refresh();

    expect($employee->image_id)->not->toBeNull()
        ->and($employee->image->collection_name)->toBe('avatar')
        ->and(
            \App\Models\Helpers\Media::where('group_id', $this->group->id)
                ->where('collection_name', 'image')
                ->pluck('id')
                ->all()
        )->not->toContain($employee->image_id);
});

test('job positions on an employee record that has left give its user no roles', function () {
    setPermissionsTeamId($this->group->id);

    $jobPosition = StoreJobPosition::make()->action($this->organisation, [
        'code'  => 'LFT'.rand(1000, 9999),
        'name'  => 'Left record position',
        'scope' => \App\Enums\HumanResources\JobPosition\JobPositionScopeEnum::ORGANISATION,
    ]);
    $role = \App\Models\SysAdmin\Role::where('name', RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_CLERK->value, $this->organisation))->first();
    $jobPosition->roles()->attach($role->id);

    $user = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);

    $leftRecord = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::LEFT,
    ]);
    SyncEmployeeJobPositions::make()->handle($leftRecord, [$jobPosition->id => []]);
    $user->employees()->attach($leftRecord->id, ['status' => true, 'group_id' => $this->group->id, 'organisation_id' => $this->organisation->id]);

    \App\Actions\SysAdmin\User\SyncRolesFromJobPositions::run($user);
    expect($user->fresh()->roles()->where('roles.id', $role->id)->exists())->toBeFalse();

    $leftRecord->update(['state' => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING]);
    \App\Actions\SysAdmin\User\SyncRolesFromJobPositions::run($user);
    expect($user->fresh()->roles()->where('roles.id', $role->id)->exists())->toBeTrue();

    $positionsAudit = $leftRecord->audits()->where('event', 'job_positions')->latest('id')->first();
    $rolesAudit     = $user->audits()->where('event', 'roles')->latest('id')->first();
    expect($positionsAudit->new_values)->toHaveKey('Left record position')
        ->and($positionsAudit->old_values)->toBe([])
        ->and($rolesAudit->new_values['added'])->toContain($role->name);
});

test('a worker position is dropped on the shops where the employee is already supervisor of the same department', function () {
    setPermissionsTeamId($this->group->id);
    $positionIds = JobPosition::where('organisation_id', $this->organisation->id)
        ->whereIn('code', ['shk-m', 'shk-c', 'cus-m', 'cus-c', 'hr-m', 'hr-c', 'dist-pik', 'dist-pak'])
        ->pluck('id', 'code');

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    SyncEmployeeJobPositions::make()->handle($employee, [
        $positionIds['shk-m']    => ['Shop' => [1, 2]],
        $positionIds['shk-c']    => ['Shop' => [1, 2]],
        $positionIds['cus-m']    => ['Shop' => [1]],
        $positionIds['cus-c']    => ['Shop' => [1, 2]],
        $positionIds['hr-m']     => [],
        $positionIds['hr-c']     => ['Organisation' => [$this->organisation->id]],
        $positionIds['dist-pik'] => ['Warehouse' => [1]],
        $positionIds['dist-pak'] => ['Warehouse' => [1]],
    ]);

    $scopes = $employee->jobPositions()->get()->mapWithKeys(fn (JobPosition $jobPosition) => [$jobPosition->code => $jobPosition->pivot->scopes]);

    expect($scopes->keys()->sort()->values()->all())->toBe(['cus-c', 'cus-m', 'dist-pak', 'dist-pik', 'hr-m', 'shk-m'])
        ->and($scopes['cus-c'])->toBe(['Shop' => [2]])
        ->and($scopes['shk-m'])->toBe(['Shop' => [1, 2]]);
});

test('pickers handle returns in goods in without seeing stock deliveries', function () {
    $warehouse = createWarehouse();
    setPermissionsTeamId($warehouse->group_id);
    \App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($warehouse->organisation);

    $picker          = JobPosition::where('organisation_id', $warehouse->organisation_id)->where('code', 'dist-pik')->firstOrFail();
    $returnsRoleName = RolesEnum::getRoleName(RolesEnum::RETURNS_CLERK->value, $warehouse);

    expect($picker->name)->toBe('Picker/Returns')
        ->and($picker->roles()->pluck('name')->all())->toContain($returnsRoleName);

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $warehouse->group_id]);
    $user->assignRole($returnsRoleName);

    $goodsIn  = \App\Actions\UI\Grp\Layout\GetWarehouseNavigation::run($warehouse, $user->fresh())['incoming'];
    $sections = collect($goodsIn['topMenu']['subSections'])->filter()->pluck('label')->values()->all();

    expect($user->hasPermissionTo("incoming.$warehouse->id.view"))->toBeFalse()
        ->and($goodsIn['route']['name'])->toBe('grp.org.warehouses.show.incoming.return_delivery_notes.state.received')
        ->and($sections)->toBe(['Returns']);

    $this->withoutVite();
    $returnsRoute        = route('grp.org.warehouses.show.incoming.return_delivery_notes.index', [$warehouse->organisation->slug, $warehouse->slug]);
    $stockDeliveriesRoute = route('grp.org.warehouses.show.incoming.stock_deliveries.index', [$warehouse->organisation->slug, $warehouse->slug]);

    $this->actingAs($user)->get($returnsRoute)->assertOk();
    $this->actingAs($user)->get($stockDeliveriesRoute)->assertForbidden();

    $userWithoutReturns = \App\Models\SysAdmin\User::factory()->create(['group_id' => $warehouse->group_id]);
    $this->actingAs($userWithoutReturns)->get($returnsRoute)->assertForbidden();
});

test('goods in supervisor and worker positions hold the goods in roles', function () {
    $warehouse = createWarehouse();
    setPermissionsTeamId($warehouse->group_id);
    \App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($warehouse->organisation);

    $supervisor = JobPosition::where('organisation_id', $warehouse->organisation_id)->where('code', 'gi-m')->firstOrFail();
    $worker     = JobPosition::where('organisation_id', $warehouse->organisation_id)->where('code', 'gi-c')->firstOrFail();

    expect($supervisor->roles()->pluck('name')->all())->toBe([RolesEnum::getRoleName(RolesEnum::GOODS_IN_SUPERVISOR->value, $warehouse)])
        ->and($worker->roles()->pluck('name')->all())->toBe([RolesEnum::getRoleName(RolesEnum::GOODS_IN_CLERK->value, $warehouse)]);
});

test('an admin holds no other position below them, so a customer service position does not make them a chat agent', function () {
    [$organisation, , $shop] = createShop();
    setPermissionsTeamId($organisation->group_id);

    $positionIds = $organisation->group->jobPositions()
        ->where(fn ($query) => $query->where('organisation_id', $organisation->id)->whereIn('code', ['org-admin', 'cus-m', 'hr-c'])->orWhere('code', 'group-admin'))
        ->pluck('id', 'code');

    $employee = Employee::factory()->create([
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
    ]);
    $user = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
    $user->employees()->attach($employee->id, ['status' => true, 'group_id' => $organisation->group_id, 'organisation_id' => $organisation->id]);

    SyncEmployeeJobPositions::make()->handle($employee, [
        $positionIds['org-admin'] => [],
        $positionIds['cus-m']     => ['Shop' => [$shop->id]],
        $positionIds['hr-c']      => ['Organisation' => [$organisation->id]],
    ]);
    \App\Actions\SysAdmin\User\SyncRolesFromJobPositions::run($user);
    $roles = $user->fresh()->roles()->pluck('name');

    expect($roles)->toContain("org-admin-$organisation->id", "shop-admin-$shop->id")
        ->not->toContain("customer-service-supervisor-$shop->id", "human-resources-clerk-$organisation->id")
        ->and($user->fresh()->hasPermissionTo("chat.$shop->id"))->toBeFalse();

    SyncEmployeeJobPositions::make()->handle($employee->fresh(), [
        $positionIds['group-admin'] => [],
        $positionIds['cus-m']       => ['Shop' => [$shop->id]],
    ]);
    \App\Actions\SysAdmin\User\SyncRolesFromJobPositions::run($user);
    $roles = $user->fresh()->roles()->pluck('name');

    expect($roles)->toContain('group-admin', "org-admin-$organisation->id", "shop-admin-$shop->id")
        ->not->toContain("customer-service-supervisor-$shop->id");
});

test('only accounting staff given the accounts orders position on a shop can create orders there', function () {
    [$organisation, , $shop] = createShop();
    setPermissionsTeamId($organisation->group_id);
    \App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions::run($shop);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($organisation);

    $employee = Employee::factory()->create(['organisation_id' => $organisation->id, 'group_id' => $organisation->group_id]);
    $user     = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
    $user->employees()->attach($employee->id, ['status' => true, 'group_id' => $organisation->group_id, 'organisation_id' => $organisation->id]);

    \App\Actions\SysAdmin\User\UpdateUserOrganisationPseudoJobPositions::make()->action($user, $organisation, ['permissions' => ['acc-m' => []]]);
    expect($user->fresh()->authTo("orders.$shop->id.edit"))->toBeFalse();

    \App\Actions\SysAdmin\User\UpdateUserOrganisationPseudoJobPositions::make()->action($user, $organisation, ['permissions' => ['acc-m' => [], 'acc-o' => ['shops' => [$shop->slug]]]]);
    $user = $user->fresh();

    expect($employee->fresh()->jobPositions()->where('code', 'acc-o')->first()->pivot->scopes)->toBe(['Shop' => [$shop->id]])
        ->and($user->roles()->pluck('name'))->toContain(RolesEnum::getRoleName(RolesEnum::ACCOUNTING_ORDERS->value, $shop))
        ->and($user->authTo("orders.$shop->id.edit"))->toBeTrue()
        ->and($user->authTo("chat.$shop->id"))->toBeFalse();
});

test('staff attachment downloads need permission on what the file is attached to', function () {
    Storage::fake('local');
    setPermissionsTeamId($this->group->id);
    $customer = createCustomer(createShop()[2]);

    $employee   = Employee::factory()->create(['organisation_id' => $this->organisation->id, 'group_id' => $this->group->id]);
    $employeeCv = createAttachedMedia('Employee', $employee->id, 'CV');
    $customerNote = createAttachedMedia('Customer', $customer->id, 'CustomerNote');
    $productSds   = createAttachedMedia('Product', 1, 'sds');

    $hrClerk = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $hrClerk->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_CLERK->value, $this->organisation));
    $colleague = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $ownUser   = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $ownUser->employees()->attach($employee->id, ['status' => true, 'group_id' => $this->group->id, 'organisation_id' => $this->organisation->id]);

    $download = fn (User $user, $media) => $this->actingAs($user)->get(route('grp.media.download', $media->ulid));

    $download($hrClerk, $employeeCv)->assertOk();
    $download($ownUser, $employeeCv)->assertOk();
    $download($colleague, $employeeCv)->assertNotFound();
    $download($hrClerk, $customerNote)->assertNotFound();
    $download($colleague, $productSds)->assertOk();

    $warehouse = createWarehouse();
    \App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);
    $supplier    = \App\Actions\SupplyChain\Supplier\StoreSupplier::make()->action($this->group, \App\Models\SupplyChain\Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $warehouse->organisation_id)->first();
    $stockDelivery = \App\Actions\GoodsIn\StockDelivery\StoreStockDelivery::make()->action($orgSupplier, ['reference' => 'INI021-'.rand(1000, 9999), 'date' => date('Y-m-d')]);
    $deliveryPaperwork = createAttachedMedia('StockDelivery', $stockDelivery->id, 'Delivery Paperwork');

    $goodsIn = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $goodsIn->givePermissionTo("incoming.$warehouse->id.view");

    $download($goodsIn, $deliveryPaperwork)->assertOk();
    $download($colleague, $deliveryPaperwork)->assertNotFound();
});

test('the marketing/offers position can create and edit offers and see orders but not edit the website', function () {
    $roles = fn (string $code) => config("blueprint.job_positions.positions.$code.roles");

    $permissions = collect($roles('mrk-m'))->flatMap(fn (\App\Enums\SysAdmin\Authorisation\RolesEnum $role) => $role->getPermissions());

    expect($permissions)->toContain(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::DISCOUNTS)
        ->toContain(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::MARKETING)
        ->toContain(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::ORDERS)
        ->not->toContain(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::WEB_EDIT)
        ->not->toContain(\App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum::SUPERVISOR_WEB);
});

test('the new employee form shows login errors on its own fields', function () {
    $orgAdmin = JobPosition::where('organisation_id', $this->organisation->id)->where('code', 'org-admin')->firstOrFail();
    $administrator = StoreEmployee::make()->action($this->organisation, [
        'worker_number'   => 'hr-form-admin',
        'alias'           => 'hr-form-admin',
        'contact_name'    => 'Hr Form Admin',
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $orgAdmin->slug, 'scopes' => []]],
        'username'        => 'hr-form-admin',
        'password'        => 'secret-password-123',
    ]);
    actingAs($administrator->getUser());

    $newEmployee = fn (string $username) => [
        'contact_name'    => 'Bicky Shrestha',
        'type'            => EmployeeTypeEnum::EMPLOYEE->value,
        'employment_type' => EmploymentTypeEnum::FULL_TIME->value,
        'worker_number'   => 'AWN01',
        'alias'           => 'Bicky',
        'state'           => EmployeeStateEnum::WORKING->value,
        'username'        => $username,
        'password'        => 'Abcdefgh1234',
    ];

    $this->post(route('grp.models.org.employee.store', $this->organisation->id), $newEmployee('Bicky'))
        ->assertSessionHasErrors('username');
    expect(Employee::where('organisation_id', $this->organisation->id)->where('alias', 'Bicky')->exists())->toBeFalse();

    $administrator->getUser()->update(['email' => 'taken-by-a-user@example.com']);
    $this->post(route('grp.models.org.employee.store', $this->organisation->id), array_merge($newEmployee('bicky'), ['work_email' => 'taken-by-a-user@example.com']))
        ->assertSessionHasErrors('work_email');
    expect(Employee::where('organisation_id', $this->organisation->id)->where('alias', 'Bicky')->exists())->toBeFalse();

    $this->post(route('grp.models.org.employee.store', $this->organisation->id), $newEmployee('bicky'))
        ->assertSessionHasNoErrors();
    expect(Employee::where('organisation_id', $this->organisation->id)->where('alias', 'Bicky')->firstOrFail()->getUser()->username)->toBe('bicky');
});

test('a human resources supervisor can open the organisation clockings and employee analytics pages', function () {
    config(['employee-analytics.enabled' => true]);
    setPermissionsTeamId($this->organisation->group_id);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $user->forgetWildcardPermissionIndex();
    actingAs($user);

    $employee = StoreEmployee::make()->action($this->organisation, array_merge(Employee::factory()->make()->toArray(), [
        'worker_number'   => 'analytics-1',
        'alias'           => 'analytics-1',
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'state'           => EmployeeStateEnum::WORKING,
    ]));
    $contract = StoreEmployeeContract::make()->action($employee, [
        'start_date'        => now()->subMonth()->toDateString(),
        'annual_leave_days' => 20,
    ]);
    EmployeeLeaveBalance::updateOrCreate(['employee_id' => $employee->id, 'employee_contract_id' => $contract->id], ['annual_used' => 3]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Analytics Workplace',
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);
    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, ['type' => 'in', 'at' => now()->toDateTimeString()], 0, true);

    get(route('grp.org.hr.clockings.index', $this->organisation->slug))->assertOk();
    get(route('grp.org.hr.clockings.show', [$this->organisation->slug, $clocking->id]))
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('pageHead.actions.1.key', 'delete')->where('pageHead.actions.1.route.name', 'grp.models.clocking-machine.clocking.delete'));
    get(route('grp.org.hr.clockings.edit', [$this->organisation->slug, $clocking->id]))
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('formData.args.updateRoute.name', 'grp.models.clocking-machine.clocking.notes.update'));

    \Pest\Laravel\from(route('grp.org.hr.clockings.edit', [$this->organisation->slug, $clocking->id]))
        ->patch(route('grp.models.clocking-machine.clocking.notes.update', $clocking->id), ['notes' => 'forgot to clock in'], ['X-Inertia' => 'true'])
        ->assertRedirect(route('grp.org.hr.clockings.edit', [$this->organisation->slug, $clocking->id]));
    expect($clocking->fresh()->notes)->toBe('forgot to clock in');

    \Pest\Laravel\delete(route('grp.models.clocking-machine.clocking.delete', ['clocking' => $clocking->id, 'from_clocking_page' => 1]))
        ->assertRedirect(route('grp.org.hr.clockings.index', $this->organisation->slug));
    expect(\App\Models\HumanResources\Clocking::find($clocking->id))->toBeNull();
    get(route('grp.org.hr.analytics.show', [$this->organisation->slug, $employee->slug]))
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('analytics.leave.leave_balance.annual_remaining', 17));
});

test('employee analytics attendance only expects the working days that have already happened', function () {
    \Carbon\Carbon::setTestNow('2026-10-14 10:00:00');

    try {
        $employee = StoreEmployee::make()->action($this->organisation, array_merge(Employee::factory()->make()->toArray(), [
            'worker_number'       => 'analytics-elapsed-1',
            'alias'               => 'analytics-elapsed-1',
            'type'                => EmployeeTypeEnum::EMPLOYEE,
            'employment_type'     => EmploymentTypeEnum::FULL_TIME,
            'state'               => EmployeeStateEnum::WORKING,
            'employment_start_at' => '2026-01-01',
        ]));

        foreach (['2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14'] as $date) {
            \App\Models\HumanResources\Timesheet::create([
                'group_id'                  => $this->group->id,
                'organisation_id'           => $this->organisation->id,
                'date'                      => $date,
                'subject_type'              => 'Employee',
                'subject_id'                => $employee->id,
                'subject_name'              => $employee->contact_name,
                'start_at'                  => $date.' 08:00:00',
                'working_duration'          => 8 * 3600,
                'breaks_duration'           => 0,
                'total_duration'            => 8 * 3600,
                'number_time_trackers'      => 1,
                'number_open_time_trackers' => 0,
            ]);
        }

        $service = new \App\Services\EmployeeAnalyticsService();
        $attendance = $service->calculateAttendanceMetrics($employee, \Carbon\Carbon::parse('2026-10-08'), \Carbon\Carbon::parse('2026-10-31'));
        $summary = $service->calculateSummaryMetrics($attendance, ['total_leave_days' => 0]);

        expect($attendance['working_days'])->toBeGreaterThan(0)->toBeLessThanOrEqual(7)
            ->and($attendance['present_days'])->toBe($attendance['working_days'])
            ->and($attendance['absent_days'])->toBe(0)
            ->and($summary['attendance_percentage'])->toBe(100.0);

        $future = $service->calculateAttendanceMetrics($employee, \Carbon\Carbon::parse('2026-10-20'), \Carbon\Carbon::parse('2026-10-31'));

        expect($future['working_days'])->toBe(0)
            ->and($future['attendance_eligible_days'])->toBe(0);
    } finally {
        \Carbon\Carbon::setTestNow();
    }
});

test('employee analytics reports the attendance window it measures and the days behind each percentage', function () {
    config(['employee-analytics.enabled' => true]);
    setPermissionsTeamId($this->organisation->group_id);

    $viewer = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $viewer->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $viewer->forgetWildcardPermissionIndex();
    actingAs($viewer);

    $employee = StoreEmployee::make()->action($this->organisation, array_merge(Employee::factory()->make()->toArray(), [
        'worker_number'       => 'analytics-window-1',
        'alias'               => 'analytics-window-1',
        'type'                => EmployeeTypeEnum::EMPLOYEE,
        'employment_type'     => EmploymentTypeEnum::FULL_TIME,
        'state'               => EmployeeStateEnum::WORKING,
        'employment_start_at' => '2026-01-01',
    ]));

    $activeScheduleIds = WorkSchedule::query()
        ->where('schedulable_type', 'Organisation')
        ->where('schedulable_id', $this->organisation->id)
        ->where('is_active', true)
        ->pluck('id');
    WorkSchedule::whereIn('id', $activeScheduleIds)->update(['is_active' => false]);
    \Carbon\Carbon::setTestNow('2026-10-12 10:00:00');

    try {
        foreach (['2026-10-01', '2026-10-02', '2026-10-05'] as $date) {
            \App\Models\HumanResources\Timesheet::create([
                'group_id'                  => $this->group->id,
                'organisation_id'           => $this->organisation->id,
                'date'                      => $date,
                'subject_type'              => 'Employee',
                'subject_id'                => $employee->id,
                'subject_name'              => $employee->contact_name,
                'start_at'                  => $date.' 08:00:00',
                'working_duration'          => 8 * 3600,
                'breaks_duration'           => 0,
                'total_duration'            => 8 * 3600,
                'number_time_trackers'      => 1,
                'number_open_time_trackers' => 0,
            ]);
        }

        $service = new \App\Services\EmployeeAnalyticsService();
        $window = fn (string $from, string $to): array => $service->getAttendanceWindow($this->organisation->id, \Carbon\Carbon::parse($from), \Carbon\Carbon::parse($to));

        $toDate = $window('2026-10-01', '2026-10-31');
        expect($toDate['status'])->toBe('to_date')
            ->and($toDate['start'])->toBe('2026-10-01')
            ->and($toDate['end'])->toBe('2026-10-12')
            ->and($toDate['today'])->toBe('2026-10-12')
            ->and($toDate['working_days'])->toBe(8)
            ->and($toDate['employees_measured'])->toBeGreaterThanOrEqual(1);

        $past = $window('2026-09-01', '2026-09-30');
        expect($past['status'])->toBe('past')
            ->and($past['start'])->toBe('2026-09-01')
            ->and($past['end'])->toBe('2026-09-30')
            ->and($past['working_days'])->toBe(22);

        $future = $window('2026-11-01', '2026-11-30');
        expect($future['status'])->toBe('future')
            ->and($future['end'])->toBeNull()
            ->and($future['working_days'])->toBe(0)
            ->and($future['employees_measured'])->toBe(0);

        $row = collect($service->getEmployeeAttendanceBreakdown($this->organisation->id, \Carbon\Carbon::parse('2026-10-01'), \Carbon\Carbon::parse('2026-10-31'), 100000))
            ->firstWhere('id', $employee->id);

        expect($row['present_days'])->toBe(3)
            ->and($row['attendance_eligible_days'])->toBe(8)
            ->and($row['attendance_percentage'])->toEqual(37.5);

        get(route('grp.org.hr.analytics.index', $this->organisation->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attendance_window.status', 'to_date')
                ->where('attendance_window.start', '2026-10-01')
                ->where('attendance_window.end', '2026-10-12')
                ->where('attendance_window.working_days', 8)
                ->has('attendance_breakdown.0.present_days')
                ->has('attendance_breakdown.0.attendance_eligible_days')
                ->has('attendance_breakdown.0.avatar')
                ->etc());
    } finally {
        \Carbon\Carbon::setTestNow();
        WorkSchedule::whereIn('id', $activeScheduleIds)->update(['is_active' => true]);
    }
});

test('employee analytics counts approved leave in days, halves half days and clips a leave to the selected range', function () {
    $employee = StoreEmployee::make()->action($this->organisation, array_merge(Employee::factory()->make()->toArray(), [
        'worker_number'       => 'analytics-leave-1',
        'alias'               => 'analytics-leave-1',
        'type'                => EmployeeTypeEnum::EMPLOYEE,
        'employment_type'     => EmploymentTypeEnum::FULL_TIME,
        'state'               => EmployeeStateEnum::WORKING,
        'employment_start_at' => '2026-01-01',
    ]));

    $makeLeave = fn (string $from, string $to, int $days, LeaveStatusEnum $status, bool $isHalfDay = false): Leave => Leave::create([
        'group_id'        => $employee->group_id,
        'organisation_id' => $employee->organisation_id,
        'employee_id'     => $employee->id,
        'employee_name'   => $employee->contact_name,
        'type'            => 'annual',
        'start_date'      => $from,
        'end_date'        => $to,
        'duration_days'   => $days,
        'is_half_day'     => $isHalfDay,
        'session'         => $isHalfDay ? 'Morning' : 'Full',
        'status'          => $status,
    ]);

    $activeScheduleIds = WorkSchedule::query()
        ->where('schedulable_type', 'Organisation')
        ->where('schedulable_id', $this->organisation->id)
        ->where('is_active', true)
        ->pluck('id');
    WorkSchedule::whereIn('id', $activeScheduleIds)->update(['is_active' => false]);
    \Carbon\Carbon::setTestNow('2029-06-29 10:00:00');

    try {
        $rangeStart = \Carbon\Carbon::parse('2029-06-04');
        $rangeEnd = \Carbon\Carbon::parse('2029-06-15');
        $service = new \App\Services\EmployeeAnalyticsService();

        expect($service->calculateLeaveMetrics($employee, $rangeStart, $rangeEnd)['total_leave_days'])->toEqual(0);

        $makeLeave('2029-06-12', '2029-06-12', 1, LeaveStatusEnum::PENDING);
        $makeLeave('2029-06-12', '2029-06-12', 1, LeaveStatusEnum::REJECTED);
        $makeLeave('2029-06-12', '2029-06-12', 1, LeaveStatusEnum::APPROVED)->delete();

        expect($service->calculateLeaveMetrics($employee, $rangeStart, $rangeEnd)['total_leave_days'])->toEqual(0);

        $makeLeave('2029-06-04', '2029-06-08', 5, LeaveStatusEnum::APPROVED);
        expect($service->calculateLeaveMetrics($employee, $rangeStart, $rangeEnd)['total_leave_days'])->toEqual(5);

        $makeLeave('2029-06-11', '2029-06-11', 1, LeaveStatusEnum::APPROVED, true);
        expect($service->calculateLeaveMetrics($employee, $rangeStart, $rangeEnd)['total_leave_days'])->toEqual(5.5);

        $makeLeave('2029-06-13', '2029-06-20', 6, LeaveStatusEnum::APPROVED);
        $leave = $service->calculateLeaveMetrics($employee, $rangeStart, $rangeEnd);
        expect($leave['total_leave_days'])->toEqual(8.5)
            ->and($leave['leave_breakdown']['annual'])->toEqual(8.5);

        \App\Models\HumanResources\Timesheet::create([
            'group_id'                  => $this->group->id,
            'organisation_id'           => $this->organisation->id,
            'date'                      => '2029-06-11',
            'subject_type'              => 'Employee',
            'subject_id'                => $employee->id,
            'subject_name'              => $employee->contact_name,
            'start_at'                  => '2029-06-11 13:00:00',
            'working_duration'          => 4 * 3600,
            'breaks_duration'           => 0,
            'total_duration'            => 4 * 3600,
            'number_time_trackers'      => 1,
            'number_open_time_trackers' => 0,
        ]);

        $attendance = $service->calculateAttendanceMetrics($employee, $rangeStart, $rangeEnd);
        $summary = $service->calculateSummaryMetrics($attendance, $leave);

        expect($attendance['working_days'])->toBe(10)
            ->and($attendance['total_leave_days'])->toEqual(8.5)
            ->and($attendance['attendance_eligible_days'])->toEqual(1.5)
            ->and($attendance['present_days'])->toEqual(0.5)
            ->and($attendance['absent_days'])->toEqual(1)
            ->and($summary['attendance_percentage'])->toEqual(33.33);

        $organisationLeave = collect($service->getTopEmployeesByLeave($this->organisation->id, $rangeStart, $rangeEnd, 1000))
            ->firstWhere('id', $employee->id);

        expect($organisationLeave['total_leave_days'])->toEqual(8.5);
    } finally {
        \Carbon\Carbon::setTestNow();
        WorkSchedule::whereIn('id', $activeScheduleIds)->update(['is_active' => true]);
    }
});

test('shopkeeper clerks are merged into the supervisor position on all the shops they held', function () {
    setPermissionsTeamId($this->group->id);
    $positionIds = JobPosition::where('organisation_id', $this->organisation->id)
        ->whereIn('code', ['shk-m', 'shk-c', 'hr-c'])
        ->pluck('id', 'code');

    $clerk = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    SyncEmployeeJobPositions::make()->handle($clerk, [
        $positionIds['shk-c'] => ['Shop' => [1, 2]],
        $positionIds['hr-c']  => [],
    ]);

    $mixed = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    SyncEmployeeJobPositions::make()->handle($mixed, [
        $positionIds['shk-m'] => ['Shop' => [1]],
        $positionIds['shk-c'] => ['Shop' => [2, 3]],
    ]);

    \App\Actions\HumanResources\JobPosition\RepairMergeJobPositions::make()->handle('shk-c', 'shk-m');

    $scopesOf = fn (Employee $employee) => $employee->jobPositions()->get()->mapWithKeys(fn (JobPosition $jobPosition) => [$jobPosition->code => $jobPosition->pivot->scopes])->sortKeys()->all();

    expect($scopesOf($clerk))->toBe(['hr-c' => [], 'shk-m' => ['Shop' => [1, 2]]])
        ->and($scopesOf($mixed))->toBe(['shk-m' => ['Shop' => [1, 2, 3]]]);

    $customerServiceIds = JobPosition::where('organisation_id', $this->organisation->id)->whereIn('code', ['cus-m', 'cus-c'])->pluck('id', 'code');
    $supervisor         = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    SyncEmployeeJobPositions::make()->handle($supervisor, [$customerServiceIds['cus-m'] => ['Shop' => [1, 2]]]);

    \App\Actions\HumanResources\JobPosition\RepairMergeJobPositions::make()->handle('cus-m', 'cus-c,cus-call');

    expect($scopesOf($supervisor))->toBe(['cus-c' => ['Shop' => [1, 2]], 'cus-call' => ['Shop' => [1, 2]]]);
});

test('every non-shop department has a view-only position that gives way to a higher grade', function () {
    setPermissionsTeamId($this->group->id);
    \App\Actions\SysAdmin\Group\Seeders\SeedJobPositionCategories::run($this->group);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($this->organisation);

    $viewerCodes = ['hr-v', 'acc-v', 'buy-v', 'wah-v', 'gi-v', 'dist-v', 'prod-v'];
    $positions   = JobPosition::where('organisation_id', $this->organisation->id)->whereIn('code', [...$viewerCodes, 'wah-sc'])->get()->keyBy('code');

    expect($positions->keys()->sort()->values()->all())->toBe(collect([...$viewerCodes, 'wah-sc'])->sort()->values()->all());

    foreach ([...$viewerCodes, 'ful-v'] as $code) {
        $permissions = collect(config("blueprint.job_positions.positions.$code.roles"))
            ->flatMap(fn (\App\Enums\SysAdmin\Authorisation\RolesEnum $role) => $role->getPermissions())
            ->map(fn ($permission) => $permission->value);

        expect($permissions)->not->toBeEmpty()
            ->and($permissions->reject(fn (string $permission) => str_ends_with($permission, '.view'))->all())->toBe([]);
    }

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    SyncEmployeeJobPositions::make()->handle($employee, [
        $positions['wah-sc']->id => ['Warehouse' => [1]],
        $positions['wah-v']->id  => ['Warehouse' => [1, 2]],
        $positions['hr-v']->id   => [],
    ]);

    $scopes = $employee->jobPositions()->get()->mapWithKeys(fn (JobPosition $jobPosition) => [$jobPosition->code => $jobPosition->pivot->scopes])->sortKeys()->all();

    expect($scopes)->toBe(['hr-v' => [], 'wah-sc' => ['Warehouse' => [1]], 'wah-v' => ['Warehouse' => [2]]]);
});

test('a section supervisor sees, read only, only the HR pages of the employees in their section', function () {
    $warehouse    = createWarehouse();
    $organisation = $warehouse->organisation;
    setPermissionsTeamId($warehouse->group_id);
    \App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($organisation);

    $positions = JobPosition::where('organisation_id', $organisation->id)->whereIn('code', ['wah-m', 'wah-sc', 'acc-c'])->get()->keyBy('code');

    $newEmployee = function () use ($organisation) {
        $employee = Employee::factory()->create([
            'organisation_id' => $organisation->id,
            'group_id'        => $organisation->group_id,
            'state'           => EmployeeStateEnum::WORKING,
        ]);
        $employee->stats()->create();

        return $employee;
    };

    $warehouseClerk = $newEmployee();
    SyncEmployeeJobPositions::make()->handle($warehouseClerk, [$positions['wah-sc']->id => ['Warehouse' => [$warehouse->id]]]);

    $accountingClerk = $newEmployee();
    SyncEmployeeJobPositions::make()->handle($accountingClerk, [$positions['acc-c']->id => []]);

    $supervisor = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
    \App\Actions\SysAdmin\User\SyncUserPseudoOrganisationJobPositions::make()->handle($supervisor, $organisation, [$positions['wah-m']->id => ['Warehouse' => [$warehouse->id]]]);
    $supervisor->refresh();

    $visibleEmployeeIds = \App\Actions\HumanResources\Employee\GetSectionSupervisedEmployeeIds::run($supervisor, $organisation);

    expect($visibleEmployeeIds)->toContain($warehouseClerk->id)
        ->not->toContain($accountingClerk->id);

    actingAs($supervisor);

    get(route('grp.org.hr.dashboard', $organisation->slug))->assertOk();
    get(route('grp.org.hr.employees.index', $organisation->slug))->assertOk();
    get(route('grp.org.hr.employees.show', [$organisation->slug, $warehouseClerk->slug]))->assertOk();
    get(route('grp.org.hr.employees.show', [$organisation->slug, $accountingClerk->slug]))->assertForbidden();
    get(route('grp.org.hr.employees.edit', [$organisation->slug, $warehouseClerk->slug]))->assertForbidden();
    get(route('grp.org.hr.timesheets.index', $organisation->slug))->assertOk();
    get(route('grp.org.hr.clockings.index', $organisation->slug))->assertOk();
    get(route('grp.org.hr.workplaces.index', $organisation->slug))->assertForbidden();
    get(route('grp.org.hr.job_positions.index', $organisation->slug))->assertForbidden();

    $outsider = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);

    expect(\App\Actions\HumanResources\Employee\GetSectionSupervisedEmployeeIds::run($outsider, $organisation))->toBe([]);

    $goodsInSupervisor = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
    \App\Actions\SysAdmin\User\SyncUserPseudoOrganisationJobPositions::make()->handle(
        $goodsInSupervisor,
        $organisation,
        [JobPosition::where('organisation_id', $organisation->id)->where('code', 'gi-m')->value('id') => ['Warehouse' => [$warehouse->id]]]
    );

    expect(\App\Actions\HumanResources\Employee\GetSectionSupervisedEmployeeIds::run($goodsInSupervisor->refresh(), $organisation))->toBe([]);

    actingAs($goodsInSupervisor);

    get(route('grp.org.hr.employees.index', $organisation->slug))->assertOk();
    get(route('grp.org.hr.employees.show', [$organisation->slug, $warehouseClerk->slug]))->assertForbidden();
    get(route('grp.org.hr.workplaces.index', $organisation->slug))->assertForbidden();
});

test('warehouse supervisors see their warehouse team and add clockings from the warehouse', function () {
    $warehouse = createWarehouse();
    setPermissionsTeamId($warehouse->group_id);
    \App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($warehouse->organisation);
    $organisation = $warehouse->organisation;

    if (!$organisation->workplaces()->exists()) {
        StoreWorkplace::make()->action($organisation, [
            'name' => 'Warehouse team workplace',
            'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
        ]);
    }

    $picker = JobPosition::where('organisation_id', $organisation->id)->where('code', 'dist-pik')->firstOrFail();
    $buyer  = JobPosition::where('organisation_id', $organisation->id)->where('code', 'buy')->firstOrFail();

    $teamMember = Employee::factory()->create(['organisation_id' => $organisation->id, 'group_id' => $organisation->group_id, 'state' => EmployeeStateEnum::WORKING]);
    SyncEmployeeJobPositions::run($teamMember, [$picker->id => ['Warehouse' => [$warehouse->id]]]);
    $otherWarehouseMember = Employee::factory()->create(['organisation_id' => $organisation->id, 'group_id' => $organisation->group_id, 'state' => EmployeeStateEnum::WORKING]);
    SyncEmployeeJobPositions::run($otherWarehouseMember, [$picker->id => ['Warehouse' => [$warehouse->id + 1000]]]);
    $outsider = Employee::factory()->create(['organisation_id' => $organisation->id, 'group_id' => $organisation->group_id, 'state' => EmployeeStateEnum::WORKING]);
    SyncEmployeeJobPositions::run($outsider, [$buyer->id => []]);

    $teamIds = \App\Actions\Inventory\WarehouseTeam\UI\ShowWarehouseTeam::teamQuery($warehouse)->pluck('employees.id');
    expect($teamIds)->toContain($teamMember->id)
        ->not->toContain($otherWarehouseMember->id)
        ->not->toContain($outsider->id);

    $manager = \App\Models\SysAdmin\User::factory()->create(['group_id' => $warehouse->group_id]);
    $manager->assignRole(RolesEnum::getRoleName(RolesEnum::WAREHOUSE_ADMIN->value, $warehouse));
    $clerk = \App\Models\SysAdmin\User::factory()->create(['group_id' => $warehouse->group_id]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::DISPATCH_CLERK->value, $warehouse));

    expect(\App\Actions\UI\Grp\Layout\GetWarehouseNavigation::run($warehouse, $manager->fresh()))->toHaveKey('team')
        ->and(\App\Actions\UI\Grp\Layout\GetWarehouseNavigation::run($warehouse, $clerk->fresh()))->not->toHaveKey('team');

    $this->withoutVite();
    \Illuminate\Support\Facades\Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
    $teamRoute = route('grp.org.warehouses.show.team.dashboard', [$organisation->slug, $warehouse->slug]);
    $this->actingAs($clerk)->get($teamRoute)->assertForbidden();

    $storeRoute = fn (Employee $employee) => route('grp.models.warehouse.team.clocking.store', ['warehouse' => $warehouse->id, 'employee' => $employee->id]);
    $this->actingAs($clerk)->post($storeRoute($teamMember), ['clocked_at' => now()->toIso8601String()])->assertForbidden();

    $this->actingAs($manager)
        ->post($storeRoute($teamMember), ['clocked_at' => now()->subMinutes(5)->setTimezone('Asia/Kuala_Lumpur')->toIso8601String(), 'notes' => 'Forgot to clock in'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $clocking = $teamMember->clockings()->latest('id')->firstOrFail();
    expect($clocking->type)->toBe(ClockingTypeEnum::MANUAL)
        ->and($clocking->notes)->toBe('Forgot to clock in')
        ->and(abs($clocking->clocked_at->diffInSeconds(now()->subMinutes(5))))->toBeLessThan(60)
        ->and($clocking->generator_type)->toBe('User')
        ->and($clocking->generator_id)->toBe($manager->id);

    $clocking->update(['is_late' => true]);

    $this->actingAs($manager)
        ->post($storeRoute($outsider), ['clocked_at' => now()->toIso8601String()])
        ->assertSessionHasErrors('employee');
    expect($outsider->clockings()->count())->toBe(0);

    $this->actingAs($manager)->get($teamRoute)
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Org/Warehouse/WarehouseTeam')
                ->where('tabs.current', 'dashboard')
                ->where('dashboard.period', 'today')
                ->where('dashboard.floor.people', fn ($people) => collect($people)->contains(fn ($person) => $person['id'] === $teamMember->id && $person['status'] === 'on_site')
                    && !collect($people)->contains('id', $outsider->id))
                ->where('dashboard.leaderboard', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $teamMember->id && $row['late'] === 1)
                    && !collect($rows)->contains('id', $outsider->id))
                ->where('dashboard.kpis.late.value', 1)
                ->has('dashboard.hourly.hours', 24)
                ->has('dashboard.daily.days', 30)
                ->has('dashboard.backlog.to_pick')
                ->missing('clockings')
                ->etc()
        );

    $this->actingAs($manager)->get($teamRoute.'?period=month')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.period', 'month')->etc());

    $this->actingAs($manager)->get($teamRoute.'?tab=clockings')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('tabs.current', 'clockings')
                ->where('clockings.date', now($organisation->timezone->name)->toDateString())
                ->where('clockings.people', fn ($people) => collect($people)->contains(fn ($person) => $person['id'] === $teamMember->id && $person['is_open'] === true && collect($person['clockings'])->contains('id', $clocking->id)))
                ->missing('dashboard')
                ->etc()
        );

    $clockingRoute = fn (Clocking $clocking, string $action) => route("grp.models.warehouse.team.clocking.$action", ['warehouse' => $warehouse->id, 'clocking' => $clocking->id]);
    $newTime       = now()->subMinutes(20)->startOfMinute();

    $this->actingAs($clerk)->patch($clockingRoute($clocking, 'update'), ['clocked_at' => $newTime->toIso8601String()])->assertForbidden();

    $this->actingAs($manager)
        ->patch($clockingRoute($clocking, 'update'), ['clocked_at' => $newTime->copy()->setTimezone('Asia/Kuala_Lumpur')->toIso8601String(), 'notes' => 'Arrived earlier'])
        ->assertSessionHasNoErrors();
    $clocking->refresh();
    expect($clocking->clocked_at->equalTo($newTime))->toBeTrue()
        ->and($clocking->notes)->toBe('Arrived earlier')
        ->and(\App\Models\HumanResources\TimeTracker::where('start_clocking_id', $clocking->id)->first()?->starts_at?->equalTo($newTime))->toBeTrue();

    $outsiderClocking = StoreClocking::make()->action($manager, $organisation->workplaces()->first(), $outsider, ['clocked_at' => now()->subMinute()]);
    $this->actingAs($manager)
        ->patch($clockingRoute($outsiderClocking, 'update'), ['clocked_at' => now()->toIso8601String()])
        ->assertSessionHasErrors('clocking');
    $this->actingAs($manager)->delete($clockingRoute($outsiderClocking, 'delete'))->assertSessionHasErrors('clocking');
    expect(Clocking::withTrashed()->whereKey($outsiderClocking->id)->exists())->toBeTrue();

    $this->actingAs($clerk)->delete($clockingRoute($clocking, 'delete'))->assertForbidden();
    $this->actingAs($manager)->delete($clockingRoute($clocking, 'delete'))->assertSessionHasNoErrors();
    expect(Clocking::withTrashed()->whereKey($clocking->id)->exists())->toBeFalse()
        ->and(\App\Models\HumanResources\Timesheet::find($clocking->timesheet_id)->number_open_time_trackers)->toBe(0);
});

test('only human resources editors can change a clocking time or notes from the clockings table', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Clocking Notes Authorisation Workplace '.rand(100000, 999999),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'type' => 'in',
        'at'   => now()->toDateTimeString(),
    ], 0, true);
    $originalClockedAt = $clocking->clocked_at;

    $route = route('grp.models.clocking-machine.clocking.notes.update', ['clocking' => $clocking->id]);

    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    actingAs($outsider)->patchJson($route, ['notes' => 'Tampered', 'clocked_at' => now()->subHours(3)->toIso8601String()])->assertForbidden();

    $clocking->refresh();
    expect($clocking->notes)->not->toBe('Tampered')
        ->and($clocking->clocked_at->equalTo($originalClockedAt))->toBeTrue();

    $editor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $editor->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $editor->forgetWildcardPermissionIndex();

    actingAs($editor)->patchJson($route, ['notes' => 'Forgot badge'])->assertOk();

    expect($clocking->refresh()->notes)->toBe('Forgot badge');
});

test('a job position whose roles are reseeded passes them to the people already holding it', function () {
    setPermissionsTeamId($this->organisation->group_id);
    $jobPosition = $this->organisation->jobPositions()->where('code', 'hr-v')->firstOrFail();
    $roleName    = RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_VIEWER->value, $this->organisation);

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
    ]);
    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'reseed-'.$employee->id,
        'password' => 'secret123',
    ]);
    $employee->jobPositions()->attach($jobPosition->id, ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id, 'scopes' => []]);

    $jobPosition->roles()->detach();
    \App\Actions\SysAdmin\User\SyncRolesFromJobPositions::run($user);
    expect($user->refresh()->hasRole($roleName))->toBeFalse();

    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($this->organisation);

    expect($user->refresh()->hasRole($roleName))->toBeTrue();
});

test('the hr dashboard leave cards state their period and link to matching leave pages, and absence cover rows carry status and type colour', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $user->forgetWildcardPermissionIndex();
    actingAs($user);

    $leaveType = StoreLeaveType::make()->action($this->organisation, [
        'code'     => 'LC'.rand(10000, 99999),
        'name'     => 'Leave Card Type '.rand(10000, 99999),
        'category' => \App\Enums\HumanResources\Leave\LeaveCategoryEnum::MEDICAL,
    ]);
    $leaveType->update(['color' => 'green']);

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $leave = Leave::create([
        'group_id'        => $employee->group_id,
        'organisation_id' => $employee->organisation_id,
        'employee_id'     => $employee->id,
        'employee_name'   => $employee->contact_name,
        'type'            => $leaveType->code,
        'leave_type_id'   => $leaveType->id,
        'start_date'      => now()->toDateString(),
        'end_date'        => now()->toDateString(),
        'duration_days'   => 1,
        'is_half_day'     => false,
        'session'         => 'Full',
        'reason'          => 'Leave card fixture',
        'status'          => LeaveStatusEnum::APPROVED,
    ]);

    $monday = now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);

    get(route('grp.org.hr.dashboard', $this->organisation->slug))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leaveOverviewPeriod.route.name', 'grp.org.hr.leaves.dashboard')
                ->where('leaveOverviewPeriod.route.parameters._query.view', 'week')
                ->where('leaveOverviewPeriod.route.parameters._query.week_start', $monday->toDateString())
                ->where('leaveOverviewPeriod.dates', fn (string $dates) => str_starts_with($dates, $monday->format('j')))
                ->where('leaveOverview.0.date', $monday->toDateString())
                ->where('leaveTypesPeriod.dates', now()->translatedFormat('F Y'))
                ->where('leaveTypesPeriod.route.parameters._query.month', now()->month)
                ->where('leaveTypes.types', fn ($types) => collect($types)->contains(
                    fn ($type) => $type['name'] === $leaveType->name
                        && data_get($type, 'route.name') === 'grp.org.hr.leaves.index'
                        && data_get($type, 'route.parameters._query.type') === $leaveType->code
                        && data_get($type, 'route.parameters._query.status') === LeaveStatusEnum::APPROVED->value
                ))
                ->etc()
        );

    get(route('grp.org.hr.leaves.index', [
        'organisation' => $this->organisation->slug,
        'type'         => $leaveType->code,
        'status'       => LeaveStatusEnum::APPROVED->value,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leaves.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$leave->id])
                ->etc()
        );

    get(route('grp.org.hr.covers.index', ['organisation' => $this->organisation->slug, 'global' => $employee->contact_name]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('data.data', fn ($rows) => collect($rows)->contains(
                    fn ($row) => $row['id'] === $leave->id
                        && $row['status'] === 'ongoing'
                        && $row['type_color'] === '#16A34A'
                        && $row['covered_by'] === null
                ))
                ->etc()
        );
});

function bulkEmailEmployee(mixed $context, array $attributes = []): Employee
{
    return Employee::factory()->create(array_merge([
        'organisation_id' => $context->organisation->id,
        'group_id'        => $context->group->id,
        'state'           => EmployeeStateEnum::WORKING,
        'email'           => 'bulk-'.uniqid().'@example.com',
    ], $attributes));
}

test('a bulk email with an explicit recipient list only reaches the working people on it who have an email', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Notification::fake();

    $first        = bulkEmailEmployee($this);
    $second       = bulkEmailEmployee($this, ['email' => null, 'work_email' => 'bulk-work-'.uniqid().'@example.com']);
    $notSelected  = bulkEmailEmployee($this);
    $withoutEmail = bulkEmailEmployee($this, ['email' => null, 'work_email' => null]);
    $hasLeft      = bulkEmailEmployee($this, ['state' => EmployeeStateEnum::LEFT]);

    $employeeBulkEmail = \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmail::make()->action($this->organisation, [
        'subject'      => 'Explicit list',
        'body'         => '<p>Hello</p>',
        'employee_ids' => [$first->id, $second->id, $withoutEmail->id, $hasLeft->id],
    ]);

    $recipients = [];
    \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmailToEmployee::assertPushedWith(
        function (\App\Models\HumanResources\EmployeeBulkEmail $pushedBulkEmail, string $email) use ($employeeBulkEmail, &$recipients): bool {
            if ($pushedBulkEmail->id === $employeeBulkEmail->id) {
                $recipients[] = $email;
            }

            return true;
        }
    );

    expect($employeeBulkEmail->number_recipients)->toBe(2)
        ->and($employeeBulkEmail->number_pending)->toBe(2)
        ->and($recipients)->toEqualCanonicalizing([$first->email, $second->work_email])
        ->and($recipients)->not->toContain($notSelected->email);
});

test('a bulk email refuses recipients from another organisation', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Notification::fake();

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o'.substr(uniqid(), -5),
                'name'  => 'Other Org '.uniqid(),
                'email' => 'org-'.uniqid().'@example.com',
            ]
        ));

    $ownEmployee = bulkEmailEmployee($this);
    $outsider    = Employee::factory()->create([
        'organisation_id' => $otherOrganisation->id,
        'group_id'        => $this->group->id,
        'state'           => EmployeeStateEnum::WORKING,
        'email'           => 'outsider-'.uniqid().'@example.com',
    ]);

    $numberBulkEmails = \App\Models\HumanResources\EmployeeBulkEmail::count();

    expect(fn () => \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmail::make()->action($this->organisation, [
        'subject'      => 'Cross organisation',
        'body'         => '<p>Hello</p>',
        'employee_ids' => [$ownEmployee->id, $outsider->id],
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(\App\Models\HumanResources\EmployeeBulkEmail::count())->toBe($numberBulkEmails);
    \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmailToEmployee::assertNotPushed();
});

test('a bulk email without a recipient list still goes to every working employee with an email', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Notification::fake();

    bulkEmailEmployee($this);
    bulkEmailEmployee($this, ['email' => null, 'work_email' => null]);
    bulkEmailEmployee($this, ['state' => EmployeeStateEnum::LEFT]);

    $numberReachable = $this->organisation->employees()
        ->where('state', EmployeeStateEnum::WORKING)
        ->get()
        ->filter(fn (Employee $employee): bool => (bool) ($employee->work_email ?: $employee->email))
        ->count();

    $employeeBulkEmail = \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmail::make()->action($this->organisation, [
        'subject' => 'Everyone',
        'body'    => '<p>Hello</p>',
    ]);

    expect($numberReachable)->toBeGreaterThan(0)
        ->and($employeeBulkEmail->number_recipients)->toBe($numberReachable);
    \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmailToEmployee::assertPushed($numberReachable);
});

test('the bulk email compose page lists working employees without exposing addresses and sending keeps its attachment limits', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Notification::fake();
    Storage::fake('local');

    $recipient    = bulkEmailEmployee($this);
    $withoutEmail = bulkEmailEmployee($this, ['email' => null, 'work_email' => null]);
    $hasLeft      = bulkEmailEmployee($this, ['state' => EmployeeStateEnum::LEFT]);

    $createRoute = route('grp.org.hr.bulk_emails.create', [$this->organisation->slug]);
    $storeRoute  = route('grp.org.hr.bulk_emails.store', [$this->organisation->slug]);
    $indexRoute  = route('grp.org.hr.bulk_emails.index', [$this->organisation->slug]);

    actingAsUserWithOnlyPermissions(User::factory()->create(['group_id' => $this->group->id]), ["human-resources.{$this->organisation->id}.view"]);
    get($createRoute)->assertForbidden();
    post($storeRoute, ['subject' => 'Nope', 'body' => '<p>Nope</p>', 'employee_ids' => [$recipient->id]])->assertForbidden();

    actingAsUserWithOnlyPermissions(User::factory()->create(['group_id' => $this->group->id]), ["human-resources.{$this->organisation->id}.view", "human-resources.{$this->organisation->id}.edit"]);

    get($createRoute)
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($recipient, $withoutEmail, $hasLeft, $indexRoute) {
            $employees = collect($page->toArray()['props']['employees'])->keyBy('id');

            expect($employees->get($recipient->id))->toMatchArray(['name' => $recipient->contact_name, 'has_email' => true])
                ->and(array_keys($employees->get($recipient->id)))->toEqualCanonicalizing(['id', 'name', 'job_title', 'has_email', 'avatar'])
                ->and($employees->get($withoutEmail->id)['has_email'])->toBeFalse()
                ->and($employees->has($hasLeft->id))->toBeFalse()
                ->and(json_encode($page->toArray()['props']['employees']))->not->toContain($recipient->email);

            $page->component('Org/HumanResources/CreateEmployeeBulkEmail', false)
                ->where('pageHead.actions.0.key', 'cancel')
                ->where('pageHead.actions.0.route.name', 'grp.org.hr.bulk_emails.index')
                ->where('sendRoute.name', 'grp.org.hr.bulk_emails.store')
                ->where('attachmentLimits.max_files', 5)
                ->where('attachmentLimits.max_file_kilobytes', 5120)
                ->where('breadcrumbs.'.(count($page->toArray()['props']['breadcrumbs']) - 1).'.creatingModel.label', __('New bulk email'));
        });

    get($indexRoute)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions.0.route.name', 'grp.org.hr.bulk_emails.create')
            ->missing('employeeOptions'));

    $navigationBuilder = new class () {
        use \App\Actions\UI\Grp\Layout\WithLayoutNavigation;
    };
    $navigationUser = Mockery::mock(User::class);
    $navigationUser->shouldReceive('authTo')->andReturn(true);
    $bulkEmailsNav = collect($navigationBuilder->getHumanResourcesNavs($navigationUser, $this->organisation, [])['hr']['topMenu']['subSections'])
        ->firstWhere('route.name', 'grp.org.hr.bulk_emails.index');
    expect(str_starts_with('grp.org.hr.bulk_emails.create', $bulkEmailsNav['root']))->toBeTrue();

    $numberBulkEmails = \App\Models\HumanResources\EmployeeBulkEmail::count();
    $message          = ['subject' => 'With files', 'body' => '<p>Hello</p>', 'employee_ids' => [$recipient->id]];
    $file             = fn (int $kilobytes) => \Illuminate\Http\UploadedFile::fake()->create('notes-'.uniqid().'.pdf', $kilobytes);

    post($storeRoute, array_merge($message, ['attachments' => array_map(fn () => $file(10), range(1, 6))]))
        ->assertSessionHasErrors('attachments');
    post($storeRoute, array_merge($message, ['attachments' => [$file(5121)]]))
        ->assertSessionHasErrors('attachments.0');
    post($storeRoute, array_merge($message, ['employee_ids' => [$recipient->id, $recipient->id]]))
        ->assertSessionHasErrors('employee_ids.0');
    expect(\App\Models\HumanResources\EmployeeBulkEmail::count())->toBe($numberBulkEmails);
    \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmailToEmployee::assertNotPushed();

    post($storeRoute, array_merge($message, ['attachments' => array_map(fn () => $file(5120), range(1, 5))]))
        ->assertSessionHasNoErrors()
        ->assertRedirect($indexRoute)
        ->assertSessionHas('notification.status', 'success');

    $employeeBulkEmail = \App\Models\HumanResources\EmployeeBulkEmail::where('organisation_id', $this->organisation->id)->where('subject', 'With files')->latest('id')->firstOrFail();

    expect($employeeBulkEmail->number_recipients)->toBe(1)
        ->and($employeeBulkEmail->attachments)->toHaveCount(5)
        ->and($employeeBulkEmail->sender_id)->toBe(auth()->id());
    \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmailToEmployee::assertPushed(1);
});

test('the bulk email list searches subject and message, filters by sent date and carries what the preview shows', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Notification::fake();
    Storage::fake('local');

    $recipient = bulkEmailEmployee($this);
    $token     = 'zq'.uniqid();

    $sender = User::factory()->create(['group_id' => $this->group->id]);
    actingAsUserWithOnlyPermissions($sender, ["human-resources.{$this->organisation->id}.view", "human-resources.{$this->organisation->id}.edit"]);

    $indexRoute = fn (array $query = []) => route('grp.org.hr.bulk_emails.index', array_merge([$this->organisation->slug], $query));
    $listedIds  = fn (array $query) => collect(get($indexRoute($query))->assertOk()->viewData('page')['props']['data']['data'])->pluck('id')->all();

    post(route('grp.org.hr.bulk_emails.store', [$this->organisation->slug]), [
        'subject'      => "Roster {$token}subject",
        'body'         => '<p>Please read the <strong>new roster</strong>.</p><p>Thanks &amp; regards</p>',
        'employee_ids' => [$recipient->id],
        'attachments'  => [\Illuminate\Http\UploadedFile::fake()->create('roster.pdf', 12)],
    ])->assertSessionHasNoErrors();

    $bySubject = \App\Models\HumanResources\EmployeeBulkEmail::where('subject', "Roster {$token}subject")->firstOrFail();
    $byBody    = \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmail::make()->action($this->organisation, [
        'subject'      => 'Canteen closed',
        'body'         => "<p>Closed for <em>{$token}body</em> works.</p>",
        'employee_ids' => [$recipient->id],
    ]);
    $byBody->forceFill(['created_at' => '2020-01-15 10:00:00'])->save();

    get($indexRoute(['filter' => ['global' => "{$token}subject"]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.search', "{$token}subject")
            ->has('data.data', 1)
            ->where('data.data.0.id', $bySubject->id)
            ->where('data.data.0.excerpt', 'Please read the new roster. Thanks & regards')
            ->where('data.data.0.number_recipients', 1)
            ->where('data.data.0.sender.name', $sender->contact_name ?: $sender->username)
            ->where('data.data.0.attachments.0.name', 'roster.pdf')
            ->where('data.data.0.attachments.0.size', 12 * 1024)
            ->missing('data.data.0.attachments.0.path'));

    expect($listedIds(['filter' => ['global' => "{$token}body"]]))->toBe([$byBody->id])
        ->and($listedIds(['filter' => ['global' => 'strong']]))->not->toContain($bySubject->id);

    $january2020 = $listedIds(['from' => '2020-01-01', 'to' => '2020-01-31']);
    expect($january2020)->toContain($byBody->id)->not->toContain($bySubject->id)
        ->and($listedIds(['from' => '2020-02-01']))->toContain($bySubject->id)->not->toContain($byBody->id)
        ->and($listedIds(['to' => '2020-01-14']))->not->toContain($byBody->id)->not->toContain($bySubject->id);

    get($indexRoute(['from' => '2020-01-01', 'to' => '2020-01-31']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.from', '2020-01-01')->where('filters.to', '2020-01-31'));

    get($indexRoute(['from' => 'yesterday']))->assertSessionHasErrors('from');
    get($indexRoute(['to' => '31/01/2020']))->assertSessionHasErrors('to');
    get($indexRoute(['filter' => ['global' => str_repeat('a', 256)]]))->assertSessionHasErrors('filter.global');
});

test('leave requests index filters by status, type, overlapping leave period and search', function () {
    $index   = fn (array $filters) => \App\Actions\HumanResources\Leave\UI\IndexLeavesAdmin::make()->handle($this->organisation, null, $filters)->total();
    $inMarch = ['from' => '2031-03-10', 'to' => '2031-03-20'];
    $type    = 'filter-type-'.rand(10000, 99999);
    $needle  = 'Quillfeather'.rand(10000, 99999);

    $baseline = [
        'march'          => $index($inMarch),
        'march approved' => $index([...$inMarch, 'status' => 'approved']),
        'march pending'  => $index([...$inMarch, 'status' => 'pending']),
        'after'          => $index(['from' => '2031-03-21']),
        'before'         => $index(['to' => '2031-03-04']),
    ];

    $straddlingPending = createPendingLeaveFixture($this->organisation, $this->group);
    $approvedInside    = createPendingLeaveFixture($this->organisation, $this->group);
    $pendingAfter      = createPendingLeaveFixture($this->organisation, $this->group);

    $straddlingPending->update(['start_date' => '2031-03-05', 'end_date' => '2031-03-12', 'employee_name' => 'Zed '.$needle]);
    $approvedInside->update(['start_date' => '2031-03-15', 'end_date' => '2031-03-16', 'status' => LeaveStatusEnum::APPROVED, 'type' => $type]);
    $pendingAfter->update(['start_date' => '2031-03-21', 'end_date' => '2031-03-22']);

    expect($index($inMarch))->toBe($baseline['march'] + 2)
        ->and($index([...$inMarch, 'status' => 'approved']))->toBe($baseline['march approved'] + 1)
        ->and($index([...$inMarch, 'status' => 'pending']))->toBe($baseline['march pending'] + 1)
        ->and($index(['type' => $type]))->toBe(1)
        ->and($index(['from' => '2031-03-21']))->toBe($baseline['after'] + 1)
        ->and($index(['to' => '2031-03-04']))->toBe($baseline['before']);

    $rules = \App\Actions\HumanResources\Leave\UI\IndexLeavesAdmin::make()->rules();

    expect(Validator::make(['status' => 'approved', 'from' => '2031-03-01', 'to' => '2031-03-31'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['status' => 'not-a-status'], $rules)->errors()->keys())->toBe(['status'])
        ->and(Validator::make(['from' => '01/03/2031', 'to' => 'tomorrow'], $rules)->errors()->keys())->toBe(['from', 'to']);

    setPermissionsTeamId($this->organisation->group_id);
    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $user->forgetWildcardPermissionIndex();
    actingAs($user);

    get(route('grp.org.hr.leaves.index', [
        'organisation' => $this->organisation->slug,
        'filter'       => ['global' => strtolower($needle)],
        'status'       => 'pending',
    ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leaves.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$straddlingPending->id])
                ->where('leaves.total', 1)
                ->has('leaves.data.0.employee_avatar')
                ->has('leaves.data.0.cover_employee_avatar')
                ->has('leaves.data.0.approver_avatar')
                ->where('filters.search', strtolower($needle))
                ->where('filters.status', 'pending')
                ->where('can_delete', true)
                ->etc()
        );

    get(route('grp.org.hr.leaves.index', [
        'organisation'  => $this->organisation->slug,
        'leaves_filter' => ['type' => $type, 'status' => 'approved'],
    ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leaves.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$approvedInside->id])
                ->where('filters.status', 'approved')
                ->where('filters.type', $type)
                ->etc()
        );
});

test('clocking adjustments index filters by status, date range and search and sends organisation-local times', function () {
    $index   = fn (array $filters) => \App\Actions\HumanResources\AttendanceAdjustment\UI\IndexAttendanceAdjustmentsAdmin::make()->handle($this->organisation, null, $filters)->total();
    $inMarch = ['from' => '2031-03-01', 'to' => '2031-03-31'];
    $needle  = 'Badgeless'.rand(10000, 99999);

    $baseline = [
        'march'          => $index($inMarch),
        'march approved' => $index([...$inMarch, 'status' => 'approved']),
        'march pending'  => $index([...$inMarch, 'status' => 'pending']),
        'from april'     => $index(['from' => '2031-04-01']),
        'until february' => $index(['to' => '2031-02-28']),
    ];

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $makeAdjustment = fn (string $date, string $status, string $reason) => AttendanceAdjustment::create([
        'group_id'           => $this->group->id,
        'organisation_id'    => $this->organisation->id,
        'employee_id'        => $employee->id,
        'employee_name'      => $employee->contact_name,
        'date'               => $date,
        'reason'             => $reason,
        'requested_start_at' => $date.' 08:30:00',
        'status'             => $status,
    ]);

    $pendingInMarch = $makeAdjustment('2031-03-01', 'pending', 'Forgot badge '.$needle);
    $makeAdjustment('2031-03-31', 'approved', 'Reader was offline');
    $makeAdjustment('2031-04-01', 'pending', 'Reader was offline');

    expect($index($inMarch))->toBe($baseline['march'] + 2)
        ->and($index([...$inMarch, 'status' => 'approved']))->toBe($baseline['march approved'] + 1)
        ->and($index([...$inMarch, 'status' => 'pending']))->toBe($baseline['march pending'] + 1)
        ->and($index(['from' => '2031-04-01']))->toBe($baseline['from april'] + 1)
        ->and($index(['to' => '2031-02-28']))->toBe($baseline['until february']);

    $rules = \App\Actions\HumanResources\AttendanceAdjustment\UI\IndexAttendanceAdjustmentsAdmin::make()->rules();

    expect(Validator::make(['status' => 'rejected', 'from' => '2031-03-01', 'to' => '2031-03-31'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['status' => 'not-a-status'], $rules)->errors()->keys())->toBe(['status'])
        ->and(Validator::make(['from' => '01/03/2031', 'to' => 'tomorrow'], $rules)->errors()->keys())->toBe(['from', 'to']);

    setPermissionsTeamId($this->organisation->group_id);
    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $user->forgetWildcardPermissionIndex();
    actingAs($user);

    $expectedLocalStart = $pendingInMarch->refresh()->requested_start_at->copy()->timezone($this->organisation->timezone->name)->format('H:i');

    get(route('grp.org.hr.adjustments.index', [
        'organisation' => $this->organisation->slug,
        'filter'       => ['global' => strtolower($needle)],
        'status'       => 'pending',
        ...$inMarch,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('adjustments.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$pendingInMarch->id])
                ->where('adjustments.data.0.requested_start_time_local', $expectedLocalStart)
                ->where('adjustments.data.0.original_start_time_local', null)
                ->where('adjustments.data.0.timezone', $this->organisation->timezone->name)
                ->where('filters.search', strtolower($needle))
                ->where('filters.status', 'pending')
                ->where('filters.from', '2031-03-01')
                ->where('can_edit', true)
                ->etc()
        );
});

function createApprovedLeaveBalanceFixture(Employee $employee, array $attributes = []): Leave
{
    $leave = Leave::create(array_merge([
        'group_id'        => $employee->group_id,
        'organisation_id' => $employee->organisation_id,
        'employee_id'     => $employee->id,
        'employee_name'   => $employee->contact_name,
        'type'            => 'annual',
        'start_date'      => now()->toDateString(),
        'end_date'        => now()->toDateString(),
        'duration_days'   => 1,
        'is_half_day'     => false,
        'session'         => 'Full',
        'reason'          => 'Balance fixture',
        'status'          => LeaveStatusEnum::APPROVED,
        'approved_at'     => now(),
    ], $attributes));

    if ($leave->status === LeaveStatusEnum::APPROVED) {
        ApproveLeave::make()->applyBalanceDeduction($leave);
    }

    return $leave;
}

test('deleting an approved leave returns its days to the balance period it belongs to', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $contract = StoreEmployeeContract::make()->action($employee, [
        'start_date'        => now()->subMonth()->toDateString(),
        'annual_leave_days' => 20,
    ]);

    $currentBalance = EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->firstOrFail();
    $nextPeriodStart = $currentBalance->period_end->copy()->addDay();
    $nextBalance    = GenerateEmployeeLeaveBalance::run($contract, \Carbon\Carbon::parse($nextPeriodStart->toDateString()));

    $used = fn (EmployeeLeaveBalance $balance): array => array_map('floatval', $balance->refresh()->only(['annual_used', 'medical_used', 'unpaid_used']));
    $none = ['annual_used' => 0.0, 'medical_used' => 0.0, 'unpaid_used' => 0.0];

    expect($used($currentBalance))->toBe($none)
        ->and($used($nextBalance))->toBe($none);

    $annual  = createApprovedLeaveBalanceFixture($employee, ['type' => 'annual', 'duration_days' => 2]);
    $medical = createApprovedLeaveBalanceFixture($employee, ['type' => 'medical', 'duration_days' => 3]);
    $unpaid  = createApprovedLeaveBalanceFixture($employee, ['type' => 'unpaid', 'duration_days' => 1]);
    $halfDay = createApprovedLeaveBalanceFixture($employee, ['type' => 'annual', 'duration_days' => 1, 'is_half_day' => true, 'session' => 'Morning']);
    $nextYear = createApprovedLeaveBalanceFixture($employee, [
        'type'          => 'annual',
        'duration_days' => 4,
        'start_date'    => $nextPeriodStart->copy()->addDays(10)->toDateString(),
        'end_date'      => $nextPeriodStart->copy()->addDays(13)->toDateString(),
    ]);
    $pending  = createApprovedLeaveBalanceFixture($employee, ['duration_days' => 5, 'status' => LeaveStatusEnum::PENDING, 'approved_at' => null]);
    $rejected = createApprovedLeaveBalanceFixture($employee, ['duration_days' => 6, 'status' => LeaveStatusEnum::REJECTED]);

    expect($used($currentBalance))->toBe(['annual_used' => 2.5, 'medical_used' => 3.0, 'unpaid_used' => 1.0])
        ->and($used($nextBalance))->toBe(['annual_used' => 4.0, 'medical_used' => 0.0, 'unpaid_used' => 0.0]);

    $shownOnEmployeePages = $employee->refresh()->currentLeaveBalance;
    expect($shownOnEmployeePages->id)->toBe($currentBalance->id)
        ->and((float) $shownOnEmployeePages->annual_used)->toBe(2.5)
        ->and((float) $shownOnEmployeePages->annual_remaining)->toBe(17.5);

    expect(DeleteLeave::make()->action($pending))->toBeTrue()
        ->and(DeleteLeave::make()->action($rejected))->toBeTrue()
        ->and($used($currentBalance))->toBe(['annual_used' => 2.5, 'medical_used' => 3.0, 'unpaid_used' => 1.0]);

    expect(DeleteLeave::make()->action($halfDay))->toBeTrue()
        ->and($used($currentBalance))->toBe(['annual_used' => 2.0, 'medical_used' => 3.0, 'unpaid_used' => 1.0]);

    expect(DeleteLeave::make()->action($halfDay))->toBeFalse()
        ->and(DeleteLeave::make()->action(Leave::withTrashed()->findOrFail($halfDay->id)))->toBeFalse()
        ->and($used($currentBalance))->toBe(['annual_used' => 2.0, 'medical_used' => 3.0, 'unpaid_used' => 1.0]);

    expect(DeleteLeave::make()->action($nextYear))->toBeTrue()
        ->and($used($nextBalance))->toBe($none)
        ->and($used($currentBalance))->toBe(['annual_used' => 2.0, 'medical_used' => 3.0, 'unpaid_used' => 1.0]);

    DeleteLeave::make()->action($annual);
    expect($used($currentBalance))->toBe(['annual_used' => 0.0, 'medical_used' => 3.0, 'unpaid_used' => 1.0]);

    DeleteLeave::make()->action($medical);
    DeleteLeave::make()->action($unpaid);
    expect($used($currentBalance))->toBe($none)
        ->and((float) $employee->refresh()->currentLeaveBalance->annual_remaining)->toBe(20.0);
});

test('leave balance counters are recalculated from approved leaves, for employees with and without a contract', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    $leave    = createApprovedLeaveBalanceFixture($employee, ['type' => 'medical', 'duration_days' => 2]);
    $fallback = EmployeeLeaveBalance::where('employee_id', $employee->id)->whereNull('employee_contract_id')->firstOrFail();

    expect((float) $fallback->medical_used)->toBe(2.0);

    $fallback->update(['annual_used' => 9, 'medical_used' => 7]);
    \App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance::run($fallback);

    expect((float) $fallback->refresh()->annual_used)->toBe(0.0)
        ->and((float) $fallback->medical_used)->toBe(2.0);

    ApproveLeave::make()->applyBalanceDeduction($leave);
    expect((float) $fallback->refresh()->medical_used)->toBe(2.0);

    $this->artisan('leave:recalculate-balances')->assertExitCode(0);

    $approver = createLeaveApproverFixture($this->organisation, 1);
    actingAs($approver->user);
    $leave->update(['status' => LeaveStatusEnum::APPROVED]);

    expect(fn () => RejectLeave::make()->handle($leave, 'Approved by mistake'))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'This leave request has already been approved.')
        ->and($leave->refresh()->status)->toBe(LeaveStatusEnum::APPROVED)
        ->and((float) $fallback->refresh()->medical_used)->toBe(2.0)
        ->and(EmployeeLeaveBalance::where('employee_id', $employee->id)->count())->toBe(1);
});

test('a bulk email records who received it and its page lists them without addresses', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Notification::fake();

    $token = 'rc'.uniqid();
    $staff = collect(range(1, 7))->map(fn (int $number) => bulkEmailEmployee($this, ['contact_name' => "Recipient {$number} {$token}"]));

    $employeeBulkEmail = \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmail::make()->action($this->organisation, [
        'subject'      => "Recorded {$token}",
        'body'         => '<p>Hello team</p>',
        'employee_ids' => $staff->pluck('id')->all(),
    ]);

    expect($employeeBulkEmail->number_recipients)->toBe(7)
        ->and($employeeBulkEmail->refresh()->recipients)->toHaveCount(7)
        ->and(array_keys($employeeBulkEmail->recipients[0]))->toEqualCanonicalizing(['employee_id', 'name'])
        ->and(array_column($employeeBulkEmail->recipients, 'name'))->toBe($staff->pluck('contact_name')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all())
        ->and(array_column($employeeBulkEmail->recipients, 'employee_id'))->toEqualCanonicalizing($staff->pluck('id')->all())
        ->and($employeeBulkEmail->getRawOriginal('recipients'))->not->toContain('@');

    $removed = $staff->get(1);
    $removed->delete();

    $leaver = $staff->first();
    $leaver->update(['state' => EmployeeStateEnum::LEFT]);

    $legacyBulkEmail = \App\Models\HumanResources\EmployeeBulkEmail::create([
        'group_id'          => $this->group->id,
        'organisation_id'   => $this->organisation->id,
        'subject'           => "Legacy {$token}",
        'body'              => '<p>Sent before recipients were recorded</p>',
        'number_recipients' => 3,
        'number_pending'    => 0,
        'attachments'       => [],
    ]);

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o'.substr(uniqid(), -5),
                'name'  => 'Other Org '.uniqid(),
                'email' => 'org-'.uniqid().'@example.com',
            ]
        ));
    $foreignBulkEmail = \App\Models\HumanResources\EmployeeBulkEmail::create([
        'group_id'          => $this->group->id,
        'organisation_id'   => $otherOrganisation->id,
        'subject'           => "Foreign {$token}",
        'body'              => '<p>Not yours</p>',
        'number_recipients' => 0,
        'number_pending'    => 0,
        'attachments'       => [],
    ]);

    $showRoute = fn (\App\Models\HumanResources\EmployeeBulkEmail $bulkEmail) => route('grp.org.hr.bulk_emails.show', [$this->organisation->slug, $bulkEmail->id]);

    actingAsUserWithOnlyPermissions(User::factory()->create(['group_id' => $this->group->id]), []);
    get($showRoute($employeeBulkEmail))->assertForbidden();

    actingAsUserWithOnlyPermissions(User::factory()->create(['group_id' => $this->group->id]), ["human-resources.{$this->organisation->id}.view"]);

    get($showRoute($employeeBulkEmail))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($employeeBulkEmail, $staff, $leaver, $removed, $token) {
            $props      = $page->toArray()['props'];
            $recipients = collect($props['recipients'])->keyBy('name');

            expect($recipients)->toHaveCount(7)
                ->and(array_keys($recipients->first()))->toEqualCanonicalizing(['id', 'name', 'avatar', 'job_title', 'is_working'])
                ->and($recipients->get($leaver->contact_name)['is_working'])->toBeFalse()
                ->and($recipients->get($staff->last()->contact_name)['is_working'])->toBeTrue()
                ->and($recipients->get($staff->last()->contact_name)['id'])->toBe($staff->last()->id)
                ->and($recipients->get($removed->contact_name))->toMatchArray(['avatar' => null, 'job_title' => null, 'is_working' => false])
                ->and(json_encode($props))->not->toContain('@example.com');

            $page->component('Org/HumanResources/EmployeeBulkEmail', false)
                ->where('email.id', $employeeBulkEmail->id)
                ->where('email.subject', "Recorded {$token}")
                ->where('email.number_recipients', 7)
                ->where('pageHead.title', "Recorded {$token}")
                ->where('pageHead.actions.0.key', 'back')
                ->where('pageHead.actions.0.route.name', 'grp.org.hr.bulk_emails.index')
                ->where('breadcrumbs.'.(count($props['breadcrumbs']) - 1).'.simple.label', "Recorded {$token}")
                ->where('breadcrumbs.'.(count($props['breadcrumbs']) - 2).'.simple.route.name', 'grp.org.hr.bulk_emails.index');
        });

    get($showRoute($legacyBulkEmail))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('email.number_recipients', 3)->has('recipients', 0));

    get($showRoute($foreignBulkEmail))->assertNotFound();

    get(route('grp.org.hr.bulk_emails.index', [$this->organisation->slug, 'filter' => ['global' => "Recorded {$token}"]]))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $props = $page->toArray()['props'];

            expect(json_encode($props['data']))->not->toContain('@example.com');

            $page->has('data.data', 1)
                ->where('data.data.0.number_recipients', 7)
                ->has('data.data.0.recipients_sample', 5)
                ->missing('data.data.0.recipients');

            expect(array_keys($props['data']['data'][0]['recipients_sample'][0]))->toEqualCanonicalizing(['id', 'name', 'avatar']);
        });

    $navigationBuilder = new class () {
        use \App\Actions\UI\Grp\Layout\WithLayoutNavigation;
    };
    $navigationUser = Mockery::mock(User::class);
    $navigationUser->shouldReceive('authTo')->andReturn(true);
    $bulkEmailsNav = collect($navigationBuilder->getHumanResourcesNavs($navigationUser, $this->organisation, [])['hr']['topMenu']['subSections'])
        ->firstWhere('route.name', 'grp.org.hr.bulk_emails.index');
    expect(str_starts_with('grp.org.hr.bulk_emails.show', $bulkEmailsNav['root']))->toBeTrue();
});

test('bulk email attachments are kept after delivery and human resources can download them', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Notification::fake();
    \Illuminate\Support\Facades\Mail::fake();
    Storage::fake('local');

    $token     = 'at'.uniqid();
    $recipient = bulkEmailEmployee($this);
    $editor    = User::factory()->create(['group_id' => $this->group->id]);
    $hrAccess  = ["human-resources.{$this->organisation->id}.view", "human-resources.{$this->organisation->id}.edit"];

    actingAsUserWithOnlyPermissions($editor, $hrAccess);

    post(route('grp.org.hr.bulk_emails.store', [$this->organisation->slug]), [
        'subject'      => "Handbook {$token}",
        'body'         => '<p>See attached</p>',
        'employee_ids' => [$recipient->id],
        'attachments'  => [\Illuminate\Http\UploadedFile::fake()->create('handbook.pdf', 12, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $employeeBulkEmail = \App\Models\HumanResources\EmployeeBulkEmail::where('subject', "Handbook {$token}")->firstOrFail();
    $storedPath        = $employeeBulkEmail->attachments[0]['path'];

    \App\Actions\HumanResources\EmployeeBulkEmail\SendEmployeeBulkEmailToEmployee::run($employeeBulkEmail, $recipient->email, $recipient->contact_name);

    \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\EmployeeBulkEmailNotification::class);
    expect($employeeBulkEmail->refresh()->number_pending)->toBe(0);
    Storage::disk('local')->assertExists($storedPath);

    $downloadRoute = fn (\App\Models\HumanResources\EmployeeBulkEmail $bulkEmail, int $attachment) => route('grp.org.hr.bulk_emails.attachments.download', [$this->organisation->slug, $bulkEmail->id, $attachment]);

    get($downloadRoute($employeeBulkEmail, 0))->assertOk()->assertDownload('handbook.pdf');
    get($downloadRoute($employeeBulkEmail, 1))->assertNotFound();

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o'.substr(uniqid(), -5),
                'name'  => 'Other Org '.uniqid(),
                'email' => 'org-'.uniqid().'@example.com',
            ]
        ));
    $foreignBulkEmail = \App\Models\HumanResources\EmployeeBulkEmail::create([
        'group_id'          => $this->group->id,
        'organisation_id'   => $otherOrganisation->id,
        'subject'           => "Foreign {$token}",
        'body'              => '<p>Not yours</p>',
        'number_recipients' => 0,
        'number_pending'    => 0,
        'attachments'       => $employeeBulkEmail->attachments,
    ]);
    get($downloadRoute($foreignBulkEmail, 0))->assertNotFound();

    actingAsUserWithOnlyPermissions(User::factory()->create(['group_id' => $this->group->id]), []);
    get($downloadRoute($employeeBulkEmail, 0))->assertForbidden();

    actingAsUserWithOnlyPermissions($editor, $hrAccess);
    get(route('grp.org.hr.bulk_emails.show', [$this->organisation->slug, $employeeBulkEmail->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('email.attachments.0.name', 'handbook.pdf')
            ->where('email.attachments.0.index', 0)
            ->where('email.attachments.0.available', true)
            ->missing('email.attachments.0.path'));

    Storage::disk('local')->delete($storedPath);

    get($downloadRoute($employeeBulkEmail, 0))->assertNotFound();
    get(route('grp.org.hr.bulk_emails.show', [$this->organisation->slug, $employeeBulkEmail->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('email.attachments.0.available', false));
});

function giveAvatarImage(Employee|User $model): void
{
    $imagePath = tempnam(sys_get_temp_dir(), 'avatar-').'.png';
    file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

    \App\Actions\Helpers\Media\SaveModelImage::run(
        model: $model,
        imageData: ['path' => $imagePath, 'originalName' => 'me.png', 'extension' => 'png'],
        scope: 'avatar'
    );
    unlink($imagePath);
}

test('the employees list, the overtime, adjustment and leave dashboards, absence cover and employee analytics pages carry each employee avatar', function () {
    config(['employee-analytics.enabled' => true]);
    setPermissionsTeamId($this->organisation->group_id);

    $viewer = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $viewer->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $viewer->forgetWildcardPermissionIndex();
    actingAs($viewer);

    $token       = 'Avatar'.uniqid();
    $newEmployee = fn (string $name): Employee => Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => EmployeeStateEnum::WORKING,
        'contact_name'    => "{$token} {$name}",
    ]);

    $withOwnPicture  = $newEmployee('Own');
    $withUserPicture = $newEmployee('Linked');
    $withoutPicture  = $newEmployee('None');

    giveAvatarImage($withOwnPicture);
    $linkedUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    giveAvatarImage($linkedUser);
    $withUserPicture->update(['user_id' => $linkedUser->id]);

    $isPictureUrl = fn (mixed $avatar): bool => is_string($avatar) && $avatar !== '';

    expect($withOwnPicture->fresh()->avatarUrl())->toBeString()->not->toBeEmpty()
        ->and($withUserPicture->fresh()->avatarUrl())->toBeString()->not->toBeEmpty()
        ->and($withoutPicture->fresh()->avatarUrl())->toBeNull();

    get(route('grp.org.hr.employees.index', ['organisation' => $this->organisation->slug, 'global' => $token]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('data.data', function ($rows) use ($withOwnPicture, $withUserPicture, $withoutPicture, $isPictureUrl) {
            $rows = collect($rows)->keyBy('id');

            return $rows->every(fn ($row) => array_key_exists('avatar', $row) && ! array_key_exists('email', $row))
                && $isPictureUrl($rows->get($withOwnPicture->id)['avatar'])
                && $isPictureUrl($rows->get($withUserPicture->id)['avatar'])
                && $rows->get($withoutPicture->id)['avatar'] === null;
        }));

    foreach (['grp.org.hr.overtime.dashboard', 'grp.org.hr.adjustments.dashboard', 'grp.org.hr.leaves.dashboard'] as $dashboardRoute) {
        get(route($dashboardRoute, $this->organisation->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('calendarData', function ($rows) use ($withOwnPicture, $withUserPicture, $withoutPicture, $isPictureUrl) {
                $rows = collect($rows)->keyBy('id');

                return $rows->every(fn ($row) => array_key_exists('avatar', $row) && ! array_key_exists('email', $row))
                    && $isPictureUrl($rows->get($withOwnPicture->id)['avatar'])
                    && $isPictureUrl($rows->get($withUserPicture->id)['avatar'])
                    && $rows->get($withoutPicture->id)['avatar'] === null;
            })->etc());
    }

    $leaveType = StoreLeaveType::make()->action($this->organisation, [
        'code'     => 'AV'.rand(10000, 99999),
        'name'     => 'Avatar Leave Type '.rand(10000, 99999),
        'category' => \App\Enums\HumanResources\Leave\LeaveCategoryEnum::MEDICAL,
    ]);
    $newLeave = fn (Employee $employee, ?Employee $cover): Leave => Leave::create([
        'group_id'          => $employee->group_id,
        'organisation_id'   => $employee->organisation_id,
        'employee_id'       => $employee->id,
        'employee_name'     => $employee->contact_name,
        'cover_employee_id' => $cover?->id,
        'type'              => $leaveType->code,
        'leave_type_id'     => $leaveType->id,
        'start_date'        => now()->toDateString(),
        'end_date'          => now()->toDateString(),
        'duration_days'     => 1,
        'is_half_day'       => false,
        'session'           => 'Full',
        'reason'            => 'Avatar fixture',
        'status'            => LeaveStatusEnum::APPROVED,
    ]);
    $coveredLeave   = $newLeave($withOwnPicture, $withUserPicture);
    $uncoveredLeave = $newLeave($withoutPicture, null);

    get(route('grp.org.hr.covers.index', ['organisation' => $this->organisation->slug, 'global' => $token]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('data.data', function ($rows) use ($coveredLeave, $uncoveredLeave, $isPictureUrl) {
            $rows = collect($rows)->keyBy('id');

            return $isPictureUrl($rows->get($coveredLeave->id)['employee_avatar'])
                && $isPictureUrl($rows->get($coveredLeave->id)['covered_by_avatar'])
                && $rows->get($uncoveredLeave->id)['employee_avatar'] === null
                && $rows->get($uncoveredLeave->id)['covered_by_avatar'] === null;
        })->etc());

    get(route('grp.org.hr.analytics.index', $this->organisation->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('attendance_breakdown', fn ($rows) => collect($rows)->every(fn ($row) => array_key_exists('avatar', $row) && ! array_key_exists('email', $row)))
            ->where('top_employees_by_leave', fn ($rows) => collect($rows)->every(fn ($row) => array_key_exists('avatar', $row) && ! array_key_exists('email', $row)))
            ->etc());

    [$attendanceRows, $leaveRows] = \App\Actions\HumanResources\EmployeeAnalytics\UI\IndexEmployeeAnalytics::make()->withAvatars(
        [['id' => $withOwnPicture->id, 'name' => 'a'], ['id' => $withoutPicture->id, 'name' => 'b']],
        [['id' => $withUserPicture->id, 'name' => 'c']]
    );

    expect($attendanceRows[0]['avatar'])->toBeString()->not->toBeEmpty()
        ->and($attendanceRows[0]['name'])->toBe('a')
        ->and($attendanceRows[1]['avatar'])->toBeNull()
        ->and($leaveRows[0]['avatar'])->toBeString()->not->toBeEmpty();
});

test('the timesheets by employee views and the by date tab carry each employee avatar', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $viewer = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $viewer->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $viewer->forgetWildcardPermissionIndex();
    actingAs($viewer);

    $token       = 'Sheetavatar'.uniqid();
    $newEmployee = function (string $name) use ($token): Employee {
        $employee = Employee::factory()->create([
            'organisation_id' => $this->organisation->id,
            'group_id'        => $this->group->id,
            'state'           => EmployeeStateEnum::WORKING,
            'contact_name'    => "{$token} {$name}",
        ]);

        \App\Models\HumanResources\Timesheet::create([
            'group_id'                  => $this->group->id,
            'organisation_id'           => $this->organisation->id,
            'date'                      => now()->toDateString(),
            'subject_type'              => 'Employee',
            'subject_id'                => $employee->id,
            'subject_name'              => $employee->contact_name,
            'start_at'                  => now()->startOfDay()->addHours(8),
            'working_duration'          => 3600,
            'breaks_duration'           => 0,
            'total_duration'            => 3600,
            'number_time_trackers'      => 1,
            'number_open_time_trackers' => 0,
        ]);

        return $employee;
    };

    $withPicture    = $newEmployee('Own');
    $withoutPicture = $newEmployee('None');
    giveAvatarImage($withPicture);

    $carriesAvatars = function ($rows) use ($withPicture, $withoutPicture): bool {
        $rows = collect($rows)->keyBy(fn ($row) => $row['subject_name']);

        return $rows->count() === 2
            && $rows->every(fn ($row) => array_key_exists('avatar', $row) && ! array_key_exists('email', $row))
            && is_string($rows->get($withPicture->contact_name)['avatar'])
            && $rows->get($withPicture->contact_name)['avatar'] !== ''
            && $rows->get($withoutPicture->contact_name)['avatar'] === null
            && (int) $rows->get($withPicture->contact_name)['working_duration'] === 3600;
    };

    foreach (\App\Enums\UI\HumanResources\TimesheetEmployeeViewEnum::cases() as $view) {
        get(route('grp.org.hr.timesheets.index', [
            'organisation'    => $this->organisation->slug,
            'tab'             => 'employee',
            'view'            => $view->value,
            'employee_filter' => ['global' => $token],
        ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('employee.data', $carriesAvatars)->etc());
    }

    get(route('grp.org.hr.timesheets.index', [
        'organisation'     => $this->organisation->slug,
        'tab'              => 'employees',
        'employees_filter' => ['global' => $token],
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('employees.data', $carriesAvatars)->etc());
});

function createEmployeeLeaveTabLeave(Employee $employee, string $startDate, int $days, LeaveStatusEnum $status, array $attributes = []): Leave
{
    return Leave::create(array_merge([
        'group_id'        => $employee->group_id,
        'organisation_id' => $employee->organisation_id,
        'employee_id'     => $employee->id,
        'employee_name'   => $employee->contact_name,
        'type'            => 'annual',
        'start_date'      => $startDate,
        'end_date'        => \Carbon\Carbon::parse($startDate)->addDays($days - 1)->toDateString(),
        'duration_days'   => $days,
        'is_half_day'     => false,
        'session'         => 'Full',
        'reason'          => 'Leave tab fixture',
        'status'          => $status,
    ], $attributes));
}

test('the employee page leave tab counts that employee leave by status and year, filters its list and follows the page authorisation', function () {
    $thisYear = now()->year;
    $lastYear = $thisYear - 1;

    $leaveType = StoreLeaveType::make()->action($this->organisation, [
        'code'     => 'ET'.rand(10000, 99999),
        'name'     => 'Employee Tab Type '.rand(10000, 99999),
        'category' => \App\Enums\HumanResources\Leave\LeaveCategoryEnum::MEDICAL,
    ]);
    $leaveType->update(['color' => 'green']);

    $newEmployee = fn (): Employee => Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => EmployeeStateEnum::WORKING,
    ]);
    $employee  = $newEmployee();
    $colleague = $newEmployee();
    $employee->stats()->create();

    $contract = StoreEmployeeContract::make()->action($employee, [
        'start_date'        => now()->subMonth()->toDateString(),
        'annual_leave_days' => 20,
    ]);
    EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->firstOrFail()->update(['annual_used' => 3.5, 'medical_used' => 1]);

    $approvedTyped    = createEmployeeLeaveTabLeave($employee, "$thisYear-03-02", 3, LeaveStatusEnum::APPROVED, ['leave_type_id' => $leaveType->id, 'type' => $leaveType->code, 'cover_employee_id' => $colleague->id]);
    $approvedHalfDay  = createEmployeeLeaveTabLeave($employee, "$thisYear-04-10", 1, LeaveStatusEnum::APPROVED, ['is_half_day' => true, 'session' => 'Morning']);
    $pending          = createEmployeeLeaveTabLeave($employee, "$thisYear-05-05", 2, LeaveStatusEnum::PENDING);
    $rejected         = createEmployeeLeaveTabLeave($employee, "$thisYear-02-01", 1, LeaveStatusEnum::REJECTED, ['rejection_reason' => 'Peak season']);
    $approvedLastYear = createEmployeeLeaveTabLeave($employee, "$lastYear-08-03", 2, LeaveStatusEnum::APPROVED);
    $rejectedLastYear = createEmployeeLeaveTabLeave($employee, "$lastYear-09-01", 1, LeaveStatusEnum::REJECTED);
    createEmployeeLeaveTabLeave($employee, "$thisYear-06-01", 4, LeaveStatusEnum::APPROVED)->delete();
    createEmployeeLeaveTabLeave($employee, "$lastYear-10-01", 5, LeaveStatusEnum::PENDING)->delete();
    createEmployeeLeaveTabLeave($colleague, "$thisYear-03-02", 7, LeaveStatusEnum::APPROVED);

    $index = \App\Actions\HumanResources\Leave\UI\IndexEmployeeLeaves::make();

    expect($index->summary($index->statistics($employee)))->toEqual(['total' => 6, 'approved' => 3, 'pending' => 1, 'rejected' => 2, 'days_taken' => 5.5])
        ->and($index->resolveFilters([], 'leaves'))->toBe(['status' => null, 'year' => $thisYear])
        ->and($index->resolveFilters(['leaves_year' => 'all', 'leaves_status' => 'pending'], 'leaves'))->toBe(['status' => 'pending', 'year' => null])
        ->and(Validator::make(['leaves_status' => 'approved', 'leaves_year' => (string) $lastYear], $index->rules('leaves'))->passes())->toBeTrue()
        ->and(Validator::make(['leaves_status' => 'cancelled', 'leaves_year' => 'next'], $index->rules('leaves'))->errors()->keys())->toBe(['leaves_status', 'leaves_year']);

    setPermissionsTeamId($this->organisation->group_id);
    $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $user->forgetWildcardPermissionIndex();
    actingAs($user);

    $leaveTab  = fn (array $query = []) => get(route('grp.org.hr.employees.show', ['organisation' => $this->organisation->slug, 'employee' => $employee->slug, 'tab' => 'leaves', ...$query]));
    $summaryIs = fn (array $expected) => fn ($summary) => collect($summary)->map(fn ($value) => (float) $value)->all() == $expected;
    $idsAre    = fn (array $expected) => fn ($rows) => collect($rows)->pluck('id')->all() === $expected;

    $leaveTab()
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('tabs.current', 'leaves')
                ->where('tabs.navigation', fn ($navigation) => collect($navigation)->keys()->take(2)->all() === ['showcase', 'leaves'])
                ->where('leaves.filters.year', $thisYear)
                ->where('leaves.filters.status', null)
                ->where('leaves.year_options', fn ($years) => collect($years)->all() === [$thisYear, $lastYear])
                ->where('leaves.summary', $summaryIs(['total' => 4.0, 'approved' => 2.0, 'pending' => 1.0, 'rejected' => 1.0, 'days_taken' => 3.5]))
                ->where('leaves.by_type', function ($types) use ($leaveType) {
                    $types = collect($types)->map(fn ($type) => collect($type)->all())->all();

                    return count($types) === 2
                        && $types[0]['key'] === 'id:'.$leaveType->id
                        && $types[0]['label'] === $leaveType->name
                        && $types[0]['color_hex'] === '#16A34A'
                        && [$types[0]['requests'], $types[0]['approved'], (float) $types[0]['approved_days']] === [1, 1, 3.0]
                        && $types[1]['key'] === 'code:annual'
                        && [$types[1]['requests'], $types[1]['approved'], (float) $types[1]['approved_days']] === [3, 1, 0.5];
                })
                ->where('leaves.balance', fn ($balance) => (float) $balance['annual_allowance'] === 20.0
                    && (float) $balance['annual_used'] === 3.5
                    && (float) $balance['annual_remaining'] === 16.5
                    && (float) $balance['medical_used'] === 1.0
                    && (float) $balance['unpaid_used'] === 0.0)
                ->where('leaves.leaves.total', 4)
                ->where('leaves.leaves.data', $idsAre([$pending->id, $approvedHalfDay->id, $approvedTyped->id, $rejected->id]))
                ->where('leaves.leaves.data.1.days', fn ($days) => (float) $days === 0.5)
                ->where('leaves.leaves.data.2.days', fn ($days) => (float) $days === 3.0)
                ->where('leaves.leaves.data.2.type_color_hex', '#16A34A')
                ->where('leaves.leaves.data.2.cover_employee_name', $colleague->contact_name)
                ->where('leaves.leaves.data.3.rejection_reason', 'Peak season')
                ->where('leaves.leave_requests_route.name', 'grp.org.hr.leaves.index')
                ->where('leaves.leave_requests_route.parameters.filter.global', $employee->contact_name)
                ->where('leaves.query_parameters.status', 'leaves_status')
                ->where('leaves.query_parameters.year', 'leaves_year')
                ->etc()
        );

    $leaveTab(['leaves_year' => $lastYear])
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leaves.filters.year', $lastYear)
                ->where('leaves.summary', $summaryIs(['total' => 2.0, 'approved' => 1.0, 'pending' => 0.0, 'rejected' => 1.0, 'days_taken' => 2.0]))
                ->where('leaves.leaves.data', $idsAre([$rejectedLastYear->id, $approvedLastYear->id]))
                ->etc()
        );

    $leaveTab(['leaves_year' => 'all', 'leaves_status' => 'rejected'])
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leaves.filters.year', 'all')
                ->where('leaves.filters.status', 'rejected')
                ->where('leaves.summary', $summaryIs(['total' => 6.0, 'approved' => 3.0, 'pending' => 1.0, 'rejected' => 2.0, 'days_taken' => 5.5]))
                ->where('leaves.leaves.data', $idsAre([$rejected->id, $rejectedLastYear->id]))
                ->etc()
        );

    $leaveTab(['leaves_status' => 'approved'])
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leaves.leaves.data', $idsAre([$approvedHalfDay->id, $approvedTyped->id]))
                ->where('leaves.summary', $summaryIs(['total' => 4.0, 'approved' => 2.0, 'pending' => 1.0, 'rejected' => 1.0, 'days_taken' => 3.5]))
                ->etc()
        );

    $leaveTab(['leaves_year' => $thisYear + 1])
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leaves.summary', $summaryIs(['total' => 0.0, 'approved' => 0.0, 'pending' => 0.0, 'rejected' => 0.0, 'days_taken' => 0.0]))
                ->where('leaves.by_type', fn ($types) => collect($types)->isEmpty())
                ->where('leaves.leaves.total', 0)
                ->where('leaves.year_options', fn ($years) => collect($years)->all() === [$thisYear + 1, $thisYear, $lastYear])
                ->etc()
        );

    $leaveTab(['leaves_status' => 'cancelled'])->assertSessionHasErrors('leaves_status');

    get(route('grp.org.hr.employees.show', [$this->organisation->slug, $employee->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'showcase')->missing('leaves')->etc());

    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o'.substr(uniqid(), -5),
                'name'  => 'Other Org '.uniqid(),
                'email' => 'org-'.uniqid().'@example.com',
            ]
        ));

    $otherOrganisationUser = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $otherOrganisationUser->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $otherOrganisation));
    $otherOrganisationUser->forgetWildcardPermissionIndex();
    actingAs($otherOrganisationUser);

    $leaveTab()->assertForbidden();
    $leaveTab(['organisation' => $otherOrganisation->slug])->assertNotFound();
});

test('a section supervisor opens the leave tab of an employee in their section only', function () {
    $warehouse    = createWarehouse();
    $organisation = $warehouse->organisation;
    setPermissionsTeamId($warehouse->group_id);
    \App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($organisation);

    $positions = JobPosition::where('organisation_id', $organisation->id)->whereIn('code', ['wah-m', 'wah-sc', 'acc-c'])->get()->keyBy('code');

    $newEmployee = function () use ($organisation): Employee {
        $employee = Employee::factory()->create([
            'organisation_id' => $organisation->id,
            'group_id'        => $organisation->group_id,
            'state'           => EmployeeStateEnum::WORKING,
        ]);
        $employee->stats()->create();

        return $employee;
    };

    $warehouseClerk = $newEmployee();
    SyncEmployeeJobPositions::make()->handle($warehouseClerk, [$positions['wah-sc']->id => ['Warehouse' => [$warehouse->id]]]);

    $accountingClerk = $newEmployee();
    SyncEmployeeJobPositions::make()->handle($accountingClerk, [$positions['acc-c']->id => []]);

    $clerkLeave = createEmployeeLeaveTabLeave($warehouseClerk, now()->year.'-03-02', 2, LeaveStatusEnum::APPROVED);
    createEmployeeLeaveTabLeave($accountingClerk, now()->year.'-03-02', 2, LeaveStatusEnum::APPROVED);

    $supervisor = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
    \App\Actions\SysAdmin\User\SyncUserPseudoOrganisationJobPositions::make()->handle($supervisor, $organisation, [$positions['wah-m']->id => ['Warehouse' => [$warehouse->id]]]);
    actingAs($supervisor->refresh());

    get(route('grp.org.hr.employees.show', ['organisation' => $organisation->slug, 'employee' => $warehouseClerk->slug, 'tab' => 'leaves']))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('tabs.navigation', fn ($navigation) => collect($navigation)->has('leaves') && !collect($navigation)->has('requests'))
                ->where('leaves.summary.approved', 1)
                ->where('leaves.leaves.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$clerkLeave->id])
                ->etc()
        );

    get(route('grp.org.hr.employees.show', ['organisation' => $organisation->slug, 'employee' => $accountingClerk->slug, 'tab' => 'leaves']))->assertForbidden();
});

test('the clockings list breadcrumb carries the organisation its link needs', function () {
    $breadcrumbs = \App\Actions\HumanResources\Clocking\UI\IndexClockings::make()
        ->getBreadcrumbs('grp.org.hr.clockings.index', ['organisation' => $this->organisation->slug]);

    $clockingsCrumb = collect($breadcrumbs)->last();

    expect(data_get($clockingsCrumb, 'simple.route.name'))->toBe('grp.org.hr.clockings.index')
        ->and(data_get($clockingsCrumb, 'simple.route.parameters'))->toBe(['organisation' => $this->organisation->slug])
        ->and(route(data_get($clockingsCrumb, 'simple.route.name'), data_get($clockingsCrumb, 'simple.route.parameters')))->toContain('/hr/clocking');
});

test('a leave with a verdict cannot be approved or rejected again while multi-level approval still works', function () {
    Notification::fake();

    createLeaveApproverFixture($this->organisation, 1);
    createLeaveApproverFixture($this->organisation, 2);

    $approved = createPendingLeaveFixture($this->organisation, $this->group);
    $levels   = $approved->approvalLevels();
    $balance  = fn (Leave $leave) => (float) EmployeeLeaveBalance::where('employee_id', $leave->employee_id)->value('annual_used');

    expect(count($levels))->toBeGreaterThanOrEqual(2);

    $lastApprover = null;
    foreach ($levels as $level) {
        $lastApprover = createLeaveApproverFixture($this->organisation, $level);
        actingAs($lastApprover->user);
        ApproveLeave::make()->handle($approved);

        expect($approved->refresh()->status)->toBe($level === end($levels) ? LeaveStatusEnum::APPROVED : LeaveStatusEnum::PENDING);
    }

    expect($balance($approved))->toBe(2.0);

    expect(fn () => RejectLeave::make()->handle($approved, 'Changed my mind'))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'This leave request has already been approved.')
        ->and(fn () => ApproveLeave::make()->handle($approved))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'This leave request has already been approved.');

    $leaveRoute = fn (string $name, Leave $leave) => route($name, ['organisation' => $this->organisation->slug, 'leave' => $leave->id]);

    setPermissionsTeamId($this->organisation->group_id);
    $lastApprover->user->update(['status' => true]);
    $lastApprover->user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $lastApprover->user->forgetWildcardPermissionIndex();
    actingAs($lastApprover->user->refresh());

    post($leaveRoute('grp.org.hr.leaves.reject', $approved), ['rejection_reason' => 'Changed my mind'])->assertSessionHasErrors('status');
    post($leaveRoute('grp.org.hr.leaves.approve', $approved))->assertSessionHasErrors('status');

    expect($approved->refresh()->status)->toBe(LeaveStatusEnum::APPROVED)
        ->and($approved->rejection_reason)->toBeNull()
        ->and($balance($approved))->toBe(2.0);

    $rejected = createPendingLeaveFixture($this->organisation, $this->group);

    $firstLevelApprover = createLeaveApproverFixture($this->organisation, $levels[0]);
    actingAs($firstLevelApprover->user);
    ApproveLeave::make()->handle($rejected);
    expect($rejected->refresh()->status)->toBe(LeaveStatusEnum::PENDING);

    actingAs($lastApprover->user);
    post($leaveRoute('grp.org.hr.leaves.reject', $rejected), ['rejection_reason' => 'No cover available'])->assertSessionHasNoErrors();

    expect($rejected->refresh()->status)->toBe(LeaveStatusEnum::REJECTED)
        ->and($rejected->rejection_reason)->toBe('No cover available');

    expect(fn () => ApproveLeave::make()->handle($rejected))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'This leave request has already been rejected.')
        ->and(fn () => RejectLeave::make()->handle($rejected, 'Again'))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'This leave request has already been rejected.');

    post($leaveRoute('grp.org.hr.leaves.approve', $rejected))->assertSessionHasErrors('status');

    expect($rejected->refresh()->status)->toBe(LeaveStatusEnum::REJECTED)
        ->and($rejected->rejection_reason)->toBe('No cover available')
        ->and($balance($rejected))->toBe(0.0);
});

test('the overtime list and the clocking adjustment list carry each employee avatar', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $viewer = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $viewer->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $viewer->forgetWildcardPermissionIndex();
    actingAs($viewer);

    $withPicture    = createOvertimeRequestFixture($this->organisation, $this->group);
    $withoutPicture = createOvertimeRequestFixture($this->organisation, $this->group);
    $withPicture->update(['requested_date' => '2033-07-07']);
    $withoutPicture->update(['requested_date' => '2033-07-07']);

    if ($withoutPicture->employee_id === $withPicture->employee_id) {
        $withoutPicture->update(['employee_id' => Employee::factory()->create([
            'organisation_id' => $this->organisation->id,
            'group_id'        => $this->group->id,
        ])->id]);
    }

    $pictured = $withPicture->employee;
    giveAvatarImage($pictured);
    $plain = $withoutPicture->refresh()->employee;
    $plain->update(['image_id' => null, 'user_id' => null]);

    $carriesAvatars = function (array $keys, int $picturedId, int $plainId): Closure {
        return function ($rows) use ($keys, $picturedId, $plainId): bool {
            $rows = collect($rows)->keyBy('id');

            return $rows->every(fn ($row) => collect($keys)->every(fn ($key) => array_key_exists($key, $row)) && ! array_key_exists('email', $row))
                && is_string($rows->get($picturedId)['employee_avatar'])
                && $rows->get($picturedId)['employee_avatar'] !== ''
                && $rows->get($plainId)['employee_avatar'] === null;
        };
    };

    get(route('grp.org.hr.overtime.index', ['organisation' => $this->organisation->slug, 'from' => '2033-07-07', 'to' => '2033-07-07']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', $carriesAvatars(['employee_avatar', 'approver_avatar'], $withPicture->id, $withoutPicture->id))
            ->etc());

    $makeAdjustment = fn (Employee $employee) => AttendanceAdjustment::create([
        'group_id'           => $this->group->id,
        'organisation_id'    => $this->organisation->id,
        'employee_id'        => $employee->id,
        'employee_name'      => $employee->contact_name,
        'date'               => '2033-07-08',
        'reason'             => 'Avatar fixture',
        'requested_start_at' => '2033-07-08 08:30:00',
        'status'             => 'pending',
    ]);
    $picturedAdjustment = $makeAdjustment($pictured);
    $plainAdjustment    = $makeAdjustment($plain);

    get(route('grp.org.hr.adjustments.index', ['organisation' => $this->organisation->slug, 'from' => '2033-07-08', 'to' => '2033-07-08']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('adjustments.data', $carriesAvatars(['employee_avatar', 'approver_avatar'], $picturedAdjustment->id, $plainAdjustment->id))
            ->etc());
});

test('human resources pages and actions never serve or change a record of another organisation', function () {
    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o'.substr(uniqid(), -5),
                'name'  => 'Other Org '.uniqid(),
                'email' => 'org-'.uniqid().'@example.com',
            ]
        ));

    $newEmployee = function (\App\Models\SysAdmin\Organisation $organisation): Employee {
        $employee = Employee::factory()->create([
            'organisation_id' => $organisation->id,
            'group_id'        => $organisation->group_id,
            'state'           => EmployeeStateEnum::WORKING,
        ]);
        $employee->stats()->create();

        return $employee;
    };

    $employee      = $newEmployee($this->organisation);
    $otherEmployee = $newEmployee($otherOrganisation);

    $contract      = StoreEmployeeContract::make()->action($employee, ['start_date' => now()->subMonth()->toDateString(), 'annual_leave_days' => 20]);
    $otherContract = StoreEmployeeContract::make()->action($otherEmployee, ['start_date' => now()->subMonth()->toDateString(), 'annual_leave_days' => 20]);
    $timesheet     = makeAdjustmentTimesheet($this, $employee, '2031-05-06');
    $employeeUser  = StoreUserFromEmployee::make()->handle($employee, ['username' => 'scope-'.$employee->id, 'password' => 'secret123']);
    $workplace     = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Scoped Workplace '.uniqid(),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);
    $jobPosition   = JobPosition::where('organisation_id', $this->organisation->id)->firstOrFail();
    $overtimeType  = StoreOvertimeType::make()->action($this->organisation, [
        'code'              => 'SC'.rand(10000, 99999),
        'name'              => 'Scoped overtime '.uniqid(),
        'category'          => \App\Enums\HumanResources\Overtime\OvertimeCategoryEnum::cases()[0]->value,
        'compensation_type' => \App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum::cases()[0]->value,
    ]);

    $employee->refresh();
    $nameBefore      = $employee->contact_name;
    $pinBefore       = $employee->pin;
    $contractsBefore = $employee->contracts()->count();
    $annualBefore    = (float) $contract->annual_leave_days;

    setPermissionsTeamId($this->organisation->group_id);
    $hrUserOf = function (\App\Models\SysAdmin\Organisation $organisation): User {
        $user = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
        $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $organisation));
        $user->forgetWildcardPermissionIndex();

        return $user;
    };

    $other = $otherOrganisation->slug;
    $pages = fn (string $organisation, Employee $subject, EmployeeContract $subjectContract): array => [
        'show'             => route('grp.org.hr.employees.show', [$organisation, $subject->slug]),
        'leave tab'        => route('grp.org.hr.employees.show', [$organisation, $subject->slug, 'tab' => 'leaves']),
        'edit'             => route('grp.org.hr.employees.edit', [$organisation, $subject->slug]),
        'positions'        => route('grp.org.hr.employees.show.positions.index', [$organisation, $subject->slug]),
        'timesheets'       => route('grp.org.hr.employees.show.timesheets.index', [$organisation, $subject->slug]),
        'contracts'        => route('grp.org.hr.employees.show.contracts.index', [$organisation, $subject->slug]),
        'contracts create' => route('grp.org.hr.employees.show.contracts.create', [$organisation, $subject->slug]),
        'contracts edit'   => route('grp.org.hr.employees.show.contracts.edit', [$organisation, $subject->slug, $subjectContract->id]),
    ];

    actingAs($hrUserOf($otherOrganisation));

    foreach ($pages($other, $employee, $contract) as $page => $url) {
        expect(get($url)->status())->toBe(404, $page);
    }

    foreach ([
        'pin'               => route('grp.org.hr.employees.generate-pin', [$other, $employee->slug]),
        'timesheets pdf'    => route('grp.org.hr.employees.show.timesheets.pdf', [$other, $employee->slug]),
        'timesheets export' => route('grp.org.hr.employees.show.timesheets.export', [$other, $employee->slug]),
        'timesheet'         => route('grp.org.hr.employees.show.timesheets.show', [$other, $employee->slug, $timesheet->id]),
        'own timesheet url' => route('grp.org.hr.employees.show.timesheets.show', [$other, $otherEmployee->slug, $timesheet->id]),
        'org timesheet'     => route('grp.org.hr.timesheets.show', [$other, $timesheet->id]),
        'user'              => route('grp.org.hr.employees.show.users.show', [$other, $employee->slug, $employeeUser->slug]),
        'unlinked user'     => route('grp.org.hr.employees.show.users.show', [$other, $otherEmployee->slug, $employeeUser->slug]),
        'user edit'         => route('grp.org.hr.employees.show.users.edit', [$other, $otherEmployee->slug, $employeeUser->slug]),
        'foreign contract'  => route('grp.org.hr.employees.show.contracts.edit', [$other, $otherEmployee->slug, $contract->id]),
        'contract json'     => route('grp.org.hr.employees.contract', [$other, $employee->slug]),
        'workplace'         => route('grp.org.hr.workplaces.show', [$other, $workplace->slug]),
        'workplace edit'    => route('grp.org.hr.workplaces.edit', [$other, $workplace->slug]),
        'job position'      => route('grp.org.hr.job_positions.show', [$other, $jobPosition->slug]),
    ] as $page => $url) {
        expect(get($url)->status())->toBe(404, $page);
    }

    post(route('grp.org.hr.employees.regenerate-pin', [$other, $employee->slug]))->assertNotFound();
    $this->patch(route('grp.org.hr.employees.adjust-leave', [$other, $employee->slug]))->assertNotFound();
    $this->patch(route('grp.org.hr.overtime_types.update', [$other, $overtimeType->id]), ['name' => 'Taken over'])->assertNotFound();
    $this->delete(route('grp.org.hr.overtime_types.delete', [$other, $overtimeType->id]))->assertNotFound();

    $this->patch(route('grp.models.employee.update', $employee->id), ['contact_name' => 'Taken Over'])->assertForbidden();
    $this->delete(route('grp.models.employee..delete', $employee->id))->assertForbidden();
    post(route('grp.models.employee.contracts.store', $employee->id), ['start_date' => now()->addYear()->toDateString()])->assertForbidden();
    $this->patch(route('grp.models.employee.contracts.update', $contract->id), ['annual_leave_days' => 99])->assertForbidden();

    $employee->refresh();
    expect($employee->trashed())->toBeFalse()
        ->and($employee->contact_name)->toBe($nameBefore)
        ->and($employee->pin)->toBe($pinBefore)
        ->and($employee->contracts()->count())->toBe($contractsBefore)
        ->and((float) $contract->refresh()->annual_leave_days)->toBe($annualBefore)
        ->and($overtimeType->refresh()->name)->not->toBe('Taken over');

    foreach ($pages($other, $otherEmployee, $otherContract) as $page => $url) {
        expect(get($url)->status())->toBe(200, $page);
    }

    actingAs($hrUserOf($this->organisation));

    foreach ($pages($this->organisation->slug, $employee, $contract) as $page => $url) {
        expect(get($url)->status())->toBe(200, $page);
    }

    get(route('grp.org.hr.employees.show.timesheets.show', [$this->organisation->slug, $employee->slug, $timesheet->id]))->assertOk();
    get(route('grp.org.hr.workplaces.show', [$this->organisation->slug, $workplace->slug]))->assertOk();
    get(route('grp.org.hr.job_positions.show', [$this->organisation->slug, $jobPosition->slug]))->assertOk();

    $this->patch(route('grp.models.employee.contracts.update', $contract->id), ['annual_leave_days' => 21])->assertRedirect();
    $this->patch(route('grp.models.employee.update', $employee->id), ['contact_name' => 'Renamed By Own Hr'])->assertRedirect();

    expect((float) $contract->refresh()->annual_leave_days)->toBe(21.0)
        ->and($employee->refresh()->contact_name)->toBe('Renamed By Own Hr');
});

test('an employee with a contract but no balance for the requested year can request leave and gets that year balance', function () {
    Notification::fake();

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => EmployeeStateEnum::WORKING,
    ]);
    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'leave-year-'.$employee->id,
        'password' => 'secret123',
    ]);

    $contractStart = now()->subYears(2)->subMonths(2)->startOfMonth();
    $contract      = StoreEmployeeContract::make()->action($employee, [
        'start_date'        => $contractStart->toDateString(),
        'annual_leave_days' => 18,
    ]);

    $firstYear = EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->firstOrFail();
    $firstYear->update(['annual_used' => 4, 'medical_used' => 1]);
    $firstYearBefore = $firstYear->refresh()->only(['period_start', 'period_end', 'annual_used', 'medical_used', 'unpaid_used']);

    $leaveType = StoreLeaveType::make()->action($this->organisation, [
        'code'     => 'YR'.rand(10000, 99999),
        'name'     => 'Year balance type '.rand(10000, 99999),
        'category' => \App\Enums\HumanResources\Leave\LeaveCategoryEnum::MEDICAL,
    ]);

    $day = now()->addDays(10)->startOfDay();
    while ($day->isWeekend() || Holiday::where('organisation_id', $this->organisation->id)->whereDate('from', '<=', $day)->whereDate('to', '>=', $day)->exists()) {
        $day->addDay();
    }

    actingAs($user);

    post(route('grp.clocking_employees.leaves.store'), [
        'organisation' => $this->organisation->slug,
        'type'         => $leaveType->code,
        'start_date'   => $day->toDateString(),
        'end_date'     => $day->toDateString(),
        'reason'       => 'Check-up',
    ])->assertSessionHasNoErrors();

    expect(Leave::where('employee_id', $employee->id)->where('status', LeaveStatusEnum::PENDING)->count())->toBe(1);

    $balances = EmployeeLeaveBalance::where('employee_id', $employee->id)->orderBy('id')->get();
    $thisYear = $balances->firstWhere('id', '!=', $firstYear->id);

    expect($balances)->toHaveCount(2)
        ->and($thisYear->employee_contract_id)->toBe($contract->id)
        ->and($thisYear->period_start->toDateString())->toBe($contractStart->copy()->addYears(2)->toDateString())
        ->and($thisYear->period_end->toDateString())->toBe($contractStart->copy()->addYears(3)->subDay()->toDateString())
        ->and([(float) $thisYear->annual_used, (float) $thisYear->medical_used, (float) $thisYear->unpaid_used])->toBe([0.0, 0.0, 0.0])
        ->and($thisYear->contract->annual_leave_days)->toBe(18.0)
        ->and((float) $thisYear->annual_remaining)->toBe(18.0)
        ->and($firstYear->refresh()->only(['period_start', 'period_end', 'annual_used', 'medical_used', 'unpaid_used']))->toEqual($firstYearBefore);
});

test('editing contract dates keeps its yearly leave balances distinct and their figures untouched', function () {
    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $contract = StoreEmployeeContract::make()->action($employee, [
        'start_date'        => '2031-01-10',
        'annual_leave_days' => 20,
    ]);

    $yearOne   = EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->firstOrFail();
    $yearTwo   = GenerateEmployeeLeaveBalance::run($contract, \Carbon\Carbon::parse('2032-01-10'));
    $yearThree = GenerateEmployeeLeaveBalance::run($contract, \Carbon\Carbon::parse('2033-01-10'));

    $yearOne->update(['annual_used' => 3]);
    $yearTwo->update(['medical_used' => 2.5]);
    $yearThree->update(['unpaid_used' => 1]);

    $rows    = [$yearOne, $yearTwo, $yearThree];
    $periods = fn () => array_map(fn (EmployeeLeaveBalance $row) => [$row->refresh()->period_start->toDateString(), $row->period_end->toDateString()], $rows);
    $figures = fn () => array_map(fn (EmployeeLeaveBalance $row) => [(float) $row->refresh()->annual_used, (float) $row->medical_used, (float) $row->unpaid_used], $rows);

    $originalPeriods = [['2031-01-10', '2032-01-09'], ['2032-01-10', '2033-01-09'], ['2033-01-10', '2034-01-09']];
    $originalFigures = [[3.0, 0.0, 0.0], [0.0, 2.5, 0.0], [0.0, 0.0, 1.0]];

    expect($periods())->toBe($originalPeriods);

    $updated = UpdateEmployeeContract::make()->action($contract, ['start_date' => '2031-01-10', 'annual_leave_days' => 22, 'notes' => 'Allowance raised']);

    expect($updated->annual_leave_days)->toBe(22.0)
        ->and($periods())->toBe($originalPeriods)
        ->and($figures())->toBe($originalFigures);

    UpdateEmployeeContract::make()->action($contract->refresh(), ['start_date' => '2031-03-01']);

    $shiftedPeriods = [['2031-03-01', '2032-02-29'], ['2032-03-01', '2033-02-28'], ['2033-03-01', '2034-02-28']];

    expect($periods())->toBe($shiftedPeriods)
        ->and($figures())->toBe($originalFigures)
        ->and(EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->count())->toBe(3);

    $leaveInYearTwo = createApprovedLeaveBalanceFixture($employee, [
        'start_date' => '2032-06-01',
        'end_date'   => '2032-06-01',
        'status'     => LeaveStatusEnum::PENDING,
    ]);

    expect(\App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance::make()->balanceForLeave($leaveInYearTwo, false)->id)->toBe($yearTwo->id);

    EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->update(['period_start' => '2031-03-01', 'period_end' => null]);
    UpdateEmployeeContract::make()->action($contract->refresh(), ['end_date' => '2040-12-31']);

    expect($periods())->toBe($shiftedPeriods)
        ->and($figures())->toBe($originalFigures);

    UpdateEmployeeContract::make()->action($contract->refresh(), ['end_date' => '2032-06-30']);

    expect($periods())->toBe([['2031-03-01', '2032-02-29'], ['2032-03-01', '2032-06-30'], ['2033-03-01', '2034-02-28']])
        ->and($figures())->toBe($originalFigures)
        ->and(\App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance::make()->balanceForLeave($leaveInYearTwo, false)->id)->toBe($yearTwo->id);
});

test('leave records of another organisation cannot be changed and generating a balance needs hr edit', function () {
    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o'.substr(uniqid(), -5),
                'name'  => 'Other Leave Org '.uniqid(),
                'email' => 'org-'.uniqid().'@example.com',
            ]
        ));

    $leave     = createPendingLeaveFixture($this->organisation, $this->group);
    $approver  = createLeaveApproverFixture($this->organisation, 1);
    $leaveType = StoreLeaveType::make()->action($this->organisation, [
        'code'     => 'XO'.rand(10000, 99999),
        'name'     => 'Scoped leave type',
        'category' => \App\Enums\HumanResources\Leave\LeaveCategoryEnum::PERSONAL,
    ]);

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
        'state'           => EmployeeStateEnum::WORKING,
    ]);
    $contract = StoreEmployeeContract::make()->action($employee, ['start_date' => now()->subMonth()->toDateString(), 'annual_leave_days' => 20]);

    setPermissionsTeamId($this->organisation->group_id);
    $hrUserOf = function (\App\Models\SysAdmin\Organisation $organisation): User {
        $user = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
        $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $organisation));
        $user->forgetWildcardPermissionIndex();

        return $user;
    };

    $generateBalance = route('grp.models.employee.contracts.generate_balance', ['contract' => $contract->id]);
    $balanceCount    = fn (): int => EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->count();

    actingAs($hrUserOf($otherOrganisation));
    $other = $otherOrganisation->slug;

    $this->delete(route('grp.org.hr.leaves.delete', [$other, $leave->id]))->assertNotFound();
    $this->patch(route('grp.org.hr.leaves.types.update', [$other, $leaveType->id]), ['name' => 'Taken over'])->assertNotFound();
    $this->delete(route('grp.org.hr.leaves.types.delete', [$other, $leaveType->id]))->assertNotFound();
    $this->delete(route('grp.org.hr.leave_approvers.delete', [$other, $approver->id]))->assertNotFound();
    post($generateBalance)->assertForbidden();

    expect(Leave::find($leave->id))->not->toBeNull()
        ->and($leaveType->refresh()->name)->toBe('Scoped leave type')
        ->and(LeaveApprover::find($approver->id))->not->toBeNull()
        ->and($balanceCount())->toBe(1);

    actingAs(User::factory()->create(['group_id' => $this->group->id, 'status' => true]));
    post($generateBalance)->assertForbidden();

    actingAs($hrUserOf($this->organisation));
    $own = $this->organisation->slug;

    post($generateBalance)->assertRedirect();
    expect($balanceCount())->toBe(1);

    $this->patch(route('grp.org.hr.leaves.types.update', [$own, $leaveType->id]), ['name' => 'Renamed by own hr'])->assertRedirect();
    expect($leaveType->refresh()->name)->toBe('Renamed by own hr');

    $this->delete(route('grp.org.hr.leaves.types.delete', [$own, $leaveType->id]))->assertRedirect();
    $this->delete(route('grp.org.hr.leave_approvers.delete', [$own, $approver->id]))->assertRedirect();
    $this->delete(route('grp.org.hr.leaves.delete', [$own, $leave->id]))->assertRedirect();

    expect(LeaveType::find($leaveType->id))->toBeNull()
        ->and(LeaveApprover::find($approver->id))->toBeNull()
        ->and(Leave::find($leave->id))->toBeNull();

    EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->delete();
    $this->artisan('leave:generate-balances')->assertExitCode(0);

    expect($balanceCount())->toBe(1)
        ->and(GenerateEmployeeLeaveBalance::run($contract)->employee_contract_id)->toBe($contract->id);
});

test('generating a clocking machine qr code needs hr edit', function () {
    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'QR Guard Workplace '.uniqid(),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::BRANCH,
    ]);
    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'QR Guard Machine '.uniqid(),
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
    ]);

    $generate    = route('grp.models.clocking-machine.qr.generate', ['clockingMachine' => $clockingMachine->slug]);
    $qrCodeCount = fn (): int => ClockingMachineQRCode::where('clocking_machine_id', $clockingMachine->id)->count();
    $baseline    = $qrCodeCount();

    setPermissionsTeamId($this->organisation->group_id);

    actingAs(User::factory()->create(['group_id' => $this->group->id, 'status' => true]));
    $this->postJson($generate, ['label' => 'Not allowed'])->assertForbidden();
    expect($qrCodeCount())->toBe($baseline);

    $hrUser = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $hrUser->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $hrUser->forgetWildcardPermissionIndex();

    actingAs($hrUser);
    $this->postJson($generate, ['label' => 'Front door'])->assertOk()->assertJsonPath('success', true);
    expect($qrCodeCount())->toBe($baseline + 1);
});

test('clockings pages and the org chart are closed to users without human resources access', function () {
    $employee = StoreEmployee::make()->action($this->organisation, array_merge(Employee::factory()->make()->toArray(), [
        'worker_number'   => 'closed-'.uniqid(),
        'alias'           => 'closed-'.uniqid(),
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'state'           => EmployeeStateEnum::WORKING,
    ]));
    $workplace = StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Closed Pages Workplace '.uniqid(),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::BRANCH,
    ]);
    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, ['type' => 'in', 'at' => now()->toDateTimeString()], 0, true);

    $organisation = $this->organisation->slug;
    $pages        = [
        route('grp.org.hr.clockings.index', $organisation),
        route('grp.org.hr.clockings.show', [$organisation, $clocking->id]),
        route('grp.org.hr.clockings.edit', [$organisation, $clocking->id]),
        route('grp.org.hr.org_chart', $organisation),
    ];

    setPermissionsTeamId($this->organisation->group_id);
    actingAs(User::factory()->create(['group_id' => $this->group->id, 'status' => true]));

    foreach ($pages as $page) {
        get($page)->assertForbidden();
    }
});

test('the opening adjustment of a leave balance survives recalculation and is edited by own hr only', function () {
    $otherOrganisation = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()
        ->action($this->group, array_merge(
            \App\Models\SysAdmin\Organisation::factory()->definition(),
            [
                'code'  => 'o'.substr(uniqid(), -5),
                'name'  => 'Other Adjustment Org '.uniqid(),
                'email' => 'org-'.uniqid().'@example.com',
            ]
        ));

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $contract = StoreEmployeeContract::make()->action($employee, ['start_date' => now()->subMonth()->toDateString(), 'annual_leave_days' => 20]);
    $balance  = EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->firstOrFail();

    $figures = fn (): array => array_map('floatval', $balance->refresh()->only(['annual_used', 'annual_adjustment', 'medical_used', 'medical_adjustment', 'unpaid_used', 'unpaid_adjustment']));

    expect($figures())->toBe(['annual_used' => 0.0, 'annual_adjustment' => 0.0, 'medical_used' => 0.0, 'medical_adjustment' => 0.0, 'unpaid_used' => 0.0, 'unpaid_adjustment' => 0.0]);

    $adjust = \App\Actions\HumanResources\EmployeeContract\UpdateLeaveBalanceAdjustment::make();
    $adjust->action($balance, ['annual_adjustment' => 3, 'medical_adjustment' => 1.5]);

    expect($figures())->toBe(['annual_used' => 3.0, 'annual_adjustment' => 3.0, 'medical_used' => 1.5, 'medical_adjustment' => 1.5, 'unpaid_used' => 0.0, 'unpaid_adjustment' => 0.0]);

    $leave = createApprovedLeaveBalanceFixture($employee, ['type' => 'annual', 'duration_days' => 2]);
    expect($figures()['annual_used'])->toBe(5.0)
        ->and($figures()['annual_adjustment'])->toBe(3.0);

    $leave->update(['duration_days' => 4]);
    \App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance::make()->forLeave($leave);
    expect($figures()['annual_used'])->toBe(7.0);

    $this->artisan('leave:recalculate-balances', ['--execute' => true])->assertExitCode(0);
    expect($figures()['annual_used'])->toBe(7.0)
        ->and($figures()['medical_used'])->toBe(1.5);

    DeleteLeave::make()->action($leave);
    expect($figures()['annual_used'])->toBe(3.0)
        ->and($figures()['annual_adjustment'])->toBe(3.0);

    foreach ([['annual_adjustment' => 0.3], ['annual_adjustment' => -1], ['unpaid_adjustment' => 400], ['medical_adjustment' => 'lots']] as $invalid) {
        expect(fn () => $adjust->action($balance, $invalid))->toThrow(\Illuminate\Validation\ValidationException::class);
    }

    $untouched = EmployeeLeaveBalance::create(['employee_id' => $employee->id, 'employee_contract_id' => null, 'annual_used' => 9, 'medical_used' => 0, 'unpaid_used' => 0]);
    expect((float) $untouched->refresh()->annual_used)->toBe(9.0)
        ->and((float) $untouched->annual_adjustment)->toBe(0.0);

    setPermissionsTeamId($this->organisation->group_id);
    $hrUserOf = function (\App\Models\SysAdmin\Organisation $organisation): User {
        $user = User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
        $user->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $organisation));
        $user->forgetWildcardPermissionIndex();

        return $user;
    };
    $adjustmentRoute = route('grp.models.employee.leave_balance.adjustment', ['balance' => $balance->id]);

    actingAs($hrUserOf($otherOrganisation));
    $this->patch($adjustmentRoute, ['annual_adjustment' => 10])->assertForbidden();

    actingAs(User::factory()->create(['group_id' => $this->group->id, 'status' => true]));
    $this->patch($adjustmentRoute, ['annual_adjustment' => 10])->assertForbidden();

    expect($figures()['annual_adjustment'])->toBe(3.0);

    actingAs($hrUserOf($this->organisation));
    $this->patch($adjustmentRoute, ['annual_adjustment' => 4.5, 'medical_adjustment' => 0, 'unpaid_adjustment' => 0])->assertRedirect();
    $this->patch($adjustmentRoute, ['annual_adjustment' => 0.25])->assertSessionHasErrors('annual_adjustment');

    expect($figures())->toBe(['annual_used' => 4.5, 'annual_adjustment' => 4.5, 'medical_used' => 0.0, 'medical_adjustment' => 0.0, 'unpaid_used' => 0.0, 'unpaid_adjustment' => 0.0]);
});

test('contract changes that touch leave balances are previewed and saved with the chosen figures', function () {
    $planner = \App\Actions\HumanResources\EmployeeContract\PlanContractLeaveBalances::make();
    $preview = \App\Actions\HumanResources\EmployeeContract\PreviewContractLeaveBalances::make();
    $entryOf = fn (array $result, string|int $key): ?array => collect($result['entries'])->firstWhere('key', (string) $key);
    $zero    = ['annual_used' => 0.0, 'medical_used' => 0.0, 'unpaid_used' => 0.0];

    $employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);
    $contract = StoreEmployeeContract::make()->action($employee, ['start_date' => '2031-01-10', 'annual_leave_days' => 20]);
    $yearOne  = EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->firstOrFail();
    $yearTwo  = GenerateEmployeeLeaveBalance::run($contract, \Carbon\Carbon::parse('2032-01-10'));
    $leave    = createApprovedLeaveBalanceFixture($employee, ['type' => 'annual', 'duration_days' => 2, 'start_date' => '2031-02-03', 'end_date' => '2031-02-04']);

    expect((float) $yearOne->refresh()->annual_used)->toBe(2.0);
    $yearOne->update(['annual_used' => 6]);

    setPermissionsTeamId($this->organisation->group_id);
    $hrUser = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $hrUser->assignRole(RolesEnum::getRoleName(RolesEnum::HUMAN_RESOURCES_SUPERVISOR->value, $this->organisation));
    $hrUser->forgetWildcardPermissionIndex();
    actingAs($hrUser);

    $updateRoute  = route('grp.models.employee.contracts.update', $contract->id);
    $previewRoute = route('grp.models.employee.contracts.preview', $contract->id);

    $notesOnly = $preview->handle($contract, 'update', ['notes' => 'Renewed']);
    expect($notesOnly['requires_confirmation'])->toBeFalse()
        ->and($notesOnly['entries'])->toBe([]);

    $this->patch($updateRoute, ['notes' => 'Renewed'])->assertSessionHasNoErrors();
    expect($contract->refresh()->notes)->toBe('Renewed')
        ->and((float) $yearOne->refresh()->annual_used)->toBe(6.0)
        ->and((float) $yearOne->annual_adjustment)->toBe(0.0);

    $allowance = $this->postJson($previewRoute, ['operation' => 'update', 'annual_leave_days' => 25])->assertOk()->json();
    $yearOneEntry = $entryOf($allowance, $yearOne->id);

    expect($allowance['requires_confirmation'])->toBeTrue()
        ->and($yearOneEntry['kind'])->toBe('changed')
        ->and($yearOneEntry['allowance_before'])->toEqual(20)
        ->and($yearOneEntry['allowance_after'])->toEqual(25)
        ->and($yearOneEntry['before']['annual_used'])->toEqual(6)
        ->and($yearOneEntry['has_choice'])->toBeTrue()
        ->and($yearOneEntry['options']['carry']['annual_used'])->toEqual(6)
        ->and($yearOneEntry['options']['recorded']['annual_used'])->toEqual(2)
        ->and($entryOf($allowance, $yearTwo->id)['has_choice'])->toBeFalse();

    $this->patch($updateRoute, ['annual_leave_days' => 25])->assertSessionHasErrors('balance_changes');
    $this->patch($updateRoute, ['annual_leave_days' => 25, 'confirm_balance_changes' => true])->assertSessionHasErrors('balance_choices');
    expect((float) $contract->refresh()->annual_leave_days)->toBe(20.0)
        ->and((float) $yearOne->refresh()->annual_used)->toBe(6.0);

    $this->patch($updateRoute, ['annual_leave_days' => 25, 'confirm_balance_changes' => true, 'balance_choices' => [$yearOne->id => 'recorded']])->assertSessionHasNoErrors();
    expect((float) $contract->refresh()->annual_leave_days)->toBe(25.0)
        ->and((float) $yearOne->refresh()->annual_used)->toBe(2.0)
        ->and((float) $yearOne->annual_adjustment)->toBe(0.0);

    $yearOne->update(['annual_used' => 6]);
    $dates        = $preview->handle($contract->refresh(), 'update', ['start_date' => '2031-03-01']);
    $yearOneEntry = $entryOf($dates, $yearOne->id);

    expect($dates['requires_confirmation'])->toBeTrue()
        ->and($yearOneEntry['period_before']['start'])->toBe('2031-01-10')
        ->and($yearOneEntry['period_after']['start'])->toBe('2031-03-01')
        ->and($yearOneEntry['period_after']['end'])->toBe('2032-02-29')
        ->and($yearOneEntry['options']['carry']['annual_used'])->toEqual(6)
        ->and($yearOneEntry['options']['recorded']['annual_used'])->toEqual(0)
        ->and($entryOf($dates, $yearTwo->id)['period_after']['start'])->toBe('2032-03-01');

    $this->patch($updateRoute, ['start_date' => '2031-03-01', 'confirm_balance_changes' => true, 'balance_choices' => [$yearOne->id => 'carry']])->assertSessionHasNoErrors();

    expect($yearOne->refresh()->period_start->toDateString())->toBe('2031-03-01')
        ->and((float) $yearOne->annual_used)->toBe(6.0)
        ->and((float) $yearOne->annual_adjustment)->toBe(6.0)
        ->and($yearTwo->refresh()->period_start->toDateString())->toBe('2032-03-01');

    \App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance::run($yearOne);
    expect((float) $yearOne->refresh()->annual_used)->toBe(6.0);

    $carryDelete    = $preview->handle($contract->refresh(), 'delete', []);
    $recordedDelete = $preview->handle($contract, 'delete', ['balance_choices' => [$yearOne->id => 'recorded']]);
    $newUnlinked    = \App\Actions\HumanResources\EmployeeContract\PlanContractLeaveBalances::NEW_UNLINKED_KEY;

    expect($carryDelete['requires_confirmation'])->toBeTrue()
        ->and($entryOf($carryDelete, $yearOne->id)['kind'])->toBe('deleted')
        ->and($entryOf($carryDelete, $yearOne->id)['has_choice'])->toBeTrue()
        ->and($entryOf($carryDelete, $yearOne->id)['options']['carry']['annual_used'])->toEqual(6)
        ->and($entryOf($carryDelete, $yearTwo->id)['has_choice'])->toBeFalse()
        ->and($entryOf($carryDelete, $newUnlinked)['after']['annual_used'])->toEqual(8)
        ->and($entryOf($recordedDelete, $newUnlinked)['after']['annual_used'])->toEqual(2);

    $deleteRoute = route('grp.models.employee.contracts.delete', $contract->id);
    $this->delete($deleteRoute)->assertSessionHasErrors('balance_changes');
    $this->delete($deleteRoute, ['confirm_balance_changes' => true])->assertSessionHasErrors('balance_choices');
    expect(EmployeeContract::find($contract->id))->not->toBeNull()
        ->and(EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->count())->toBe(2);

    $this->delete($deleteRoute, ['confirm_balance_changes' => true, 'balance_choices' => [$yearOne->id => 'carry']])->assertSessionHasNoErrors();

    $remaining = EmployeeLeaveBalance::where('employee_id', $employee->id)->get();

    expect(EmployeeContract::find($contract->id))->toBeNull()
        ->and($remaining)->toHaveCount(1)
        ->and($remaining->first()->employee_contract_id)->toBeNull()
        ->and((float) $remaining->first()->annual_used)->toBe(8.0)
        ->and((float) $remaining->first()->annual_adjustment)->toBe(6.0)
        ->and(Leave::find($leave->id))->not->toBeNull();

    \App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance::run($remaining->first());
    expect((float) $remaining->first()->refresh()->annual_used)->toBe(8.0);

    $storeRoute        = route('grp.models.employee.contracts.store', $employee->id);
    $storePreviewRoute = route('grp.models.employee.contracts.preview_store', $employee->id);
    $newContract       = ['start_date' => '2031-01-01', 'annual_leave_days' => 15];
    $unlinked          = $remaining->first();

    $store = $this->postJson($storePreviewRoute, $newContract)->assertOk()->json();
    $newBalance = \App\Actions\HumanResources\EmployeeContract\PlanContractLeaveBalances::NEW_BALANCE_KEY;

    expect($store['requires_confirmation'])->toBeTrue()
        ->and($entryOf($store, $newBalance)['kind'])->toBe('created')
        ->and($entryOf($store, $newBalance)['after']['annual_used'])->toEqual(2)
        ->and($entryOf($store, $unlinked->id)['kind'])->toBe('adjusted')
        ->and($entryOf($store, $unlinked->id)['before']['annual_used'])->toEqual(8)
        ->and($entryOf($store, $unlinked->id)['after']['annual_used'])->toEqual(6);

    $this->post($storeRoute, $newContract)->assertSessionHasErrors('balance_changes');
    expect($employee->contracts()->count())->toBe(0);

    $this->post($storeRoute, [...$newContract, 'confirm_balance_changes' => true])->assertSessionHasNoErrors();

    $created = $employee->contracts()->firstOrFail();
    $created->load('leaveBalances');

    expect($created->leaveBalances)->toHaveCount(1)
        ->and((float) $created->leaveBalances->first()->annual_used)->toBe(2.0)
        ->and((float) $created->leaveBalances->first()->annual_adjustment)->toBe(0.0)
        ->and((float) $unlinked->refresh()->annual_used)->toBe(6.0)
        ->and((float) $unlinked->annual_adjustment)->toBe(6.0);

    $freshEmployee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->group->id,
    ]);

    expect($planner->forStore($freshEmployee, $newContract)['requires_confirmation'])->toBeFalse();
    $this->post(route('grp.models.employee.contracts.store', $freshEmployee->id), $newContract)->assertSessionHasNoErrors();

    $freshBalance = EmployeeLeaveBalance::where('employee_id', $freshEmployee->id)->firstOrFail();
    expect($freshBalance->period_start->toDateString())->toBe('2031-01-01')
        ->and($freshBalance->period_end->toDateString())->toBe('2031-12-31')
        ->and([(float) $freshBalance->annual_used, (float) $freshBalance->annual_adjustment])->toBe([0.0, 0.0])
        ->and($zero)->toBe(['annual_used' => 0.0, 'medical_used' => 0.0, 'unpaid_used' => 0.0]);
});
