<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Feb 2025 14:15:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\Dispatching\Printer\Json\GetPrinters;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\UI\WithInertia;
use App\Http\Resources\UI\LoggedUserResource;
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
            $printers = cache()->remember($cacheKey, now()->addMinutes(1), function () {
                return GetPrinters::make()->action([])->map(function ($printer) {
                    return [
                        'value' => $printer->id,
                        'label' => $printer->name . ' (' . $printer->computer->name . ')'  . ' - ' . $printer->state,
                    ];
                })->values()->toArray();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to fetch printers: ' . $e->getMessage());
            $printers = [];
        }

        return [
            "title"       => __("Preferences"),
            "pageHead"    => [
                "title"        => __("Preferences"),

            ],
            "formData" => [
                "blueprint" => [
                    [
                        "label"  => __("Preferences"),
                        "icon"   => "fal fa-sliders-v",
                        "fields" => [
                            "language_id" => [
                                "type"    => "select",
                                "label"   => __("language"),
                                "value"   => $user->language_id,
                                'options' => GetLanguagesOptions::make()->translated(),
                            ],
                            "app_theme" => [
                                "type"  => "app_theme",
                                "label" => __("theme color"),
                                "value" => Arr::get($user->settings, 'app_theme'),
                            ],
                            "hide_logo" => [
                                "type"    => "toggle",
                                "label"   => __("Hide logo"),
                                "noIcon"    => true,
                                "value"   => Arr::get($user->settings, 'hide_logo'),

                            ],
                            'preferred_printer' => [
                                'type'     => 'select_printer',
                                'label'    => __('preferred printer'),
                                'required' => false,
                                'options'  => $printers,
                                'value'    => Arr::get($user->settings, 'preferred_printer_id'),
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
                'user' => LoggedUserResource::make($user)->getArray(),
            ],
        ];
    }

    public function htmlResponse(User $user): Response
    {

        return Inertia::render("EditModel", $this->generateBlueprint($user));
    }
}
