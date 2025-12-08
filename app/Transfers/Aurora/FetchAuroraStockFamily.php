<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraStockFamily extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {
        $code = $this->auroraModelData->{'Category Code'};
        if ($code == '' || $code == 'GloveSupply' || str_starts_with($code, '505579')) {
            return;
        }

        $code = preg_replace('/\(BOX\)$/', '-BOX', $code);
        $code = preg_replace('/\s+/', '-', $code);

        $sourceSlug = Str::kebab(strtolower($code));

        $this->parsedData['stock_family'] = [
            'code' => $code,
            'name' => $this->auroraModelData->{'Category Label'},
            'state' => match ($this->auroraModelData->{'Part Category Status'}) {
                'InUse' => StockFamilyStateEnum::ACTIVE,
                'Discontinuing' => StockFamilyStateEnum::DISCONTINUING,
                'NotInUse' => StockFamilyStateEnum::DISCONTINUED,
                default => StockFamilyStateEnum::IN_PROCESS
            },
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Category Key'},
            'source_slug' => $sourceSlug,
            'images' => $this->parseImages(),
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    private function parseImages(): array
    {
        $images = $this->getModelImagesCollection(
            'Category',
            $this->auroraModelData->{'Category Key'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });

        return $images->toArray();
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Category Dimension')
            ->leftJoin('Part Category Dimension', 'Part Category Key', 'Category Key')
            ->where('Category Key', $id)->first();
    }
}
