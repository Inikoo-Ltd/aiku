<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Chat\ChatMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchChat
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options = []): array
    {
        $messagesQuery = ChatMessage::search($query);
        if ($organisationId = Arr::get($options, 'organisation_id')) {
            $messagesQuery->where('organisation_id', $organisationId);
        }

        return [
            'scope'   => 'chat',
            'results' => [
                'chat_messages' => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => Str::limit((string)($document['message'] ?? ''), 120),
                    'name'  => str_replace('_', ' ', (string)($document['sender_type'] ?? '')),
                    'state' => null,
                ], $this->rawDocuments($messagesQuery)),
            ],
        ];
    }


}
