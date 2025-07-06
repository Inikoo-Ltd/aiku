<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 19:23:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitHydrateImages implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }

    public function handle(TradeUnit $tradeUnit): void
    {
        // Get all images associated with the trade unit
        $images = $tradeUnit->images()->get();

        // Count the number of images
        $numberImages = $images->count();

        // Calculate total image size
        $totalImageSize = 0;
        $maxImageSize = 0;

        /** @var Media $image */
        foreach ($images as $image) {
            $size = $image->size;
            $totalImageSize += $size;

            if ($size > $maxImageSize) {
                $maxImageSize = $size;
            }
        }

        // Calculate average image size
        $averageImageSize = $numberImages > 0 ? $totalImageSize / $numberImages : 0;

        // Update trade unit stats
        $tradeUnit->stats->update([
            'number_images' => $numberImages,
            'total_image_size' => $totalImageSize,
            'average_image_size' => $averageImageSize,
            'max_image_size' => $maxImageSize > 0 ? $maxImageSize : null
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'hydrate:trade-unit-images';
    }

    public function asCommand()
    {
        $tradeUnits = TradeUnit::all();
        foreach ($tradeUnits as $tradeUnit) {
            $this->handle($tradeUnit);
        }
    }
}
