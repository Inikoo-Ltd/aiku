<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\EmployeeAttendanceTool;
use App\Mcp\Tools\EmployeeDirectoryTool;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Guest;
use App\Actions\SysAdmin\Guest\StoreGuest;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group = $this->organisation->group;

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
});

test('user without hr permission is denied', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(EmployeeDirectoryTool::class, [
        'organisation' => $this->organisation->slug,
        'query'        => 'x',
    ]);

    $response->assertHasErrors(['Organisation not found or permission denied.']);
});

test('admin can search employee directory', function () {
    $employee = StoreEmployee::make()->action(
        $this->organisation,
        array_merge(
            Employee::factory()->definition(),
            [
                'contact_name'    => 'John Developer',
                'worker_number'   => 'EMP-'.uniqid(),
                'job_title'       => 'Software Engineer',
                'work_email'      => uniqid('john').'@example.com',
                'state'           => EmployeeStateEnum::WORKING,
                'type'            => EmployeeTypeEnum::EMPLOYEE,
                'employment_type' => EmploymentTypeEnum::FULL_TIME,
            ]
        ),
        audit: false
    );

    $response = AikuServer::actingAs($this->user)->tool(EmployeeDirectoryTool::class, [
        'organisation' => $this->organisation->slug,
        'query'        => 'John',
    ]);

    $response->assertOk()->assertSee('John Developer');
});

test('directory response never contains salary', function () {
    $employee = StoreEmployee::make()->action(
        $this->organisation,
        array_merge(
            Employee::factory()->definition(),
            [
                'contact_name'    => 'Jane Manager',
                'worker_number'   => 'EMP-'.uniqid(),
                'job_title'       => 'Manager',
                'work_email'      => uniqid('jane').'@example.com',
                'state'           => EmployeeStateEnum::WORKING,
                'type'            => EmployeeTypeEnum::EMPLOYEE,
                'employment_type' => EmploymentTypeEnum::FULL_TIME,
                'salary'          => ['amount' => 50000, 'currency' => 'USD'],
            ]
        ),
        audit: false
    );

    $response = AikuServer::actingAs($this->user)->tool(EmployeeDirectoryTool::class, [
        'organisation' => $this->organisation->slug,
        'query'        => 'Jane',
    ]);

    $response->assertOk()
        ->assertSee('Jane Manager')
        ->assertDontSee('salary')
        ->assertDontSee('date_of_birth');
});

test('admin can view employee attendance', function () {
    $employee = StoreEmployee::make()->action(
        $this->organisation,
        array_merge(
            Employee::factory()->definition(),
            [
                'contact_name'    => 'Alice Worker',
                'worker_number'   => 'EMP-'.uniqid(),
                'state'           => EmployeeStateEnum::WORKING,
                'type'            => EmployeeTypeEnum::EMPLOYEE,
                'employment_type' => EmploymentTypeEnum::FULL_TIME,
            ]
        ),
        audit: false
    );

    Timesheet::create([
        'group_id'           => $this->organisation->group_id,
        'organisation_id'    => $this->organisation->id,
        'date'               => '2026-07-20',
        'subject_type'       => 'Employee',
        'subject_id'         => $employee->id,
        'subject_name'       => $employee->contact_name,
        'working_duration'   => 28800,
        'breaks_duration'    => 3600,
        'total_duration'     => 32400,
        'number_time_trackers' => 1,
        'number_open_time_trackers' => 0,
    ]);

    $response = AikuServer::actingAs($this->user)->tool(EmployeeAttendanceTool::class, [
        'organisation' => $this->organisation->slug,
        'employee'     => $employee->slug,
        'from'         => '2026-07-20',
        'to'           => '2026-07-22',
    ]);

    $response->assertOk()
        ->assertSee("Alice Worker")
        ->assertSee('"days_with_timesheet":1')
        ->assertSee('"total_working_hours":8');
});

test('employee not found returns error', function () {
    $response = AikuServer::actingAs($this->user)->tool(EmployeeAttendanceTool::class, [
        'organisation' => $this->organisation->slug,
        'employee'     => 'nonexistent-employee',
        'from'         => '2026-07-20',
        'to'           => '2026-07-22',
    ]);

    $response->assertHasErrors(['Employee not found.']);
});
