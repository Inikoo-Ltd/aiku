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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Starts a campaign, in Aiku and nowhere else.
 *
 * Nothing is asked for first, the way a mailshot or a Whatsapp campaign asks for nothing: the button
 * makes the campaign and opens it, and every decision about it, its type included, is made on its own
 * page where it can be changed again tomorrow. A form standing between the button and the campaign
 * only collects answers nobody has yet.
 *
 * It gets a row, a state of in process and no Google id, because that is the id of a thing that does
 * not exist yet. Google hears about it when somebody publishes it.
 */
class StoreGoogleAdsCampaign extends OrgAction
{
    /**
     * The types Google's API will accept a create for. Video is absent because Google refuses to
     * create one through the API whatever bidding strategy is offered, and Shopping because it needs
     * a Merchant Center feed; both are still built in Google Ads itself.
     */
    public const array CHANNEL_TYPES = ['SEARCH', 'PERFORMANCE_MAX', 'DISPLAY', 'DEMAND_GEN'];

    /**
     * Search, because it is the one type that needs no images to be publishable, and the one a
     * suggestion ever proposes. It is a starting point rather than a decision: the type sits at the
     * top of the campaign's own page and costs a click to change while nothing has been published.
     */
    private const string DEFAULT_CHANNEL_TYPE = 'SEARCH';

    /**
     * The types, and what each is for in one line, for the picker on the campaign's own page.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function campaignTypes(): array
    {
        $types = [
            'SEARCH' => [
                'label'       => __('Search'),
                'description' => __('Text ads against what people type into Google. The only type that bids on keywords.'),
            ],
            'PERFORMANCE_MAX' => [
                'label'       => __('Performance Max'),
                'description' => __('Google assembles its own ads from what you give it and shows them everywhere. Needs images and a logo.'),
            ],
            'DISPLAY' => [
                'label'       => __('Display'),
                'description' => __('Image ads on the websites and apps in Google\'s display network.'),
            ],
            'DEMAND_GEN' => [
                'label'       => __('Demand Gen'),
                'description' => __('Image ads on YouTube, Discover and Gmail, aimed at people not yet looking for you.'),
            ],
        ];

        return collect(self::CHANNEL_TYPES)
            ->map(fn (string $type) => array_merge(['value' => $type], $types[$type]))
            ->values()
            ->all();
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (blank($this->get('channel_type'))) {
            $this->set('channel_type', self::DEFAULT_CHANNEL_TYPE);
        }

        if (blank($this->get('name'))) {
            $this->set('name', $this->defaultName($request));
        }
    }

    /**
     * Named after whoever made it and the day they did, as the Whatsapp campaigns are, with a counter
     * for the second one made the same day so a list of them can still be told apart.
     */
    private function defaultName(ActionRequest $request): string
    {
        $base = __('New campaign by :user (:date)', [
            'user' => $request->user()->contact_name ?: $request->user()->username,
            'date' => now()->format('d/m/Y'),
        ]);

        /* prepareForValidation runs before initialisationFromShop, so the shop comes from the route
           rather than from $this->shop. */
        $shop = $request->route('shop');

        $taken = TrafficSourceCampaign::join('traffic_sources', 'traffic_sources.id', '=', 'traffic_source_campaigns.traffic_source_id')
            ->where('traffic_sources.shop_id', $shop->id)
            ->where('traffic_source_campaigns.name', 'like', $base.'%')
            ->pluck('traffic_source_campaigns.name')
            ->all();

        $name   = $base;
        $suffix = 1;

        while (in_array($name, $taken, true)) {
            $suffix++;
            $name = $base.' #'.$suffix;
        }

        return $name;
    }

    public function handle(Shop $shop, array $modelData): TrafficSourceCampaign
    {
        $trafficSource = TrafficSource::where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->firstOrFail();

        return TrafficSourceCampaign::create([
            'traffic_source_id' => $trafficSource->id,
            'name'              => trim((string) Arr::get($modelData, 'name')),
            'type'              => TrafficSourcesTypeEnum::GOOGLE_ADS->value,
            'channel_type'      => Arr::get($modelData, 'channel_type'),
            'state'             => GoogleAdsCampaignStateEnum::IN_PROCESS,
            'in_process_at'     => now(),
            'data'              => [
                'headlines'    => [],
                'descriptions' => [],

                /* A suggestion arrives with the terms it worked the campaign out from, so the page
                   opens with them already in the box rather than asking for them back. */
                'keywords' => collect(Arr::get($modelData, 'keywords', []))
                    ->map(fn ($keyword) => trim((string) $keyword))
                    ->filter()
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'channel_type' => ['required', Rule::in(self::CHANNEL_TYPES)],
            'keywords'     => ['sometimes', 'array', 'max:100'],
            'keywords.*'   => ['string', 'max:80'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): TrafficSourceCampaign
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(TrafficSourceCampaign $campaign): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.marketing.google_ads.show', [
            'organisation'          => $this->organisation->slug,
            'shop'                  => $this->shop->slug,
            'trafficSourceCampaign' => $campaign->slug,
        ]);
    }
}
