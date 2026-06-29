<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 02:55:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

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
use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\HumanResources\Overtime\StoreOvertimeRequest;
use App\Actions\HumanResources\Overtime\StoreOvertimeType;
use App\Actions\HumanResources\AttendanceAdjustment\StoreAttendanceAdjustment;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Workplace;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\Holiday;
use App\Models\HumanResources\LeaveType;
use App\Models\HumanResources\HolidayYear;
use App\Models\HumanResources\EmployeeContract;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\HumanResources\OvertimeType;
use App\Models\HumanResources\AttendanceAdjustment;
use Illuminate\Support\Facades\Storage;
use App\Actions\Helpers\Avatars\GetDiceBearAvatar;

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
