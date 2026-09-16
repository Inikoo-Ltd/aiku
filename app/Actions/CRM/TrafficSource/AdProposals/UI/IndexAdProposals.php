<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\AdProposalStateEnum;
use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceAdProposal;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The suggestions waiting on a marketer.
 *
 * A queue rather than a chat box, because a conversation with no opening line is a blank page and
 * nobody knows what to ask an empty prompt. Here the work arrives already framed: this is the change,
 * this is what it is based on, do it or do not.
 */
class IndexAdProposals extends OrgAction
{
    public function handle(Shop $shop): Collection
    {
        return TrafficSourceAdProposal::with('trafficSourceCampaign')
            ->where('shop_id', $shop->id)
            ->open()
            ->orderByDesc('amount')
            ->orderByDesc('id')
            ->get();
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(Collection $proposals, ActionRequest $request): Response
    {
        $parameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Shop/CRM/AdProposals',
            [
                'breadcrumbs' => array_merge(
                    ShowShop::make()->getBreadcrumbs($parameters),
                    [[
                        'type'   => 'simple',
                        'simple' => [
                            'route' => ['name' => 'grp.org.shops.show.marketing.ad_proposals.index', 'parameters' => $parameters],
                            'label' => __('Suggestions'),
                            'icon'  => 'fal fa-lightbulb',
                        ],
                    ]],
                ),
                'title'    => __('Suggestions'),
                'pageHead' => [
                    'title' => $this->shop->name,
                    'icon'  => ['icon' => 'fal fa-lightbulb', 'title' => __('Suggestions')],
                    'model' => __('Marketing'),
                ],

                'proposals' => $proposals->map(fn (TrafficSourceAdProposal $proposal) => [
                    'id'        => $proposal->id,
                    'type'      => $proposal->type->value,
                    'type_label' => AdProposalTypeEnum::labels()[$proposal->type->value],
                    'term'      => data_get($proposal->evidence, 'term'),
                    'campaign'  => $proposal->trafficSourceCampaign?->name,
                    'rationale' => $proposal->rationale,
                    'evidence'  => $proposal->evidence,
                    'amount'    => (float) $proposal->amount,
                    'match_type' => data_get($proposal->payload, 'match_type'),

                    /* Null when the campaign has more than one ad group, and the card then has to ask:
                       a keyword in the wrong ad group spends against the wrong ads. */
                    'ad_group_id' => data_get($proposal->payload, 'ad_group_id'),
                    'ad_groups'   => data_get($proposal->evidence, 'ad_groups', []),

                    /* Drafted copy, which a marketer strikes through rather than accepts wholesale. */
                    'headlines'          => data_get($proposal->payload, 'headlines', []),
                    'existing_headlines' => data_get($proposal->evidence, 'headlines', []),

                    /* A draft opens the campaign form instead of applying; the suggestion carries what
                       it knows and a person finishes it. */
                    'is_draft'     => $proposal->type->isDraft(),
                    'draft_route'  => $proposal->type->isDraft() ? [
                        'name'       => 'grp.org.shops.show.marketing.google_ads.create',
                        'parameters' => array_merge($parameters, [
                            'name'     => data_get($proposal->payload, 'name'),
                            'keywords' => implode("\n", data_get($proposal->payload, 'keywords', [])),
                        ]),
                    ] : null,

                    'apply_route' => [
                        'name'       => 'grp.models.org.shop.ad_proposal.apply',
                        'parameters' => [
                            'organisation'            => $this->organisation->id,
                            'shop'                    => $this->shop->id,
                            'trafficSourceAdProposal' => $proposal->id,
                        ],
                    ],
                    'dismiss_route' => [
                        'name'       => 'grp.models.org.shop.ad_proposal.dismiss',
                        'parameters' => [
                            'organisation'            => $this->organisation->id,
                            'shop'                    => $this->shop->id,
                            'trafficSourceAdProposal' => $proposal->id,
                        ],
                    ],
                ])->values(),

                'currency' => $this->shop->currency->code,
                'stats'    => $this->stats($this->shop),
            ]
        );
    }

    /**
     * What has already been decided, so the page can say the queue has been worked rather than just
     * showing nothing and leaving somebody wondering whether it is broken.
     */
    private function stats(Shop $shop): array
    {
        $counts = TrafficSourceAdProposal::where('shop_id', $shop->id)
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        return [
            'applied'    => (int) $counts->get(AdProposalStateEnum::APPLIED->value, 0),
            'dismissed'  => (int) $counts->get(AdProposalStateEnum::DISMISSED->value, 0),
            'last_run_at' => TrafficSourceAdProposal::where('shop_id', $shop->id)->max('created_at'),
        ];
    }
}
