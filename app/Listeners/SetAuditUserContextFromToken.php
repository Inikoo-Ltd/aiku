<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Listeners;

use Illuminate\Support\Facades\Context;
use Laravel\Sanctum\Events\TokenAuthenticated;

class SetAuditUserContextFromToken
{
    public function handle(TokenAuthenticated $event): void
    {
        $tokenable = $event->token->tokenable;

        if ($tokenable) {
            Context::addHidden('audit_user', [get_class($tokenable), $tokenable->getAuthIdentifier()]);
        }
    }
}
