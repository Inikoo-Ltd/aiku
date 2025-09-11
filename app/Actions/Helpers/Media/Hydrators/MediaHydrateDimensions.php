<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media\Hydrators;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\Media;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MediaHydrateDimensions implements ShouldBeUnique
{
    use AsAction;
    use WithActionUpdate;

    public function getJobUniqueId(Media $media): string
    {
        return $media->id;
    }

    public function handle(Media $media): void
    {
        $path = $media->getPath();
        list($width, $height) = getimagesize($path);
        $this->update($media, ['width' => $width, 'height' => $height]);
    }


}
