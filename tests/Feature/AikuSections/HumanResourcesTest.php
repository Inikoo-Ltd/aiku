<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 26 Apr 2023 15:26:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\HumanResources\ClockingMachine\GetClockingMachineAppQRCode;
use App\Actions\HumanResources\ClockingMachine\HydrateClockingMachine;
use App\Actions\HumanResources\ClockingMachine\Search\ReindexClockingMachineSearch;
use App\Actions\HumanResources\ClockingMachine\StoreClockingMachine;
use App\Actions\HumanResources\ClockingMachine\UpdateClockingMachine;
use App\Actions\HumanResources\Employee\HydrateEmployees;
use App\Actions\HumanResources\Employee\Search\ReindexEmployeeSearch;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployeeWorkingHours;
use App\Actions\HumanResources\JobPosition\Search\ReindexJobPositionSearch;
use App\Actions\HumanResources\Timesheet\StoreTimesheet;
use App\Actions\HumanResources\Workplace\Search\ReindexWorkplaceSearch;
use App\Actions\HumanResources\Workplace\StoreWorkplace;
use App\Actions\HumanResources\Workplace\UpdateWorkplace;
use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Enums\HumanResources\Workplace\WorkplaceTypeEnum;
use App\Models\Helpers\Address;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\JobPositionStats;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->adminGuest   = createAdminGuest($this->organisation->group);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
    setPermissionsTeamId($this->organisation->group->id);
});

test('check seeded job positions', function () {
    expect($this->organisation->group->humanResourcesStats->number_job_positions)->toBe(28);
    /** @var JobPosition $jobPosition */
    $jobPosition = $this->organisation->jobPositions()->first();
    expect($jobPosition->stats)->toBeInstanceOf(JobPositionStats::class)
        ->and($jobPosition->stats->number_employees)->toBe(0);
});

test('create working place successful', function () {
    $modelData = [
        'name'    => 'office',
        'type'    => WorkplaceTypeEnum::BRANCH,
        'address' => Address::factory()->definition()
    ];

    $workplace = StoreWorkplace::make()->action($this->organisation, $modelData);
    expect($workplace)->toBeInstanceOf(Workplace::class)
        ->and($this->organisation->humanResourcesStats->number_workplaces)->toBe(1)
        ->and($this->organisation->humanResourcesStats->number_workplaces_type_branch)->toBe(1)
        ->and($this->organisation->humanResourcesStats->number_workplaces_type_home)->toBe(0);


    return $workplace;
});

test('update working place successful', function ($createdWorkplace) {
    $arrayData = [
        'name'    => 'vica smith',
        'type'    => WorkplaceTypeEnum::HOME,
        'address' => Address::factory()->definition()
    ];

    $workplace = UpdateWorkplace::run($createdWorkplace, $arrayData);
    $this->organisation->refresh();

    expect($workplace->name)->toBe($arrayData['name'])
        ->and($this->organisation->humanResourcesStats->number_workplaces_type_branch)->toBe(0)
        ->and($this->organisation->humanResourcesStats->number_workplaces_type_home)->toBe(1);
})->depends('create working place successful');

test('create working place by command', function () {
    $this->artisan("workplace:create {$this->organisation->slug} office2 hq")->assertExitCode(0);
    $this->artisan("workplace:create {$this->organisation->slug} office2 hq")->assertExitCode(1);
    $workplace = Workplace::where('name', 'office2')->first();
    $this->organisation->refresh();
    expect($workplace)->not->toBeNull()
        ->and($this->organisation->humanResourcesStats->number_workplaces)->toBe(2);
});

test('create employee successful', function () {
    $arrayData = [
        'alias'               => 'artha',
        'contact_name'        => 'artha',
        'employment_start_at' => '2019-01-01',
        'date_of_birth'       => '2000-01-01',
        'job_title'           => 'director',
        'state'               => EmployeeStateEnum::HIRED,
        'positions'           => ['acc-m'],
        'worker_number'       => '1234567890',
        'work_email'          => null,
        'email'               => null,
        'username'            => null,
        'type'                => EmployeeTypeEnum::EMPLOYEE,
    ];
    $employee  = StoreEmployee::make()->action($this->organisation, $arrayData);

    expect($employee)->toBeInstanceOf(Employee::class)
        ->and($employee->stats->number_job_positions)->toBe(0)
        ->and($this->organisation->humanResourcesStats->number_employees)->toBe(1)
        ->and($this->organisation->humanResourcesStats->number_employees_type_employee)->toBe(1)
        ->and($this->organisation->humanResourcesStats->number_employees_state_hired)->toBe(1)
        ->and($this->organisation->humanResourcesStats->number_employees_state_working)->toBe(0);

    return $employee;
});

