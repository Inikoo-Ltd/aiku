<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 02:04:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Helpers\GoogleDrive\UploadFileGoogleDrive;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class UploadShopProductsToGoogleDrive extends OrgAction
{
    use WithActionUpdate;

    public string $commandSignature = 'drive:shop-products {shop}';

    public function handle(Shop $shop): void
    {
        foreach ($shop->getFamilies() as $family) {
            foreach ($family->getProducts() as $product) {
                $folderToSave = "Products/$family->code/" . $product->code;

                foreach ($product->images as $image) {
                    UploadFileGoogleDrive::run($shop->organisation, $image->getPath(), $folderToSave);
                }
            }
        }
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        $this->handle($shop);
    }
}
