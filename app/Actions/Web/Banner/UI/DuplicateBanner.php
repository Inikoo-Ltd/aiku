<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:15 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner\UI;

use App\Enums\Web\Banner\BannerStateEnum;
use App\Models\Web\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

// todo review carefully this action
class DuplicateBanner
{
    use AsAction;

    public function handle(Banner $banner): Banner
    {
        $banner->load('images', 'portfolioWebsite');
        $newBanner = $banner->replicate();
        $newBanner->name = $banner->name.'[Duplicated]';
        $newBanner->live_at = $newBanner->live_snapshot_id = null;
        $newBanner->state = BannerStateEnum::UNPUBLISHED;
        $newBanner->push();

        $newBanner->stats()->create();

        foreach ($banner->getRelations() as $relationName => $relation) {
            if ($relationName == 'portfolioWebsite' && count($relation) > 0) {
                $relation[0]->banners()->attach(
                    $newBanner->id,
                    [
                        'ulid' => Str::ulid(),
                    ]
                );
            } else {
                $newBanner->{$relationName}()->sync($relation);
            }
        }

        return $newBanner;
    }

    public function asController(Banner $banner): Banner
    {
        return $this->handle($banner);
    }

    public function htmlResponse(Banner $banner): RedirectResponse
    {
        return redirect()->route(
            'portfolio.banners.show',
            [
                $banner->slug,
            ]
        );
    }
}
