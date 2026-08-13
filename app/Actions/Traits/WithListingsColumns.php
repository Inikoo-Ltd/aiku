<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Shop\ShopTypeEnum;

trait WithListingsColumns
{
    public function hasListingsColumns(object $parent): bool
    {
        $shop = match (class_basename($parent)) {
            'Shop', 'MasterShop'                       => $parent,
            'ProductCategory', 'Collection', 'Product' => $parent->shop,
            'MasterProductCategory', 'MasterAsset'     => $parent->masterShop,
            default                                    => null,
        };

        return $shop?->type == ShopTypeEnum::DROPSHIPPING;
    }
}
