<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Banner\Search\BannerRecordSearch;
use App\Enums\Web\Banner\BannerStateEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Banner;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SetSnapshotToBanner extends OrgAction
{
    use WithWebEditAuthorisation;
    use AsAction;
    use WithAttributes;

    public function handle(Snapshot $snapshot): void
    {
        /** @var Banner $banner */
        $banner = $snapshot->parent;

        $snapshotConditions = [];

        if ($banner->state === BannerStateEnum::LIVE) {
            $snapshotConditions['live_snapshot_id'] = $snapshot->id;
        } else {
            $snapshotConditions['unpublished_snapshot_id'] = $snapshot->id;
        }

        $banner->update(
            [
                'compiled_layout'   => $snapshot->compiledLayout(),
                'state'             => BannerStateEnum::LIVE,
                'switch_off_at'     => null,
                ...$snapshotConditions

            ]
        );

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
