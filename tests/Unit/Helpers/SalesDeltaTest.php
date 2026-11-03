<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use Illuminate\Support\Number;

it('gives no change against a last year of refunds only', function () {
    foreach ([new ProductsResource(null), new SubDepartmentsResource(null)] as $resource) {
        $calculateDelta = fn (float $current, float $previous) => (fn () => $this->calculateDelta($current, $previous))->call($resource);

        expect($calculateDelta(38765, -10))->toBeNull()
            ->and($calculateDelta(38765, 0))->toBeNull()
            ->and($calculateDelta(150, 100)['value'])->toEqual(50);
    }
});

it('treats a negative last year like none in the dashboard deltas', function () {
    $stats = CalculateTimeSeriesStats::make();
    $delta = fn (float $current, float $previous) => (fn () => $this->calculateDelta($current, $previous))->call($stats);

    expect($delta(38765, -10)['formatted_value'])->toBe($delta(38765, 0)['formatted_value'])
        ->and(Number::rawDelta(38765, -10))->toBe(Number::rawDelta(38765, 0))
        ->and(Number::rawDelta(150, 100))->toEqual(1.5);
});
