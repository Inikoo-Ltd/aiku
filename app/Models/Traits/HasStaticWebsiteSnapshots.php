<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Actions\Web\StaticWebsite\StoreStaticWebsiteSnapshot;
use App\Models\Helpers\Snapshot;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Every change to the HTML of an info website page or shared part is kept as a snapshot, whoever made
 * it, so a mistake can always be undone. A writer may say what it did through $snapshotComment.
 */
trait HasStaticWebsiteSnapshots
{
    public ?string $snapshotComment = null;

    public static function bootHasStaticWebsiteSnapshots(): void
    {
        static::saved(function (self $model) {
            if ($model->wasRecentlyCreated || $model->wasChanged('html')) {
                StoreStaticWebsiteSnapshot::run($model, $model->snapshotComment);
                $model->snapshotComment = null;
            }
        });
    }

    public function snapshots(): MorphMany
    {
        return $this->morphMany(Snapshot::class, 'parent');
    }
}
