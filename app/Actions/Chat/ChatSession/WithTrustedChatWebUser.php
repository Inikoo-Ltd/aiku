<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The chat endpoints are open to anonymous website visitors, so the web user a conversation is
 * attached to must come from the visitor's own login and never from the request: an id in the
 * body would let anyone bind their conversation to another customer's account, and everything
 * shown to agents about that customer follows the binding.
 */
trait WithTrustedChatWebUser
{
    protected function trustedWebUserId(mixed $claimedWebUserId): ?int
    {
        $trustedId = Auth::guard('retina')->id();
        $claimedId = is_numeric($claimedWebUserId) ? (int) $claimedWebUserId : null;

        if ($claimedId === null || $claimedId === $trustedId) {
            return $trustedId;
        }

        Log::warning('Chat web user claimed without a matching login', [
            'claimed_web_user_id' => $claimedId,
            'logged_in_as'        => $trustedId,
            'ip'                  => request()->ip(),
            'enforced'            => (bool) config('app.enforce_chat_identity'),
        ]);

        return config('app.enforce_chat_identity') ? $trustedId : $claimedId;
    }
}
