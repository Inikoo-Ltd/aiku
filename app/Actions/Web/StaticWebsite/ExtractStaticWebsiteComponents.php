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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Moves the header and footer every page repeats into one shared component per site, leaving a marker
 * in each page. The homepage's copy is stored; a page whose copy differs from it in anything but the
 * old host's render noise keeps its own, and is reported, rather than being forced onto the shared one.
 */
class ExtractStaticWebsiteComponents
{
    use AsAction;
    use WithStaticWebsiteHtml;

    public string $commandSignature = 'static_website:extract_components
        {domain : Domain of the info website, e.g. aw-freedom.com}
        {--force : Replace a component edited in Aiku with the imported copy}';

    /**
     * @return array<string, array{status: string, pages_linked: int, pages_left_inline: list<string>}>
     */
    public function handle(StaticWebsite $staticWebsite, bool $force = false): array
    {
        /** @var Collection<int, StaticWebpage> $webpages */
        $webpages = $staticWebsite->webpages()->whereNotNull('html')->orderByRaw("path = '/' desc")->orderBy('path')->get();
        $summary  = [];

        DB::transaction(function () use ($staticWebsite, $webpages, $force, &$summary) {
            foreach (StaticWebsiteComponentEnum::cases() as $component) {
                $summary[$component->value] = $this->extract($staticWebsite, $webpages, $component, $force);
            }

            foreach ($webpages as $webpage) {
                if ($webpage->isDirty('html')) {
                    $webpage->timestamps      = false;
                    $webpage->snapshotComment = __('Header and footer now come from the shared parts');
                    $webpage->save();
                }
            }
        });

        return $summary;
    }

    /**
     * @param Collection<int, StaticWebpage> $webpages
     *
     * @return array{status: string, pages_linked: int, pages_left_inline: list<string>}
     */
    private function extract(StaticWebsite $staticWebsite, Collection $webpages, StaticWebsiteComponentEnum $component, bool $force): array
    {
        $result = ['status' => 'not found', 'pages_linked' => 0, 'pages_left_inline' => []];

        $source = $webpages->first(function (StaticWebpage $webpage) use ($component) {
            $element = $this->findComponentElement($webpage->html, $component);

            return $element && trim(substr($webpage->html, $element[1], $element[2] - $element[1])) !== $component->marker();
        });

        /** @var StaticWebsiteComponent|null $existing */
        $existing = $staticWebsite->components()->where('code', $component->value)->first();

        if ($source) {
            [, $openEnd, $closeStart] = $this->findComponentElement($source->html, $component);
            $sourceHtml = substr($source->html, $openEnd, $closeStart - $openEnd);
            $comparable = $this->comparableComponentHtml($sourceHtml);

            if ($existing && $existing->wasEditedAfterImport() && !$force) {
                $result['status'] = 'kept, edited in Aiku';
            } else {
                $crawledAt = $staticWebsite->crawled_at ?? now();

                $shared = $existing ?? $staticWebsite->components()->make(['code' => $component->value]);
                $shared->snapshotComment = __('Taken from :path', ['path' => $source->path]);
                $shared->forceFill([
                    'html'       => $this->withoutPageSpecificState($sourceHtml),
                    'crawled_at' => $crawledAt,
                    'updated_at' => $crawledAt,
                ])->save();

                $result['status'] = $existing ? 'updated' : 'created';
            }
        } elseif ($existing) {
            $result['status'] = 'already shared';
        }

        foreach ($webpages as $webpage) {
            $element = $this->findComponentElement($webpage->html, $component);
            if (!$element) {
                continue;
            }

            [, $openEnd, $closeStart] = $element;
            $inner = substr($webpage->html, $openEnd, $closeStart - $openEnd);

            if (trim($inner) === $component->marker()) {
                $result['pages_linked']++;
            } elseif ($source && $this->comparableComponentHtml($inner) === $comparable) {
                $webpage->html = substr_replace($webpage->html, $component->marker(), $openEnd, $closeStart - $openEnd);
                $result['pages_linked']++;
            } else {
                $result['pages_left_inline'][] = $webpage->path;
            }
        }

        return $result;
    }

    public function asCommand(Command $command): int
    {
        $staticWebsite = StaticWebsite::where('domain', StoreStaticWebsite::normaliseDomain($command->argument('domain')))->first();
        if (!$staticWebsite) {
            $command->error("No info website for {$command->argument('domain')}");

            return 1;
        }

        $summary = $this->handle($staticWebsite, (bool) $command->option('force'));

        $command->table(['Component', 'Status', 'Pages sharing it', 'Pages keeping their own copy'], collect($summary)->map(fn (array $row, string $code) => [
            $code,
            $row['status'],
            $row['pages_linked'],
            implode(', ', $row['pages_left_inline']) ?: '-',
        ])->values()->all());

        return 0;
    }
}
