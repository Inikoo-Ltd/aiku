<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePortfolios;
use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydratePortfolios;
use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydratePortfolios;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePortfolios;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePortfolios;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;

test('portfolio count hydrators run on the replica-reading hydrators queue', function (string $hydrator) {
    expect((new $hydrator())->jobQueue)->toBe('hydrators-slave');
})->with([
    ShopHydratePortfolios::class,
    ShopPlatformStatsHydratePortfolios::class,
    PlatformHydratePortfolios::class,
    GroupHydratePortfolios::class,
    OrganisationHydratePortfolios::class,
]);

test('shop platform portfolio stats are unique per shop and platform', function () {
    $shop               = (new Shop())->forceFill(['id' => 7]);
    $shopifyPlatform    = (new Platform())->forceFill(['id' => 1]);
    $wooCommercePlatform = (new Platform())->forceFill(['id' => 2]);

    $hydrator = new ShopPlatformStatsHydratePortfolios();

    expect($hydrator->getJobUniqueId($shop, $shopifyPlatform))->not->toBe($hydrator->getJobUniqueId($shop, $wooCommercePlatform));
});
