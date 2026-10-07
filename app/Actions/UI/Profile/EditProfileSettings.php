<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Feb 2025 14:15:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\Helpers\TimeZone\Json\IndexTimeZones;
use App\Actions\Dispatching\Printer\Json\GetPrintNodePrinters;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\SysAdmin\User\GetUserOrderAlerts;
use App\Actions\SysAdmin\User\UI\GetLoggedUser;
use App\Enums\Ordering\Order\OrderAlertTypeEnum;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class EditProfileSettings
{
    use AsAction;
    use WithInertia;

    public function asController(ActionRequest $request): User
    {
        return $request->user();
    }

    public function jsonResponse(User $user): array
    {
        return $this->generateBlueprint($user);
    }

    public function generateBlueprint(User $user): array
    {
        try {
            $cacheKey = "user_printers_" . $user->id;
            $printers = cache()->remember($cacheKey, now()->addMinutes(), function () {
                return GetPrintNodePrinters::make()->action([])->map(function ($printer) {

                    $state = $printer->state;
                    if ($printer->state == 'offline') {
                        $state = '🚫';
                    } elseif ($printer->state == 'online') {
                        $state = '✅';
                    }

                    return [
                        'value' => $printer->id,
                        'label' => '['.$printer->id.'] '.$printer->name . ' (' . $printer->computer->name . ')'  . ' ' . $state,
                    ];
                })->values()->toArray();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to fetch printers: ' . $e->getMessage());
            $printers = [];
        }

        $organisations = $user->authorisedOrganisations()
            ->orderBy('organisations.name')
            ->get(['organisations.id', 'organisations.slug', 'organisations.code', 'organisations.name'])
            ->map(fn ($organisation) => [
                'id'    => $organisation->id,
                'slug'  => $organisation->slug,
                'code'  => $organisation->code,
                'label' => $organisation->name,
            ])->all();

        return [
            "title"       => __("Preferences"),
            "pageHead"    => [
                "title"        => __("Preferences"),

            ],
            "formData" => [
                "blueprint" => [
                    [
                        "label"  => __("Language"),
                        "icon"   => "fal fa-language",
                        "fields" => [
                            "language_id" => [
                                "type"    => "select",
                                "label"   => __("Language"),
                                "value"   => $user->language_id,
                                'options' => GetLanguagesOptions::make()->translated(),
                            ],
                        ],
                    ],
                    [
                        "label"  => __("Appearance"),
                        "icon"   => "fal fa-paint-brush",
                        "fields" => [
                            "app_theme" => [
                                "type"  => "app_theme",
                                "label" => __("Theme color"),
                                "value" => Arr::get($user->settings, 'app_theme'),
                            ],
                            "org_themes" => [
                                "type"        => "org_themes",
                                "label"       => __("Colour per organisation"),
                                "information" => __("Give each organisation its own colour for the left navigation, so you can tell at a glance which one you are working in"),
                                "full"        => true,
                                "options"     => [
                                    "organisations" => $organisations,
                                ],
                                "value"       => [
                                    "enabled" => (bool) Arr::get($user->settings, 'org_themes.enabled', false),
                                    "themes"  => array_values(Arr::get($user->settings, 'org_themes.themes') ?: []),
                                ],
                            ],
                            "chat_theme" => [
                                "type"  => "chat_theme",
                                "label" => __("Chat panel color"),
                                "value" => Arr::get($user->settings, 'chat_theme'),
                            ],
                            "hide_logo" => [
                                "type"    => "toggle",
                                "label"   => __("Hide logo"),
                                "noIcon"    => true,
                                "value"   => Arr::get($user->settings, 'hide_logo'),

                            ],
                        ],
                    ],
                    [
                        "label"  => __("Alerts"),
                        "icon"   => "fal fa-volume-up",
                        "fields" => [
                            "alert_preview_seconds" => [
                                "type"        => "select",
                                "label"       => __("Pop-up time"),
                                "information" => __("How long a new customer message or a new order stays on screen"),
                                "value"       => Arr::get($user->settings, 'alert_preview_seconds', 6),
                                "options"     => collect([3, 4, 6, 8, 10, 15, 20])->map(fn (int $seconds) => [
                                    'value' => $seconds,
                                    'label' => __(':count seconds', ['count' => $seconds]),
                                ])->all(),
                            ],
                            "alert_popup_previews" => [
                                "type"         => "alert_popup_previews",
                                "label"        => __("Preview pop-ups"),
                                "information"  => __("Shows a sample of each pop-up with your pop-up time, so you can see what it looks like"),
                                "noSaveButton" => true,
                                "full"         => true,
                                "value"        => null,
                            ],
                            "alert_sounds" => [
                                "type"        => "alert_sounds",
                                "label"       => __("Chat alerts"),
                                "full"        => true,
                                "information" => __("The sound played when a customer or a colleague writes to you. The speaker button mutes a sound"),
                                "value"       => Arr::get($user->settings, 'alert_sounds'),
                            ],
                            "order_alerts" => [
                                "type"        => "order_alerts",
                                "label"       => __("New order alerts"),
                                "information" => __("A sound and a pop-up when a shop you follow gets an order. Ecom orders are small, normal or big compared with that shop's orders in the last 90 days, worked out every night. Dropshipping rings when an order is left unpaid or a customer's store sends its first order"),
                                "full"        => true,
                                "options"     => [
                                    "shops" => GetUserOrderAlerts::make()->shopOptions($user)->map(fn ($shop) => [
                                        'id'    => $shop->id,
                                        'code'  => $shop->code,
                                        'label' => $shop->name,
                                        'type'  => $shop->type->value,
                                    ])->all(),
                                    "types" => collect(OrderAlertTypeEnum::cases())->map(fn (OrderAlertTypeEnum $type) => [
                                        'value' => $type->value,
                                        'label' => $type->label(),
                                    ])->all(),
                                ],
                                "value"       => GetUserOrderAlerts::make()->formValue($user),
                            ],
                        ],
                    ],
                    [
                        "label"  => __("Printers"),
                        "icon"   => "fal fa-print",
                        "fields" => [
                            'preferred_printer' => [
                                'type'     => 'select_printer',
                                'label'    => __('Preferred printer'),
                                'required' => false,
                                'options'  => $printers,
                                'value'    => Arr::get($user->settings, 'preferred_printer_id'),
                            ],
                            'preferred_leaflet_printer' => [
                                'type'        => 'select_printer',
                                'label'       => __('Leaflet printer'),
                                'information' => __('Printer used for inserts and leaflets. Leave empty to use your preferred printer.'),
                                'required'    => false,
                                'options'     => $printers,
                                'value'       => Arr::get($user->settings, 'preferred_leaflet_printer_id'),
                            ],
                        ],
                    ],
                    [
                        "label"  => __("Timezone"),
                        "icon"   => "fal fa-clock",
                        "fields" => [
                            "timezone"  =>  [
                                "type"        => "select_infinite",
                                "label"       => __("Your timezone"),
                                "information" => __("Times across Aiku are shown in this timezone. Defaults to the timezone of the organisation you work for"),
                                "options"     => IndexTimeZones::make()->optionsFor([$user->timezone_name]),
                                "fetchRoute"  => [
                                    "name" => "grp.json.timezones",
                                ],
                                "valueProp"   => "value",
                                "labelProp"   => "label",
                                "required"    => false,
                                "value"       => $user->timezone_name,
                            ],
                        ],
                    ],
                ],
                "args"      => [
                    "updateRoute" => [
                        "name"       => "grp.models.profile.update"
                    ],
                ],
            ],
            'auth'          => [
                'user' => GetLoggedUser::run($user),
            ],
        ];
    }

    public function htmlResponse(User $user): Response
    {

        return Inertia::render("EditModel", $this->generateBlueprint($user));
    }
}
