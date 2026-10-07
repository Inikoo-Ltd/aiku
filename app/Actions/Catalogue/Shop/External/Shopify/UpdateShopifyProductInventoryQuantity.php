<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Sentry;

class UpdateShopifyProductInventoryQuantity extends OrgAction implements ShouldBeUnique
{
    use WithShopifyExternalShopApi;

    public const string NOT_STOCKED_AT_LOCATION = 'not stocked at the location';

    public string $jobQueue = 'hydrators-slave';

    public int $jobTries = 1;

    public function getJobUniqueId(Product $product): string
    {
        return (string) $product->id;
    }

    public function handle(Product $product): void
    {
        $shop = $product->shop;

        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            return;
        }

        $this->pushInventory($shop, collect([$product]));
    }

    /**
     * Products still in process have no stock in Aiku yet, so the store keeps its own number for them;
     * a product just discontinued is set to 0 so the store cannot sell what Aiku no longer supplies.
     *
     * @param Collection<int, Product> $products
     * @return array{updated: int, failed: int, skipped: int, message?: string}
     */
    public function pushInventory(Shop $shop, Collection $products): array
    {
        $summary = ['updated' => 0, 'failed' => 0, 'skipped' => 0];

        if (!$this->isShopifyPushAllowed()) {
            return [...$summary, 'skipped' => $products->count()];
        }

        $shopifyUser = $this->getShopifyExternalShopUser($shop);

        if ($blockedReason = $this->getShopifyExternalShopBlockedReason($shopifyUser)) {
            return [...$summary, 'skipped' => $products->count(), 'message' => $blockedReason];
        }

        $locationId = $this->getShopifyExternalShopLocationId($shop, $shopifyUser);

        if (!$locationId) {
            Sentry::captureMessage("Shopify inventory not updated, no location found ($shop->slug)");

            return [...$summary, 'skipped' => $products->count(), 'message' => __('No Shopify location to set the stock at')];
        }

        $items = $products
            ->filter(fn (Product $product) => $this->canPushInventory($product))
            ->map(fn (Product $product) => [
                'product'           => $product,
                'inventoryItemId'   => Arr::get($product->data, 'shopify.inventory_item_id'),
                'inventoryTracked'  => (bool) Arr::get($product->data, 'shopify.inventory_tracked', false),
                'quantity'          => $this->getQuantityToPush($product),
            ])
            ->values();

        $summary['skipped'] = $products->count() - $items->count();

        foreach ($items->chunk($this->shopifyInventoryBatchSize) as $batch) {
            $batch  = $batch->values();
            $result = $this->setShopifyExternalShopInventoryQuantities($shopifyUser, $batch->map(fn (array $item) => [
                'inventoryItemId' => $item['inventoryItemId'],
                'locationId'      => $locationId,
                'quantity'        => $item['quantity'],
            ])->all());

            if ($message = Arr::get($result, 'message')) {
                $summary['failed'] += $batch->count();
                Sentry::captureMessage("Shopify inventory update failed ($shop->slug): $message");

                continue;
            }

            foreach ($batch as $index => $item) {
                $error = $result['failed'][$index] ?? null;

                if ($error && str_contains(strtolower($error), self::NOT_STOCKED_AT_LOCATION)) {
                    $error = Arr::get($this->activateShopifyExternalShopInventoryItem($shopifyUser, $item['inventoryItemId'], $locationId, $item['quantity']), 'message');
                }

                if ($error) {
                    $summary['failed']++;
                    $this->reportFailure($item['product'], $error);

                    continue;
                }

                if (!$item['inventoryTracked']) {
                    $this->startTracking($shopifyUser, $item['product']);
                }

                $summary['updated']++;
            }
        }

        return $summary;
    }

    public function isShopifyPushAllowed(): bool
    {
        return $this->isShopifyExternalShopWriteAllowed();
    }

    public function canPushInventory(Product $product): bool
    {
        if (!$product->marketplace_id || !Arr::get($product->data, 'shopify.inventory_item_id')) {
            return false;
        }

        return $product->state !== ProductStateEnum::IN_PROCESS;
    }

    public function getQuantityToPush(Product $product): int
    {
        if ($product->state === ProductStateEnum::DISCONTINUED) {
            return 0;
        }

        return max((int) floor((float) $product->available_quantity), 0);
    }

    private function startTracking(ShopifyUser $shopifyUser, Product $product): void
    {
        $result = $this->trackShopifyExternalShopInventoryItem($shopifyUser, Arr::get($product->data, 'shopify.inventory_item_id'));

        if ($message = Arr::get($result, 'message')) {
            $this->reportFailure($product, 'Could not turn on stock tracking: '.$message);

            return;
        }

        $data = $product->data ?? [];
        data_set($data, 'shopify.inventory_tracked', true);
        $product->updateQuietly(['data' => $data]);
    }

    /**
     * A discontinued product is usually gone from the store too, so Shopify not finding it is expected.
     */
    private function reportFailure(Product $product, string $error): void
    {
        if ($product->state === ProductStateEnum::DISCONTINUED) {
            return;
        }

        Sentry::captureMessage('Shopify inventory update failed for '.$product->slug.': '.$error);
    }

    public string $commandSignature = 'external_shop:shopify_inventory {model?}';

    public function asCommand(Command $command): int
    {
        $model = $command->argument('model');

        if ($model && $product = Product::where('slug', $model)->first()) {
            $command->info("Updating Shopify inventory for product $product->code");
            $this->handle($product);

            return 0;
        }

        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('state', ShopStateEnum::OPEN)
            ->when($model, fn ($query) => $query->where('slug', $model))
            ->get();

        if ($model && $shops->isEmpty()) {
            $command->error("No Shopify external shop or product $model");

            return 1;
        }

        foreach ($shops as $shop) {
            $summary = $this->pushShopInventory($shop);

            $command->info("$shop->name: updated {$summary['updated']}, failed {$summary['failed']}, skipped {$summary['skipped']}".(Arr::has($summary, 'message') ? ' ('.$summary['message'].')' : ''));
        }

        return 0;
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message?: string}
     */
    public function pushShopInventory(Shop $shop): array
    {
        $summary = ['updated' => 0, 'failed' => 0, 'skipped' => 0];

        Product::where('shop_id', $shop->id)
            ->whereNotNull('marketplace_id')
            ->whereNotIn('state', [ProductStateEnum::IN_PROCESS, ProductStateEnum::DISCONTINUED])
            ->chunkById(500, function (Collection $products) use ($shop, &$summary) {
                $result = $this->pushInventory($shop, $products);

                foreach (['updated', 'failed', 'skipped'] as $key) {
                    $summary[$key] += $result[$key];
                }

                if (Arr::has($result, 'message')) {
                    $summary['message'] = $result['message'];
                }
            });

        return $summary;
    }
}
