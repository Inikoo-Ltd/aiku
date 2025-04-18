<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Jul 2024 23:57:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\GoogleDrive\Traits\WithTokenPath;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrganisationSettings extends OrgAction
{
    use WithTokenPath;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(
            [
                'organisations.edit',
                'org-admin.'.$this->organisation->id
            ]
        );
    }


    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $organisation;
    }



    public function htmlResponse(Organisation $organisation): Response
    {

        $title = __('Organisation settings');

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                ],
                "formData" => [
                    "blueprint" => [
                        [
                            "label"  => __("details"),
                            "icon"   => "fa-light fa-fingerprint",
                            "fields" => [
                                "name" => [
                                    "type"  => "input",
                                    "label" => __("Name"),
                                    "value" => $organisation->name,
                                ],
                                "contact_name" => [
                                    "type"  => "input",
                                    "label" => __("Contact name"),
                                    "value" => $organisation->contact_name
                                ],
                                "email" => [
                                    "type"  => "input",
                                    "label" => __("Email"),
                                    "value" => $organisation->email
                                ],
                                "phone" => [
                                    "type"  => "input",
                                    "label" => __("Phone"),
                                    "value" => $organisation->phone
                                ],
                                'address' => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressFormFieldsResource::make($organisation->address)->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ]
                                ],
                            ],
                        ],
                        [
                            "label"  => __("branding"),
                            "icon"   => "fa-light fa-copyright",
                            "fields" => [
                                "ui_name" => [
                                    "type"  => "input",
                                    "label" => __("UI display name"),
                                    "value" => Arr::get($organisation->settings, 'ui.name', $organisation->name)
                                ],
                                "logo" => [
                                    "type"  => "avatar",
                                    "label" => __("logo"),
                                    "value" => $organisation->imageSources(320, 320),
                                ],
                            ],
                        ],
                        [
                            "label"  => __("Invoice formats"),
                            "icon"   => "fa-light fa-file-invoice",
                            "fields" => [
                                'show_isdoc' => [
                                    'type'  => 'toggle',
                                    'label' => __('Show ISDoc invoice'),
                                    'value' => Arr::get($organisation->settings, 'invoice_export.show_isdoc', false),
                                ],
                                'show_omega' => [
                                    'type'  => 'toggle',
                                    'label' => __('Show Omega'),
                                    'value' => Arr::get($organisation->settings, 'invoice_export.show_omega', false),
                                ],
                            ],
                        ],
                        [
                            "label"  => __("google drive"),
                            "icon"   => "fab fa-google",
                            "button" => [
                                "title"   => !file_exists($this->getTokenPath()) ? "Authorize" : "Authorized",
                                "route"   => [
                                    'name'       => 'grp.models.org.google_drive.authorize',
                                    'parameters' => [$organisation->id]
                                ],
                                "disable" => file_exists($this->getTokenPath())
                            ],

                            "fields" => [
                                "google_client_id" => [
                                    "type"  => "password",
                                    "label" => __("client ID"),
                                    "value" => Arr::get($organisation->settings, 'google.id'),
                                    "use_generate_password" => false
                                ],
                                "google_client_secret" => [
                                    "type"  => "password",
                                    "label" => __("client secret"),
                                    "value" => Arr::get($organisation->settings, 'google.secret'),
                                    "use_generate_password" => false
                                ],
                                "google_drive_folder_key" => [
                                    "type"  => "password",
                                    "label" => __("google drive folder key"),
                                    "value" => Arr::get($organisation->settings, 'google.drive.folder'),
                                    "use_generate_password" => false
                                ],
                                "google_redirect_uri" => [
                                    "type"       => "input",
                                    "label"      => __("google redirect URI"),
                                    "value"      => url('/'),
                                    "readonly"   => true,
                                    "copyButton" => true,
                                ]
                            ],

                        ],

                    ],
                    "args"      => [
                        "updateRoute" => [
                            "name"       => "grp.models.org.settings.update",
                            "parameters" => [$organisation->id],
                        ],
                    ],
                ],


            ]
        );
    }



    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.settings.edit',
                                'parameters' => [$this->organisation->slug]
                            ],
                            'label'  => __('Organisation settings'),
                        ]
                    ]
                ]
            );
    }
}
