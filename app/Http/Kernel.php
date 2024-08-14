<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Mar 2023 22:07:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http;

use App\Http\Middleware\ApiBindGroupInstance;
use App\Http\Middleware\RetinaPreparingAccount;
use App\Http\Middleware\SetHanAsAppScope;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\BindGroupInstance;
use App\Http\Middleware\CheckWebsiteState;
use App\Http\Middleware\DetectWebsite;
use App\Http\Middleware\HandleAikuPublicInertiaRequests;
use App\Http\Middleware\HandleRetinaInertiaRequests;
use App\Http\Middleware\LogUserRequestMiddleware;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleIrisInertiaRequests;
use App\Http\Middleware\ResetUserPasswordMiddleware;
use App\Http\Middleware\ResetWebUserPasswordMiddleware;
use App\Http\Middleware\RetinaAuthenticate;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\HandleInertiaGrpRequests;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class Kernel extends HttpKernel
{
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [

        'webhooks' => [
            ForceJsonResponse::class,
            EnsureFrontendRequestsAreStateful::class,
            SubstituteBindings::class,
        ],

        'bk-api' => [
            ForceJsonResponse::class,
            EnsureFrontendRequestsAreStateful::class,
            SubstituteBindings::class,
        ],

        'han' => [
            ForceJsonResponse::class,
            EnsureFrontendRequestsAreStateful::class,
            SetHanAsAppScope::class,
            SubstituteBindings::class,
        ],

        'maya' => [
            ForceJsonResponse::class,
            EnsureFrontendRequestsAreStateful::class,
            SubstituteBindings::class,
        ],

        'api' => [
            ForceJsonResponse::class,
            EnsureFrontendRequestsAreStateful::class,
            SubstituteBindings::class,
        ],
        'grp' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            BindGroupInstance::class,
            SubstituteBindings::class,
            SetLocale::class,
            LogUserRequestMiddleware::class,
            HandleInertiaGrpRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ],

        'aiku-public' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            HandleAikuPublicInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ],
        'iris'        => [
            DetectWebsite::class,
            CheckWebsiteState::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            HandleIrisInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ],
        'retina'      => [
            DetectWebsite::class,
            CheckWebsiteState::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            HandleRetinaInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class
        ],


        //==== Other Middleware Groups
        'horizon'     => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            BindGroupInstance::class,
            SubstituteBindings::class,
        ],

        'broadcast' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            'auth:broadcasting'
        ],


    ];

    protected $routeMiddleware = [
        'auth'                   => Authenticate::class,
        'retina-auth'            => RetinaAuthenticate::class,
        'auth.basic'             => AuthenticateWithBasicAuth::class,
        'auth.session'           => AuthenticateSession::class,
        'cache.headers'          => SetCacheHeaders::class,
        'can'                    => Authorize::class,
        'guest'                  => RedirectIfAuthenticated::class,
        'password.confirm'       => RequirePassword::class,
        'signed'                 => ValidateSignature::class,
        'throttle'               => ThrottleRequests::class,
        'verified'               => EnsureEmailIsVerified::class,
        'inertia'                => HandleInertiaGrpRequests::class,
        'bind_group'             => ApiBindGroupInstance::class,
        'grp-reset-pass'         => ResetUserPasswordMiddleware::class,
        'retina-reset-pass'      => ResetWebUserPasswordMiddleware::class,
        'retina-prepare-account' => RetinaPreparingAccount::class,
        'abilities'              => CheckAbilities::class,
        'ability'                => CheckForAnyAbility::class,
    ];
}
