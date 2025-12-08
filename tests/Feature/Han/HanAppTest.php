<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 May 2024 14:37:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\HumanResources\ClockingMachine\StoreClockingMachine;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\HumanResources\Workplace\StoreWorkplace;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Workplace\WorkplaceTypeEnum;
use App\Models\Helpers\Address;
use App\Models\Helpers\Timezone;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->adminGuest = createAdminGuest($this->organisation->group);
    $this->user = $this->adminGuest->getUser();

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    $this->qrCode = Str::ulid()->toBase32();

    $workplace = $this->organisation->workplaces()->first();

    if (! $workplace) {
        $workplace = StoreWorkplace::make()->action($this->organisation, [
            'name' => 'office',
            'type' => WorkplaceTypeEnum::BRANCH,
            'address' => Address::factory()->definition(),
            'timezone_id' => Timezone::where('name', 'Asia/Kuala_Lumpur')->first()->id,
        ]);

        StoreClockingMachine::run($workplace, [
            'name' => 'ABC',
            'type' => ClockingMachineTypeEnum::MOBILE_APP,
        ]);

        StoreEmployee::make()->action($this->organisation, Employee::factory()->definition());
    }

    $this->workplace = $workplace;
    /** @var ClockingMachine $clockingMachine */
    $clockingMachine = $this->workplace->clockingMachines()->first();
    $this->clockingMachine = $clockingMachine;
    /** @var Employee $employee */
    $employee = $this->organisation->employees()->first();
    $this->employee = $employee;
});

test('connect clocking machine to han', function () {
    $qrCode = $this->clockingMachine->qr_code;

    $response = postJson(
        route(
            'han.connect',
            [
                'qr_code' => $qrCode,
                'device_name' => 'test device',
                'device_uuid' => Str::uuid()->toString(),
            ]
        )
    );

    $response->assertOk();
    $response->assertJsonStructure([
        'token',
        'data',
    ]);
});

test('find employee by pin', function () {
    Sanctum::actingAs($this->clockingMachine);

    $response = $this->postJson(route('han.employee.pin.validate'), [
        'pin' => $this->employee->pin,
    ]);

    $response
        ->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $this->employee->id,
                'alias' => $this->employee->alias,
                'contact_name' => $this->employee->contact_name,
                'worker_number' => $this->employee->worker_number,
                'state' => $this->employee->state->value,
            ],
        ]);
});

test('save clocking', function () {
    Sanctum::actingAs($this->clockingMachine);
    $this->employee->refresh();
    expect($this->employee->stats->number_clockings)->toBe(0);

    $response = $this->postJson(route(
        'han.employee.clocking.store',
        [
            'employee' => $this->employee->id,
        ]
    ));
    $this->employee->refresh();
    $response->assertStatus(201);

    expect($this->employee->stats->number_clockings)->toBe(1);

});

test('do not find employee using wrong pin', function () {
    Sanctum::actingAs($this->clockingMachine);
    $response = $this->postJson(route('han.employee.pin.validate'), [
        'pin' => 'XX11XX',
    ]);
    $response->assertStatus(422);

});

test('can not find employees from another organisation', function () {
    Sanctum::actingAs($this->clockingMachine);

    $otherOrganisation = StoreOrganisation::make()->action($this->organisation->group, Organisation::factory()->definition());
    $employeeOtherOrganisation = StoreEmployee::make()->action($otherOrganisation, Employee::factory()->definition());
    expect($otherOrganisation)->toBeInstanceOf(Organisation::class)
        ->and($otherOrganisation->id)->not->toBe($this->organisation->id)
        ->and($employeeOtherOrganisation)->toBeInstanceOf(Employee::class);

    $response = $this->postJson(route('han.employee.pin.validate'), [
        'pin' => $employeeOtherOrganisation->pin,
    ]);
    $response->assertStatus(404);
});

test('find employee fail if employee status is left', function () {
    Sanctum::actingAs($this->clockingMachine);

    UpdateEmployee::make()->action($this->employee, ['state' => EmployeeStateEnum::LEFT]);
    $this->employee->refresh();
    $response = $this->postJson(route('han.employee.pin.validate'), [
        'pin' => $this->employee->pin,
    ]);
    $response->assertStatus(404);
});
