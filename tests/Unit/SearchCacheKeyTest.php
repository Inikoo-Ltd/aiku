<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Search\Search;
use App\Actions\Search\SearchCatalogue;

test('short queries cache without choking on nested options', function () {
    SearchCatalogue::shouldRun()->andReturn(['results' => []]);

    $results = Search::make()->handle('catalogue', 'e', [
        'shop_id'       => 1,
        'is_in_website' => true,
        'boosts'        => [11, 22, 33],
        'language'      => 'en',
    ]);

    expect($results)->toBe(['results' => []]);
});
