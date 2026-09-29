<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Switches a published campaign on at Google, which is the moment it starts spending its budget.
 *
 * A route of its own rather than a status sent in a request body, because this is a button in the page
 * head and those carry no body. It is also the clearer record: the log says somebody resume this
 * campaign, not that they set a field to a string.
 */
class ResumeGoogleAdsCampaign extends OrgAction
{
    /**
     * @throws GoogleAdsException
     */
    public function handle(TrafficSourceCampaign $campaign): TrafficSourceCampaign
    {
        return UpdateGoogleAdsCampaign::make()->handle($campaign, ['status' => 'ENABLED']);
    }

    public function asController(Organisation $organisation, Shop $shop, TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): TrafficSourceCampaign
    {
        if (
            $trafficSourceCampaign->trafficSource->shop_id !== $shop->id
            || $trafficSourceCampaign->trafficSource->type !== TrafficSourcesTypeEnum::GOOGLE_ADS->value
        ) {
            throw new NotFoundHttpException();
        }

        $this->initialisationFromShop($shop, $request);

        return $this->handle($trafficSourceCampaign);
    }

    public function rules(): array
    {
        return [];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
