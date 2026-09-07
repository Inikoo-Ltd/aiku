<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 15:44:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Allegro\Order\GetAllegroOrdersFromApi;
use App\Actions\Dropshipping\Amazon\Orders\GetRetinaOrdersFromAmazon;
use App\Actions\Dropshipping\Ebay\Orders\FetchEbayUserOrders;
use App\Actions\Dropshipping\Magento\Orders\GetRetinaOrdersFromMagento;
use App\Actions\Dropshipping\Shopify\Order\FetchShopifyOrdersFromApi;
use App\Actions\Dropshipping\Tiktok\Order\GetTiktokOrdersApi;
use App\Actions\Dropshipping\WooCommerce\Orders\FetchWooUserOrders;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Helpers\PlatformResponseFormatter;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Lets a customer pull their own orders from a sales channel on demand, for when a webhook was
 * missed and an order never reached AW.
 *
 * It runs while they wait rather than on a queue, because the point is that they see what
 * happened, and every platform import already refuses to create an order it has seen before.
 */
class FetchRetinaCustomerSalesChannelOrders extends RetinaAction
{
    private const COOLDOWN_SECONDS = 120;

    public function authorize(ActionRequest $request): bool
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $request->route('customerSalesChannel');

        return $customerSalesChannel->customer_id == $this->customer->id;
    }

    /**
     * @return array{status: string, title: string, description: string}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        $platformUser = $customerSalesChannel->user;

        if (!$platformUser) {
            return $this->notification(
                'error',
                __('Channel not connected'),
                __('This channel is not connected, reconnect it and try again.')
            );
        }

        if (!$this->isSupported($customerSalesChannel)) {
            return $this->notification(
                'error',
                __('Not available'),
                __('Fetching orders on demand is not available for :platform.', ['platform' => $customerSalesChannel->platform->name])
            );
        }

        $cooldownKey = 'retina_fetch_orders_'.$customerSalesChannel->id;

        if (Cache::has($cooldownKey)) {
            return $this->notification(
                'error',
                __('Just a moment'),
                __('Orders were fetched for this channel a moment ago, please wait a couple of minutes before trying again.')
            );
        }

        Cache::put($cooldownKey, true, self::COOLDOWN_SECONDS);

        $ordersBefore = $customerSalesChannel->orders()->count();

        try {
            $this->fetch($customerSalesChannel, $platformUser);
        } catch (\Throwable $e) {
            return $this->notification(
                'error',
                __('Could not fetch orders'),
                $this->explainFailure($customerSalesChannel, $e)
            );
        }

        $newOrders = $customerSalesChannel->orders()->count() - $ordersBefore;

        if ($newOrders < 1) {
            return $this->notification(
                'success',
                __('No new orders'),
                __('Everything on :platform is already in AW.', ['platform' => $customerSalesChannel->platform->name])
            );
        }

        return $this->notification(
            'success',
            __('Orders fetched'),
            trans_choice('{1} :count new order was imported.|[2,*] :count new orders were imported.', $newOrders, ['count' => $newOrders])
        );
    }

    private function isSupported(CustomerSalesChannel $customerSalesChannel): bool
    {
        return in_array($customerSalesChannel->platform->type, [
            PlatformTypeEnum::SHOPIFY,
            PlatformTypeEnum::WOOCOMMERCE,
            PlatformTypeEnum::TIKTOK,
            PlatformTypeEnum::EBAY,
            PlatformTypeEnum::AMAZON,
            PlatformTypeEnum::ALLEGRO,
            PlatformTypeEnum::MAGENTO,
        ]);
    }

    /**
     * @throws \Throwable
     */
    private function fetch(CustomerSalesChannel $customerSalesChannel, $platformUser): void
    {
        match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::SHOPIFY => FetchShopifyOrdersFromApi::run($platformUser),
            PlatformTypeEnum::WOOCOMMERCE => FetchWooUserOrders::run($platformUser),
            PlatformTypeEnum::TIKTOK => GetTiktokOrdersApi::run($platformUser),
            PlatformTypeEnum::EBAY => FetchEbayUserOrders::run($platformUser),
            PlatformTypeEnum::AMAZON => GetRetinaOrdersFromAmazon::run($platformUser),
            PlatformTypeEnum::ALLEGRO => GetAllegroOrdersFromApi::run($platformUser),
            PlatformTypeEnum::MAGENTO => GetRetinaOrdersFromMagento::run($platformUser),
            default => null,
        };
    }

    /**
     * The platform answer is put through the same formatter the upload errors use, so a customer
     * gets the reason and the hint rather than a raw API sentence.
     */
    private function explainFailure(CustomerSalesChannel $customerSalesChannel, \Throwable $e): string
    {
        $formatted = PlatformResponseFormatter::make()->format($e->getMessage());

        $message = Arr::get($formatted, 'message') ?: __('The channel did not answer.');
        $hint = Arr::get($formatted, 'hint');

        return __(':platform could not be read: :message', [
            'platform' => $customerSalesChannel->platform->name,
            'message' => $message,
        ]).($hint ? ' '.$hint : '');
    }

    /**
     * @return array{status: string, title: string, description: string}
     */
    private function notification(string $status, string $title, string $description): array
    {
        return [
            'status' => $status,
            'title' => $title,
            'description' => $description,
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }

    public function htmlResponse(array $notification): RedirectResponse
    {
        return Redirect::back()->with('notification', $notification);
    }
}
