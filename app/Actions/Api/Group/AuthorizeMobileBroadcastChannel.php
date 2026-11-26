<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-46m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

/**
 * Signs a private channel subscription for the staff app's token, through the same channel rules as the web.
 * Only the channels the app listens to: the user's own, an open record's and the goods out backlog.
 */
class AuthorizeMobileBroadcastChannel
{
    use AsController;

    public const string CHANNELS = '/^private-grp\.(personal\.\d+|mobile\.[a-z_]+\.\d+|org\.\d+\.dispatching-backlog)$/';

    public function asController(ActionRequest $request): mixed
    {
        Validator::make($request->all(), [
            'socket_id'    => ['required', 'string', 'max:64', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:128', 'regex:'.self::CHANNELS],
        ])->validate();

        return Broadcast::auth($request);
    }
}
