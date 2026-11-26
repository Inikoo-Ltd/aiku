<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-46m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\Chat\Widget\GetChatWidgetConfig;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsController;

/**
 * Where the staff app opens its websocket, the same Pusher-protocol server the web and the chat widget use;
 * null when the server has none set up, and the app keeps polling.
 */
class GetMobileRealtime
{
    use AsController;

    public function asController(): JsonResponse
    {
        return response()->json(['data' => GetChatWidgetConfig::broadcasting()]);
    }
}
