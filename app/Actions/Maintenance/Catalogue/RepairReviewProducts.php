<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 15:29:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;

class RepairReviewProducts
{
    use WithActionUpdate;


    protected function handle(Shop $shop): void
    {
        if ($shop->language->code === 'en') {
            $shop->products()->update([
                'is_extra_description_reviewed' => true,
                'is_description_reviewed' => true,
                'is_title_reviewed' => true,
                'is_name_reviewed' => true
            ]);
        }
    }

    public string $commandSignature = 'review:products';

    public function asCommand(): void
    {
        $shops = Shop::all();

        foreach ($shops as $shop) {
            $this->handle($shop);
        }
    }

}
