<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Feb 2024 11:17:33 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Profile;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\CRM\WebUser;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaProfile extends RetinaAction
{
    public function asController(ActionRequest $request): WebUser
    {
        return $request->user();
    }

    public function htmlResponse(WebUser $webUser, ActionRequest $request): Response
    {
        $customer = $webUser->customer;

        $personalInformationFields = [
            'contact_name' => [
                'type'  => 'input',
                'label' => __('Contact name'),
                'value' => $customer->contact_name
            ],
            'email'        => [
                'type'  => 'input',
                'label' => __('Email'),
                'value' => $customer->email
            ],
            'about'        => [
                'type'  => 'textarea',
                'label' => __('about'),
                'value' => $customer->about
            ],
            'image'        => [
                'type'  => 'avatar',
                'label' => __('avatar'),
                'value' => !blank($webUser->image_id)
                    ? $customer->imageSources(320, 320)
                    : [
                        'original' => '/retina-default-user.svg'
                    ],
            ],
        ];

        if ($webUser->is_root) {
            unset($personalInformationFields['email']);
        }


        $sections["properties"] = [
            "label"  => __("Personal information"),
            "icon"   => "fal fa-user-circle",
            "fields" => $personalInformationFields,
        ];

        $sections["credentials"] = [
            "label"  => __("Username/Password"),
            "icon"   => "fal fa-key",
            "fields" => [
                "username" => [
                    "type"  => "input",
                    "label" => __("username"),
                    "value" => $webUser->username,
                ],
                "password" => [
                    "type"  => "password",
                    "label" => __("password"),
                    "value" => "",
                ],
            ],
        ];

        if ($webUser->is_root) {
            $sections["customer"] = [
                "label"  => __("Account details"),
                "icon"   => "fal fa-user",
                "fields" => [
                    'company_name'    => [
                        'type'  => 'input',
                        'label' => __('Company'),
                        'value' => $customer->company_name
                    ],
                    'email'           => [
                        'type'  => 'input',
                        'label' => __('Email'),
                        'value' => $customer->email
                    ],
                    'phone'           => [
                        'type'  => 'phone',
                        'label' => __('Phone'),
                        'value' => $customer->phone
                    ],
                    'contact_address' => [
                        'type'    => 'address',
                        'label'   => __('Address'),
                        'value'   => AddressFormFieldsResource::make($customer->address)->getArray(),
                        'options' => [
                            'countriesAddressData' => GetAddressData::run()
                        ]
                    ]
                ],
            ];
        }



        $currentSection = "properties";
        if ($request->has("section") && Arr::has($sections, $request->input("section"))) {
            $currentSection = $request->input("section");
        }

        return Inertia::render("EditModel", [
            "title"       => __("Profile"),
            "breadcrumbs" => $this->getBreadcrumbs(),
            "pageHead"    => [
                "title" => __("My Profile"),
            ],

            "formData" => [
                "current"   => $currentSection,
                "blueprint" => $sections,
                "args"      => [
                    "updateRoute" => [
                        "name" => "retina.models.profile.update",
                    ],
                ],
            ],
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(ShowRetinaDashboard::make()->getBreadcrumbs(), [
            [
                "type"   => "simple",
                "simple" => [
                    "route" => [
                        "name" => "retina.profile.show",
                    ],
                    "label" => __("My profile"),
                ],
            ],
        ]);
    }
}
