<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\UI;

use App\Actions\GrpAction;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrganisation extends GrpAction
{
    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("sysadmin.view");
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($organisation);
    }


    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {

        return Inertia::render("EditModel", [
            "title"       => __("Organisation"),
            "breadcrumbs" => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            "pageHead" => [
                "title"   => $organisation->name,
                "actions" => [
                    [
                        "type"  => "button",
                        "style" => "exitEdit",
                        "route" => [
                            "name"       => preg_replace('/edit$/', "show", $request->route()->getName()),
                            "parameters" => array_values($request->route()->originalParameters()),
                        ],
                    ],
                ],
            ],


            "formData" => [
                "blueprint" => [
                    [
                        "label"   => __("Details"),
                        "title"   => __("Id"),
                        "icon"    => "fal fa-fingerprint",
                        "current" => true,
                        "fields"  => [
                            "name" => [
                                "type"        => "input",
                                "label"       => __("name"),
                                "value"       => $organisation->name ?? '',
                            ],
                            "ui_name" => [
                                "type"  => "input",
                                "label" => __("UI display name"),
                                "value" => Arr::get($organisation->settings, 'ui.name', $organisation->name)
                            ],
                            "contact_name" => [
                                "type"  => "input",
                                "label" => __("Contact name"),
                                "value" => $organisation->contact_name
                            ],
                            "email" => [
                                "type"        => "input",
                                "label"       => __("email"),
                                "value"       => $organisation->email ?? '',
                            ],
                            "phone" => [
                                "type"        => "input",
                                "label"       => __("phone"),
                                "value"       => $organisation->phone ?? '',
                            ],
                            'address' => [
                                'type'    => 'address',
                                'label'   => __('Address'),
                                'value'   => AddressFormFieldsResource::make($organisation->address)->getArray(),
                                'options' => [
                                    'countriesAddressData' => GetAddressData::run()
                                ]
                            ],
                            "image" => [
                                "type"  => "avatar",
                                "label" => __("Logo"),
                                "value" => $organisation->imageSources(320, 320)
                            ],
                        ],
                    ],
                ],
                "args" => [
                    "updateRoute" => [
                        "name"       => "grp.models.organisation.update",
                        "parameters" => [$organisation->id],
                    ],
                ],
            ],
        ]);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowOrganisation::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', "show", $routeName),
            routeParameters: $routeParameters,
            suffix: "(" . __("editing") . ")"
        );
    }
}
