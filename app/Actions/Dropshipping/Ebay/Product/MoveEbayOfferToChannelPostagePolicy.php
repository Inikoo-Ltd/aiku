<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 17:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A listing keeps the postage policy it was published with. When the channel moves to another policy, the
 * listings still on the one it left are moved with it; listings the seller put on any other policy stay as they are
 */
class MoveEbayOfferToChannelPostagePolicy
{
    use AsAction;

    public string $jobQueue = 'ebay';

    public string $commandSignature = 'ebay:move-offers-to-channel-postage {customerSalesChannel} {previousPolicyId}';

    public static function dispatchForChannel(CustomerSalesChannel $customerSalesChannel, string $previousPolicyId): int
    {
        $count = 0;

        $customerSalesChannel->portfolios()
            ->whereNotNull('platform_product_id')
            ->chunkById(100, function ($portfolios) use ($previousPolicyId, &$count) {
                foreach ($portfolios as $portfolio) {
                    self::dispatch($portfolio, $previousPolicyId);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return string 'moved', 'skipped', or the reason eBay refused
     */
    public function handle(Portfolio $portfolio, string $previousPolicyId): string
    {
        /** @var EbayUser $ebayUser */
        $ebayUser = $portfolio->customerSalesChannel->user;

        if (blank($ebayUser->fulfillment_policy_id) || $ebayUser->fulfillment_policy_id === $previousPolicyId) {
            return 'skipped';
        }

        $response = (array) $ebayUser->updateOffer($portfolio->platform_product_id, ['fulfillment_policy_from' => $previousPolicyId]);

        if (Arr::get($response, 'skipped')) {
            return 'skipped';
        }

        $error = EbayUser::ebayResponseError($response) ?? Arr::get($response, 'error');

        if ($error) {
            Log::warning('eBay offer not moved to the channel postage policy', ['portfolio_id' => $portfolio->id, 'error' => $error]);

            return $error;
        }

        return 'moved';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        $previousPolicyId     = $command->argument('previousPolicyId');

        $results = $customerSalesChannel->portfolios()
            ->whereNotNull('platform_product_id')
            ->get()
            ->mapWithKeys(fn (Portfolio $portfolio) => [$portfolio->id => $this->handle($portfolio, $previousPolicyId)]);

        $command->info('moved: '.$results->filter(fn ($result) => $result === 'moved')->count().', skipped: '.$results->filter(fn ($result) => $result === 'skipped')->count());

        foreach ($results->reject(fn ($result) => in_array($result, ['moved', 'skipped'])) as $portfolioId => $error) {
            $command->error("portfolio $portfolioId: $error");
        }

        return 0;
    }
}
