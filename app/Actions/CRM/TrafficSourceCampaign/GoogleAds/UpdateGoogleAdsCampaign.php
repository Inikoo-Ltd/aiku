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
 * Pushes a campaign's status or daily budget back to Google Ads, and records who did it.
 *
 * Google is changed first and the local row only afterwards, so a rejected write leaves Aiku saying
 * exactly what the account still says. The reverse order would show a budget that was never applied
 * until the next nightly fetch quietly took it away again.
 *
 * The local row is written from the same values rather than re-read from Google: a mutate response
 * reflects the account within milliseconds of the change, whereas `primary_status` can take minutes
 * to catch up, so a re-read here would often hand back the state we just replaced. The nightly fetch
 * is what reconciles.
 */
class UpdateGoogleAdsCampaign extends OrgAction
{
    use WithGoogleAdsWriteErrors;

    /**
     * REMOVED is deliberately absent. It cannot be undone through the API or the Google UI, and a
     * campaign nobody wants running is served just as well by PAUSED, which keeps its history
     * readable on this page.
     */
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

        $data   = $campaign->data ?? [];
        $status = Arr::get($modelData, 'status');
        $budget = Arr::get($modelData, 'budget_amount');

        $auditOld = [];
        $auditNew = [];

        /* A refusal from Google is an ordinary answer, not a server fault: the budget is under the
           account minimum, the campaign is already removed. It belongs on the control that caused it. */
        try {
            if ($status !== null && $status !== Arr::get($data, 'status')) {
                $client->mutate('campaigns', [[
                    'update' => [
                        'resourceName' => "customers/{$client->customerId()}/campaigns/{$campaign->reference}",
                        'status'       => $status,
                    ],
                    'updateMask' => 'status',
                ]]);

                $auditOld['data.status'] = Arr::get($data, 'status');
                $auditNew['data.status'] = $status;
                $data['status']          = $status;

                /* Google recomputes primary_status asynchronously, so whatever is stored is now a claim
                   about the old status. Dropping it makes the page fall back to `status`, which is the
                   honest thing to show until the next fetch. */
                unset($data['primary_status'], $data['primary_status_reasons']);
            }

            if ($budget !== null && (float) $budget !== (float) Arr::get($data, 'budget_amount')) {
                $this->pushBudget($client, $data, (float) $budget);

                $auditOld['data.budget_amount'] = Arr::get($data, 'budget_amount');
                $auditNew['data.budget_amount'] = (float) $budget;
                $data['budget_amount']          = (float) $budget;
            }
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, $budget !== null ? 'budget_amount' : 'status');
        }

        if ($auditOld === []) {
            return $campaign;
        }

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
     * @throws GoogleAdsException
     */
    private function pushBudget(GoogleAdsClient $client, array $data, float $amount): void
    {
        $budgetId = Arr::get($data, 'budget_id');

        if (blank($budgetId)) {
            throw ValidationException::withMessages([
                'budget_amount' => __('This campaign has no budget on record yet. Run the Google Ads fetch and try again.'),
            ]);
        }

        if (Arr::get($data, 'budget_is_shared')) {
            throw ValidationException::withMessages([
                'budget_amount' => __('This budget is shared with other campaigns, so changing it here would change them too. Edit it in Google Ads instead.'),
            ]);
        }

        /* Google takes budgets in micros, a millionth of the account's currency unit, and rejects a
           fractional one outright rather than rounding it. */
        $client->mutate('campaignBudgets', [[
            'update' => [
                'resourceName' => "customers/{$client->customerId()}/campaignBudgets/{$budgetId}",
                'amountMicros' => (string) (int) round($amount * 1_000_000),
            ],
            'updateMask' => 'amountMicros',
        ]]);
    }

    public function rules(): array
    {
        return [
            'status'        => ['sometimes', 'nullable', Rule::in(self::STATUSES)],
            'budget_amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
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

        /* Reaching this route already means the user can open the shop's marketing pages, which is
           the bar this was asked to sit at. Any narrower rule belongs here, in one place: pushing a
           budget change spends real money the moment Google accepts it. */

        $this->initialisationFromShop($shop, $request);

        return $this->handle($trafficSourceCampaign, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
