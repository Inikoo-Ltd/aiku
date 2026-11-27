<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\SysAdmin\User;

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
     * Change them together with the texts in SlackPrivateConsentDialog.vue.
     */
    public const string PRIVATE_CONSENT_VERSION = 'v5.5';

    public const string PRIVATE_TERMS_VERSION = 'v5.5';

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
}
