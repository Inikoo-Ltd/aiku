<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Slide;

use App\Models\Helpers\Snapshot;
use App\Models\Web\Slide;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreSlide
{
    use AsAction;

    public function handle(Snapshot $snapshot, array $modelData): Slide
    {
        data_fill($modelData, 'ulid', Str::ulid());
        /** @var Slide $slide */
        $slide = $snapshot->slides()->create($modelData);

        return $slide;
    }
}
