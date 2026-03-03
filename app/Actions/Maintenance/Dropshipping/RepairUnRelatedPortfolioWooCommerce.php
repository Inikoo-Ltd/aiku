<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Oct 2025 10:21:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairUnRelatedPortfolioWooCommerce
{
    use AsAction;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser, Collection $portfolios): void
    {
        $continue = true;
        $fetchedPortfolios = [];

        do {
            $fetchedPortfolio = $wooCommerceUser->getWooCommerceProducts([
                'per_page' => 20,
            ]);

            if (blank($fetchedPortfolio)) {
                $continue = false;
            }

            echo count($fetchedPortfolio) . "\n";

            $fetchedPortfolios[] = $fetchedPortfolio;

        } while ($continue);

        $fetchedPortfolioIds = collect($fetchedPortfolio)->pluck('id');
        $relatedPortfolioIds = $portfolios->pluck('platform_product_id');

        $foundedProduct = [];
        foreach ($fetchedPortfolioIds as $fetchedPortfolioId) {
            if ($relatedPortfolioIds->contains($fetchedPortfolioId)) {
                continue;
            } else {
                $foundedProduct[] = $fetchedPortfolioId;
            }
        }

        dd($foundedProduct);

        if (!blank($foundedProduct)) {
            echo '🤘🏻 Success to update SKU \n';
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:woo_unrelated_sku {customerSalesChannel} {portfolio?}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannelSlug = $command->argument('customerSalesChannel');

        if (!blank($customerSalesChannelSlug)) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannelSlug)->first();
            $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereNotNull('platform_product_id')
                ->get();

            $this->handle($customerSalesChannel->user, $portfolios);
        } else {
            foreach (WooCommerceUser::all() as $wooUser) {
                $portfolios = $wooUser->customerSalesChannel->portfolios;

                $this->handle($wooUser, $portfolios);
            }
        }
    }
}
