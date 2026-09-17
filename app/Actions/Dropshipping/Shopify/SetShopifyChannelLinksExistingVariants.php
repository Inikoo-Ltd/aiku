<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 19:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Linking portfolios to the variants a merchant already has is switched on channel by channel,
 * every other channel keeps creating its own variant when matching (HELP-3180).
 */
class SetShopifyChannelLinksExistingVariants
{
    use AsAction;

    public string $commandSignature = 'shopify:link-existing-variants {customerSalesChannel : Slug of the channel} {--off : Switch it off again}';

    public string $commandDescription = 'Let a Shopify channel link portfolios to the variants the merchant already has';

    public function handle(CustomerSalesChannel $customerSalesChannel, bool $linksExistingVariants): CustomerSalesChannel
    {
        $settings = $customerSalesChannel->settings ?? [];
        data_set($settings, 'shopify.link_existing_variants', $linksExistingVariants);

        $customerSalesChannel->update(['settings' => $settings]);

        return $customerSalesChannel;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        if (!$customerSalesChannel || $customerSalesChannel->platform?->type !== PlatformTypeEnum::SHOPIFY) {
            $command->error('No Shopify channel has that slug');

            return 1;
        }

        $linksExistingVariants = !$command->option('off');
        $this->handle($customerSalesChannel, $linksExistingVariants);

        $command->info($customerSalesChannel->slug.' ('.$customerSalesChannel->name.') '.($linksExistingVariants ? 'now links' : 'no longer links').' portfolios to existing variants');

        return 0;
    }
}
