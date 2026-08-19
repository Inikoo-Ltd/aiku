<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media\UI;

use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShowIrisAudio
{
    use AsAction;

    public function asController(Media $media): BinaryFileResponse
    {
        $isAudio = str_starts_with((string) $media->mime_type, 'audio/')
            || in_array(strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)), ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac'], true);
        abort_unless($isAudio, 404);

        return response()->file($media->getPath(), [
            'Content-Type'   => $media->mime_type,
            'Content-Length' => $media->size,
            'Cache-Control'  => 'public, max-age=31536000, immutable',
        ]);
    }
}