test('add job position to employee', function (Employee $employee) {
    /** @var JobPosition $jobPosition */
    $jobPosition = $this->organisation->jobPositions()->where('slug', 'hr-c')->first();

    UpdateEmployee::make()->action($employee, [
        'job_positions' => [
            [
                'slug'   => $jobPosition->slug,
                'scopes' => []
            ]
        ]
    ]);
    $jobPosition->refresh();
    $employee->refresh();
    expect($employee->stats->number_job_positions)->toBe(1)
        ->and($jobPosition->stats->number_employees)->toBe(1);
})->depends('create employee successful');


test('update employees successful', function ($lastEmployee) {
    $arrayData = [
        'contact_name'  => 'vica',
        'date_of_birth' => '2019-01-01',
        'job_title'     => 'director',
        'state'         => EmployeeStateEnum::WORKING
    ];

    $updatedEmployee = UpdateEmployee::run($lastEmployee, $arrayData);

    expect($updatedEmployee->contact_name)->toBe($arrayData['contact_name'])
        ->and($this->organisation->humanResourcesStats->number_employees)->toBe(1)
        ->and($this->organisation->humanResourcesStats->number_employees_type_employee)->toBe(1)
        ->and($this->organisation->humanResourcesStats->number_employees_state_hired)->toBe(0)
        ->and($this->organisation->humanResourcesStats->number_employees_state_working)->toBe(1);
})->depends('create employee successful');

test('update employee working hours', function (Employee $employee) {
    $employee = UpdateEmployeeWorkingHours::run($employee, [10]);
    expect($employee['working_hours'])->toBeArray(10);

    return $employee;
})->depends('create employee successful');

test('create clocking machines', function ($workplace) {
    $arrayData = [
        'name' => 'ABC',
        'type' => ClockingMachineTypeEnum::STATIC_NFC,
    ];

    $clockingMachine = StoreClockingMachine::run($workplace, $arrayData);
    expect($clockingMachine->name)->toBe($arrayData['name']);

    return $clockingMachine;
})->depends('create working place successful');


test('update clocking machines', function ($createdClockingMachine) {
    $arrayData = [
        'name' => 'XYZ',
        'type' => ClockingMachineTypeEnum::BIOMETRIC
    ];

    $updatedClockingMachine = UpdateClockingMachine::make()->action($createdClockingMachine, $arrayData);

    expect($updatedClockingMachine->name)->toBe($arrayData['name']);
})->depends('create clocking machines');

test('get clocking machine app qrcode', function (ClockingMachine $clockingMachine) {
    $qr = GetClockingMachineAppQRCode::run($clockingMachine);
    expect($qr)->not()->toBeNull()
        ->and($qr)->toBeArray()
        ->and($qr['code'])->toContain($clockingMachine->slug);

    return $qr;
})->depends('create clocking machines');

test('can show hr dashboard', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.hr.dashboard', $this->organisation->slug));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/HumanResourcesDashboard')
            ->has('breadcrumbs', 2)
            ->where('stats.0.stat', 1)->where('stats.0.route.name', 'grp.org.hr.employees.index')
            ->where('stats.1.stat', 2)->where('stats.1.route.name', 'grp.org.hr.workplaces.index');
    });
});

test('can show list of workplaces', function () {
    $response = get(route('grp.org.hr.workplaces.index', $this->organisation->slug));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Workplaces')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('data.data', 2);
    });
});

test('can show workplace', function () {
    $workplace = Workplace::first();
    $response  = get(route('grp.org.hr.workplaces.show', [$this->organisation->slug, $workplace->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($workplace) {
        $page
            ->component('Org/HumanResources/Workplace')
            ->has('breadcrumbs', 3);
    });
});

test('can show list of employees', function () {
    $response = get(route('grp.org.hr.employees.index', $this->organisation->slug));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Employees')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('data.data', 1);
    });
});

