<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;

class GetFaireBrand extends OrgAction
{
    public function handle(Shop $shop): array
    {
        return $shop->getFaireBrand();
    }
}
