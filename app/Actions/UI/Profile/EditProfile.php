<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:22:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Actions\Chat\Staff\Slack\Write\StartSlackConnection;
use App\Actions\SysAdmin\User\UI\GetLoggedUser;
use App\Actions\UI\WithInertia;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
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
                                    ['value' => 'email', 'label' => __('Email'), 'available' => (bool) $user->notificationEmail(), 'unavailable_reason' => __('Add your email above to use this')],
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
                                    'secretKey'         => null,
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
                    ...SlackWriteAccess::allows($user) ? [
                        [
                            'key'    => 'slack',
                            'label'  => __('Slack'),
                            'icon'   => 'fal fa-comments',
                            'fields' => [
                                'slack_connection' => [
                                    'type'         => 'slack_connection',
                                    'label'        => __('Your Slack account'),
                                    'noSaveButton' => true,
                                    'value'        => null,
                                    'connection'   => $this->slackConnection($user),
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

    /**
     * The other Slack workspaces the person has an account in (INI-540), each connected on its own, for public channels only.
     *
     * @return list<array<string, mixed>>
     */
    private function otherSlackWorkspaces(User $user): array
    {
        return collect(\App\Actions\Chat\Staff\Slack\SlackWorkspace::others())
            ->filter(fn ($workspace) => $workspace->personOf($user) !== null)
            ->map(function ($workspace) use ($user) {
                $connection = SlackConnection::forWorkspace($workspace)->where('user_id', $user->id)->first();
                $person     = $workspace->personOf($user);

                return [
                    'team_id'            => $workspace->teamId,
                    'name'               => $workspace->name,
                    'state'              => match (true) {
                        $connection?->isLive() && $connection->missingScopes() === [] => 'connected',
                        (bool) $connection?->needsReconnection()                       => 'reconnect',
                        default                                                        => 'none',
                    },
                    'slack_name'         => $person->display_name ?: $person->real_name ?: $person->name,
                    'connected_at'       => $connection?->connected_at,
                    'unavailable_reason' => StartSlackConnection::unavailableReason($user, $workspace),
                    'connect_url'        => route('grp.chat.staff.slack.connect.start', ['team_id' => $workspace->teamId]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function slackConnection(User $user): array
    {
        $connection  = SlackConnection::main()->where('user_id', $user->id)->first();
        $slackPerson = SlackChannel::linkedSlackPerson($user);

        return [
            'state'              => match (true) {
                $connection?->isLive() && $connection->missingScopes() === [] => 'connected',
                (bool) $connection?->needsReconnection() => 'reconnect',
                default                              => 'none',
            },
            'slack_name'         => $slackPerson?->display_name ?: $slackPerson?->real_name ?: $slackPerson?->name,
            'connected_at'       => $connection?->connected_at,
            'unavailable_reason' => StartSlackConnection::unavailableReason($user),
            'error'              => session()->pull(StartSlackConnection::ERROR_KEY),
            'notice'             => session()->pull(StartSlackConnection::NOTICE_KEY),
            'connect_url'        => route('grp.chat.staff.slack.connect.start'),
            'other_workspaces'   => $this->otherSlackWorkspaces($user),
            'disconnect_route'   => ['name' => 'grp.chat.staff.slack.connect.delete'],
            'private'            => SlackWriteAccess::privateOptInOpen() ? [
                'on'              => (bool) $connection?->hasPrivateConsent(),
                'consented_at'    => $connection?->hasPrivateConsent() ? $connection->private_consented_at : null,
                'consent_version' => SlackWriteAccess::PRIVATE_CONSENT_VERSION,
                'terms_version'   => SlackWriteAccess::PRIVATE_TERMS_VERSION,
                'start_route'     => ['name' => 'grp.chat.staff.slack.connect.private.start'],
                'withdraw_route'  => ['name' => 'grp.chat.staff.slack.connect.private.delete'],
            ] : null,
        ];
    }

    public function htmlResponse(User $user): Response
    {
        return Inertia::render("EditModel", array_merge($this->generateBlueprint($user), [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]));
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(ShowProfile::make()->getBreadcrumbs(), [
            [
                "type"   => "simple",
                "simple" => [
                    "route" => [
                        "name" => "grp.profile.edit",
                    ],
                    "label" => __("Personal settings"),
                ],
            ],
        ]);
    }
}
