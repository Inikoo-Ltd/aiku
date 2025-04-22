<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Contacter\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Contacter;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowContacter extends OrgAction
{
    // use WithContactersSubNavigation;
    use WithCRMAuthorisation;

    private Shop $parent;

    public function handle(Contacter $contacter): Contacter
    {
        return $contacter;
    }

    public function asController(Organisation $organisation, Shop $shop, Contacter $contacter, ActionRequest $request): Contacter
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($contacter);
    }

    public function htmlResponse(Contacter $contacter, ActionRequest $request): Response
    {
        // dd($collection->stats);
        // $subNavigation = null;
        // if ($this->parent instanceof Shop) {
        //     $subNavigation = $this->getSubNavigation($request);
        // }
        return Inertia::render(
            'Org/Shop/CRM/Contacter',
            [
                'title'       => __('contacter'),
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $request->route()->getName(),
                //     $request->route()->originalParameters()
                // ),
                'pageHead'    => [
                    'title'     => $contacter->name,
                    'model'     => __('contacter'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-user-plus'],
                            'title' => __('contacter')
                        ],
                    // 'subNavigation' => $subNavigation,
                ],
                // 'tabs' => [
                //     'current'    => $this->tab,
                //     'navigation' => ContacterTabsEnum::navigation()
                // ],
            ]
        );
    }

    // public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    // {
    //     $headCrumb = function (Contacter $contacter, array $routeParameters, $suffix) {
    //         return [

    //             [
    //                 'type'           => 'modelWithIndex',
    //                 'modelWithIndex' => [
    //                     'index' => [
    //                         'route' => $routeParameters['index'],
    //                         'label' => __('Contacters')
    //                     ],
    //                     'model' => [
    //                         'route' => $routeParameters['model'],
    //                         'label' => $contacter->slug,
    //                     ],
    //                 ],
    //                 'suffix'         => $suffix,

    //             ],

    //         ];
    //     };

    //     $contacter = Contacter::where('slug', $routeParameters['contacter'])->first();

    //     return match ($routeName) {
    //         'grp.org.shops.show.crm.contacters.show' =>
    //         array_merge(
    //             IndexContacters::make()->getBreadcrumbs('grp.org.shops.show.crm.contacters.index', $routeParameters),
    //             $headCrumb(
    //                 $contacter,
    //                 [
    //                     'index' => [
    //                         'name'       => 'grp.org.shops.show.crm.contacters.index',
    //                         'parameters' => $routeParameters
    //                     ],
    //                     'model' => [
    //                         'name'       => 'grp.org.shops.show.crm.contacters.show',
    //                         'parameters' => $routeParameters
    //                     ]
    //                 ],
    //                 $suffix
    //             )
    //         ),
    //         default => []
    //     };
    // }
}
