<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DownloadChatAttachment
{
    use AsAction;

    /**
     * Inline serving backs the audio player in the chat bubble: a media element needs
     * a streamable response (byte ranges, inline disposition) rather than a download.
     */
    public function handle(string $ulid, bool $inline = false)
    {

        $media = Media::where('ulid', $ulid)->firstOrFail();


        if (!in_array($media->model_type, ['App\Models\Chat\ChatMessage', 'App\Models\Chat\MetaChatMessage'])) {
            abort(403);
        }

        if ($inline) {
            return response()->file($media->getPath(), ['Content-Type' => $media->mime_type]);
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    public function asController(string $ulid, ActionRequest $request)
    {
        return $this->handle($ulid, $request->boolean('inline'));
    }
}
