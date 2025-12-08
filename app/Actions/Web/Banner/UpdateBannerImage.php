<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Enums\Web\Banner\BannerStateEnum;
use App\Models\Web\Banner;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBannerImage
{
    use AsAction;
    use HasBannerCommand;

    public function handle(Banner $banner): Banner
    {
        $snapshot = match ($banner->state) {
            BannerStateEnum::LIVE, BannerStateEnum::SWITCH_OFF => $banner->liveSnapshot,
            default => $banner->unpublishedSnapshot,
        };

        /** @var Slide $slide */
        $slide = $snapshot->slides()->where('visibility', true)->first();
        $image_id = $slide?->image_id;

        if (! $image_id and $banner->state == BannerStateEnum::UNPUBLISHED) {
            $image_id = Arr::get($banner->data, 'unpublished_image_id');
        }

        $banner->image_id = $image_id;
        $banner->saveQuietly();

        return $banner;
    }

    public function getCommandSignature(): string
    {
        return 'banner:set-image {slug}';
    }

    public function asCommand(Command $command): int
    {

        if ($banner = $this->getBanner($command)) {
            $banner = $this->handle($banner);

            $command->info("Done! banner $banner->name image updated  🥳");

            return 0;
        }

        return 1;

    }
}
