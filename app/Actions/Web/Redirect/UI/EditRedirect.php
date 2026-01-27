<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-13h-11m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Redirect\UI;

use App\Actions\OrgAction;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditRedirect extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Redirect $redirect, ActionRequest $request): Response
    {
        $title = __('Edit Redirect');

        $webpage = Webpage::find($redirect->to_webpage_id);

        return Inertia::render(
            'EditModel',
            [
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $request->route()->originalParameters()
                // ),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => 'fal fa-terminal',
                    'model' => $title,
                    'title' => $redirect->from_url,
                    // 'actions'   => [
                    //     [
                    //         'type'  => 'button',
                    //         'style' => 'exitEdit',
                    //         'route' => [
                    //             'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                    //             'parameters' => array_values($request->route()->originalParameters())
                    //         ]
                    //     ]
                    // ],
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => $title,
                                'fields' => [
                                    'type' => [
                                        'type'     => 'select',
                                        'label'    => __('Type'),
                                        'options'  => collect(RedirectTypeEnum::cases())->map(fn ($case) => [
                                            'label' => $case->label(),
                                            'value' => $case->value,
                                        ])->values()->toArray(),
                                        'required'  => true,
                                        'value'    => $redirect->type
                                    ],
                                    // 'path' => [
                                    //     'type'     => 'input',
                                    //     'label'    => __('path'),
                                    //     'value'    => $redirect->path
                                    // ],
                                    'to_webpage_id' => [
                                        'type'     => 'select_infinite',
                                        'fetchRoute'    => [
                                            'name'  => 'grp.json.active_webpages.index',
                                            'parameters' => [
                                                'shop'  => $redirect->shop->slug
                                            ]
                                        ],
                                        'options'   => $webpage ? [
                                            [
                                                'code'          => $webpage->code,
                                                'canonical_url' => $webpage->canonical_url,
                                                'id'            => $redirect->to_webpage_id
                                            ]
                                        ] : [],
                                        'labelProp'             => 'code',
                                        'labelAdditionalProp'   => 'canonical_url',
                                        'valueProp'             => 'id',
                                        'label'                 => __('Target URL'),
                                        'value'                 => $redirect->to_webpage_id
                                    ],
                                ]
                            ]
                        ],
                    'args'      => [
                        'updateRoute' => [
                            'name' => 'grp.models.redirect.update',
                            'parameters' => [
                                'redirect' => $redirect->id
                            ]
                        ],
                    ],
                ],

            ]
        );
    }

    /**
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     */
    public function inWebpage(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, Redirect $redirect, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($redirect, $request);
    }

    /**
     * @throws \Exception
     * @noinspection PhpUnusedParameterInspection
     */
    public function inWebpageInFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, Redirect $redirect, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($redirect, $request);
    }

    public function inWebsite(Organisation $organisation, Shop $shop, Website $website, Redirect $redirect, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($redirect, $request);
    }

    public function inWebsiteInFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Redirect $redirect, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($redirect, $request);
    }



}
