<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Helpers\Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Receives a day of advertising spend posted by an ad platform script (see docs/google-ads-cost-ingestion).
 *
 * The bearer token is a Sanctum token whose tokenable *is* the shop, so a token cannot address a shop
 * other than the one it was minted for: there is no shop id in the credential path at all, and the
 * optional `shop` field in the body is only a sanity check against the wrong token being pasted into
 * the wrong account's script. Tokens are hashed at rest, named, and revocable one at a time, which is
 * what an external agency holding one for a single shop requires.
 */
class ReceiveTrafficSourceCostWebhook
{
    use AsAction;

    public const ABILITY = 'traffic-source-costs';

    private Shop $shop;

    public function authorize(ActionRequest $request): bool
    {
        $token = PersonalAccessToken::findToken((string) $request->bearerToken());

        if (!$token || !$token->can(self::ABILITY) || !$token->tokenable instanceof Shop) {
            return false;
        }

        $shopSlug = $request->input('shop');

        if (filled($shopSlug) && $shopSlug !== $token->tokenable->slug) {
            return false;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        $this->shop = $token->tokenable;

        return true;
    }

    public function rules(): array
    {
        return [
            'shop'                    => ['sometimes', 'string'],
            'source'                  => ['required', Rule::in(TrafficSourcesTypeEnum::values())],
            'currency'                => ['required', 'string', 'size:3'],
            'costs'                   => ['required', 'array', 'min:1'],
            'costs.*.date'            => ['required', 'date_format:Y-m-d'],
            'costs.*.amount'          => ['required', 'numeric', 'min:0'],
            'costs.*.campaign'        => ['nullable', 'string', 'max:255'],
            'costs.*.campaign_name'   => ['nullable', 'string', 'max:255'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $request->validate($this->rules());

        $trafficSource = TrafficSource::where('shop_id', $this->shop->id)
            ->where('type', $request->input('source'))
            ->first();

        if (!$trafficSource) {
            abort(422, "Shop '{$this->shop->slug}' has no '{$request->input('source')}' traffic source.");
        }

        $currency = Currency::where('code', strtoupper((string) $request->input('currency')))->first();

        if (!$currency) {
            abort(422, "Unknown currency '{$request->input('currency')}'.");
        }

        $stored = 0;
        $errors = [];

        foreach ($request->input('costs') as $index => $cost) {
            try {
                StoreTrafficSourceCost::run($trafficSource, [
                    'date'                       => Arr::get($cost, 'date'),
                    'source_amount'              => (float) Arr::get($cost, 'amount'),
                    'source_currency_id'         => $currency->id,
                    'traffic_source_campaign_id' => $this->campaignId($trafficSource, $cost),
                ]);
                $stored++;
            } catch (\Throwable $e) {
                /* One unusable line must not cost the caller the rest of the day's spend: the script
                   posts the whole account in one request and cannot retry a subset. */
                $errors[] = ['index' => $index, 'message' => $e->getMessage()];
                Log::warning('Traffic source cost webhook row failed', [
                    'shop'  => $this->shop->slug,
                    'cost'  => $cost,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'shop'   => $this->shop->slug,
            'source' => $request->input('source'),
            'stored' => $stored,
            'errors' => $errors,
        ];
    }

    /**
     * Campaign references are the ad platform's own numeric campaign ids, which is exactly what the
     * touch history stores, so no mapping table is needed. A campaign that has had spend but no click
     * yet has no row, hence firstOrCreate; `reference` is globally unique, so a reference already
     * claimed by another shop's source falls back to the source-level total rather than mis-crediting.
     */
    private function campaignId(TrafficSource $trafficSource, array $cost): ?int
    {
        $reference = Arr::get($cost, 'campaign');

        if (blank($reference)) {
            return null;
        }

        $campaign = TrafficSourceCampaign::where('reference', $reference)->first();

        if ($campaign) {
            return $campaign->traffic_source_id === $trafficSource->id ? $campaign->id : null;
        }

        return TrafficSourceCampaign::create([
            'traffic_source_id' => $trafficSource->id,
            'reference'         => $reference,
            'name'              => Arr::get($cost, 'campaign_name') ?: $reference,
            'type'              => $trafficSource->type,
        ])->id;
    }
}
