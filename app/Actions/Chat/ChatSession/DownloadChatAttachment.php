<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsAction;

class DownloadChatAttachment
{
    use AsAction;

    public function handle(string $ulid)
    {

        $media = Media::where('ulid', $ulid)->firstOrFail();


        if ($media->model_type !== 'App\Models\Chat\ChatMessage') {
            abort(403);
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    public function asController(string $ulid)
    {
        return $this->handle($ulid);
    }
}
