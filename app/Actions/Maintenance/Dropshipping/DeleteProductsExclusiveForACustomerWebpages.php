<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\WithExternalPlatforms;
use App\Actions\Dropshipping\Shopify\Webhook\StoreWebhooksToShopify;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\DeleteWebpage;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteProductsExclusiveForACustomerWebpages
{
    use AsAction;
    use WithActionUpdate;
    use WithExternalPlatforms;


    public function getCommandSignature(): string
    {
        return 'repair:delete_products_exclusive_for_a_customer_webpages';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): void
    {
        $dsShops = Shop::where('type', ShopTypeEnum::DROPSHIPPING)->pluck('id');


        /** @var Product $product */
        foreach(Product::whereIn('shop_id', $dsShops)->whereNotNull('exclusive_for_customer_id')->get() as $product) {
            $command->info($product->code);

            $webpage = $product->webpage;
            if($webpage) {
                DeleteWebpage::make()->action($webpage, forceDelete: true);
                $product->update(['webpage_id' => null]);
            }



        }


    }

}
