<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\Helpers\Snapshot\UpdateSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Banner\Search\BannerRecordSearch;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Web\Banner\BannerStateEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Banner;
use Lorisleiva\Actions\ActionRequest;

class SetSnapshotToBanner extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithActionUpdate;

    public function handle(Snapshot $snapshot): void
    {
        /** @var Banner $banner */
        $banner = $snapshot->parent;

        $snapshotConditions = [];

        if ($banner->state === BannerStateEnum::LIVE) {
            $snapshotConditions['live_snapshot_id'] = $snapshot->id;
        }

        $snapshotConditions['unpublished_snapshot_id'] = $snapshot->id;

        $banner->update(
            [
                'compiled_layout'   => $snapshot->compiledLayout(),
                'state'             => BannerStateEnum::LIVE,
                'switch_off_at'     => null,
                ...$snapshotConditions

            ]
        );

        foreach ($banner->snapshots()->where('state', SnapshotStateEnum::LIVE)->get() as $liveSnapshot) {
            UpdateSnapshot::run($liveSnapshot, [
                'state'           => SnapshotStateEnum::HISTORIC,
                'published_until' => now()
            ]);
        }

        $this->update($snapshot, [
            'state' => SnapshotStateEnum::LIVE
        ]);

        UpdateBannerImage::run($banner);
        BannerRecordSearch::dispatch($banner);

        // return $banner;
    }

    public function asController(Banner $banner, Snapshot $snapshot, ActionRequest $request): void
    {
        $this->initialisationFromShop($banner->shop, $request);

        $this->handle($snapshot);
    }
}
