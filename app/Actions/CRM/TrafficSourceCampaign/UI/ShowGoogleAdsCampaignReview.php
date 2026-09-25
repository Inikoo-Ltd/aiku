<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\CheckGoogleAdsCampaign;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\StoreGoogleAdsCampaign;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\StoreGoogleAdsImage;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowGoogleAdsCampaignReview extends OrgAction
{
    use WithGoogleAdsCampaignJourney;

    private const array IMAGE_ROLES = ['marketing_images', 'square_marketing_images', 'logos'];

    public function handle(TrafficSourceCampaign $trafficSourceCampaign): TrafficSourceCampaign
    {
        return $trafficSourceCampaign;
    }

    public function asController(Organisation $organisation, Shop $shop, TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): TrafficSourceCampaign
    {
        $this->initialisationFromShop($shop, $request);

        if (
            $trafficSourceCampaign->trafficSource->shop_id !== $shop->id
            || $trafficSourceCampaign->trafficSource->type !== TrafficSourcesTypeEnum::GOOGLE_ADS->value
        ) {
            throw new NotFoundHttpException();
        }

        return $this->handle($trafficSourceCampaign);
    }

    /**
     * @return array{status: 'incomplete'|'refused'|'accepted', messages: array<int, string>}
     */
    public function verdict(TrafficSourceCampaign $campaign): array
    {
        $missing = $this->missingForGoogle($campaign);

        if ($missing !== []) {
            return ['status' => 'incomplete', 'messages' => $missing];
        }

        try {
            CheckGoogleAdsCampaign::run(
                $campaign->trafficSource->shop,
                $campaign->name,
                (string) $campaign->channel_type,
                $campaign->data ?? []
            );
        } catch (ValidationException $exception) {
            return ['status' => 'refused', 'messages' => $this->refusalMessages($exception->errors())];
        } catch (GoogleAdsException $exception) {
            return ['status' => 'refused', 'messages' => [$exception->getMessage()]];
        }

        return ['status' => 'accepted', 'messages' => []];
    }

    /**
     * @param array<string, array<int, string>> $errors
     * @return array<int, string>
     */
    private function refusalMessages(array $errors): array
    {
        return collect($errors)
            ->flatMap(function (array $messages, string $field) {
                $label = $this->fieldLabel($field);

                return array_map(fn (string $message) => $label ? $label.': '.$message : $message, $messages);
            })
            ->unique()
            ->values()
            ->all();
    }

    private function fieldLabel(string $field): ?string
    {
        if (preg_match('/^(headlines|descriptions)\.(\d+)$/', $field, $matches)) {
            $position = (int) $matches[2] + 1;

            return $matches[1] === 'headlines'
                ? __('Headline :position', ['position' => $position])
                : __('Description :position', ['position' => $position]);
        }

        return match ($field) {
            'name'               => __('Campaign'),
            'budget_amount'      => __('Daily budget'),
            'max_cpc', 'cpc_bid' => __('Cost per click'),
            'final_url'          => __('Landing page'),
            'keywords'           => __('Keywords'),
            'country_codes'      => __('Shown in'),
            'ad_group_name'      => __('Ad group'),
            default              => null,
        };
    }

    public function htmlResponse(TrafficSourceCampaign $campaign, ActionRequest $request): Response|RedirectResponse
    {
        $parameters = $request->route()->originalParameters();

        if (!$campaign->state->isInProcess()) {
            return redirect()->route('grp.org.shops.show.marketing.google_ads.show', $parameters);
        }

        $verdict = $this->verdict($campaign);
        $data    = $campaign->data ?? [];

        return Inertia::render(
            'Org/Shop/CRM/GoogleAdsCampaignReview',
            [
                'breadcrumbs' => ShowGoogleAdsCampaign::make()->getBreadcrumbs($campaign, $parameters),
                'title'       => __('Review').' '.$campaign->name,
                'pageHead'    => [
                    'title'      => $campaign->name,
                    'model'      => __('Campaign:'),
                    'modelStyle' => 'text-sm',
                    'titleStyle' => 'font-normal text-lg',
                    'icon'       => ['icon' => ['fab', 'fa-google'], 'title' => __('Google Ads campaign')],
                    'actions'    => $verdict['status'] === 'accepted' ? [
                        [
                            'type'  => 'button',
                            'style' => 'primary',
                            'label' => __('Publish to Google Ads'),
                            'icon'  => ['fal', 'fa-cloud-upload'],
                            'route' => [
                                'name'       => 'grp.models.org.shop.google_ads.campaign.publish',
                                'parameters' => [
                                    'organisation'          => $this->organisation->id,
                                    'shop'                  => $this->shop->id,
                                    'trafficSourceCampaign' => $campaign->id,
                                ],
                                'method'     => 'post',
                            ],
                        ],
                    ] : [],
                ],
                'journey'  => $this->getGoogleAdsCampaignJourney($campaign, 'review'),
                'verdict'  => $verdict,
                'campaign' => [
                    'name'         => $campaign->name,
                    'channel_type' => $campaign->channel_type,
                    'type_label'   => collect(StoreGoogleAdsCampaign::campaignTypes())->firstWhere('value', $campaign->channel_type)['label'] ?? $campaign->channel_type,
                    'data'         => Arr::except($data, self::IMAGE_ROLES),
                    'countries'    => $this->countryNames((array) Arr::get($data, 'country_codes', [])),
                ],
                'images'        => $this->chosenImages($data),
                'currency'      => $this->shop->currency->code,
                'compose_route' => [
                    'name'       => 'grp.org.shops.show.marketing.google_ads.show',
                    'parameters' => $parameters,
                ],
            ]
        );
    }

    /**
     * @param array<int, string> $codes
     * @return array<int, string>
     */
    private function countryNames(array $codes): array
    {
        $names = collect(GetCountriesOptions::run())->pluck('label', 'code');

        return array_map(fn (string $code) => $names[$code] ?? $code, $codes);
    }

    /**
     * @return array<string, array<int, array{id: int, name: string, thumbnail: string}>>
     */
    private function chosenImages(array $data): array
    {
        $ids = collect(self::IMAGE_ROLES)
            ->flatMap(fn (string $role) => (array) Arr::get($data, $role, []))
            ->unique()
            ->values();

        $media = Media::whereIn('id', $ids)->get()->keyBy('id');

        return collect(self::IMAGE_ROLES)
            ->mapWithKeys(fn (string $role) => [
                $role => collect((array) Arr::get($data, $role, []))
                    ->map(fn ($id) => $media->get((int) $id))
                    ->filter()
                    ->map(fn (Media $image) => StoreGoogleAdsImage::shape($image))
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
