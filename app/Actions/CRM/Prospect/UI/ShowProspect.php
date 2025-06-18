<?php

/*
 * author Arya Permana - Kirin
 * created on 10-03-2025-11h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Prospect\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Enums\UI\CRM\ProspectTabsEnum;
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

    private Shop $parent;

    public function handle(Prospect $prospect): Prospect
    {
        return $prospect;
    }

    public function asController(Organisation $organisation, Shop $shop, Prospect $prospect, ActionRequest $request): Prospect
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($prospect);
    }

    public function htmlResponse(Prospect $prospect, ActionRequest $request): Response
    {
        $subNavigation = null;
        if ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigation($request);
        }
        return Inertia::render(
            'Org/Shop/CRM/Prospect',
            [
                'title'       => __('collection'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $prospect->name,
                    'model'     => __('prospect'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-user-plus'],
                            'title' => __('prospect')
                        ],
                    'subNavigation' => $subNavigation,
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProspectTabsEnum::navigation()
                ],
            ]
        );
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
