<?php

use App\Actions\HumanResources\AttendanceAdjustment\StoreAttendanceAdjustment;
use App\Actions\HumanResources\Leave\StoreLeave;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Laravel\mock;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->withoutMiddleware();
});

test('leave submit returns not found when action cannot resolve employee', function () {
    mock(StoreLeave::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andThrow(new NotFoundHttpException('Employee record not found for current user.'));

    $response = post(route('grp.clocking_employees.leaves.store'), [
        'organisation' => 'awa',
        'type'         => 'annual',
        'start_date'   => now()->addDay()->format('Y-m-d'),
        'end_date'     => now()->addDays(2)->format('Y-m-d'),
        'reason'       => 'Request leave',
    ]);

    $response->assertNotFound();
});

test('leave submit redirects to leaves tab when action succeeds', function () {
    mock(StoreLeave::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andReturn(new RedirectResponse(route('grp.clocking_employees.index', ['tab' => 'leaves'])));

    $response = post(route('grp.clocking_employees.leaves.store'), [
        'organisation' => 'awa',
        'type'         => 'annual',
        'start_date'   => now()->addDay()->format('Y-m-d'),
        'end_date'     => now()->addDays(2)->format('Y-m-d'),
        'reason'       => 'Request leave',
    ]);

    $response->assertRedirect(route('grp.clocking_employees.index', ['tab' => 'leaves']));
});

test('adjustment submit returns not found when action cannot resolve employee', function () {
    mock(StoreAttendanceAdjustment::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andThrow(new NotFoundHttpException('Employee record not found for current user.'));

    $response = post(route('grp.clocking_employees.adjustments.store'), [
        'organisation'       => 'awa',
        'date'               => now()->subDay()->format('Y-m-d'),
        'requested_start_at' => '08:00',
        'requested_end_at'   => '17:00',
        'reason'             => 'Request adjustment',
    ]);

    $response->assertNotFound();
});

test('adjustment submit redirects to adjustments tab when action succeeds', function () {
    mock(StoreAttendanceAdjustment::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andReturn(new RedirectResponse(route('grp.clocking_employees.index', ['tab' => 'adjustments'])));

    $response = post(route('grp.clocking_employees.adjustments.store'), [
        'organisation'       => 'awa',
        'date'               => now()->subDay()->format('Y-m-d'),
        'requested_start_at' => '08:00',
        'requested_end_at'   => '17:00',
        'reason'             => 'Request adjustment',
    ]);

    $response->assertRedirect(route('grp.clocking_employees.index', ['tab' => 'adjustments']));
});
