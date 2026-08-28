<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2026 10:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ApplyPricingRuleToEbayPortfolios implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel, bool $resetOptedOutProducts = false): string
    {
        return $customerSalesChannel->id.'-'.(int) $resetOptedOutProducts;
    }

    public function handle(CustomerSalesChannel $customerSalesChannel, bool $resetOptedOutProducts = false): void
    {
        $settings = $customerSalesChannel->settings;

        if (
            Arr::get($settings, 'do_not_update_prices')
            || $customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN
        ) {
            return;
        }

        $type  = Arr::get($settings, 'pricing.type');
        $value = Arr::get($settings, 'pricing.value');

        if (!in_array($type, ['percent', 'fixed']) || !is_numeric($value)) {
            return;
        }

        $customerSalesChannel->portfolios()
            ->where('status', true)
            ->chunkById(100, function ($portfolios) use ($type, $value, $resetOptedOutProducts) {
                /** @var Portfolio $portfolio */
                foreach ($portfolios as $portfolio) {
                    $hasOwnPricing = Arr::get($portfolio->settings, 'pricing_opt_out')
                        || Arr::get($portfolio->settings, 'pricing.type');

                    if ($hasOwnPricing) {
                        if (!$resetOptedOutProducts) {
                            continue;
                        }

                        $portfolioSettings = $portfolio->settings;
                        unset($portfolioSettings['pricing_opt_out'], $portfolioSettings['pricing']);
                        $portfolio->update(['settings' => $portfolioSettings]);
                    }

                    $base = $portfolio->item?->rrp ?? 0;

                    $newPrice = $type === 'percent'
                        ? round($base * (1 + $value / 100), 2)
                        : round($base + $value, 2);

                    if ($newPrice <= 0) {
                        continue;
                    }

                    if ((float) $portfolio->customer_price === $newPrice && !$hasOwnPricing) {
                        continue;
                    }

                    UpdatePortfolio::run($portfolio, ['customer_price' => $newPrice]);
                    UpdateEbayOffer::dispatch($portfolio);
                }
            });
    }
}
