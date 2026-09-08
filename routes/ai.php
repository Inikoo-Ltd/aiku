<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Middleware\EnsureCanUseMcp;
use App\Http\Middleware\LogMcpRequest;
use App\Mcp\Servers\AikuServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
 * Registered AFTER Mcp::oauthRoutes() on purpose: Laravel's route collection lets a
 * later identical method+uri replace an earlier one, which is how these override the
 * package's versions.
 *
 * laravel/mcp advertises the authorization server without
 * token_endpoint_auth_methods_supported. RFC 8414 then defaults it to
 * client_secret_basic, so strict clients conclude public PKCE clients are not
 * supported and refuse to connect (ChatGPT fails silently at this step).
 * Keep in sync with Registrar::authorizationServerMetadata().
 */
$authorizationServerMetadata = fn () => response()->json([
    'issuer'                                => url('/'),
    'authorization_endpoint'                => route('passport.authorizations.authorize'),
    'token_endpoint'                        => route('passport.token'),
    'registration_endpoint'                 => url('oauth/register'),
    'response_types_supported'              => ['code'],
    'code_challenge_methods_supported'      => ['S256'],
    'scopes_supported'                      => ['mcp:use'],
    'grant_types_supported'                 => ['authorization_code', 'refresh_token'],
    'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
]);

Mcp::oauthRoutes();

Route::get('/.well-known/oauth-authorization-server', $authorizationServerMetadata)
    ->name('mcp.oauth.authorization-server');

Route::get('/.well-known/oauth-authorization-server/{path}', $authorizationServerMetadata)
    ->where('path', '.*')
    ->name('aiku.mcp.oauth.authorization-server.nested');

Mcp::web('/mcp/aiku', AikuServer::class)->middleware(['auth:mcp,mcp-oauth', EnsureCanUseMcp::class, 'bind_group', LogMcpRequest::class]);
