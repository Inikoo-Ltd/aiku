<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Models\Catalogue\Shop;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Asks Google whether it would accept this campaign, and creates nothing.
 *
 * Worth offering as its own step because Google's requirements differ by campaign type in ways no
 * form can fully encode: a Performance Max account with brand guidelines switched on wants a square
 * logo attached to the campaign, a responsive display ad has minimum counts per image shape, and
 * both are refused with a sentence that only Google can write.
 *
 * Images are the one thing this does commit. They go into Google's asset library, which costs
 * nothing, spends nothing and is reused by the publish that follows, and without them there is
 * nothing for Google to judge an image campaign on.
 */
class CheckGoogleAdsCampaign
{
    use AsAction;
    use WithGoogleAdsImageAssets;
    use WithGoogleAdsWriteErrors;

    /**
     * @throws GoogleAdsException
     */
    public function handle(Shop $shop, string $name, string $channelType, array $data): void
    {
        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw ValidationException::withMessages([
                'name' => GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'),
            ]);
        }

        try {
            $assets     = $this->uploadImages($shop, $data);
            $operations = BuildGoogleAdsCampaignOperations::run($client, $name, $channelType, $data, $assets);

            $client->mutateOperations($operations, true);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, 'name');
        }
    }
}
