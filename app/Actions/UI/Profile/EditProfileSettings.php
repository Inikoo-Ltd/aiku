<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Feb 2025 14:15:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\UI\WithInertia;
use App\Http\Resources\UI\LoggedUserResource;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
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
        return [
            "title"       => __("Settings"),
            "pageHead"    => [
                "title"        => __("Edit settings"),

            ],
            "formData" => [
                "blueprint" => [
                    [
                        "label"  => __("Settings"),
                        "icon"   => "fal fa-cog",
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
                                "value"   => Arr::get($user->settings, 'hide_logo'),

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
