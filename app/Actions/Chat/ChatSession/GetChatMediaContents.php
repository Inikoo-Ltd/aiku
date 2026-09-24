<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Helpers\Media;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatMediaContents
{
    use AsAction;

    public function handle(Media $media): string
    {
        if (!$media->getCustomProperty('archived_at')) {
            return (string) stream_get_contents($media->stream());
        }

        $encoded = DB::connection('archive')
            ->table(ArchiveChatMedia::ARCHIVE_TABLE)
            ->where('media_id', $media->id)
            ->selectRaw("encode(contents, 'base64') as contents")
            ->value('contents');

        return (string) base64_decode(preg_replace('/\s+/', '', (string) $encoded));
    }
}
