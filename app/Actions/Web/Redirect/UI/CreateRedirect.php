<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-11h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Redirect\UI;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\UI\ShowWebpage;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateRedirect extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Website|Webpage $parent, ActionRequest $request): Response
    {
        if ($parent instanceof Website) {
            $route = [
                'name'       => 'grp.models.website.redirect.store',
                'parameters' => [
                    'website' => $parent->id,
                ]
            ];
        } else {
            $route = [
                'name'       => 'grp.models.webpage.redirect.store',
                'parameters' => [
                    'webpage' => $parent->id
                ]
            ];
        }

        $title = __('New Redirect');

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'       => [
                        'icon'  => ['fal', 'fa-terminal'],
                        'title' => __("Webpage Redirect")
                    ],
                    'iconRight'  => $parent instanceof Webpage ? WebpageStateEnum::stateIcon()[$parent->state->value] : [],
                    'afterTitle' => [
                        'label' => $parent->getUrl(),
                    ],
                    'model'      => class_basename($parent),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit create redirect'),
                            'route' => [
                                'name'       => preg_replace('/redirect.create$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
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
                                    'required' => true,
                                    'options'  => Options::forEnum(RedirectTypeEnum::class),
                                ],
                                'from_url' => [
                                    'type'     => 'input',
                                    'label'    => __('From URL'),
                                    'required' => true
                                ],
                                'placeholder'   => [
                                    'type'     => 'input',
                                    'label'    => __('To URL'),
                                    'value'    => $parent->getUrl(),
                                    'readonly' => true,
                                    'disabled' => true,
                                ]
                            ]
                        ]
                    ],
                    'route'      => $route,
                    'additionalSubmitButton' => [
                        'label' => 'Save & Create Another One',
                        'param' => [
                            'disableReload' => true
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
    public function inWebpage(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($webpage, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Exception
     */
    public function inWebpageInFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($webpage, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowWebpage::make()->getBreadcrumbs(
                routeName: $routeName,
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Redirect'),
                    ]
                ]
            ]
        );
    }
}
