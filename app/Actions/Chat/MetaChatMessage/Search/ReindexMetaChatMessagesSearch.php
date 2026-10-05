<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatMessage\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Chat\MetaChatMessage;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexMetaChatMessagesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:meta_chat_messages';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(MetaChatMessage::class, $reindex, $reset);
    }


}
