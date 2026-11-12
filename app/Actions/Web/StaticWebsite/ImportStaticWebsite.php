<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Loads a bundle written by devops/static-websites/freeze.mjs: the assets go to the media disk under
 * static-websites/<domain>/, every page is stored with its frozen HTML, and the header and footer the
 * pages repeat become the site's shared components. A page or component changed in Aiku after it was
 * imported is left alone unless forced, so a re-import never overwrites the UK team's edits.
 */
class ImportStaticWebsite
{
    use AsAction;

    public string $commandSignature = 'static_website:import
        {domain : Domain of the info website, e.g. aw-freedom.com}
        {bundle? : Bundle folder, defaults to storage/app/static-websites/<domain>}
        {--force : Overwrite pages that were changed in Aiku after the last import}';

    /**
     * @return array{assets_uploaded: int, assets_kept: int, pages_created: int, pages_updated: int, pages_skipped_as_edited: list<string>, pages_only_in_aiku: list<string>, components: array<string, array{status: string, pages_linked: int, pages_left_inline: list<string>}>}
     */
    public function handle(StaticWebsite $staticWebsite, string $bundlePath, bool $force = false): array
    {
        $manifest = $this->readManifest($staticWebsite, $bundlePath);
        $frozenAt = Carbon::parse($manifest['frozen_at']);

        $summary = [
            'assets_uploaded'         => 0,
            'assets_kept'             => 0,
            'pages_created'           => 0,
            'pages_updated'           => 0,
            'pages_skipped_as_edited' => [],
            'pages_only_in_aiku'      => [],
        ];

        $disk = StaticWebsite::assetDisk();
        foreach (File::files($bundlePath.'/assets') as $asset) {
            $storagePath = $staticWebsite->assetStoragePath($asset->getFilename());
            if ($disk->exists($storagePath)) {
                $summary['assets_kept']++;
                continue;
            }
            $disk->put($storagePath, File::get($asset->getPathname()), ['ContentType' => StaticWebsite::assetContentType($asset->getFilename())]);
            $summary['assets_uploaded']++;
        }

        DB::transaction(function () use ($staticWebsite, $bundlePath, $manifest, $frozenAt, $force, &$summary) {
            foreach ($manifest['pages'] as $pageData) {
                /** @var StaticWebpage|null $webpage */
                $webpage = $staticWebsite->webpages()->where('path', $pageData['path'])->first();

                if ($webpage && !$force && $this->wasEditedAfterImport($webpage)) {
                    $summary['pages_skipped_as_edited'][] = $webpage->path;
                    continue;
                }

                $webpage ??= $staticWebsite->webpages()->make(['path' => $pageData['path']]);
                $isNew = !$webpage->exists;

                $webpage->snapshotComment = __('Copied from :source', ['source' => $pageData['source_url']]);
                $webpage->forceFill([
                    'title'       => $pageData['title'] ?: null,
                    'description' => $pageData['description'] ?: null,
                    'html'        => File::get($bundlePath.'/'.$pageData['file']),
                    'source_url'  => $pageData['source_url'],
                    'crawled_at'  => $frozenAt,
                    'updated_at'  => $frozenAt,
                ])->save();

                $summary[$isNew ? 'pages_created' : 'pages_updated']++;
            }

            $manifestPaths = collect($manifest['pages'])->pluck('path');
            $summary['pages_only_in_aiku'] = $staticWebsite->webpages()->whereNotIn('path', $manifestPaths)->pluck('path')->all();

            $staticWebsite->update([
                'source_url' => $manifest['source'],
                'crawled_at' => $frozenAt,
            ]);
        });

        $summary['components'] = ExtractStaticWebsiteComponents::run($staticWebsite->refresh(), $force);

        return $summary;
    }

    private function wasEditedAfterImport(StaticWebpage $webpage): bool
    {
        return $webpage->crawled_at && $webpage->updated_at && $webpage->updated_at->gt($webpage->crawled_at);
    }

    /**
     * @return array{domain: string, source: string, frozen_at: string, pages: list<array{path: string, source_url: string, title: ?string, description: ?string, file: string}>}
     */
    private function readManifest(StaticWebsite $staticWebsite, string $bundlePath): array
    {
        $manifestFile = $bundlePath.'/pages.json';
        if (!File::exists($manifestFile) || !File::isDirectory($bundlePath.'/assets')) {
            throw new RuntimeException("No frozen bundle at $bundlePath (expected pages.json and assets/)");
        }

        $manifest = json_decode(File::get($manifestFile), true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['domain'] ?? null) !== $staticWebsite->domain) {
            throw new RuntimeException("The bundle is for {$manifest['domain']}, not $staticWebsite->domain");
        }

        foreach ($manifest['pages'] as $pageData) {
            if (!File::exists($bundlePath.'/'.$pageData['file'])) {
                throw new RuntimeException("The bundle lists {$pageData['path']} but {$pageData['file']} is missing");
            }
        }

        return $manifest;
    }

    public function asCommand(Command $command): int
    {
        $staticWebsite = StaticWebsite::where('domain', StoreStaticWebsite::normaliseDomain($command->argument('domain')))->first();
        if (!$staticWebsite) {
            $command->error("No info website for {$command->argument('domain')}; create it with static_website:create first");

            return 1;
        }

        $bundlePath = $command->argument('bundle') ?? storage_path('app/static-websites/'.$staticWebsite->domain);

        try {
            $summary = $this->handle($staticWebsite, $bundlePath, (bool) $command->option('force'));
        } catch (RuntimeException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $command->table(['', ''], [
            ['Assets uploaded', $summary['assets_uploaded']],
            ['Assets already on disk', $summary['assets_kept']],
            ['Pages created', $summary['pages_created']],
            ['Pages updated', $summary['pages_updated']],
            ['Pages kept because edited in Aiku', implode(', ', $summary['pages_skipped_as_edited']) ?: '-'],
            ['Pages only in Aiku', implode(', ', $summary['pages_only_in_aiku']) ?: '-'],
            ...collect($summary['components'])->map(fn (array $row, string $code) => [
                "Shared $code",
                "{$row['status']}, used by {$row['pages_linked']} pages".($row['pages_left_inline'] ? '; own copy kept on '.implode(', ', $row['pages_left_inline']) : ''),
            ])->values()->all(),
        ]);
        $command->line("Run static_website:verify $staticWebsite->domain to check nothing still points at the old host.");

        return 0;
    }
}
