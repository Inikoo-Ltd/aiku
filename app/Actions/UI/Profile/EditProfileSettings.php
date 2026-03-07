<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Feb 2025 14:15:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\Dispatching\Printer\Json\GetPrintNodePrinters;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\UI\Notification\GetNotificationStateOptions;
use App\Actions\UI\WithInertia;
use App\Http\Resources\UI\LoggedUserResource;
use App\Models\Notifications\UserNotificationSetting;
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

        $stateOptionsCache = [];
        $notificationSettings = $user->notificationSettings()
            ->with(['notificationType', 'scope'])
            ->get()
            ->map(function (UserNotificationSetting $setting) use (&$stateOptionsCache) {
                $scopeLabel = __('Global');
                if ($setting->scope_type) {
                    $scopeName = class_basename($setting->scope_type);
                    $scopeValue = null;
                    if ($setting->scope) {
                        $scopeValue = $setting->scope->name
                            ?? $setting->scope->code
                            ?? $setting->scope->contact_name
                            ?? $setting->scope->username
                            ?? $setting->scope->slug
                            ?? (string) $setting->scope->getKey();
                    }
                    $scopeLabel = $scopeName . ($scopeValue ? ": {$scopeValue}" : '');
                }

                $typeId = $setting->notification_type_id;
                if (!array_key_exists($typeId, $stateOptionsCache)) {
                    $options = GetNotificationStateOptions::run($typeId);
                    $stateOptionsCache[$typeId] = $options['states'] ?? [];
                }

                $filters = is_array($setting->filters) ? $setting->filters : [];
                if (array_key_exists('state', $filters) && is_array($filters['state']) && count($filters['state']) === 0) {
                    unset($filters['state']);
                }
                if (count($filters) === 0) {
                    $filters = [];
                }

                return [
                    'id' => $setting->id,
                    'notification_type_id' => $typeId,
                    'type_name' => $setting->notificationType?->name ?? __('Unknown Type'),
                    'type_slug' => $setting->notificationType?->slug,
                    'scope_type' => $setting->scope_type,
                    'scope_id' => $setting->scope_id,
                    'scope_label' => $scopeLabel,
                    'is_enabled' => (bool) $setting->is_enabled,
                    'filters' => $filters,
                    'available_states' => $stateOptionsCache[$typeId],
                ];
            })
            ->values()
            ->toArray();

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
                    [
                        "label"  => __("Timezone"),
                        "icon"   => "fal fa-clock",
                        "fields" => [
                            "timezones"  =>  [
                                "type"    => "select_infinite",
                                "label"   => __("Timezone"),
                                "information"   => __("Select your timezone to show in the footer"),
                                "options"   => collect(Arr::get($user->settings, 'timezones', []))
                                    ->map(fn ($tz) => ['label' => $tz, 'value' => $tz])
                                    ->values()
                                    ->toArray(),
                                "mode"      => "multiple",
                                "fetchRoute"    => [
                                    "name"       => "grp.json.timezones",
                                ],
                                "valueProp" => "value",
                                "labelProp" => "label",
                                "required" => false,
                                "value"   => Arr::get($user->settings, 'timezones')
                            ]
                        ],
                    ],
                    [
                        "label"  => __("Notification"),
                        "icon"   => "fal fa-bell",
                        "fields" => [
                            "notification_settings"  =>  [
                                "type"  => "notification_preferences",
                                "label" => __("Notification preferences"),
                                "full"  => true,
                                "noTitle" => true,
                                "information" => __("Empty filter means you will receive all states."),
                                "value" => $notificationSettings
                            ]
                        ]
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
