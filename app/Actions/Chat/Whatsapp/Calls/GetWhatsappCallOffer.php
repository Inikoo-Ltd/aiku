<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Calls;

use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatCall;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The customer's SDP offer, handed to the browser that is about to answer. It is fetched
 * rather than broadcast because a private channel is read by every agent watching the
 * conversation, and only the one picking up the call has any use for it.
 */
class GetWhatsappCallOffer
{
    use AsAction;

    public function handle(MetaChatCall $metaChatCall): array
    {
        if (!$metaChatCall->isLive()) {
            return ['ok' => false, 'message' => __('This call has already ended.'), 'code' => 409];
        }

        return [
            'ok'   => true,
            'data' => ['remote_sdp' => data_get($metaChatCall->metadata, 'remote_sdp')],
        ];
    }

    public function asController(string $organisation, MetaChatCall $metaChatCall, ActionRequest $request): array
    {
        $user = Auth::user();

        if (!$user || !ChatAgent::where('user_id', $user->id)->exists()) {
            return ['ok' => false, 'message' => __('Only agents can answer calls.'), 'code' => 403];
        }

        return $this->handle($metaChatCall);
    }
}
