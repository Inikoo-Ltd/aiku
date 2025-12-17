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
use Spatie\LaravelOptions\Options;

class EditRedirect extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Redirect $redirect, ActionRequest $request): Response
    {

        $title = __('Edit Redirect');
        return Inertia::render(
            'CreateModel',
            [
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $request->route()->originalParameters()
                // ),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
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
                                        'options'  => Options::forEnum(RedirectTypeEnum::class),
                                        'value'    => $redirect->type
                                    ],
                                    'path' => [
                                        'type'     => 'input',
                                        'label'    => __('path'),
                                        'value'    => $redirect->path
                                    ],
                                ]
                            ]
                        ],
                    'route'      => [
                        'name' => 'grp.models.redirect.update',
                        'parameters' => [
                            'redirect' => $redirect->id
                        ]
                    ]
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
