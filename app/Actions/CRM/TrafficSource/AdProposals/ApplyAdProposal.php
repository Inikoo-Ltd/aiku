<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\StoreGoogleAdsKeyword;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\UpdateGoogleAdsAdAssets;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\UpdateGoogleAdsCampaignElement;
use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\UpdateGoogleAdsNegativeKeywords;
use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\AdProposalStateEnum;
use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceAdProposal;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Carries out a proposal somebody has approved.
 *
 * Nothing here talks to Google. Each type hands its stored payload to the action a person would have
 * used by hand, which validates, pushes, records an audit entry naming the approver and refuses
 * anything it does not like. Approving therefore cannot do more than filling in that form could, and
 * a proposal built from a bad suggestion still cannot produce a change the manual path would reject.
 *
 * The staleness check is those same refusals rather than a re-run of the detector's query. A proposal
 * raised on Monday and approved on Friday fails on exactly the things that matter: the keyword is
 * already there, the ad group has gone, the campaign was removed. Re-running the detection query
 * would cost an API call to learn less.
 */
class ApplyAdProposal extends OrgAction
{
    /**
     * @throws GoogleAdsException
     */
    public function handle(TrafficSourceAdProposal $proposal, array $modelData = []): TrafficSourceAdProposal
    {
        if ($proposal->state !== AdProposalStateEnum::OPEN) {
            throw ValidationException::withMessages([
                'proposal' => __('This suggestion has already been dealt with.'),
            ]);
        }

        /* A new campaign is a draft, not an instruction. It has no campaign to act on and needs a
           budget, targeting and an ad before it could serve, so the card sends the marketer to the
           campaign form with what is known filled in and they finish it there. */
        if ($proposal->type->isDraft()) {
            throw ValidationException::withMessages([
                'proposal' => __('This one is a starting point rather than a change. Open it to review and create the campaign.'),
            ]);
        }

        $campaign = $proposal->trafficSourceCampaign;

        if (!$campaign) {
            return $this->settle($proposal, AdProposalStateEnum::STALE, __('The campaign it referred to is no longer here.'));
        }

        try {
            match ($proposal->type) {
                AdProposalTypeEnum::ADD_SEARCH_TERM_KEYWORD,
                AdProposalTypeEnum::ADD_DEMAND_KEYWORD => StoreGoogleAdsKeyword::make()->handle($campaign, [
                    /* The card supplies this when the campaign has more than one ad group, because a
                       keyword in the wrong one spends on the wrong ads. */
                    'ad_group_id' => Arr::get($modelData, 'ad_group_id') ?: Arr::get($proposal->payload, 'ad_group_id'),
                    'text'        => Arr::get($proposal->payload, 'text'),
                    'match_type'  => Arr::get($proposal->payload, 'match_type', 'PHRASE'),
                ]),

                AdProposalTypeEnum::EXCLUDE_SEARCH_TERM => UpdateGoogleAdsNegativeKeywords::make()->handle($campaign, [
                    'text'       => Arr::get($proposal->payload, 'text'),
                    'match_type' => Arr::get($proposal->payload, 'match_type', 'PHRASE'),
                ]),

                AdProposalTypeEnum::PAUSE_KEYWORD => UpdateGoogleAdsCampaignElement::make()->handle($campaign, $proposal->payload),

                AdProposalTypeEnum::STRENGTHEN_AD => UpdateGoogleAdsAdAssets::make()->handle($campaign, [
                    'ad_group_id' => Arr::get($proposal->payload, 'ad_group_id'),
                    'ad_id'       => Arr::get($proposal->payload, 'ad_id'),

                    /* The card lets a marketer strike out any line it drafted, so what comes back is
                       what they approved, not what was suggested. */
                    'headlines' => Arr::get($modelData, 'headlines') ?: Arr::get($proposal->payload, 'headlines', []),
                ]),

                AdProposalTypeEnum::NEW_CAMPAIGN => null,
            };
        } catch (ValidationException $exception) {
            /* The underlying action refused, which nearly always means the account has moved on since
               the suggestion was raised: the keyword exists already, the ad group was deleted. That is
               not a failure worth chasing, it is a suggestion that has expired. */
            return $this->settle(
                $proposal,
                AdProposalStateEnum::STALE,
                implode(' ', $exception->validator->errors()->all())
            );
        } catch (GoogleAdsException $exception) {
            return $this->settle($proposal, AdProposalStateEnum::FAILED, $exception->getMessage());
        }

        return $this->settle($proposal, AdProposalStateEnum::APPLIED);
    }

    private function settle(TrafficSourceAdProposal $proposal, AdProposalStateEnum $state, ?string $reason = null): TrafficSourceAdProposal
    {
        $proposal->update([
            'state'              => $state,
            'failure_reason'     => $reason,
            'decided_by_user_id' => request()->user()?->id,
            'decided_at'         => now(),
        ]);

        return $proposal->refresh();
    }

    public function rules(): array
    {
        return [
            'ad_group_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'headlines'   => ['sometimes', 'array', 'max:15'],
            'headlines.*' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
        ];
    }

    /**
     * @throws GoogleAdsException
     */
    public function asController(Organisation $organisation, Shop $shop, TrafficSourceAdProposal $trafficSourceAdProposal, ActionRequest $request): TrafficSourceAdProposal
    {
        if ($trafficSourceAdProposal->shop_id !== $shop->id) {
            throw new NotFoundHttpException();
        }

        $this->initialisationFromShop($shop, $request);

        return $this->handle($trafficSourceAdProposal, $this->validatedData);
    }

    public function htmlResponse(TrafficSourceAdProposal $proposal): RedirectResponse
    {
        return back()->with('notification', match ($proposal->state) {
            AdProposalStateEnum::APPLIED => [
                'status'      => 'success',
                'title'       => __('Applied in Google Ads'),
                'description' => __('You can pause it again from the campaign page if it does not work out.'),
            ],
            AdProposalStateEnum::STALE => [
                'status'      => 'warning',
                'title'       => __('No longer applies'),
                'description' => $proposal->failure_reason,
            ],
            default => [
                'status'      => 'error',
                'title'       => __('Google refused it'),
                'description' => $proposal->failure_reason,
            ],
        });
    }
}
