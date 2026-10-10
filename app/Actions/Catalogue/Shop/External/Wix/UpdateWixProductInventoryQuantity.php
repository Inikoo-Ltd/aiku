<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Shop\Traits\WithWixExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Sentry;
use Sentry\State\Scope;

/**
 * One push sets every variant of the Wix product from its quantities when it runs, so the stock
 * changes of its variants within the minute it is delayed collapse into it. The lock lasts that
 * same minute: a change after it queues a push of its own, and a job lost on the way never holds
 * the product's pushes back for longer.
 */
class UpdateWixProductInventoryQuantity extends OrgAction implements ShouldBeUnique
{
    use WithWixExternalShopApi;

    public string $jobQueue = 'hydrators-slave';
    public int $jobTries = 1;
    public int $jobUniqueFor = 60;

    public function getJobUniqueId(Product $product): string
    {
        return $product->shop_id.':'.($product->marketplace_second_id ?? 'product-'.$product->id);
    }

    public function handle(Product $product): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (!$product->marketplace_id || !$product->marketplace_second_id) {
            return;
        }

        $wixUser = WixUser::where('external_shop_id', $product->shop_id)->first();

        if (!$wixUser) {
            return;
        }

        $variantQuantities = Product::where('shop_id', $product->shop_id)
            ->where('marketplace_second_id', $product->marketplace_second_id)
            ->whereNotNull('marketplace_id')
            ->whereNotIn('state', [ProductStateEnum::IN_PROCESS, ProductStateEnum::DISCONTINUED])
            ->get(['marketplace_id', 'available_quantity'])
            ->mapWithKeys(fn (Product $variant) => [$variant->marketplace_id => max((int) floor($variant->available_quantity), 0)])
            ->all();

        if (empty($variantQuantities)) {
            return;
        }

        $result = $this->setWixProductInventory($wixUser, $product->marketplace_second_id, $variantQuantities);

        if (Arr::has($result, 'message')) {
            Sentry::withScope(function (Scope $scope) use ($product, $result) {
                $scope->setFingerprint(['wix-inventory-update-failed', (string) $product->shop_id]);
                $scope->setTag('product', $product->slug);
                Sentry::captureMessage('Wix inventory update failed for '.$product->slug.': '.Arr::get($result, 'message'));
            });
        }
    }

    public string $commandSignature = 'wix:inventory {model}';

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('model'))->first();
        if ($shop) {
            $command->info("Updating inventory for shop $shop->name");
            $shop->products()
                ->whereNotNull('marketplace_second_id')
                ->whereNotIn('state', [ProductStateEnum::IN_PROCESS, ProductStateEnum::DISCONTINUED])
                ->get()
                ->unique('marketplace_second_id')
                ->each(function (Product $product) use ($command) {
                    $command->info("Updating inventory for Wix product of $product->code");
                    $this->handle($product);
                });

            return 0;
        }

        $product = Product::where('slug', $command->argument('model'))->firstOrFail();
        $command->info("Updating inventory for product $product->name");
        $this->handle($product);

        return 0;
    }
}
