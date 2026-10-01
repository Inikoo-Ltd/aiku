<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Writes some keys of a conversation's metadata in one statement, leaving the others as the
 * database holds them. The jobs a customer message starts (the reading, the urgent flag, the out
 * of hours reply, the draft) run side by side and each loaded the conversation before waiting on
 * a model: writing back the whole column put back a stale copy and lost what another had just
 * set, like the urgent flag of a cancel request. A null value removes the key.
 */
class SetChatSessionMetadata
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(ChatSession|MetaChatSession $chatSession, array $values): void
    {
        $set    = array_filter($values, fn ($value) => $value !== null);
        $remove = array_keys(array_diff_key($values, $set));

        DB::update(
            'update '.$chatSession->getTable()." set metadata = ((coalesce(metadata::jsonb, '{}'::jsonb) - ?::text[]) || ?::jsonb)::json, updated_at = ? where id = ?",
            ['{'.implode(',', array_map(fn (string $key) => '"'.$key.'"', $remove)).'}', json_encode((object) $set), now(), $chatSession->id]
        );

        $chatSession->metadata = array_merge(array_diff_key($chatSession->metadata ?? [], array_flip($remove)), $set);
        $chatSession->syncOriginalAttribute('metadata');
    }
}