test('can show employee', function () {
    $employee = Employee::first();
    expect($employee->getUser())->toBeInstanceOf(User::class);

    $response = get(route('grp.org.hr.employees.show', [$this->organisation->slug, $employee->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($employee) {
        $page
            ->component('Org/HumanResources/Employee')
            ->has('breadcrumbs', 3)
            ->where('pageHead.meta.1.route.name', 'grp.org.sysadmin.users.show')
            ->where('pageHead.meta.1.route.parameters', $employee->alias)
            ->has('tabs.navigation', 7);
    });
})->todo();

test('new timesheet for employee', function (Employee $employee) {
    $timesheet = StoreTimesheet::make()->action($employee, [
        'date' => now(),
    ]);

    $employee->refresh();

    expect($timesheet)->toBeInstanceOf(Timesheet::class)
        ->and($timesheet->subject_id)->toBe($employee->id)
        ->and($timesheet->subject_type)->toBe('Employee')
        ->and($timesheet->number_time_trackers)->toBe(0)
        ->and($timesheet->working_duration)->toBe(0)
        ->and($timesheet->breaks_duration)->toBe(0)
        ->and($employee->stats->number_timesheets)->toBe(1);

    return $timesheet;
})->depends('create employee successful');

test('create clocking', function (Timesheet $timesheet, Workplace $workplace) {
    /** @var Employee $employee */
    $employee = $timesheet->subject;

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'clocked_at' => now()->subMinutes(10),
    ]);
    $clocking->refresh();

    expect($clocking)->toBeInstanceOf(Clocking::class)
        ->and($clocking->subject_id)->toBe($employee->id)
        ->and($clocking->subject_type)->toBe('Employee')
        ->and($clocking->workplace_id)->toBe($workplace->id)
        ->and($clocking->clocking_machine_id)->toBeNull()
        ->and($clocking->type)->toBe(ClockingTypeEnum::MANUAL)
        ->and($employee->stats->number_timesheets)->toBe(1)
        ->and($employee->stats->number_clockings)->toBe(1)
        ->and($employee->stats->number_time_trackers)->toBe(1);

    return $timesheet;
})->depends('new timesheet for employee', 'create working place successful');

test('second clocking ', function (Timesheet $timesheet, Workplace $workplace) {
    /** @var Employee $employee */
    $employee = $timesheet->subject;

    $clocking = StoreClocking::make()->action($this->organisation, $workplace, $employee, [
        'clocked_at' => now()->subMinutes(5),
    ]);
    $clocking->refresh();
    $timesheet = $clocking->timesheet;
    $employee->refresh();

    expect($clocking)->toBeInstanceOf(Clocking::class)
        ->and($clocking->subject_id)->toBe($employee->id)
        ->and($clocking->subject_type)->toBe('Employee')
        ->and($clocking->workplace_id)->toBe($workplace->id)
        ->and($clocking->clocking_machine_id)->toBeNull()
        ->and($timesheet->number_time_trackers)->toBe(1)
        ->and($clocking->type)->toBe(ClockingTypeEnum::MANUAL)
        ->and($employee->stats->number_timesheets)->toBe(1)
        ->and($employee->stats->number_clockings)->toBe(2)
        ->and($employee->stats->number_time_trackers)->toBe(1);

    $timeTracker = $timesheet->timeTrackers->first();
    expect($timeTracker->status)->toBe(TimeTrackerStatusEnum::CLOSED)
        ->and($timeTracker->end_clocking_id)->toBe($clocking->id);
})->depends('create clocking', 'create working place successful');

test('hydrate clocking machine', function (ClockingMachine $clockingMachine) {
    HydrateClockingMachine::run($clockingMachine);
    $this->artisan('hydrate:clocking-machine '.$this->organisation->slug)->assertExitCode(0);
})->depends('create clocking machines')->todo();


test('employees notes search', function () {
    $this->artisan('search:employees')->assertExitCode(0);

    $employees = Employee::first();
    ReindexEmployeeSearch::run($employees);
    expect($employees->universalSearch()->count())->toBe(1);
});

test('workplaces notes search', function () {
    $this->artisan('search:workplaces')->assertExitCode(0);

    $workplace = Workplace::first();
    ReindexWorkplaceSearch::run($workplace);
    expect($workplace->universalSearch()->count())->toBe(1);
});

test('job positions notes search', function () {
    $this->artisan('search:job_positions')->assertExitCode(0);

    $jobPosition = JobPosition::first();
    ReindexJobPositionSearch::run($jobPosition);
    expect($jobPosition->universalSearch()->count())->toBe(1);
});

test('clocking machines notes search', function () {
    $this->artisan('search:clocking_machines')->assertExitCode(0);

    $clockingMachine = ClockingMachine::first();
    ReindexClockingMachineSearch::run($clockingMachine);
    expect($clockingMachine->universalSearch()->count())->toBe(1);
});

test('hydrate employees', function () {
    $this->artisan('hydrate:employees')->assertExitCode(0);
    $employee = Employee::first();
    HydrateEmployees::run($employee);
});
