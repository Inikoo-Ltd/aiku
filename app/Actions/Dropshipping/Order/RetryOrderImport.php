<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 14:52:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Order;

use App\Actions\Dropshipping\Allegro\Order\ValidateIncomingAllegroOrder;
use App\Actions\Dropshipping\Amazon\Orders\StoreOrderFromAmazon;
use App\Actions\Dropshipping\Ebay\Orders\StoreOrderFromEbay;
use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CreateFulfilmentOrderFromShopify;
use App\Actions\Dropshipping\Shopify\Order\GetShopifyFulfilmentOrderFromApi;
use App\Actions\Dropshipping\Tiktok\Order\ValidateIncomingTiktokOrder;
use App\Actions\Dropshipping\WooCommerce\Orders\StoreOrderFromWooCommerce;
use App\Enums\Dropshipping\OrderImportRetryStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Re-imports a single order that exists on a sales channel but never made it into AW, usually
 * because a webhook was missed or failed.
 *
 * Nothing is written unless $import is passed, and an order already in AW is never imported a
 * second time, so a support agent can inspect an incident without risking a duplicate shipment
 * or a duplicate charge.
 */
class RetryOrderImport
{
    use AsAction;

    /**
     * @return array{status: OrderImportRetryStatusEnum, message: string, order: Order|null, platform_order: array}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, string $platformOrderId, bool $import = false): array
    {
        $user = $customerSalesChannel->user;

        if (!$user) {
            return $this->result(
                OrderImportRetryStatusEnum::CHANNEL_UNAVAILABLE,
                __('This channel has no connected platform account, reconnect it before retrying the import.')
            );
        }

        if (!$this->isSupported($customerSalesChannel)) {
            return $this->result(
                OrderImportRetryStatusEnum::CHANNEL_UNAVAILABLE,
                __('Retrying an order import is not supported for :platform.', ['platform' => $customerSalesChannel->platform->type->value])
            );
        }

        if ($existingOrder = $this->findExistingOrder($customerSalesChannel, $platformOrderId)) {
            return $this->result(
                OrderImportRetryStatusEnum::ALREADY_IMPORTED,
                __('Already in AW as :reference, nothing was imported.', ['reference' => $existingOrder->reference]),
                $existingOrder
            );
        }

        try {
            $platformOrder = $this->fetchFromPlatform($customerSalesChannel, $user, $platformOrderId);
        } catch (\Throwable $e) {
            return $this->result(
                OrderImportRetryStatusEnum::FAILED,
                __('Could not read the order from the channel: :reason', ['reason' => $e->getMessage()])
            );
        }

        if (!$platformOrder) {
            return $this->result(
                OrderImportRetryStatusEnum::NOT_FOUND_ON_PLATFORM,
                __('The channel has no order with that id, check the id belongs to this channel.')
            );
        }

        if ($unmatched = $this->getPortfolioMismatch($customerSalesChannel, $platformOrder)) {
            return $this->result(OrderImportRetryStatusEnum::FAILED, $unmatched, null, $platformOrder);
        }

        if (!$import) {
            return $this->result(
                OrderImportRetryStatusEnum::READY_TO_IMPORT,
                __('The order was found on the channel and is not in AW yet, re-run with --import to bring it in.'),
                null,
                $platformOrder
            );
        }

        try {
            $this->import($customerSalesChannel, $user, $platformOrder);
        } catch (\Throwable $e) {
            return $this->result(
                OrderImportRetryStatusEnum::FAILED,
                __('The import stopped with: :reason', ['reason' => $e->getMessage()]),
                null,
                $platformOrder
            );
        }

        $order = $this->findExistingOrder($customerSalesChannel, $platformOrderId);

        if (!$order) {
            return $this->result(
                OrderImportRetryStatusEnum::FAILED,
                __('The channel returned the order but AW did not create it. The usual cause is that none of its products are in this channel portfolio, or the order is in a state the import skips.'),
                null,
                $platformOrder
            );
        }

        return $this->result(
            OrderImportRetryStatusEnum::IMPORTED,
            __('Imported as :reference.', ['reference' => $order->reference]),
            $order,
            $platformOrder
        );
    }

    private function isSupported(CustomerSalesChannel $customerSalesChannel): bool
    {
        return in_array($customerSalesChannel->platform->type, [
            PlatformTypeEnum::SHOPIFY,
            PlatformTypeEnum::WOOCOMMERCE,
            PlatformTypeEnum::TIKTOK,
            PlatformTypeEnum::AMAZON,
            PlatformTypeEnum::EBAY,
            PlatformTypeEnum::ALLEGRO,
        ]);
    }

    /**
     * StoreOrderFromAmazon never sets platform_order_id, it keeps the whole Amazon payload in
     * data, so Amazon has to be matched the same way GetRetinaOrdersFromAmazon matches it.
     */
    private function findExistingOrder(CustomerSalesChannel $customerSalesChannel, string $platformOrderId): ?Order
    {
        if ($customerSalesChannel->platform->type === PlatformTypeEnum::AMAZON) {
            return $customerSalesChannel->orders()
                ->whereRaw("data->>'AmazonOrderId' = ?", [$platformOrderId])
                ->first();
        }

        return $customerSalesChannel->orders()
            ->where('platform_order_id', $platformOrderId)
            ->first();
    }

