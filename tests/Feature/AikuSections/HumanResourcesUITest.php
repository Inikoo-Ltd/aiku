<?php

/*
 * Author: Arya Permana <aryapermana02@gmail.com>
 * Created: Thu, 13 Jun 2024 13:27:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\HumanResources\ClockingMachine\StoreClockingMachine;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\JobPosition\StoreJobPosition;
use App\Actions\HumanResources\Timesheet\StoreTimesheet;
use App\Actions\HumanResources\Workplace\StoreWorkplace;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Enums\HumanResources\Workplace\WorkplaceTypeEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\Workplace;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {

    $this->organisation = createOrganisation();
    $this->adminGuest   = createAdminGuest($this->organisation->group);

    $workplace = Workplace::first();
    if (!$workplace) {
        data_set($storeData, 'name', 'workplace');
        data_set($storeData, 'type', WorkplaceTypeEnum::HQ->value);

        $workplace = StoreWorkplace::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->workplace = $workplace;

    $clockingMachine = ClockingMachine::first();
    if (!$clockingMachine) {
        data_set($storeData, 'name', 'machine');
        data_set($storeData, 'type', ClockingMachineTypeEnum::BIOMETRIC->value);

        $clockingMachine = StoreClockingMachine::make()->action(
            $this->workplace,
            $storeData
        );
    }
    $this->clockingMachine = $clockingMachine;

    $employee = Employee::first();
    if (!$employee) {
        $storeData = Employee::factory()->definition();

        $employee = StoreEmployee::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->employee = $employee;

    $jobPosition = JobPosition::first();
    if (!$jobPosition) {
        data_set($storeData, 'code', 'wp');
        data_set($storeData, 'name', 'Kirin');
        $jobPosition = StoreJobPosition::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->jobPosition = $jobPosition;

    $timesheet = Timesheet::first();
    if (!$timesheet) {
        data_set($storeData, 'date', '02-10-2002');
        $timesheet = StoreTimesheet::make()->action(
            $this->employee,
            $storeData
        );
    }
    $this->timesheet = $timesheet;
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
});

test('UI Index calendar', function () {
    $response = $this->get(route('grp.org.hr.calendars.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Calendar')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'Employees')
                        ->etc()
            )
            ->has('data');
    });
});

test('UI show calendar', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.hr.calendars.show', [$this->organisation->slug, $this->employee->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Calendar')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', $this->employee->worker_number)
                        ->etc()
            )
            ->has('tabs');

    });
})->todo(); //authorization problem

test('UI Index clockings', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.hr.workplaces.show.clockings.index', [$this->organisation->slug, $this->workplace->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Clockings')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'Clockings')
                        ->etc()
            )
            ->has('data');
    });
});

test('UI Index clocking machines', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.hr.workplaces.show.clocking_machines.index', [$this->organisation->slug, $this->workplace->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/ClockingMachines')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'Clocking machines')
                        ->etc()
            )
            ->has('data');
    });
});

test('UI show clocking machine', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.hr.workplaces.show.clocking_machines.show', [$this->organisation->slug, $this->workplace->slug, $this->clockingMachine->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/ClockingMachine')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', $this->clockingMachine->name)
                        ->etc()
            )
            ->has('tabs');

    });
});

test('UI create clocking machine', function () {
    $response = get(route('grp.org.hr.workplaces.show.clocking_machines.create', [$this->organisation->slug, $this->workplace->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI edit clocking machine', function () {
    $response = get(route('grp.org.hr.workplaces.show.clocking_machines.edit', [$this->organisation->slug, $this->workplace->slug, $this->clockingMachine->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 2)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                        ->where('name', 'grp.models.clocking_machine..update')
                        ->where('parameters', $this->clockingMachine->id)
            )
            ->has('breadcrumbs', 4);
    });
});

test('UI Index employees', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.hr.employees.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Employees')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'Employees')
                        ->etc()
            )
            ->has('data');
    });
});

test('UI create employee', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.hr.employees.create', [$this->organisation->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI show employee', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.hr.employees.show', [$this->organisation->slug, $this->employee->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Employee')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', $this->employee->contact_name)
                        ->etc()
            )
            ->has('tabs');

    });
});

test('UI edit employee', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.hr.employees.edit', [$this->organisation->slug, $this->employee->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                        ->where('name', 'grp.models.employee.update')
                        ->where('parameters', [$this->employee->id])
            )
            ->has('breadcrumbs', 3);
    });
});

test('UI show job position', function () {

    $response = $this->get(route('grp.org.hr.job_positions.show', [$this->organisation->slug, $this->jobPosition->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/JobPosition')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', $this->jobPosition->name)
                        ->etc()
            )
            ->has('tabs');
    });
});

test('UI Index job positions', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.hr.job_positions.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/JobPositions')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'Responsibilities')
                        ->etc()
            )
            ->has('data');
    });
});



test('UI create workplace', function () {
    $response = get(route('grp.org.hr.workplaces.create', [$this->organisation->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI edit workplace', function () {
    $response = get(route('grp.org.hr.workplaces.edit', [$this->organisation->slug, $this->workplace->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                        ->where('name', 'grp.models.workplace.update')
                        ->where('parameters', $this->workplace->id)
            )
            ->has('breadcrumbs', 3);
    });
});

test('UI Index timesheets', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.hr.timesheets.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Timesheets')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'Timesheets')
                        ->etc()
            )
            ->has('data');
    });
});

test('UI show timesheet', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.hr.timesheets.show', [$this->organisation->slug, $this->timesheet->id]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/HumanResources/Timesheet')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', $this->timesheet->date->format('l, j F Y'))
                        ->etc()
            )
            ->has('tabs');
    });
});

test('UI get section route hr employee index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.hr.employees.index', [
        'organisation' => $this->organisation->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_HR->value)
        ->and($sectionScope->model_slug)->toBe($this->organisation->slug);
});
