<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackConnection;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slack never takes scopes off a token, only auth.revoke does: so switching private conversations off revokes the token
 * (and with it the private permissions), then sends the person straight back through Slack for the public permissions only.
 */
class WithdrawSlackPrivateConsent
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return SlackWriteAccess::allows($request->user());
    }

    public function asController(ActionRequest $request): Response
    {
        $user       = $request->user();
        $connection = SlackConnection::where('user_id', $user->id)->first();
        if ($connection?->private_consented_at === null) {
            return StartSlackConnection::backToSettings(StartSlackConnection::NOTICE_KEY, __('Private conversations are already off'));
        }

        $mayCallSlack = app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS);
        DisconnectSlackAccount::make()->handle($user, $mayCallSlack);

        if (StartSlackConnection::unavailableReason($user)) {
            return StartSlackConnection::backToSettings(StartSlackConnection::NOTICE_KEY, __('Private conversations are off. Your Slack account is disconnected; connect it again for public channels when you want'));
        }

        return Inertia::location(StartSlackConnection::authorizeUrl(
            $user,
            SlackWriteAccess::USER_SCOPES,
            notice: __('Private conversations are off. Your Slack account is connected again for public channels only')
        ));
    }
}
