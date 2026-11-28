<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\StaffMessage;
use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;

class SlackWriteAccess
{
    public const array USER_SCOPES = [
        'chat:write',
        'reactions:write',
        'files:write',
        'channels:write',
        'pins:write',
        'bookmarks:write',
        'reminders:write',
        'users.profile:write',
        'users:write',
        'dnd:write',
        'dnd:read',
        'channels:read',
        'channels:history',
        'users:read',
        'usergroups:read',
        'emoji:read',
        'reminders:read',
    ];

    /**
     * Asked only by the private conversations opt-in (INI-429), never by the public connection: two separate yeses.
     * Reads DMs, group DMs and private channels the person is in and their files, opens and writes DMs and group DMs, and creates private channels (INI-431/433/441/564).
     */
    public const array PRIVATE_SCOPES = [
        'im:history',
        'im:read',
        'mpim:history',
        'mpim:read',
        'groups:history',
        'groups:read',
        'groups:write',
        'files:read',
        'im:write',
        'mpim:write',
    ];

    /**
     * Versions of the consent text and small print (INI-427 v5.5, comments 36856 and the part 2 after it).
     * Change them together with the texts in SlackConsentDetails.vue.
     */
    public const string PRIVATE_CONSENT_VERSION = 'v5.5';

    public const string PRIVATE_TERMS_VERSION = 'v5.6';

    /**
     * Launch gate (INI-429): opened by Raul on 26 Nov 2026, managers were already asked to join.
     */
    public const bool PRIVATE_OPT_IN_LAUNCHED = true;

    public const array WRITE_ENVIRONMENTS = ['production', 'testing'];

    public static function privateOptInOpen(): bool
    {
        return self::PRIVATE_OPT_IN_LAUNCHED || !app()->environment('production');
    }

    public static function allows(User $user): bool
    {
        return $user->status && SlackChannel::userMayReadPublicChannels($user);
    }

    /**
     * Verba opens only after a staff member has agreed and connected all their Slack in one go. The only way past without it
     * is to say they are not in the company's Slack, which counts only while no Slack person is linked to them. Null when they may go in.
     *
     * @return array{linked: bool, returning: bool, company: string, consent_version: string, terms_version: string, start_route: array{name: string}}|null
     */
    public static function joinPending(User $user): ?array
    {
        if (!$user->status || $user->worksOnlyForAgents() || !self::privateOptInOpen() || Arr::get($user->settings, 'verba_join_later_until', '') > now()->toDateString()
            || !app()->environment(self::WRITE_ENVIRONMENTS) || !StartSlackConnection::isConfigured()) {
            return null;
        }
        $linked = SlackWorkspace::main()->personOf($user) !== null;
        if ($linked ? SlackConnection::main()->where('user_id', $user->id)->first()?->hasPrivateConsent() : Arr::get($user->settings, 'verba_no_slack')) {
            return null;
        }

        return [
            'linked'          => $linked,
            'returning'       => Arr::get($user->settings, 'verba_welcome_seen') || SlackConnection::where('user_id', $user->id)->exists() || StaffMessage::where('user_id', $user->id)->exists(),
            'company'         => SlackWorkspace::main()->displayName() ?? (string) $user->group?->name,
            'consent_version' => self::PRIVATE_CONSENT_VERSION,
            'terms_version'   => self::PRIVATE_TERMS_VERSION,
            'start_route'     => ['name' => 'grp.chat.staff.slack.connect.private.start'],
        ];
    }

    public static function reasonUnavailable(User $user, ?\App\Actions\Chat\Staff\Slack\SlackWorkspace $workspace = null): ?string
    {
        $workspace ??= \App\Actions\Chat\Staff\Slack\SlackWorkspace::main();
        if (!self::allows($user)) {
            return __('Writing to Slack from here is not available to you');
        }
        if (!app()->environment(self::WRITE_ENVIRONMENTS)) {
            return __('Writing to Slack is switched off on this machine');
        }
        if (!$workspace->isMain() && !$workspace->personOf($user)) {
            return __('You have no account in this Slack workspace, so only messages can be sent here');
        }
        $connection = SlackConnection::forWorkspace($workspace)->where('user_id', $user->id)->first();
        if ($connection?->isLive() && $connection->missingScopes() === []) {
            return null;
        }

        return $connection?->needsReconnection()
            ? __('Reconnect your Slack account to write here')
            : __('Connect your Slack account to write here');
    }

    /**
     * Why this person cannot write in a Slack DM or group DM from Verba (INI-593), or null when they can: only with their own
     * token and the private opt-in, never through the stand-in bot.
     */
    public static function directReasonUnavailable(User $user): ?string
    {
        if (!self::allows($user)) {
            return __('Writing to Slack from here is not available to you');
        }
        if (!app()->environment(self::WRITE_ENVIRONMENTS)) {
            return __('Writing to Slack is switched off on this machine');
        }
        $connection = SlackConnection::main()->where('user_id', $user->id)->first();
        if ($connection && ($connection->needsReconnection() || ($connection->private_consented_at !== null && !$connection->isLive()))) {
            return __('Reconnect your Slack account to reply here');
        }
        if (!$connection?->hasPrivateConsent()) {
            return __('Turn on private conversations in your Slack settings to reply here');
        }

        return null;
    }
}
