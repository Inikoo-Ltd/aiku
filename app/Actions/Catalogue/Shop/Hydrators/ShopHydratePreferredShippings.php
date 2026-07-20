<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Mon, 20 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePreferredShippings implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Shop $shop): string
    {
        return (string) $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_preferred_shippings' => $shop->preferredShippings()->count(),
        ];

        $shop->stats->update($stats);

    }
}
