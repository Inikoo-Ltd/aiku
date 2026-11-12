<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\Actions\Web\StaticWebsite\Iris\ServeStaticWebsite;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows a stored info website page inside Aiku, before its domain points here. The page runs its own
 * scripts, so it is served in a CSP sandbox: it gets an opaque origin and cannot reach the viewer's
 * Aiku session. Its assets therefore come from a public route that allows cross-origin fonts.
 */
class ShowStaticWebsitePreview extends OrgAction
{
    public const string SANDBOX_POLICY = 'sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-forms';

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        if ($user->authTo('group-webmaster.view')) {
            return true;
        }

        foreach ($this->group->shops()->pluck('id') as $shopId) {
            if ($user->authTo("marketing.$shopId.view")) {
                return true;
            }
        }

        return false;
    }

    public function handle(StaticWebsite $staticWebsite, string $path): Response
    {
        $path = '/'.trim($path, '/');

        /** @var StaticWebpage|null $webpage */
        $webpage = $staticWebsite->webpages()->where('path', $path)->first(['id', 'html']);

        if (!$webpage || !$webpage->html) {
            return response(__('This page is not part of :name.', ['name' => $staticWebsite->name]), 404, [
                'Content-Type'            => 'text/plain; charset=UTF-8',
                'Content-Security-Policy' => self::SANDBOX_POLICY,
            ]);
        }

        $pagePrefix  = rtrim(route('grp.marketing.info_websites.preview', [$staticWebsite->slug]), '/');
        $assetPrefix = Str::beforeLast(route('static_website_preview_asset', [$staticWebsite->slug, 'asset']), 'asset');

        $html = ServeStaticWebsite::pointAssetsAt($webpage->html, $assetPrefix);
        $html = preg_replace('/(\shref=)(["\'])\/(?!\/)/', '$1$2'.$pagePrefix.'/', $html) ?? $html;
        $html = ServeStaticWebsite::make()->withRuntime($html);

        return response($html, 200, [
            'Content-Type'            => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => self::SANDBOX_POLICY,
            'Cache-Control'           => 'no-store, private',
            'X-Robots-Tag'            => 'noindex, nofollow',
        ]);
    }

    public function asController(StaticWebsite $staticWebsite, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);
        abort_unless($staticWebsite->group_id === $this->group->id, 404);

        return $this->handle($staticWebsite, (string) $request->route('path', ''));
    }
}
