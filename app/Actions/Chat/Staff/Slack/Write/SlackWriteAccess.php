<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Helpers\Ticket;
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
    ];

    public const array WRITE_ENVIRONMENTS = ['production', 'testing'];

    public static function allows(User $user): bool
    {
        return $user->status && Ticket::canBeManagedBy($user) && SlackChannel::userMayReadPublicChannels($user);
    }

    public static function reasonUnavailable(User $user): ?string
    {
        if (!self::allows($user)) {
            return __('Writing to Slack from here is not available to you');
        }
        if (!app()->environment(self::WRITE_ENVIRONMENTS)) {
            return __('Writing to Slack is switched off on this machine');
        }
        $connection = SlackConnection::where('user_id', $user->id)->first();
        if ($connection?->isLive() && $connection->missingScopes() === []) {
            return null;
        }

        return $connection?->needsReconnection()
            ? __('Reconnect your Slack account to write here')
            : __('Connect your Slack account to write here');
    }
}
