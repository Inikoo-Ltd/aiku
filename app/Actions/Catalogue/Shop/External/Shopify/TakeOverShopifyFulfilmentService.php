<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class TakeOverShopifyFulfilmentService extends OrgAction
{
    use WithShopifyExternalShopApi;

    public string $commandSignature = 'external_shop:shopify_take_over_fulfilment_service {shop} {--stop-channel : Set the dropshipping channel of the store to inactive first: it takes no new orders and still ships the ones it has} {--revert : Send the fulfilment requests back to dropshipping} {--force : Do not ask for confirmation}';

    /**
     * A store moving from a dropshipping channel keeps its fulfilment location, its stock and its order routing;
     * only where Shopify sends the fulfilment requests changes, from dropshipping to this shop.
     *
     * @return string|null why it could not be done
     */
    public function handle(Shop $shop, ?Command $command = null, bool $stopChannel = false): ?string
    {
        $isWriteAllowed = $this->isShopifyExternalShopWriteAllowed();

        if (!$isWriteAllowed) {
            $command?->warn($this->getShopifyExternalShopWriteBlockedMessage().'. Everything else runs on this database only.');
        }

        if ($stopChannel) {
            $this->stopDropshippingChannel($shop, $command);
        }

        [$shopifyUser, $fulfilmentServiceId, $error] = $this->getFulfilmentServiceToMove($shop);

        if ($error) {
            return $error;
        }

        $fulfilmentService = $this->getShopifyExternalShopFulfilmentService($shopifyUser, $fulfilmentServiceId);

        if ($message = Arr::get($fulfilmentService, 'message')) {
            return $message;
        }

        if ($isWriteAllowed && !$this->isOurFulfilmentService($fulfilmentService)) {
            return __('The fulfilment service calls :url, not this server (:domain); nothing changed', [
                'url'    => Arr::get($fulfilmentService, 'callbackUrl'),
                'domain' => config('app.domain'),
            ]);
        }

        $copied = CopyShopifyPortfoliosToExternalShop::run($shop, $command);
        $command?->info("Portfolios: copied {$copied['copied']}, updated {$copied['updated']}, skipped {$copied['skipped']}");

        GetShopifyProducts::run($shop, $command);

        if ($isWriteAllowed) {
            $result = $this->updateShopifyExternalShopFulfilmentServiceCallback($shopifyUser, $fulfilmentServiceId, $this->getShopifyExternalShopFulfilmentServiceCallbackUrl($shopifyUser));

            if ($message = Arr::get($result, 'message')) {
                return $message;
            }
        } else {
            $command?->warn('Fulfilment service left calling '.Arr::get($fulfilmentService, 'callbackUrl'));
        }

        $settings = $shop->settings ?? [];
        data_set($settings, 'shopify.fulfilment_service_id', $fulfilmentServiceId);
        data_set($settings, 'shopify.location_id', Arr::get($fulfilmentService, 'location.id'));
        data_set($settings, 'shopify.location_name', Arr::get($fulfilmentService, 'location.name'));
        $shop->update(['settings' => $settings]);

        $command?->info('Fulfilment requests now come to '.$shop->name.' at '.Arr::get($fulfilmentService, 'location.name'));

        CheckExternalShopShopifyConnection::run($shop->refresh());

        $summary = UpdateShopifyProductInventoryQuantity::make()->pushShopInventory($shop);
        $command?->info("Stock pushed: updated {$summary['updated']}, failed {$summary['failed']}, skipped {$summary['skipped']}".(Arr::has($summary, 'message') ? ' ('.$summary['message'].')' : ''));

        GetShopifyOrdersInShop::run($shop, $command);

        return null;
    }

    public function revert(Shop $shop, ?Command $command = null): ?string
    {
        $shopifyUser         = $this->getShopifyExternalShopUser($shop);
        $fulfilmentServiceId = Arr::get($shop->settings, 'shopify.fulfilment_service_id');

        if (!$shopifyUser || !$fulfilmentServiceId) {
            return __('This shop has not taken over a fulfilment service');
        }

        if ($this->isShopifyExternalShopWriteAllowed()) {
            $result = $this->updateShopifyExternalShopFulfilmentServiceCallback($shopifyUser, $fulfilmentServiceId, 'https://'.config('app.domain')."/webhooks/shopify/$shopifyUser->id");

            if ($message = Arr::get($result, 'message')) {
                return $message;
            }
        } else {
            $command?->warn($this->getShopifyExternalShopWriteBlockedMessage());
        }

        $settings = $shop->settings ?? [];
        data_forget($settings, 'shopify.fulfilment_service_id');
        data_forget($settings, 'shopify.location_id');
        data_forget($settings, 'shopify.location_name');
        $shop->update(['settings' => $settings]);

        $command?->info('Fulfilment requests go to dropshipping again; set the dropshipping channel back to open to process them there');

        return null;
    }

    /**
     * The service must already call this very server, so a misconfigured environment can never point a live
     * store's orders somewhere else.
     */
    public function isOurFulfilmentService(array $fulfilmentService): bool
    {
        return parse_url((string) Arr::get($fulfilmentService, 'callbackUrl'), PHP_URL_HOST) === config('app.domain');
    }

    /**
     * The channel is set inactive, not closed: every dropshipping Shopify job only serves open channels, so it takes
     * no new orders, while its Shopify connection stays so the orders it already has are still shipped and fulfilled.
     */
    private function stopDropshippingChannel(Shop $shop, ?Command $command): void
    {
        $customerSalesChannelId = $this->getShopifyExternalShopUser($shop)?->customer_sales_channel_id;

        if (!$customerSalesChannelId) {
            return;
        }

        $stopped = CustomerSalesChannel::where('id', $customerSalesChannelId)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->update(['status' => CustomerSalesChannelStatusEnum::INACTIVE]);

        if ($stopped) {
            $command?->info("Dropshipping channel $customerSalesChannelId set inactive: no new orders, its open orders still ship");
        }
    }

    /**
     * @return array{0: ?ShopifyUser, 1: ?string, 2: ?string}
     */
    private function getFulfilmentServiceToMove(Shop $shop): array
    {
        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            return [null, null, __('Shop is not a Shopify external shop')];
        }

        $shopifyUser = $this->getShopifyExternalShopUser($shop);

        if (!$shopifyUser) {
            return [null, null, __('The shop is not connected to a Shopify store')];
        }

        if ($shopifyUser->customer_sales_channel_id && CustomerSalesChannel::where('id', $shopifyUser->customer_sales_channel_id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->exists()) {
            return [null, null, __('The dropshipping channel of this store is still open; run with --stop-channel so it takes no new orders')];
        }

        if (!$fulfilmentServiceId = $shopifyUser->shopify_fulfilment_service_id) {
            return [null, null, __('The Shopify store has no fulfilment service to take over')];
        }

        return [$shopifyUser, $fulfilmentServiceId, null];
    }

    public function asCommand(Command $command): int
    {
        $shop   = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $revert = (bool) $command->option('revert');

        $question = match (true) {
            $revert                            => "Send the Shopify fulfilment requests of $shop->name back to dropshipping?",
            $command->option('stop-channel')  => "Stop the dropshipping channel of this store taking new orders and send its Shopify fulfilment requests to $shop->name?",
            default                            => "Send the Shopify fulfilment requests of $shop->name to this shop instead of dropshipping?",
        };

        if (!$command->option('force') && !$command->confirm($question)) {
            return 1;
        }

        $error = $revert ? $this->revert($shop, $command) : $this->handle($shop, $command, (bool) $command->option('stop-channel'));

        if ($error) {
            $command->error($error);

            return 1;
        }

        $command->info('Done');

        return 0;
    }
}
