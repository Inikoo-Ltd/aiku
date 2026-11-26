<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the user's open staff app to fetch its permissions again after its roles or rights changed.
 */
class BroadcastUserPermissionsChanged implements ShouldBroadcast, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithSockets;

    public bool $afterCommit = true;

    public int $uniqueFor = 60;

    public function __construct(public int $userId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.personal.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'permissions-changed';
    }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId];
    }
}
