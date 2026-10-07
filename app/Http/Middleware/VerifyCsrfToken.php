<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'webhooks',
        'shopify-user/*',
        'redirect-unsubscribe/*',
        'app/api/chats/sessions',
        'app/api/chats/offline-message',
        'app/api/chats/messages/*/send',
    ];
}
