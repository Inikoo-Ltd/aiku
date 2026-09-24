<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:22:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\SysAdmin\User\UI\GetLoggedUser;
use App\Actions\UI\WithInertia;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class EditProfile
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
            "title"    => __("Personal settings"),
            "pageHead" => [
                "title" => __("Personal settings"),

            ],
            "formData" => [
                "blueprint" => [
                    [
                        "label"   => __("Notifications"),
                        "icon"    => "fal fa-bell",
                        "current" => true,
                        "fields" => [
                            "email"                => [
                                "type"  => "input",
                                "label" => __("Email"),
                                "value" => $user->email,
                            ],
                            "slack_user_id"        => [
                                "type"        => "input",
                                "label"       => __("Slack ID"),
                                "information" => __("Your Slack member ID, found in your Slack profile under Copy member ID"),
                                "placeholder" => "U01ABCDEF23",
                                "value"       => $user->slack_user_id,
                            ],
                            "notifications"        => [
                                "type"        => "notification_channels",
                                "full"        => true,
                                "label"       => __("Notify me when"),
                                "information" => __("You always get the notification inside Aiku; pick where else you want it"),
                                "events"      => UserNotificationEnum::options(),
                                "channels"    => [
                                    ['value' => 'email', 'label' => __('Email'), 'available' => (bool) $user->email, 'unavailable_reason' => __('Add your email above to use this')],
                                    ['value' => 'slack', 'label' => __('Slack'), 'available' => (bool) $user->slack_user_id, 'unavailable_reason' => __('Add your Slack ID above to use this')],
                                    [
                                        'value'         => 'browser',
                                        'label'         => __('Browser'),
                                        'available'     => (bool) config('services.webpush.public_key'),
                                        'unavailable_reason' => __('Browser notifications are not set up on this server'),
                                        'push'          => [
                                            'public_key'    => config('services.webpush.public_key'),
                                            'devices_count' => $user->pushSubscriptions()->count(),
                                            'store_route'   => ['name' => 'grp.profile.push-subscriptions.store'],
                                            'delete_route'  => ['name' => 'grp.profile.push-subscriptions.delete'],
                                        ],
                                    ],
                                ],
                                "value"       => UserNotificationEnum::valuesFor($user),
                            ],
                        ],
                    ],
                    [
                        "label"  => __("Log in"),
                        "icon"   => "fal fa-user-lock",
                        "fields" => [
                            "username"   => [
                                "type"     => "input",
                                "label"    => __("Username"),
                                "value"    => $user->username,
                                "readonly" => true,
                            ],
                            "password"   => [
                                "type"  => "password",
                                "label" => __("Password"),
                                "value" => "",
                            ],
                            "enable_2fa" => [
                                "type"         => "toggle2fa",
                                "label"        => __("Two Factor Authentication"),
                                "noSaveButton" => true,
                                "value"        => [
                                    'has_2fa'           => (bool)$user->google2fa_secret,
                                    'secretKey'         => $user->google2fa_secret,
                                    'one_time_password' => null,
                                ],
                            ],
                            "passkeys"   => [
                                "type"         => "passkeys",
                                "label"        => __("Passkeys"),
                                "noSaveButton" => true,
                                "value"        => $user->passkeys()
                                    ->get(['id', 'name', 'last_used_at', 'created_at'])
                                    ->toArray(),
                            ],
                        ],
                    ],
                    ...$user->chatAgent ? [
                        [
                            'label'  => __('Email signature'),
                            'icon'   => 'fal fa-envelope',
                            'fields' => [
                                'chat_signature' => [
                                    'type'  => 'textarea',
                                    'label' => __('Signature used on email replies'),
                                    'value' => $user->chatAgent->signature,
                                ],
                            ],
                        ],
                    ] : [],
                    ...EditProfileSettings::make()->generateBlueprint($user)['formData']['blueprint'],
                ],
                "args"      => [
                    "updateRoute" => [
                        "name" => "grp.models.profile.update"
                    ],
                ],
            ],
            'auth'     => [
                'user' => GetLoggedUser::run($user)
            ],
        ];
    }

    public function htmlResponse(User $user): Response
    {
        return Inertia::render("EditModel", $this->generateBlueprint($user));
    }


}
