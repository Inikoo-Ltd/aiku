<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatMessage\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Chat\ChatMessage;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexChatMessagesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:chat_messages';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(ChatMessage::class, $reindex, $reset);
    }


}
