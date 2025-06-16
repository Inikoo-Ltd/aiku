<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media\Hydrators;

use App\Models\Helpers\Media;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MediaHydrateMultiplicity implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Media $media): string
    {
        return $media->id;
    }

    public function handle(Media $media): void
    {
        $multiplicity = DB::table('media')->where('checksum', $media->checksum)->where('group_id', $media->group_id)->count();
        $media->update(['multiplicity' => $multiplicity]);
    }


}
