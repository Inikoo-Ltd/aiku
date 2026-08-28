<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\TrafficSource\MergeTrafficSourceTouchHistories;

it('interleaves two histories by timestamp', function () {
    expect(MergeTrafficSourceTouchHistories::run('1700000200pmailshot-9', '1700000100a|1700000300b123'))
        ->toBe('1700000100a|1700000200pmailshot-9|1700000300b123');
});

it('drops exact duplicate touches', function () {
    expect(MergeTrafficSourceTouchHistories::run('1700000100a|1700000200b', '1700000200b'))
        ->toBe('1700000100a|1700000200b');
});

it('handles a null side', function () {
    expect(MergeTrafficSourceTouchHistories::run(null, '1700000100a'))->toBe('1700000100a');
    expect(MergeTrafficSourceTouchHistories::run('1700000100a', null))->toBe('1700000100a');
});

it('returns null when both sides are empty', function () {
    expect(MergeTrafficSourceTouchHistories::run(null, null))->toBeNull();
    expect(MergeTrafficSourceTouchHistories::run('', ''))->toBeNull();
});

it('normalises legacy comma separators', function () {
    expect(MergeTrafficSourceTouchHistories::run('1700000100a,1700000200b', null))
        ->toBe('1700000100a|1700000200b');
});
