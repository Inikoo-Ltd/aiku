<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Shop\Traits\WithWixExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;

class UpdateWixProductInventoryQuantity extends OrgAction
{
    use WithWixExternalShopApi;

    public string $jobQueue = 'hydrators-slave';
    public int $jobTries = 1;

    public function handle(Product $product): void
    {
        if(! app()->isProduction()) {
            return;
        }

        if (!$product->marketplace_id || !$product->marketplace_second_id) {
            return;
        }

        if ($product->state == ProductStateEnum::IN_PROCESS || $product->state == ProductStateEnum::DISCONTINUED) {
            return;
        }

        $wixUser = WixUser::where('external_shop_id', $product->shop_id)->first();

        if (!$wixUser) {
            return;
        }

        $result = $this->setWixVariantInventory(
            $wixUser,
            $product->marketplace_second_id,
            $product->marketplace_id,
            (int) floor($product->available_quantity)
        );

        if (Arr::has($result, 'message')) {
            Sentry::captureMessage('Wix inventory update failed for '.$product->slug.': '.Arr::get($result, 'message'));
        }
    }

    public string $commandSignature = 'wix:inventory {model}';

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('model'))->first();
        if ($shop) {
            $command->info("Updating inventory for shop $shop->name");
            foreach ($shop->products as $product) {
                $command->info("Updating inventory for product $product->code");
                $this->handle($product);
            }

            return 0;
        }

        $product = Product::where('slug', $command->argument('model'))->firstOrFail();
        $command->info("Updating inventory for product $product->name");
        $this->handle($product);

        return 0;
    }
}
