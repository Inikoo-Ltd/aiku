<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Helpers\DimensionsFormatter;

it('formats cylinder dimensions for both spellings', function ($type) {
    expect(DimensionsFormatter::make()->dimensions(json_encode([
        'h'     => 0.16,
        'w'     => 0.15,
        'type'  => $type,
        'units' => 'cm',
    ])))->toBe('16x15 (cm)');
})->with(['cylinder', 'cilinder']);
