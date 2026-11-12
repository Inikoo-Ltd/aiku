<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\Iris;

use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers every request made to an info website's domain: the stored pages with the runtime that
 * replaces the old host's scripts, the frozen assets, and a robots.txt and sitemap.xml built from the
 * pages. Nothing here touches the shop website stack, so an info website has no session or cookies.
 */
class ServeStaticWebsite
{
    use AsAction;

    public const string RUNTIME_PATH = '/static-websites/static-site';

    public function handle(StaticWebsite $staticWebsite, Request $request): Response
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return response('', 405, ['Allow' => 'GET, HEAD']);
        }

        $canonicalHost = 'www.'.$staticWebsite->domain;
        if (strtolower($request->getHost()) === $staticWebsite->domain) {
            return redirect()->away('https://'.$canonicalHost.$request->getRequestUri(), 301);
        }

        $path = '/'.trim(rawurldecode($request->getPathInfo()), '/');

        if (str_starts_with($path, StaticWebsite::ASSET_URL_PREFIX)) {
            return $this->assetResponse($staticWebsite, Str::after($path, StaticWebsite::ASSET_URL_PREFIX));
        }

        return match ($path) {
            '/robots.txt'  => $this->robots($canonicalHost),
            '/sitemap.xml' => $this->sitemap($staticWebsite, $canonicalHost),
            default        => $this->page($staticWebsite, $path),
        };
    }

    private function page(StaticWebsite $staticWebsite, string $path): Response
    {
        /** @var StaticWebpage|null $webpage */
        $webpage = $staticWebsite->webpages()->where('path', $path)->first(['id', 'html']);

        if (!$webpage || !$webpage->html) {
            return response($this->notFoundHtml($staticWebsite), 404, [
                'Content-Type'  => 'text/html; charset=UTF-8',
                'Cache-Control' => 'public, max-age=60',
            ]);
        }

        return response($this->withRuntime($webpage->html), 200, [
            'Content-Type'          => 'text/html; charset=UTF-8',
            'Cache-Control'         => 'public, max-age=300',
            'X-AIKU-STATIC-WEBSITE' => (string) $staticWebsite->id,
        ]);
    }

    /**
     * Frozen asset names carry a hash of their source, so they are cached for a year. A stylesheet
     * can be served from elsewhere than /_assets/ (the preview) by passing where its own references
     * should point instead.
     */
    public function assetResponse(StaticWebsite $staticWebsite, string $name, ?string $assetUrlPrefix = null): Response
    {
        $disk        = StaticWebsite::assetDisk();
        $storagePath = $staticWebsite->assetStoragePath($name);

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name) || str_contains($name, '..') || !$disk->exists($storagePath)) {
            return response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $contents = (string) $disk->get($storagePath);
        if ($assetUrlPrefix !== null && str_ends_with($name, '.css')) {
            $contents = self::pointAssetsAt($contents, $assetUrlPrefix);
        }

        return response($contents, 200, [
            'Content-Type'                => StaticWebsite::assetContentType($name),
            'Cache-Control'               => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public static function pointAssetsAt(string $contents, string $assetUrlPrefix): string
    {
        return preg_replace('#(?<=["\'(\s,=])'.preg_quote(StaticWebsite::ASSET_URL_PREFIX, '#').'#', rtrim($assetUrlPrefix, '/').'/', $contents) ?? $contents;
    }

    private function robots(string $canonicalHost): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: https://$canonicalHost/sitemap.xml\n", 200, [
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function sitemap(StaticWebsite $staticWebsite, string $canonicalHost): Response
    {
        $urls = $staticWebsite->webpages()->whereNotNull('html')->orderBy('path')->get(['path', 'updated_at'])
            ->map(fn (StaticWebpage $webpage) => sprintf(
                "  <url><loc>%s</loc><lastmod>%s</lastmod></url>",
                e('https://'.$canonicalHost.($webpage->path === '/' ? '/' : $webpage->path)),
                $webpage->updated_at?->toDateString()
            ))
            ->implode("\n");

        return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n$urls\n</urlset>\n", 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function withRuntime(string $html): string
    {
        $version    = $this->runtimeVersion();
        $stylesheet = '<link rel="stylesheet" href="'.self::RUNTIME_PATH.'.css?v='.$version.'">';
        $script     = '<script src="'.self::RUNTIME_PATH.'.js?v='.$version.'" defer></script>';

        $html = preg_replace('/<\/head>/i', $stylesheet.'</head>', $html, 1) ?? $html;

        $bodyEnd = strripos($html, '</body>');

        return $bodyEnd === false ? $html.$script : substr_replace($html, $script, $bodyEnd, 0);
    }

    private function runtimeVersion(): string
    {
        $files = [public_path(self::RUNTIME_PATH.'.css'), public_path(self::RUNTIME_PATH.'.js')];

        return substr(md5(implode('|', array_map(fn (string $file) => is_file($file) ? (string) filemtime($file) : '0', $files))), 0, 10);
    }

    private function notFoundHtml(StaticWebsite $staticWebsite): string
    {
        $name = e($staticWebsite->name);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Page not found · $name</title></head>
            <body style="font-family: system-ui, sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center; text-align: center; color: #222;">
            <main><h1 style="font-weight: 500;">Page not found</h1><p>This page is no longer here.</p><p><a href="/" style="color: inherit;">Go to the $name home page</a></p></main>
            </body>
            </html>
            HTML;
    }
}
