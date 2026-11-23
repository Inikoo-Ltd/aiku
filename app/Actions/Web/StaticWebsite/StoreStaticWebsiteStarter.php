<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Mon, 23 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Gives an info website with no content yet something to build on: a home page holding the shared
 * header, footer and styles, so its pages can be written with the assistant instead of being frozen
 * from another host. New pages copy the home page, so they keep the same parts.
 */
class StoreStaticWebsiteStarter
{
    use AsAction;

    public const string STYLES = 'styles';

    public const array COMPONENTS = ['header', 'footer', self::STYLES];

    public string $commandSignature = 'static_website:starter {domain : Domain of an info website without pages, e.g. hop-hare.com}';

    public function handle(StaticWebsite $staticWebsite, ?string $comment = null): void
    {
        if ($staticWebsite->webpages()->exists() || $staticWebsite->components()->exists()) {
            throw ValidationException::withMessages([
                'website' => "$staticWebsite->domain already has content; change it with info-website-preview-tool instead.",
            ]);
        }

        DB::transaction(function () use ($staticWebsite, $comment) {
            foreach ($this->componentsHtml($staticWebsite) as $code => $html) {
                $component                  = $staticWebsite->components()->make(['code' => $code]);
                $component->snapshotComment = $comment;
                $component->html            = $html;
                $component->save();
            }

            $webpage                  = $staticWebsite->webpages()->make(['path' => '/']);
            $webpage->snapshotComment = $comment;
            $webpage->fill([
                'title'       => $staticWebsite->name,
                'description' => null,
                'html'        => $this->homePageHtml($staticWebsite),
            ])->save();
        });
    }

    /**
     * @return array<string, string>
     */
    private function componentsHtml(StaticWebsite $staticWebsite): array
    {
        $name = e($staticWebsite->name);
        $year = now()->year;

        return [
            'header'     => <<<HTML
                <div class="header-inner"><a class="header-title" href="/">$name</a><nav class="header-nav"><div class="header-nav-item"><a href="/">Home</a></div></nav></div>
                HTML,
            'footer'     => <<<HTML
                <div class="footer-inner"><p>&copy; $year $name</p></div>
                HTML,
            self::STYLES => <<<'HTML'
                <style>
                :root{--text:#1f2328;--muted:#5f6670;--accent:#2f5d50;--background:#ffffff;--surface:#f6f5f2;--border:#e5e5e5;--content-width:1100px}
                *{box-sizing:border-box}
                body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;font-size:17px;line-height:1.6;color:var(--text);background:var(--background)}
                img,video{max-width:100%;height:auto}
                a{color:var(--accent)}
                h1,h2,h3{line-height:1.2}
                .header-inner,.footer-inner,#page>section{max-width:var(--content-width);margin:0 auto;padding-left:20px;padding-right:20px}
                .header{border-bottom:1px solid var(--border)}
                .header-inner{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px 24px;padding-top:18px;padding-bottom:18px}
                .header-title{font-size:1.35rem;font-weight:600;color:var(--text);text-decoration:none}
                .header-nav{display:flex;flex-wrap:wrap;gap:8px 22px}
                .header-nav-item>a{color:var(--text);text-decoration:none}
                .header-nav-item>a:hover,.header-nav-item>a[aria-current=page]{color:var(--accent);text-decoration:underline;text-underline-offset:6px}
                #page>section{padding-top:56px;padding-bottom:56px}
                #footer-sections{margin-top:40px;background:var(--surface)}
                .footer-inner{padding-top:32px;padding-bottom:32px;color:var(--muted);font-size:.9rem}
                </style>
                HTML,
        ];
    }

    private function homePageHtml(StaticWebsite $staticWebsite): string
    {
        $name      = e($staticWebsite->name);
        $url       = e('https://www.'.$staticWebsite->domain.'/');
        $header    = StaticWebsiteComponent::marker('header');
        $footer    = StaticWebsiteComponent::marker('footer');
        $styles    = StaticWebsiteComponent::marker(self::STYLES);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>$name</title>
            <meta name="description" content="">
            <link rel="canonical" href="$url">
            <meta property="og:site_name" content="$name">
            <meta property="og:type" content="website">
            <meta property="og:title" content="$name">
            <meta property="og:description" content="">
            <meta property="og:url" content="$url">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="$name">
            <meta name="twitter:description" content="">
            $styles
            </head>
            <body>
            <header id="header" class="header">$header</header>
            <main id="page"><section><h1>$name</h1></section></main>
            <footer id="footer-sections">$footer</footer>
            </body>
            </html>
            HTML;
    }

    public function asCommand(Command $command): int
    {
        $staticWebsite = StaticWebsite::where('domain', StoreStaticWebsite::normaliseDomain($command->argument('domain')))->first();
        if (!$staticWebsite) {
            $command->error('No info website for '.$command->argument('domain').'. Create it first with static_website:create.');

            return 1;
        }

        try {
            $this->handle($staticWebsite, __('Starter created from the command line'));
        } catch (ValidationException $e) {
            $command->error(collect($e->errors())->flatten()->join(' '));

            return 1;
        }

        $command->info("$staticWebsite->domain has a starter home page with a shared header, footer and styles. Build it with Claude, then run static_website:launch.");

        return 0;
    }
}
