<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\McpChange;
use App\Models\Web\StaticWebsite;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Puts the pages and shared parts an AI change touched back as they were: a created one is removed,
 * a deleted one comes back with its content from the snapshot kept just before the change.
 */
class RevertStaticWebsiteChange
{
    use AsAction;

    public function handle(McpChange $mcpChange): void
    {
        $target        = Arr::get($mcpChange->data, 'target', []);
        $snapshotIds   = Arr::get($mcpChange->data, 'before_snapshot_ids', ['pages' => [], 'components' => []]);
        $staticWebsite = StaticWebsite::findOrFail($target['static_website_id']);
        $comment       = __('Reverted AI change :id', ['id' => $mcpChange->id]);

        foreach ($mcpChange->before['components'] ?? [] as $code => $before) {
            $component = $staticWebsite->components()->where('code', $code)->first();
            if ($before === null) {
                $component?->delete();
                continue;
            }
            $component ??= $staticWebsite->components()->make(['code' => $code]);
            $component->snapshotComment = $comment;
            $component->html            = $this->htmlOf($snapshotIds['components'][$code] ?? null);
            $component->save();
        }

        foreach ($mcpChange->before['pages'] ?? [] as $path => $before) {
            $webpage = $staticWebsite->webpages()->where('path', $path)->first();
            if ($before === null) {
                $webpage?->delete();
                continue;
            }
            $webpage ??= $staticWebsite->webpages()->make(['path' => $path]);
            $webpage->snapshotComment = $comment;
            $webpage->fill([
                'title'       => $before['title'],
                'description' => $before['description'],
                'html'        => $this->htmlOf($snapshotIds['pages'][$path] ?? null),
            ])->save();
        }
    }

    private function htmlOf(?int $snapshotId): string
    {
        $html = $snapshotId ? Snapshot::find($snapshotId)?->compiled_layout : null;
        if ($html === null) {
            throw ValidationException::withMessages(['message' => __('The version before this change is missing, so it cannot be reverted automatically. Restore it from the snapshots in Aiku.')]);
        }

        return $html;
    }
}
