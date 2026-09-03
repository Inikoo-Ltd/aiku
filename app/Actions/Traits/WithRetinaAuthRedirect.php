<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

trait WithRetinaAuthRedirect
{
    protected static string $retinaIntendedUrlSessionKey = 'retina_intended_url';

    /**
     * Paths that must never be used as the place to send a web user back to.
     */
    protected static array $retinaAuthPaths = [
        'app/login',
        'app/login-google',
        'app/logout',
        'app/register',
        'app/registration-form',
        'app/register-from-google',
        'app/register-from-standalone',
        'app/rp',
        'app/reset-password',
        'app/reset-password-send',
        'app/reset-password-error',
        'app/auth-shopify',
        'app/prepare-account',
        'app/finish-pre-register',
    ];

    /**
     * Records where the web user was when they entered the authentication flow, so that
     * they can be sent back there once they are logged in.
     */
    public function rememberRetinaIntendedUrl(Request $request, Website $website): void
    {
        $intendedUrl = $this->getRetinaWebpageUrl($website, $request->input('ref'))
            ?? $this->getRetinaRefererUrl($request);

        if ($intendedUrl) {
            Session::put(static::$retinaIntendedUrlSessionKey, $intendedUrl);
        }
    }

    /**
     * After logging in the web user goes back to where they came from, never to the welcome page.
     */
    public function getRetinaLoginRedirectUrl(Website $website, mixed $ref = null): string
    {
        return $this->getRetinaWebpageUrl($website, $ref)
            ?? Session::pull(static::$retinaIntendedUrlSessionKey)
            ?? $this->getRetinaWebpageUrlByType($website, WebpageTypeEnum::STOREFRONT)
            ?? '';
    }

    /**
     * The welcome page is the landing page of the website and is only shown after registering.
     */
    public function getRetinaRegistrationRedirectUrl(Website $website, mixed $ref = null): string
    {
        Session::forget(static::$retinaIntendedUrlSessionKey);

        return $this->getRetinaWebpageUrlByType($website, WebpageTypeEnum::LANDING_PAGE)
            ?? $this->getRetinaWebpageUrl($website, $ref)
            ?? $this->getRetinaWebpageUrlByType($website, WebpageTypeEnum::STOREFRONT)
            ?? '';
    }

    /**
     * After logging out the web user is sent back to the public storefront.
     */
    public function getRetinaLogoutRedirectUrl(Website $website): ?string
    {
        return $this->getRetinaWebpageUrlByType($website, WebpageTypeEnum::STOREFRONT);
    }

    protected function getRetinaWebpageUrl(Website $website, mixed $ref): ?string
    {
        if (!$ref || !is_numeric($ref)) {
            return null;
        }

        $webpage = Webpage::where('id', $ref)
            ->where('website_id', $website->id)
            ->where('state', WebpageStateEnum::LIVE)
            ->first();

        if (!$webpage) {
            return null;
        }

        return ShowIrisWebpage::make()->getEnvironmentUrl($webpage->canonical_url);
    }

    protected function getRetinaWebpageUrlByType(Website $website, WebpageTypeEnum $type): ?string
    {
        $webpage = Webpage::where('type', $type)
            ->where('state', WebpageStateEnum::LIVE)
            ->where('website_id', $website->id)
            ->first();

        if (!$webpage) {
            return null;
        }

        return ShowIrisWebpage::make()->getEnvironmentUrl($webpage->canonical_url);
    }

    protected function getRetinaRefererUrl(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (!$referer) {
            return null;
        }

        $parsedReferer = parse_url($referer);

        if (!is_array($parsedReferer) || ($parsedReferer['host'] ?? null) !== $request->getHost()) {
            return null;
        }

        if (in_array(trim($parsedReferer['path'] ?? '', '/'), static::$retinaAuthPaths, true)) {
            return null;
        }

        return $referer;
    }
}
