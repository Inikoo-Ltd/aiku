<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Keeps the current HTML of an info website page or shared part as the live snapshot and turns the
 * previous one historic. Identical content is not kept twice.
 */
class StoreStaticWebsiteSnapshot
{
    use AsAction;

    public string $commandSignature = 'static_website:snapshot {domain : Domain of the info website, e.g. aw-freedom.com}';

    public function handle(StaticWebpage|StaticWebsiteComponent $model, ?string $comment = null): ?Snapshot
    {
        $html = (string) $model->html;
        if ($html === '') {
            return null;
        }

        $checksum = md5($html);
        if ($model->snapshots()->latest('id')->value('checksum') === $checksum) {
            return null;
        }

        $user = auth()->user();

        return DB::transaction(function () use ($model, $html, $checksum, $comment, $user) {
            $model->snapshots()->where('state', SnapshotStateEnum::LIVE)->update([
                'state'           => SnapshotStateEnum::HISTORIC,
                'published_until' => now(),
            ]);

            /** @var Snapshot $snapshot */
            $snapshot = $model->snapshots()->create([
                'group_id'        => $model->staticWebsite->group_id,
                'scope'           => $model instanceof StaticWebpage ? SnapshotScopeEnum::WEBPAGE : SnapshotScopeEnum::from($model->code->value),
                'builder'         => SnapshotBuilderEnum::STATIC_HTML,
                'state'           => SnapshotStateEnum::LIVE,
                'published_at'    => now(),
                'checksum'        => $checksum,
                'layout'          => $model instanceof StaticWebpage
                    ? ['path' => $model->path, 'title' => $model->title]
                    : ['code' => $model->code->value],
                'compiled_layout' => $html,
                'comment'         => $comment ? mb_substr($comment, 0, 255) : null,
                'publisher_type'  => $user?->getMorphClass(),
                'publisher_id'    => $user?->getKey(),
            ]);

            return $snapshot;
        });
    }

    public function asCommand(Command $command): int
    {
        $staticWebsite = StaticWebsite::where('domain', StoreStaticWebsite::normaliseDomain($command->argument('domain')))->first();
        if (!$staticWebsite) {
            $command->error("No info website for {$command->argument('domain')}");

            return 1;
        }

        $stored = 0;
        foreach ([...$staticWebsite->webpages()->get(), ...$staticWebsite->components()->get()] as $model) {
            $stored += $this->handle($model, __('Content as it was before snapshots were kept')) ? 1 : 0;
        }

        $command->info("$staticWebsite->domain: $stored snapshots stored, the rest were already up to date");

        return 0;
    }
}
