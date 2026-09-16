<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\GoogleAdsCampaignStateEnum;
use App\Models\CRM\TrafficSourceCampaign;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Creates at Google the campaign that until now existed only in Aiku.
 *
 * It arrives paused, always. A campaign that starts spending the moment it is published has no undo,
 * and the point of writing it here first is that somebody reads it before the money starts. Switching
 * it on is a separate act on the campaign page, which is what moves it from published and paused to
 * published and serving.
 *
 * One atomic request, so Google applies every operation or none: there is no half-built campaign to
 * find and clean up afterwards. Images are the exception and go up first, because Google's brand
 * guidelines check cannot see a logo created in the same request as the campaign that needs it.
 *
 * A refusal leaves the campaign exactly as it was, in process, with Google's own sentence attached,
 * because the thing to do about a refusal is edit what it named and try again.
 */
class PublishGoogleAdsCampaign extends OrgAction
{
    use WithGoogleAdsImageAssets;
    use WithGoogleAdsWriteErrors;

    /**
     * @throws GoogleAdsException
     */
    public function handle(TrafficSourceCampaign $campaign): TrafficSourceCampaign
    {
        if (!$campaign->state->isInProcess()) {
            throw ValidationException::withMessages([
                'state' => __('This campaign already exists at Google, so there is nothing to publish.'),
            ]);
        }

        $shop   = $campaign->trafficSource->shop;
        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw ValidationException::withMessages([
                'state' => GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'),
            ]);
        }

        $data = $campaign->data ?? [];

        try {
            $assets     = $this->uploadImages($shop, $data);
            $operations = BuildGoogleAdsCampaignOperations::run($client, $campaign->name, (string) $campaign->channel_type, $data, $assets);
            $responses  = $client->mutateOperations($operations);
        } catch (ValidationException $exception) {
            $campaign->update(['last_error' => Arr::first(Arr::flatten($exception->errors()))]);

            throw $exception;
        } catch (GoogleAdsException $exception) {
            $campaign->update(['last_error' => $exception->getMessage()]);

            $this->refuse($exception, 'state');
        }

        /* The campaign is the second operation, so its response carries the id everything else in
           Aiku keys off. Read from the response rather than searched for: a campaign created a moment
           ago can take a while to turn up in a query. */
        $resourceName = (string) Arr::get($responses, '1.campaignResult.resourceName');
        $reference    = Arr::last(explode('/', $resourceName));

        $campaign->update([
            'reference'    => $reference !== '' ? $reference : null,
            'state'        => GoogleAdsCampaignStateEnum::PUBLISHED_PAUSED,
            'published_at' => now(),
            'last_error'   => null,
            'data'         => array_merge($data, ['status' => 'PAUSED', 'primary_status' => 'PAUSED']),
        ]);

        return $campaign->refresh();
    }

    public function asController(TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): TrafficSourceCampaign
    {
        $this->initialisationFromShop($trafficSourceCampaign->trafficSource->shop, $request);

        return $this->handle($trafficSourceCampaign);
    }

    public function rules(): array
    {
        return [];
    }
}
