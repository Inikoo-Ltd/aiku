<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use App\Enums\UI\Masters\CompetitorTabsEnum;
use App\Http\Resources\Masters\CompetitorProductsResource;
use App\Http\Resources\Masters\CompetitorsResource;
use App\Http\Resources\Masters\MasterAssetCompetitorProductsResource;
use App\Models\Masters\Competitor;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCompetitor extends OrgAction
{
    use WithMastersAuthorisation;
    use WithCompetitorsSubNavigation;

    public function handle(Competitor $competitor): Competitor
    {
        return $competitor;
    }

    public function asController(Competitor $competitor, ActionRequest $request): Competitor
    {
        $this->initialisationFromGroup(group(), $request)->withTab(CompetitorTabsEnum::values());

        return $this->handle($competitor);
    }

    public function htmlResponse(Competitor $competitor, ActionRequest $request): Response
    {
        return Inertia::render(
            'Masters/Competitor',
            [
                'title'       => __('Competitor').': '.$competitor->name,
                'breadcrumbs' => array_merge(
                    $this->getCompetitorsBreadcrumbs('grp.masters.competitors.index', __('Competitors')),
                    [['type' => 'simple', 'simple' => ['label' => $competitor->name]]]
                ),
                'pageHead'    => [
                    'title'         => $competitor->name,
                    'model'         => __('Competitor'),
                    'icon'          => ['icon' => ['fal', 'fa-binoculars'], 'title' => __('Competitor')],
                    'subNavigation' => $this->getCompetitorsSubNavigation($this->group),
                    'actions'       => $this->canEdit ? [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => ['name' => 'grp.masters.competitors.edit', 'parameters' => ['competitor' => $competitor->id]],
                        ],
                    ] : [],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => CompetitorTabsEnum::navigation(),
                ],
                'competitor'  => [
                    'name'          => $competitor->name,
                    'website'       => $competitor->website,
                    'master_shop'   => ['name' => $competitor->masterShop->name, 'slug' => $competitor->masterShop->slug],
                    'sells_to'      => CompetitorSellsToEnum::labels()[$competitor->sells_to->value],
                    'currency_code' => $competitor->currency->code,
                    'read_method'   => CompetitorsResource::readMethod($competitor->feed_url, (bool) $competitor->search_url),
                    'has_login'     => (bool) $competitor->username,
                    'status'        => $competitor->status?->value,
                    'last_error'    => $competitor->last_error,
                    'fetched_at'    => $competitor->fetched_at,
                    'number_products' => $competitor->number_products,
                ],
                'can_edit'    => $this->canEdit,

                CompetitorTabsEnum::MATCHES->value => $this->tab == CompetitorTabsEnum::MATCHES->value
                    ? fn () => MasterAssetCompetitorProductsResource::collection(IndexMasterAssetCompetitorProducts::run($competitor, 'all', CompetitorTabsEnum::MATCHES->value))
                    : Inertia::optional(fn () => MasterAssetCompetitorProductsResource::collection(IndexMasterAssetCompetitorProducts::run($competitor, 'all', CompetitorTabsEnum::MATCHES->value))),

                CompetitorTabsEnum::PRODUCTS->value => $this->tab == CompetitorTabsEnum::PRODUCTS->value
                    ? fn () => CompetitorProductsResource::collection(IndexCompetitorProducts::run($competitor, CompetitorTabsEnum::PRODUCTS->value))
                    : Inertia::optional(fn () => CompetitorProductsResource::collection(IndexCompetitorProducts::run($competitor, CompetitorTabsEnum::PRODUCTS->value))),
            ]
        )->table(IndexMasterAssetCompetitorProducts::make()->tableStructure(CompetitorTabsEnum::MATCHES->value, withMasterShop: false))
            ->table(IndexCompetitorProducts::make()->tableStructure(CompetitorTabsEnum::PRODUCTS->value));
    }
}
