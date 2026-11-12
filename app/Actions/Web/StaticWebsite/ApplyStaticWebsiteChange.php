<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Models\Web\StaticWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Saves a staged change exactly as it was previewed, in one transaction, and only while the pages
 * and shared parts it touches are still what the preview was worked out from. Every page and part
 * written keeps a snapshot of itself through the models.
 */
class ApplyStaticWebsiteChange
{
    use AsAction;

    /**
     * @param array<string, mixed> $staged
     */
    public function handle(StaticWebsite $staticWebsite, array $staged, string $comment): void
    {
        $current = GetStaticWebsiteContentState::run($staticWebsite, array_keys($staged['pages']), array_keys($staged['components']));
        if ($current != $staged['base']) {
            throw ValidationException::withMessages([
                'change' => 'The website changed after this preview, so it was not applied. Preview the change again and ask the user to confirm the new preview.',
            ]);
        }

        $comment = Str::limit($comment, 250);

        DB::transaction(function () use ($staticWebsite, $staged, $comment) {
            foreach ($staged['components'] as $code => $html) {
                $component = $staticWebsite->components()->where('code', $code)->first();
                if ($html === null) {
                    $component?->delete();
                    continue;
                }
                $component ??= $staticWebsite->components()->make(['code' => $code]);
                $component->snapshotComment = $comment;
                $component->html            = $html;
                $component->save();
            }

            foreach ($staged['pages'] as $path => $page) {
                $webpage = $staticWebsite->webpages()->where('path', $path)->first();
                if ($page === null) {
                    $webpage?->delete();
                    continue;
                }
                $webpage ??= $staticWebsite->webpages()->make(['path' => $path]);
                $webpage->snapshotComment = $comment;
                $webpage->fill([
                    'title'       => $page['title'],
                    'description' => $page['description'],
                    'html'        => $page['html'],
                ])->save();
            }
        });
    }

    /**
     * The snapshot holding what each page and part is now, kept before a change so it can be reverted.
     *
     * @param list<string> $paths
     * @param list<string> $codes
     *
     * @return array{pages: array<string, int|null>, components: array<string, int|null>}
     */
    public function snapshotsBeforeChange(StaticWebsite $staticWebsite, array $paths, array $codes): array
    {
        $ids = ['pages' => [], 'components' => []];

        foreach ($paths as $path) {
            $webpage = $staticWebsite->webpages()->where('path', $path)->first();
            if ($webpage) {
                StoreStaticWebsiteSnapshot::run($webpage, __('As it was before an AI change'));
            }
            $ids['pages'][$path] = $webpage?->snapshots()->latest('id')->value('id');
        }
        foreach ($codes as $code) {
            $component = $staticWebsite->components()->where('code', $code)->first();
            if ($component) {
                StoreStaticWebsiteSnapshot::run($component, __('As it was before an AI change'));
            }
            $ids['components'][$code] = $component?->snapshots()->latest('id')->value('id');
        }

        return $ids;
    }
}
