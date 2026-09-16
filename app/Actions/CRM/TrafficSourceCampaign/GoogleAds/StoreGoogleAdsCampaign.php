<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\GoogleAdsCampaignStateEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Builds a Search campaign in Google Ads: a budget, the campaign, its country targeting, one ad group,
 * its keywords, and a responsive search ad, created as a single transaction.
 *
 * A campaign is useless without all six. Created one call at a time, an ad refused on policy would
 * leave a funded campaign and an empty ad group behind, and the person who clicked the button would
 * have no idea which half existed. GoogleAdsService applies them together or not at all.
 *
 * It is always created paused. The alternative is a campaign that starts spending the moment the form
 * is submitted, on ads nobody has read back yet, and there is no undo for money already spent.
 */
class StoreGoogleAdsCampaign extends OrgAction
{
    use WithGoogleAdsImageAssets;
    use WithGoogleAdsWriteErrors;

    private const array MATCH_TYPES = ['BROAD', 'PHRASE', 'EXACT'];

    /**
     * The types Google's API will accept a create for. Video is absent because Google refuses to
     * create one through the API whatever bidding strategy is offered, and Shopping because it needs
     * a Merchant Center feed; both are still built in Google Ads itself.
     */
    public const array CHANNEL_TYPES = ['SEARCH', 'PERFORMANCE_MAX', 'DISPLAY', 'DEMAND_GEN'];


    /**
     * @return array{validated: bool, campaign_reference: string|null, resource_name: string|null}
     * @throws GoogleAdsException
     */
    /**
     * Writes the campaign into Aiku and stops there.
     *
     * Nothing reaches Google at this point, which is the whole change: a campaign can be started on
     * Monday, given its ads on Tuesday and read by somebody else before a penny is committed. It gets
     * a row, a state of in process and no Google id, because it is the id of a thing that does not
     * exist yet.
     *
     * `validate_only` still asks Google whether it would accept what is written so far, and still
     * creates nothing, so the answer is available long before anyone commits to it.
     *
     * @throws GoogleAdsException
     */
    public function handle(Shop $shop, array $modelData): array
    {
        $trafficSource = TrafficSource::where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->firstOrFail();

        $campaign = TrafficSourceCampaign::create([
            'traffic_source_id' => $trafficSource->id,
            'name'              => trim((string) Arr::get($modelData, 'name')),
            'type'              => TrafficSourcesTypeEnum::GOOGLE_ADS->value,
            'channel_type'      => Arr::get($modelData, 'channel_type'),
            'state'             => GoogleAdsCampaignStateEnum::IN_PROCESS,
            'in_process_at'     => now(),
            'data'              => ['headlines' => [], 'descriptions' => [], 'keywords' => []],
        ]);

        return ['campaign' => $campaign];
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'channel_type' => ['required', Rule::in(self::CHANNEL_TYPES)],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(array $result, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.marketing.google_ads.show', [
            'organisation'          => $this->organisation->slug,
            'shop'                  => $this->shop->slug,
            'trafficSourceCampaign' => $result['campaign']->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Campaign started'),
            'description' => __('Fill it in here. Nothing reaches Google until you publish it.'),
        ]);
    }
}