    /**
     * @throws \Exception
     */
    private function fetchFromPlatform(CustomerSalesChannel $customerSalesChannel, $user, string $platformOrderId): array
    {
        return match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::SHOPIFY => GetShopifyFulfilmentOrderFromApi::run($user, $platformOrderId),
            PlatformTypeEnum::WOOCOMMERCE => $this->readResponse($user->getWooCommerceOrder((int)$platformOrderId, false)),
            PlatformTypeEnum::TIKTOK => Arr::first(Arr::get($user->getOrder($platformOrderId), 'data.orders', [])) ?? [],
            PlatformTypeEnum::AMAZON => Arr::get($this->readResponse($user->getOrder($platformOrderId)), 'payload', []),
            PlatformTypeEnum::EBAY, PlatformTypeEnum::ALLEGRO => $this->readResponse($user->getOrder($platformOrderId)),
            default => [],
        };
    }

    /**
     * The eBay and Amazon clients swallow their exceptions and answer with an error key, which
     * would otherwise read as "the order does not exist" instead of naming the real problem.
     *
     * @throws \Exception
     */
    private function readResponse(mixed $response): array
    {
        if (!is_array($response)) {
            return [];
        }

        if ($error = Arr::get($response, 'error')) {
            throw new \Exception(is_string($error) ? $error : json_encode($error));
        }

        return $response;
    }

    private function import(CustomerSalesChannel $customerSalesChannel, $user, array $platformOrder): void
    {
        match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::SHOPIFY => CreateFulfilmentOrderFromShopify::run($user, $platformOrder),
            PlatformTypeEnum::WOOCOMMERCE => StoreOrderFromWooCommerce::run($user, $platformOrder),
            PlatformTypeEnum::TIKTOK => ValidateIncomingTiktokOrder::run($user, $platformOrder),
            PlatformTypeEnum::AMAZON => StoreOrderFromAmazon::run($user, $platformOrder),
            PlatformTypeEnum::EBAY => StoreOrderFromEbay::run($user, $platformOrder),
            PlatformTypeEnum::ALLEGRO => ValidateIncomingAllegroOrder::run($user, $platformOrder),
            default => null,
        };
    }

    /**
     * WooCommerce and eBay keep this guard in their pollers rather than in their store actions,
     * so it is repeated here, otherwise a retry would create an order the normal flow skips.
     */
    private function getPortfolioMismatch(CustomerSalesChannel $customerSalesChannel, array $platformOrder): ?string
    {
        $lineItemIds = match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::WOOCOMMERCE => collect(Arr::get($platformOrder, 'line_items', []))->pluck('product_id')->filter()->all(),
            PlatformTypeEnum::EBAY => collect(Arr::get($platformOrder, 'lineItems', []))->pluck('legacyItemId')->filter()->all(),
            default => null,
        };

        if ($lineItemIds === null) {
            return null;
        }

        if ($lineItemIds === []) {
            return __('The order on the channel has no line items to import.');
        }

        $column = $customerSalesChannel->platform->type === PlatformTypeEnum::EBAY
            ? 'platform_product_variant_id'
            : 'platform_product_id';

        $hasPortfolioProduct = DB::table('portfolios')
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereIn($column, $lineItemIds)
            ->exists();

        if ($hasPortfolioProduct) {
            return null;
        }

        return __('None of the products in this order are in this channel portfolio, so there is nothing AW can ship.');
    }

    /**
     * @return array{status: OrderImportRetryStatusEnum, message: string, order: Order|null, platform_order: array}
     */
    private function result(OrderImportRetryStatusEnum $status, string $message, ?Order $order = null, array $platformOrder = []): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'order' => $order,
            'platform_order' => $platformOrder,
        ];
    }

    public string $commandSignature = 'ds:order:retry-import {customerSalesChannel : slug or id} {platformOrderId : the order id on the sales channel} {--import : actually import, otherwise only report}';

    public function asCommand(Command $command): int
    {
        $argument = (string)$command->argument('customerSalesChannel');

        $customerSalesChannel = CustomerSalesChannel::where('slug', $argument)
            ->when(is_numeric($argument), fn ($query) => $query->orWhere('id', (int)$argument))
            ->first();

        if (!$customerSalesChannel) {
            $command->error('Customer sales channel not found.');

            return 1;
        }

        $platformOrderId = (string)$command->argument('platformOrderId');
        $import = (bool)$command->option('import');

        $command->line('  Channel   '.$customerSalesChannel->customer->name.' / '.$customerSalesChannel->platform->name);
        $command->line('  Order id  '.$platformOrderId);
        $command->newLine();

        $result = $this->handle($customerSalesChannel, $platformOrderId, $import);

        /** @var OrderImportRetryStatusEnum $status */
        $status = $result['status'];

        $line = '  '.$status->label().': '.$result['message'];

        if ($status->isSuccessful()) {
            $command->info($line);
        } else {
            $command->error($line);
        }

        return $status->isSuccessful() ? 0 : 1;
    }
}
