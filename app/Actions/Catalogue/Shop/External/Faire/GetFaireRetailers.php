<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;

class GetFaireRetailers extends OrgAction
{
    public function handle(Shop $shop, string $retailerId): array
    {
        return $shop->getFaireRetailers($retailerId);
    }
}
