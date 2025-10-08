<?php

/*
 * author Arya Permana - Kirin
 * created on 10-03-2025-11h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Prospect\UI;

use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Enums\UI\CRM\ProspectTabsEnum;
use App\Http\Resources\CRM\ProspectsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Lead\ProspectResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowProspect extends OrgAction
{
    use WithProspectsSubNavigation;
    use WithCRMAuthorisation;


    public function handle(Prospect $prospect): Prospect
    {
        return $prospect;
    }

    public function asController(Organisation $organisation, Shop $shop, Prospect $prospect, ActionRequest $request): Prospect
    {

        $this->initialisationFromShop($shop, $request)->withTab(ProspectTabsEnum::values());
        return $this->handle($prospect);
    }

    public function htmlResponse(Prospect $prospect, ActionRequest $request): Response
    {
        $shop = $prospect->shop;
        $subNavigation = $this->getSubNavigation($shop, $request);
        return Inertia::render(
            'Org/Shop/CRM/Prospect',
            [
                'title'       => __('Prospect').' '.$prospect->name,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $prospect->name,
                    'model'     => __('Prospect'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-user-plus'],
                            'title' => __('Prospect')
                        ],
                    'subNavigation' => $subNavigation,
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProspectTabsEnum::navigation()
                ],

                ProspectTabsEnum::SHOWCASE->value => $this->tab == ProspectTabsEnum::SHOWCASE->value ?
                    fn () => $this->getProspectShowcase($prospect)
                    : Inertia::lazy(fn () => $this->getProspectShowcase($prospect)),

                ProspectTabsEnum::DISPATCHED_EMAILS->value => $this->tab == ProspectTabsEnum::DISPATCHED_EMAILS->value ?
                    fn () => ProspectsResource::collection(IndexDispatchedEmails::run($prospect, ProspectTabsEnum::DISPATCHED_EMAILS->value))
                    : Inertia::lazy(fn () => ProspectsResource::collection(IndexDispatchedEmails::run($prospect, ProspectTabsEnum::DISPATCHED_EMAILS->value))),

                ProspectTabsEnum::HISTORY->value => $this->tab == ProspectTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($prospect, ProspectTabsEnum::HISTORY->value))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($prospect, ProspectTabsEnum::HISTORY->value))),

            ]
        )->table(
                IndexDispatchedEmails::make()->tableStructure(
                    parent: $prospect,
                    prefix: ProspectTabsEnum::DISPATCHED_EMAILS->value
                )
        )->table(
                IndexHistory::make()->tableStructure(
                    prefix: ProspectTabsEnum::HISTORY->value
                )
        );
    }


    public function getProspectShowcase(Prospect $prospect): array
    {
        return [
            'prospect' => ProspectResource::make($prospect)->getArray(),
            'update_route' => [
                'name' => 'grp.models.prospect.update',
                'parameters' => [
                    'prospect' => $prospect->id
                ],
                'method' => 'patch'
            ]
        ];
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Prospect $prospect, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Prospects')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $prospect->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $prospect = Prospect::where('slug', $routeParameters['prospect'])->first();

        return match ($routeName) {
            'grp.org.shops.show.crm.prospects.show' =>
            array_merge(
                IndexProspects::make()->getBreadcrumbs('grp.org.shops.show.crm.prospects.index', $routeParameters),
                $headCrumb(
                    $prospect,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.prospects.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.prospects.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
