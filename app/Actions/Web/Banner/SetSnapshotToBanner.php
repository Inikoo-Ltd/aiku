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

    public function handle(Snapshot $snapshot): Banner
    {
        /** @var Banner $banner */
        $banner = $snapshot->parent;

        $banner->update(
            [
                'compiled_layout' => $snapshot->compiledLayout()
            ]
        );

        UpdateBannerImage::run($banner);
        BannerRecordSearch::dispatch($banner);

        return $banner;
    }

    public function asController(Snapshot $snapshot, ActionRequest $request): Banner
    {
        /** @var Banner $banner */
        $banner = $snapshot->parent;

        $this->initialisationFromShop($banner->shop, $request);

        return $this->handle($snapshot);
    }
}
