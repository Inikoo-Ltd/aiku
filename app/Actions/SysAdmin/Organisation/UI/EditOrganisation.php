<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrganisation extends OrgAction
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
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($organisation);
    }


    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $staffOptions = User::where('group_id', $organisation->group_id)
            ->where('status', true)
            ->orderBy('contact_name')
            ->get(['id', 'contact_name', 'username', 'nickname'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->chatName()]);

        $shopOptions = $organisation->shops()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($shop) => [$shop->id => ['id' => $shop->id, 'label' => $shop->name]]);

        $sellingShopOptions = $organisation->shops()
            ->where('type', '!=', ShopTypeEnum::EXTERNAL->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($shop) => ['id' => $shop->id, 'name' => $shop->name]);

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
                                ]
                            ],
                            "image" => [
                                "type"  => "avatar",
                                "label" => __("Logo"),
                                "value" => $organisation->imageSources(320, 320)
                            ],
                        ],
                    ],
                    [
                        "label"  => __("Margins"),
                        "icon"   => "fal fa-percent",
                        "fields" => [
                            "margin_break_even_pct" => [
                                "type"        => "input",
                                "label"       => __("Break-even margin (%)"),
                                "information" => __("Orders with a margin below this are flagged as unprofitable once staff, rent and other running costs are counted. Industry guideline for this kind of shop is around 30%."),
                                "value"       => Arr::get($organisation->settings, 'margins.break_even_pct', 30),
                            ],
                        ],
                    ],
                    [
                        "label"  => __("Production"),
                        "icon"   => "fal fa-flask-potion",
                        "fields" => [
                            "default_shelf_life_days" => [
                                "type"        => "input",
                                "label"       => __("Default shelf life (days)"),
                                "information" => __("Assumed shelf life of anything we make that has none set on the artefact itself. It caps how much the factory is told to make, so we do not fill the warehouse with stock that expires before it sells."),
                                "value"       => Arr::get($organisation->settings, 'make_queue.default_shelf_life_days', 365),
                            ],
                        ],
                    ],
                    [
                        "label"  => __("Procurement"),
                        "icon"   => "fal fa-box-usd",
                        "fields" => [
                            "procurement_shop_id" => [
                                "type"        => "select",
                                "label"       => __("Procurement shop"),
                                "information" => __("Shop whose catalogue partner organisations browse when buying intercompany. Leave empty to disable the Browse tab."),
                                "placeholder" => __("Select a shop"),
                                "options"     => $shopOptions,
                                "value"       => Arr::get($organisation->settings, "procurement.shop_id"),
                            ],
                            "procurement_shop_ids" => [
                                "type"        => "ordered-select-list",
                                "label"       => __("Shops partners buy from, in order"),
                                "information" => __("Shops partner organisations buy from when ordering intercompany. For each SKO the first shop in this list that sells it is used, so put the shop that owns the product first. External shops are not allowed. Leave empty to use the procurement shop only."),
                                "placeholder" => __("Add a shop"),
                                "options"     => $sellingShopOptions,
                                "value"       => Arr::get($organisation->settings, "procurement.shop_ids", []),
                            ],
                            "delivery_tolerance_percentage" => [
                                "type"        => "input",
                                "label"       => __("Delivery tolerance (%)"),
                                "information" => __("A delivered line that differs from what was expected by no more than this is shown but not flagged as under or over delivered. 0 flags every difference."),
                                "value"       => (float) Arr::get($organisation->settings, "procurement.delivery_tolerance_percentage", 0),
                            ],
                            "delivery_tolerance_amount" => [
                                "type"        => "input",
                                "label"       => __("Delivery tolerance (:currency)", ["currency" => $organisation->currency->code]),
                                "information" => __("A difference worth no more than this is shown but not flagged. When both tolerances are set, a difference has to be within both. 0 flags every difference."),
                                "value"       => (float) Arr::get($organisation->settings, "procurement.delivery_tolerance_amount", 0),
                            ],
                        ],
                    ],
                    [
                        "label"  => __("Staff chat"),
                        "icon"   => "fal fa-comments",
                        "fields" => [
                            "staff_chat_crm_user_ids" => [
                                "type"        => "multiselect-tags",
                                "label"       => __("Ask CRM goes to"),
                                "information" => __("Used when the shop has no list of its own. Leave empty to fall back to everyone holding a customer service role."),
                                "options"     => $staffOptions,
                                "labelProp"   => "name",
                                "valueProp"   => "id",
                                "value"       => Arr::get($organisation->settings, "staff_chat.crm_user_ids", []),
                            ],
                            "staff_chat_crm_backup_user_ids" => [
                                "type"      => "multiselect-tags",
                                "label"     => __("Ask CRM backup"),
                                "options"   => $staffOptions,
                                "labelProp" => "name",
                                "valueProp" => "id",
                                "value"     => Arr::get($organisation->settings, "staff_chat.crm_backup_user_ids", []),
                            ],
                            "staff_chat_warehouse_user_ids" => [
                                "type"        => "multiselect-tags",
                                "label"       => __("Ask warehouse goes to"),
                                "information" => __("Leave empty to fall back to everyone holding a dispatch or stock controller role."),
                                "options"     => $staffOptions,
                                "labelProp"   => "name",
                                "valueProp"   => "id",
                                "value"       => Arr::get($organisation->settings, "staff_chat.warehouse_user_ids", []),
                            ],
                            "staff_chat_warehouse_backup_user_ids" => [
                                "type"      => "multiselect-tags",
                                "label"     => __("Ask warehouse backup"),
                                "options"   => $staffOptions,
                                "labelProp" => "name",
                                "valueProp" => "id",
                                "value"     => Arr::get($organisation->settings, "staff_chat.warehouse_backup_user_ids", []),
                            ],
                        ],
                    ],
                ],
                "args" => [
                    "updateRoute" => [
                        "name"       => "grp.models.org.settings.update",
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
