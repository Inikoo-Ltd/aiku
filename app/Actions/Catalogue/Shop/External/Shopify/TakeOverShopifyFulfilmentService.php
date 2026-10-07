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

    public string $commandSignature = 'external_shop:shopify_take_over_fulfilment_service {shop} {--close-channel : Close the dropshipping channel of the store first, keeping its Shopify connection} {--revert : Send the fulfilment requests back to dropshipping} {--force : Do not ask for confirmation}';

    /**
     * A store moving from a dropshipping channel keeps its fulfilment location, its stock and its order routing;
     * only where Shopify sends the fulfilment requests changes, from dropshipping to this shop.
     *
     * @return string|null why it could not be done
     */
    public function handle(Shop $shop, ?Command $command = null, bool $closeChannel = false): ?string
    {
        $isWriteAllowed = $this->isShopifyExternalShopWriteAllowed();

        if (!$isWriteAllowed) {
            $command?->warn($this->getShopifyExternalShopWriteBlockedMessage().'. Everything else runs on this database only.');
        }

        if ($closeChannel) {
            $this->closeDropshippingChannel($shop, $command);
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

        $command?->info('Fulfilment requests go to dropshipping again; reopen the dropshipping channel to process them there');

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
     * Only the channel is closed: closing it the usual way also deletes the Shopify connection and its fulfilment
     * service, which this shop takes over.
     */
    private function closeDropshippingChannel(Shop $shop, ?Command $command): void
    {
        $customerSalesChannelId = $this->getShopifyExternalShopUser($shop)?->customer_sales_channel_id;

        if (!$customerSalesChannelId) {
            return;
        }

        $closed = CustomerSalesChannel::where('id', $customerSalesChannelId)
            ->where('status', '!=', CustomerSalesChannelStatusEnum::CLOSED)
            ->update([
                'status'    => CustomerSalesChannelStatusEnum::CLOSED,
                'closed_at' => now(),
            ]);

        if ($closed) {
            $command?->info("Dropshipping channel $customerSalesChannelId closed, its Shopify connection kept");
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
            ->where('status', '!=', CustomerSalesChannelStatusEnum::CLOSED)
            ->exists()) {
            return [null, null, __('Close the dropshipping channel of this store first, so it stops taking its orders')];
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
            $command->option('close-channel') => "Close the dropshipping channel of this store and send its Shopify fulfilment requests to $shop->name?",
            default                            => "Send the Shopify fulfilment requests of $shop->name to this shop instead of dropshipping?",
        };

        if (!$command->option('force') && !$command->confirm($question)) {
            return 1;
        }

        $error = $revert ? $this->revert($shop, $command) : $this->handle($shop, $command, (bool) $command->option('close-channel'));

        if ($error) {
            $command->error($error);

            return 1;
        }

        $command->info('Done');

        return 0;
    }
}
