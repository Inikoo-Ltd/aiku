<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Enums\Web\StaticWebsite\StaticWebsiteComponentEnum;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The check to run before the old host is cancelled: no stored page or stylesheet may still load
 * anything from Squarespace or Typekit, and every /_assets/ file the pages ask for must be on the disk.
 */
class VerifyStaticWebsite
{
    use AsAction;

    public const array FORBIDDEN_HOSTS = ['squarespace.com', 'squarespace-cdn.com', 'sqspcdn.com', 'typekit.net', 'squarestylist'];

    public const array SCANNED_ASSET_EXTENSIONS = ['css', 'js', 'svg', 'bin', 'html', 'json', 'txt'];

    public string $commandSignature = 'static_website:verify {domain : Domain of the info website, e.g. aw-freedom.com}';

    /**
     * @return array{pages: int, components: int, assets_checked: int, forbidden_references: list<array{where: string, excerpt: string}>, missing_assets: list<array{where: string, asset: string}>, missing_components: list<array{where: string, component: string}>, pages_with_forms: list<string>}
     */
    public function handle(StaticWebsite $staticWebsite): array
    {
        $result = [
            'pages'                => 0,
            'components'           => 0,
            'assets_checked'       => 0,
            'forbidden_references' => [],
            'missing_assets'       => [],
            'missing_components'   => [],
            'pages_with_forms'     => [],
        ];

        $disk           = StaticWebsite::assetDisk();
        $checkedAssets  = [];
        $pendingAssets  = [];
        $forbiddenRegex = '/.{0,60}(?:'.implode('|', array_map(fn (string $host) => preg_quote($host, '/'), self::FORBIDDEN_HOSTS)).').{0,60}/i';

        $componentCodes = [];
        $staticWebsite->components()->orderBy('code')->each(function (StaticWebsiteComponent $component) use (&$result, &$pendingAssets, &$componentCodes, $forbiddenRegex) {
            $result['components']++;
            $componentCodes[] = $component->code->value;
            $where            = 'component:'.$component->code->value;

            foreach ($this->forbiddenExcerpts($component->html, $forbiddenRegex) as $excerpt) {
                $result['forbidden_references'][] = ['where' => $where, 'excerpt' => $excerpt];
            }
            foreach ($this->referencedAssets($component->html) as $asset) {
                $pendingAssets[] = [$where, $asset];
            }
            if (str_contains($component->html, '<form')) {
                $result['pages_with_forms'][] = $where;
            }
        });

        $staticWebsite->webpages()->orderBy('path')->each(function (StaticWebpage $webpage) use (&$result, &$pendingAssets, $componentCodes, $forbiddenRegex) {
            $result['pages']++;
            $html = (string) $webpage->html;

            foreach ($this->forbiddenExcerpts($html, $forbiddenRegex) as $excerpt) {
                $result['forbidden_references'][] = ['where' => $webpage->path, 'excerpt' => $excerpt];
            }
            foreach ($this->referencedAssets($html) as $asset) {
                $pendingAssets[] = [$webpage->path, $asset];
            }
            foreach (StaticWebsiteComponentEnum::cases() as $component) {
                if (str_contains($html, $component->marker()) && !in_array($component->value, $componentCodes, true)) {
                    $result['missing_components'][] = ['where' => $webpage->path, 'component' => $component->value];
                }
            }
            if (str_contains($html, '<form')) {
                $result['pages_with_forms'][] = $webpage->path;
            }
        });

        while ($pendingAssets) {
            [$where, $asset] = array_shift($pendingAssets);
            if (isset($checkedAssets[$asset])) {
                continue;
            }
            $checkedAssets[$asset] = true;
            $result['assets_checked']++;

            $storagePath = $staticWebsite->assetStoragePath($asset);
            if (!$disk->exists($storagePath)) {
                $result['missing_assets'][] = ['where' => $where, 'asset' => $asset];
                continue;
            }

            if (in_array(pathinfo($asset, PATHINFO_EXTENSION), self::SCANNED_ASSET_EXTENSIONS, true)) {
                $contents = (string) $disk->get($storagePath);
                foreach ($this->forbiddenExcerpts($contents, $forbiddenRegex) as $excerpt) {
                    $result['forbidden_references'][] = ['where' => StaticWebsite::ASSET_URL_PREFIX.$asset, 'excerpt' => $excerpt];
                }
                foreach ($this->referencedAssets($contents) as $nested) {
                    $pendingAssets[] = [StaticWebsite::ASSET_URL_PREFIX.$asset, $nested];
                }
            }
        }

        return $result;
    }

    /**
     * @param array{pages: int, forbidden_references: array, missing_assets: array, missing_components: array} $result
     */
    public static function isReady(array $result): bool
    {
        return $result['pages'] > 0 && !$result['forbidden_references'] && !$result['missing_assets'] && !$result['missing_components'];
    }

    /**
     * @return list<string>
     */
    private function forbiddenExcerpts(string $contents, string $forbiddenRegex): array
    {
        preg_match_all($forbiddenRegex, $contents, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * @return list<string>
     */
    private function referencedAssets(string $contents): array
    {
        preg_match_all('#'.preg_quote(StaticWebsite::ASSET_URL_PREFIX, '#').'([A-Za-z0-9._-]+)#', $contents, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function asCommand(Command $command): int
    {
        $staticWebsite = StaticWebsite::where('domain', StoreStaticWebsite::normaliseDomain($command->argument('domain')))->first();
        if (!$staticWebsite) {
            $command->error("No info website for {$command->argument('domain')}");

            return 1;
        }

        $result = $this->handle($staticWebsite);

        $command->info("$staticWebsite->domain: {$result['pages']} pages, {$result['components']} shared components, {$result['assets_checked']} assets checked");

        foreach ($result['forbidden_references'] as $reference) {
            $command->error("Still points at the old host in {$reference['where']}: {$reference['excerpt']}");
        }
        foreach ($result['missing_assets'] as $missing) {
            $command->error("Missing asset {$missing['asset']} used by {$missing['where']}");
        }
        foreach ($result['missing_components'] as $missing) {
            $command->error("{$missing['where']} uses the shared {$missing['component']}, which does not exist");
        }
        if ($result['pages_with_forms']) {
            $command->warn('Forms that posted to the old host and need a replacement: '.implode(', ', $result['pages_with_forms']));
        }

        if (!self::isReady($result)) {
            $command->error('Not ready: the old host cannot be cancelled yet.');

            return 1;
        }

        $command->info('No page or stylesheet depends on the old host and every asset is on the disk.');

        return 0;
    }
}
