<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\CRM\TrafficSourceCampaign\GoogleAdsElementTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pauses or resumes one ad group, ad or keyword inside a campaign, and sets a keyword's own CPC bid.
 *
 * These live in the campaign's `ad_groups` blob rather than in tables of their own, so a change is
 * pushed to Google, then patched into that blob in place. Rewriting only the element that changed
 * keeps the rest of the blob exactly as the last fetch left it, which matters because the blob is
 * also what the page renders.
 */
class UpdateGoogleAdsCampaignElement extends OrgAction
{
    use WithGoogleAdsWriteErrors;

    /** REMOVED is left out here for the same reason as on the campaign: it cannot be undone. */
    private const array STATUSES = ['ENABLED', 'PAUSED'];

    /**
     * @throws GoogleAdsException
     */
    public function handle(TrafficSourceCampaign $campaign, array $modelData): TrafficSourceCampaign
    {
        $shop   = $campaign->trafficSource->shop;
        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw ValidationException::withMessages([
                'status' => GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'),
            ]);
        }

        $type       = GoogleAdsElementTypeEnum::from(Arr::get($modelData, 'type'));
        $adGroupId  = (string) Arr::get($modelData, 'ad_group_id');
        $elementId  = (string) Arr::get($modelData, 'element_id', $adGroupId);
        $status     = Arr::get($modelData, 'status');
        $cpcBid     = Arr::get($modelData, 'cpc_bid');

        $data     = $campaign->data ?? [];
        $adGroups = $data['ad_groups'] ?? [];
        $located  = $this->locate($adGroups, $type, $adGroupId, $elementId);

        if ($located === null) {
            throw ValidationException::withMessages([
                'element_id' => __('That :element is not on this campaign. It may have been removed in Google Ads since the last fetch.', [
                    'element' => strtolower(GoogleAdsElementTypeEnum::labels()[$type->value]),
                ]),
            ]);
        }

        [$groupIndex, $elementIndex] = $located;
        $current = $elementIndex === null ? $adGroups[$groupIndex] : $adGroups[$groupIndex][$type->collectionKey()][$elementIndex];

        $update = ['resourceName' => $type->resourceName($client->customerId(), $adGroupId, $elementId)];
        $mask   = [];

        if ($status !== null && $status !== Arr::get($current, 'status')) {
            $update['status'] = $status;
            $mask[]           = 'status';
        }

        if ($cpcBid !== null) {
            if ($type !== GoogleAdsElementTypeEnum::KEYWORD) {
                throw ValidationException::withMessages([
                    'cpc_bid' => __('Only a keyword carries its own bid.'),
                ]);
            }

            $update['cpcBidMicros'] = (string) (int) round((float) $cpcBid * 1_000_000);
            $mask[]                 = 'cpcBidMicros';
        }

        if ($mask === []) {
            return $campaign;
        }

        try {
            $client->mutate($type->service(), [['update' => $update, 'updateMask' => implode(',', $mask)]]);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, $cpcBid !== null ? 'cpc_bid' : 'status');
        }

        $auditOld = [];
        $auditNew = [];
        $path     = $this->auditPath($type, $adGroupId, $elementId);

        foreach ($mask as $field) {
            $local = $field === 'status' ? 'status' : 'cpc_bid';
            $value = $field === 'status' ? $status : (float) $cpcBid;

            $auditOld["{$path}.{$local}"] = Arr::get($current, $local);
            $auditNew["{$path}.{$local}"] = $value;

            if ($elementIndex === null) {
                $adGroups[$groupIndex][$local] = $value;
            } else {
                $adGroups[$groupIndex][$type->collectionKey()][$elementIndex][$local] = $value;
            }
        }

        $data['ad_groups'] = $adGroups;
        $campaign->update(['data' => $data]);

        $campaign->auditEvent     = 'updated';
        $campaign->isCustomEvent  = true;
        $campaign->auditCustomOld = $auditOld;
        $campaign->auditCustomNew = $auditNew;
        Event::dispatch(new AuditCustom($campaign));
        $campaign->isCustomEvent  = false;
        $campaign->auditCustomOld = [];
        $campaign->auditCustomNew = [];

        return $campaign->refresh();
    }

    /**
     * Position of the element inside the blob, as [adGroupIndex, elementIndex]; the element index is
     * null for an ad group, which is the blob's own top level.
     *
     * @return array{0: int, 1: int|null}|null
     */
    private function locate(array $adGroups, GoogleAdsElementTypeEnum $type, string $adGroupId, string $elementId): ?array
    {
        foreach ($adGroups as $groupIndex => $group) {
            if ((string) Arr::get($group, 'id') !== $adGroupId) {
                continue;
            }

            if ($type === GoogleAdsElementTypeEnum::AD_GROUP) {
                return [$groupIndex, null];
            }

            foreach (Arr::get($group, $type->collectionKey(), []) as $elementIndex => $element) {
                if ((string) Arr::get($element, 'id') === $elementId) {
                    return [$groupIndex, $elementIndex];
                }
            }

            return null;
        }

        return null;
    }

    private function auditPath(GoogleAdsElementTypeEnum $type, string $adGroupId, string $elementId): string
    {
        return $type === GoogleAdsElementTypeEnum::AD_GROUP
            ? "ad_group.{$adGroupId}"
            : "ad_group.{$adGroupId}.{$type->value}.{$elementId}";
    }

    public function rules(): array
    {
        return [
            'type'         => ['required', Rule::enum(GoogleAdsElementTypeEnum::class)],
            'ad_group_id'  => ['required', 'string', 'max:32'],
            'element_id'   => ['sometimes', 'nullable', 'string', 'max:32'],
            'status'       => ['sometimes', 'nullable', Rule::in(self::STATUSES)],
            'cpc_bid'      => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
        ];
    }

    /**
     * @throws GoogleAdsException
     */
    public function asController(Organisation $organisation, Shop $shop, TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): TrafficSourceCampaign
    {
        if (
            $trafficSourceCampaign->trafficSource->shop_id !== $shop->id
            || $trafficSourceCampaign->trafficSource->type !== TrafficSourcesTypeEnum::GOOGLE_ADS->value
        ) {
            throw new NotFoundHttpException();
        }

        $this->initialisationFromShop($shop, $request);

        return $this->handle($trafficSourceCampaign, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
