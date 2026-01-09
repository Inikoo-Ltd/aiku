<?php

namespace App\Actions\CRM\ChatSession;

use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsAction;

class DownloadChatAttachment
{
    use AsAction;

    public function handle(string $ulid)
    {

        $media = Media::where('ulid', $ulid)->firstOrFail();


        if ($media->model_type !== 'App\Models\CRM\Livechat\ChatMessage') {
            abort(403);
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    public function asController(string $ulid)
    {
        return $this->handle($ulid);
    }
}
