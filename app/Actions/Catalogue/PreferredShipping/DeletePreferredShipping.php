<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Mon, 20 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\PreferredShipping;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePreferredShippings;
use App\Actions\OrgAction;
use App\Models\Catalogue\PreferredShipping;
use Lorisleiva\Actions\Concerns\AsAction;

class DeletePreferredShipping extends OrgAction
{
    use AsAction;

    public function handle(PreferredShipping $preferredShipping): void
    {
        $shop = $preferredShipping->shop;

        $preferredShipping->delete();

        ShopHydratePreferredShippings::dispatch($shop)->delay($this->hydratorsDelay);
    }

    public function action(PreferredShipping $preferredShipping, int $hydratorsDelay = 0, bool $audit = true): void
    {
        if (!$audit) {
            PreferredShipping::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($preferredShipping->shop, []);

        $this->handle($preferredShipping);
    }
}
