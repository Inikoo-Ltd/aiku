<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 00:10:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Masters\MasterShop;

trait WithMasterFetch
{
    public function getMasterShop($field = 'Product Category Store Key'): ?MasterShop
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{$field});

        $masterShop = null;


        if (in_array($shop->slug, ['uk', 'awd', 'awf', 'aroma', 'acar'])) {
            $masterShop = $shop->masterShop;
        }

        return $masterShop;
    }

}
