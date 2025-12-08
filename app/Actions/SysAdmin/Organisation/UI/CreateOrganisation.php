<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\UI;

use App\Actions\InertiaAction;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;
use App\Models\Helpers\Timezone;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateOrganisation extends InertiaAction
{
    /**
     * @throws \Exception
     */
    public function handle(): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __('New organisation'),
                'pageHead' => [
                    'title' => __('New organisation'),
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name' => 'grp.sysadmin.guests.index',
                            ],
                        ],
                    ],
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title' => __('Information'),

                            'fields' => [
                                'code' => [
                                    'type' => 'input',
                                    'label' => __('code'),
                                    'required' => true,
                                ],
                                'name' => [
                                    'type' => 'input',
                                    'label' => __('name'),
                                    'required' => true,
                                ],
                                'email' => [
                                    'type' => 'input',
                                    'label' => __('email'),
                                    'required' => true,
                                ],
                                'currency_id' => [
                                    'label' => __('currency'),
                                    'type' => 'select',
                                    'required' => true,
                                    'value' => null,
                                    'options' => Options::forModels(Currency::all()),
                                ],
                                'country_id' => [
                                    'label' => __('country'),
                                    'type' => 'select',
                                    'required' => true,
                                    'value' => null,
                                    'options' => Options::forModels(Country::all()),
                                ],
                                'language_id' => [
                                    'label' => __('language'),
                                    'type' => 'select',
                                    'required' => true,
                                    'value' => null,
                                    'options' => Options::forModels(Language::all()),
                                ],
                                'timezone_id' => [
                                    'label' => __('timezone'),
                                    'type' => 'select',
                                    'required' => true,
                                    'value' => null,
                                    'options' => Options::forModels(Timezone::all()),
                                ],

                            ],

                        ],

                    ],
                    'route' => [
                        'name' => 'grp.org.models.organisation.store',
                        'parameters' => [
                            'group' => group()->id,
                        ],

                    ],

                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('sysadmin.users.edit');
    }

    /**
     * @throws \Exception
     */
    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle();
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexOrganisations::make()->getBreadcrumbs(),
            [
                [
                    'type' => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating organisation'),
                    ],
                ],
            ]
        );
    }
}
