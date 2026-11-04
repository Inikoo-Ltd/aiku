<?php

/*
 * Author Louis Perez
 * Created on 04-11-2026-15h-31m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\Website\BreakWebsiteCache;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairWebsiteMenuV2Links
{
    use AsAction;

    private const array LINK_KEYS = ['href', 'canonical_url'];

    /**
     * @return array{changes: list<array{copy: string, path: string, old: string, new: string}>, skipped: list<array{copy: string, path: string, value: string}>}
     */
    public function handle(Website $website, bool $apply = false, ?string $backupDir = null): array
    {
        $changes = [];
        $skipped = [];

        $liveSnapshots = $website->snapshots()
            ->where('scope', 'menu')
            ->where('state', SnapshotStateEnum::LIVE)
            ->get();
        $draft     = $website->unpublishedMenuSnapshot;
        $published = Arr::get($website->published_layout, 'menu');

        $fixedLive = [];
        foreach ($liveSnapshots as $snapshot) {
            $before                    = count($changes);
            $fixed                     = $this->fixLinks($snapshot->layout, $website->domain, 'live', '', $changes, $skipped);
            $fixedLive[$snapshot->id]  = count($changes) > $before ? $fixed : null;
        }

        $fixedDraft = null;
        if ($draft) {
            $before     = count($changes);
            $fixed      = $this->fixLinks($draft->layout, $website->domain, 'draft', '', $changes, $skipped);
            $fixedDraft = count($changes) > $before ? $fixed : null;
        }

        $fixedPublished = null;
        if (is_array($published)) {
            $before         = count($changes);
            $fixed          = $this->fixLinks($published, $website->domain, 'published', '', $changes, $skipped);
            $fixedPublished = count($changes) > $before ? $fixed : null;
        }

        if (!$apply || $changes === []) {
            return ['changes' => $changes, 'skipped' => $skipped];
        }

        if ($backupDir === null) {
            throw new \InvalidArgumentException('A backup directory is required when applying.');
        }

        File::ensureDirectoryExists($backupDir);
        File::put($backupDir.'/'.$website->slug.'.json', json_encode([
            'website_id'            => $website->id,
            'website_slug'          => $website->slug,
            'live_snapshots'        => $liveSnapshots->mapWithKeys(fn ($snapshot) => [$snapshot->id => $snapshot->layout]),
            'draft_snapshot'        => $draft ? [$draft->id => $draft->layout] : null,
            'published_layout_menu' => $published,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        DB::transaction(function () use ($website, $liveSnapshots, $fixedLive, $draft, $fixedDraft, $fixedPublished) {
            foreach ($liveSnapshots as $snapshot) {
                if ($fixedLive[$snapshot->id] !== null) {
                    $snapshot->update([
                        'layout'   => $fixedLive[$snapshot->id],
                        'checksum' => md5(json_encode($fixedLive[$snapshot->id])),
                    ]);
                }
            }

            if ($draft && $fixedDraft !== null) {
                $draft->update(['layout' => $fixedDraft]);
            }

            if ($fixedPublished !== null) {
                $website->update([
                    'published_layout->menu'  => $fixedPublished,
                    'published_menu_checksum' => md5(json_encode($fixedPublished)),
                ]);
            }
        });

        BreakWebsiteCache::run($website, null);

        return ['changes' => $changes, 'skipped' => $skipped];
    }

    private function fixLinks(mixed $node, string $domain, string $copy, string $path, array &$changes, array &$skipped): mixed
    {
        if (!is_array($node)) {
            return $node;
        }

        foreach ($node as $key => $value) {
            $childPath = ltrim($path.'.'.$key, '.');

            if (is_array($value)) {
                $node[$key] = $this->fixLinks($value, $domain, $copy, $childPath, $changes, $skipped);
            } elseif (is_string($value) && in_array($key, self::LINK_KEYS, true) && preg_match('~^https?://v2\.~i', $value)) {
                $new = preg_replace('~^(https?://)v2\.('.preg_quote($domain, '~').')(?=[/?#:]|$)~i', '${1}www.${2}', $value);

                if ($new === $value) {
                    $skipped[] = ['copy' => $copy, 'path' => $childPath, 'value' => $value];
                } else {
                    $node[$key] = $new;
                    $changes[]  = ['copy' => $copy, 'path' => $childPath, 'old' => $value, 'new' => $new];
                }
            }
        }

        return $node;
    }

    public string $commandSignature = 'repair:website-menu-v2-links {website? : Website slug, every website when omitted} {--apply : Write the changes, otherwise only preview them}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $apply = (bool)$command->option('apply');
        $query = Website::query()->orderBy('id');

        if ($slug = $command->argument('website')) {
            $query->where('slug', $slug);
        }

        $websites = $query->get();
        if ($websites->isEmpty()) {
            $command->error("No website found for slug $slug.");

            return 1;
        }

        $backupDir = $apply ? storage_path('app/repairs/website-menu-v2-links/'.now()->format('Ymd-His')) : null;

        $command->info($apply ? '=== APPLYING ===' : '=== DRY RUN, nothing is written. Add --apply to write. ===');

        $totalLinks    = 0;
        $totalWebsites = 0;

        foreach ($websites as $website) {
            if ($website->is_migrating) {
                $command->line("<fg=gray>$website->slug ($website->domain): still migrating, v2 links are intended. Skipped.</>");
                continue;
            }

            $result = $this->handle($website, $apply, $backupDir);

            foreach ($result['skipped'] as $skip) {
                $command->warn("$website->slug [{$skip['copy']}] {$skip['path']}: {$skip['value']} is a v2 host of another domain, left as is.");
            }

            if ($result['changes'] === []) {
                continue;
            }

            $totalWebsites++;
            $totalLinks += count($result['changes']);

            $perCopy = collect($result['changes'])->countBy('copy')->map(fn ($count, $copy) => "$copy $count")->implode(', ');
            $command->line("<info>$website->slug</info> ($website->domain): ".count($result['changes'])." values ($perCopy)");

            if ($command->getOutput()->isVerbose()) {
                foreach ($result['changes'] as $change) {
                    $command->line("  [{$change['copy']}] {$change['path']}: <fg=red>{$change['old']}</> → <fg=green>{$change['new']}</>");
                }
            }
        }

        $command->newLine();
        $command->info(($apply ? 'Updated' : 'Would update')." $totalLinks values on $totalWebsites websites.");

        if ($apply && $totalWebsites > 0) {
            $command->info("Original layouts saved in $backupDir");
        } elseif (!$apply) {
            $command->line('Run with -v to list every value.');
        }

        return 0;
    }
}
