<?php

/*
 * Author: Oggie Sutrisna 👌
 * Created: Wed, 11 Feb 2026
 * AIKU-13EZ - Test for ensureUniqueOrgCode method in OrgAction
 * Pure unit tests using Mockery - no database required
 */

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;

/**
 * Create a test action class that exposes the protected ensureUniqueOrgCode method
 */
class TestOrgAction extends OrgAction
{
    public function testEnsureUniqueOrgCode(callable $codeGenerator, int $maxAttempts = 10): string
    {
        return $this->ensureUniqueOrgCode($codeGenerator, $maxAttempts);
    }
}

afterEach(function () {
    \Mockery::close();
});

test('ensureUniqueOrgCode generates unique code on first attempt', function () {
    // Mock Organisation static method
    $mock = \Mockery::mock('alias:' . Organisation::class);
    $mock->shouldReceive('where')
        ->once()
        ->with('code', 'UNIQUE-CODE-123')
        ->andReturnSelf();
    $mock->shouldReceive('exists')
        ->once()
        ->andReturn(false);

    $action = new TestOrgAction();
    $callCount = 0;

    $code = $action->testEnsureUniqueOrgCode(function () use (&$callCount) {
        $callCount++;
        return 'UNIQUE-CODE-123';
    });

    expect($code)->toBe('UNIQUE-CODE-123')
        ->and($callCount)->toBe(1);
});

test('ensureUniqueOrgCode retries when code already exists', function () {
    $mock = \Mockery::mock('alias:' . Organisation::class);

    // First attempt - duplicate
    $mock->shouldReceive('where')
        ->once()
        ->with('code', 'DUPLICATE-CODE')
        ->andReturnSelf();
    $mock->shouldReceive('exists')
        ->once()
        ->andReturn(true);

    // Second attempt - unique
    $mock->shouldReceive('where')
        ->once()
        ->with('code', 'UNIQUE-CODE-456')
        ->andReturnSelf();
    $mock->shouldReceive('exists')
        ->once()
        ->andReturn(false);

    $action = new TestOrgAction();
    $callCount = 0;
    $codes = ['DUPLICATE-CODE', 'UNIQUE-CODE-456'];

    $code = $action->testEnsureUniqueOrgCode(function () use (&$callCount, $codes) {
        return $codes[$callCount++];
    });

    expect($code)->toBe('UNIQUE-CODE-456')
        ->and($callCount)->toBe(2);
});

test('ensureUniqueOrgCode throws exception after max attempts', function () {
    $mock = \Mockery::mock('alias:' . Organisation::class);

    $mock->shouldReceive('where')
        ->times(6)
        ->andReturnSelf();
    $mock->shouldReceive('exists')
        ->times(6)
        ->andReturn(true);

    $action = new TestOrgAction();
    $callCount = 0;

    try {
        $action->testEnsureUniqueOrgCode(function () use (&$callCount) {
            $callCount++;
            return 'ALWAYS-DUPLICATE';
        }, 5);
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Unable to generate unique organization code after 5 attempts');
    }

    expect($callCount)->toBe(6);
});

test('ensureUniqueOrgCode respects custom max attempts parameter', function () {
    $mock = \Mockery::mock('alias:' . Organisation::class);

    $mock->shouldReceive('where')
        ->times(4)
        ->andReturnSelf();
    $mock->shouldReceive('exists')
        ->times(4)
        ->andReturn(true);

    $action = new TestOrgAction();
    $callCount = 0;

    try {
        $action->testEnsureUniqueOrgCode(function () use (&$callCount) {
            $callCount++;
            return 'CUSTOM-RETRY-CODE';
        }, 3);
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Unable to generate unique organization code after 3 attempts');
    }

    expect($callCount)->toBe(4);
});

test('ensureUniqueOrgCode default max attempts is 10', function () {
    $mock = \Mockery::mock('alias:' . Organisation::class);

    $mock->shouldReceive('where')
        ->times(10)
        ->andReturnSelf();
    $mock->shouldReceive('exists')
        ->times(10)
        ->andReturn(true, true, true, true, true, true, true, true, true, false);

    $action = new TestOrgAction();
    $callCount = 0;
    $codes = array_fill(0, 9, 'DUPLICATE');
    $codes[] = 'UNIQUE-AT-10TH';

    $code = $action->testEnsureUniqueOrgCode(function () use (&$callCount, $codes) {
        return $codes[$callCount++];
    });

    expect($code)->toBe('UNIQUE-AT-10TH')
        ->and($callCount)->toBe(10);
});
