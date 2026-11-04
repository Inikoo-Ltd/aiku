<?php

/*
 * Author Louis Perez
 * Created on 04-11-2026-15h-49m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\Website\BreakWebsiteCache;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairWebsiteV2Links
{
    use AsAction;

    private const int CHUNK_SIZE = 500;

    private const string LIVE_AND_DRAFT = "state IN ('live', 'unpublished')";

    /**
     * @return array<string, array{table: string, column: string, type: string, where: string, keepOriginal: bool}>
     */
    private function targets(): array
    {
        $liveAndDraft = self::LIVE_AND_DRAFT;

        return [
            'website layout'          => ['table' => 'websites', 'column' => 'published_layout', 'type' => 'jsonb', 'where' => 'id = ?', 'keepOriginal' => true],
            'website snapshots'       => ['table' => 'snapshots', 'column' => 'layout', 'type' => 'jsonb', 'where' => "parent_type = 'Website' AND parent_id = ? AND $liveAndDraft", 'keepOriginal' => true],
            'announcements'           => ['table' => 'announcements', 'column' => 'fields', 'type' => 'jsonb', 'where' => 'website_id = ?', 'keepOriginal' => true],
            'published announcements' => ['table' => 'announcements', 'column' => 'published_fields', 'type' => 'jsonb', 'where' => 'website_id = ?', 'keepOriginal' => true],
            'announcement snapshots'  => ['table' => 'snapshots', 'column' => 'layout', 'type' => 'jsonb', 'where' => "parent_type = 'Announcement' AND parent_id IN (SELECT id FROM announcements WHERE website_id = ?) AND $liveAndDraft", 'keepOriginal' => true],
            'banners'                 => ['table' => 'banners', 'column' => 'compiled_layout', 'type' => 'jsonb', 'where' => 'website_id = ?', 'keepOriginal' => true],
            'banner snapshots'        => ['table' => 'snapshots', 'column' => 'layout', 'type' => 'jsonb', 'where' => "parent_type = 'Banner' AND parent_id IN (SELECT id FROM banners WHERE website_id = ?) AND $liveAndDraft", 'keepOriginal' => true],
            'webpages'                => ['table' => 'webpages', 'column' => 'published_layout', 'type' => 'jsonb', 'where' => 'website_id = ?', 'keepOriginal' => false],
            'webpage snapshots'       => ['table' => 'snapshots', 'column' => 'layout', 'type' => 'jsonb', 'where' => "parent_type = 'Webpage' AND parent_id IN (SELECT id FROM webpages WHERE website_id = ?) AND $liveAndDraft", 'keepOriginal' => false],
            'web blocks'              => ['table' => 'web_blocks', 'column' => 'layout', 'type' => 'jsonb', 'where' => "id IN (SELECT web_block_id FROM model_has_web_blocks WHERE website_id = ? AND model_type = 'Webpage')", 'keepOriginal' => false],
        ];
    }

    public function v2HostPattern(): ?string
    {
        $domains = Website::query()
            ->where('is_migrating', false)
            ->pluck('domain')
            ->map(fn (?string $domain) => strtolower(trim((string)$domain)))
            ->filter()
            ->unique()
            ->sortByDesc(fn (string $domain) => strlen($domain))
            ->map(fn (string $domain) => preg_quote($domain, '/'))
            ->implode('|');

        return $domains === '' ? null : '(https?://)v2\.('.$domains.')(?![a-z0-9.-])';
    }

    /**
     * @return array<string, int> rows to fix (or fixed, when applying) per target
     */
    public function handle(Website $website, bool $apply = false, ?string $backupDir = null, ?string $v2HostPattern = null): array
    {
        if ($website->is_migrating) {
            return [];
        }

        $v2HostPattern ??= $this->v2HostPattern();
        if ($v2HostPattern === null) {
            return [];
        }

        $targets = $this->targets();

        $idsByTarget = [];
        foreach ($targets as $label => $target) {
            $idsByTarget[$label] = DB::table($target['table'])
                ->whereRaw($target['where'], [$website->id])
                ->whereRaw("CAST({$target['column']} AS text) ~* ?", [$v2HostPattern])
                ->orderBy('id')
                ->pluck('id')
                ->all();
        }

        $counts = array_map('count', $idsByTarget);

        if (!$apply || array_sum($counts) === 0) {
            return $counts;
        }

        if ($backupDir === null) {
            throw new \InvalidArgumentException('A backup directory is required when applying.');
        }

        $this->saveOriginals($website, $targets, $idsByTarget, $backupDir);

        foreach ($targets as $label => $target) {
            foreach (array_chunk($idsByTarget[$label], self::CHUNK_SIZE) as $ids) {
                DB::update(
                    "UPDATE {$target['table']} SET {$target['column']} = CAST(regexp_replace(CAST({$target['column']} AS text), ?, ?, 'gi') AS {$target['type']}) WHERE id IN (".implode(',', array_map('intval', $ids)).')',
                    [$v2HostPattern, '\1www.\2']
                );
            }
        }

        foreach (DB::table('banners')->whereIn('id', $idsByTarget['banners'])->pluck('slug') as $bannerSlug) {
            Cache::forget('banner_compiled_layout_'.$bannerSlug);
        }

        BreakWebsiteCache::run($website, null);

        return $counts;
    }

    /**
     * @param  array<string, array{table: string, column: string, type: string, where: string, keepOriginal: bool}>  $targets
     * @param  array<string, list<int>>  $idsByTarget
     */
    private function saveOriginals(Website $website, array $targets, array $idsByTarget, string $backupDir): void
    {
        $backup = ['website_id' => $website->id, 'website_slug' => $website->slug];

        foreach ($targets as $label => $target) {
            $ids = $idsByTarget[$label];
            if ($ids === []) {
                continue;
            }

            $backup[$label] = [
                'table'  => $target['table'],
                'column' => $target['column'],
                'rows'   => $target['keepOriginal']
                    ? DB::table($target['table'])->whereIn('id', $ids)->pluck($target['column'], 'id')->map(fn ($value) => json_decode($value, true))->all()
                    : $ids,
            ];
        }

        File::ensureDirectoryExists($backupDir);
        File::put(
            $backupDir.'/'.$website->slug.'.json',
            json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public string $commandSignature = 'repair:website-v2-links {website? : Website slug, every website when omitted} {--apply : Write the changes, otherwise only preview them}';

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

        $v2HostPattern = $this->v2HostPattern();
        $backupDir     = $apply ? storage_path('app/repairs/website-v2-links/'.now()->format('Ymd-His')) : null;

        $command->info($apply ? '=== APPLYING ===' : '=== DRY RUN, nothing is written. Add --apply to write. ===');

        $totals = [];
        foreach ($websites as $website) {
            if ($website->is_migrating) {
                $command->line("<fg=gray>$website->slug ($website->domain): still migrating, v2 links are intended. Skipped.</>");
                continue;
            }

            $counts = array_filter($this->handle($website, $apply, $backupDir, $v2HostPattern));
            if ($counts === []) {
                continue;
            }

            $command->line("<info>$website->slug</info> ($website->domain): ".collect($counts)->map(fn (int $rows, string $label) => "$label $rows")->implode(', '));

            foreach ($counts as $label => $rows) {
                $totals[$label] = ($totals[$label] ?? 0) + $rows;
            }
        }

        $command->newLine();
        if ($totals === []) {
            $command->info('No v2 links left to fix.');

            return 0;
        }

        $command->table(
            ['Where', $apply ? 'Rows fixed' : 'Rows to fix'],
            collect($totals)->map(fn (int $rows, string $label) => [$label, $rows])->values()->all()
        );

        if ($apply) {
            $command->info("Originals saved in $backupDir (full values for website, announcement and banner rows, ids for pages and web blocks).");
        }

        return 0;
    }
}
