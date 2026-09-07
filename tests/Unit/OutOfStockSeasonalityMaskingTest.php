<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateOutOfStockForecast;

function quarterCoverage(object $record, array $orgStockIds, array $inStockDaysByDay): array
{
    $method = new ReflectionMethod(OrgStockHydrateOutOfStockForecast::class, 'quarterCoverage');
    $method->setAccessible(true);

    return $method->invoke(new OrgStockHydrateOutOfStockForecast(), $record, $orgStockIds, $inStockDaysByDay);
}

test('a quarter fully on the shelf counts every stock day', function () {
    $record = (object) ['from' => '2026-01-01', 'to' => '2026-01-10'];

    $inStock = [];
    foreach (range(1, 10) as $day) {
        $inStock[sprintf('2026-01-%02d', $day)] = 2;
    }

    expect(quarterCoverage($record, [1, 2], $inStock))->toBe([20, 20]);
});

test('days off the shelf are excluded from the denominator', function () {
    $record = (object) ['from' => '2026-01-01', 'to' => '2026-01-10'];

    $inStock = [];
    foreach (range(1, 3) as $day) {
        $inStock[sprintf('2026-01-%02d', $day)] = 1;
    }

    [$windowDays, $availableDays] = quarterCoverage($record, [1], $inStock);

    expect($windowDays)->toBe(10)
        ->and($availableDays)->toBe(3)
        ->and($availableDays / $windowDays)->toBeLessThan(0.5);
});

test('a quarter with no movements at all contributes nothing', function () {
    $record = (object) ['from' => '2026-01-01', 'to' => '2026-01-10'];

    expect(quarterCoverage($record, [1], []))->toBe([10, 0]);
});
