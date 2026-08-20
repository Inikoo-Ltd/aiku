<?php

namespace App\Actions\Chat\MetaChatSession;

use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreMetaChatMessage
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $modelData
     */
    public function handle(MetaChatSession $metaChatSession, array $modelData): MetaChatMessage
    {
        data_set($modelData, 'meta_channel_id', $metaChatSession->meta_channel_id);
        data_set($modelData, 'meta_chat_session_id', $metaChatSession->id);
        data_set($modelData, 'is_read', Arr::get($modelData, 'is_read', false));

        return MetaChatMessage::create($modelData);
    }
}
