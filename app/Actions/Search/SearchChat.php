<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchChat
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options = []): array
    {
        $shopIds = Arr::get($options, 'shop_ids', []);

        if (empty($shopIds)) {
            return [
                'scope'   => 'chat',
                'results' => [
                    'chat_messages'     => [],
                    'whatsapp_messages' => [],
                ],
            ];
        }

        $organisationId = Arr::get($options, 'organisation_id');

        $messagesQuery = ChatMessage::search($query)->whereIn('shop_id', $shopIds);
        if ($organisationId) {
            $messagesQuery->where('organisation_id', $organisationId);
        }

        $whatsappMessagesQuery = MetaChatMessage::search($query)->whereIn('shop_id', $shopIds);
        if ($organisationId) {
            $whatsappMessagesQuery->where('organisation_id', $organisationId);
        }

        return [
            'scope'   => 'chat',
            'results' => [
                'chat_messages'     => array_map($this->toResultItem(...), $this->rawDocuments($messagesQuery)),
                'whatsapp_messages' => array_map($this->toResultItem(...), $this->rawDocuments($whatsappMessagesQuery)),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{id: int, code: string, name: string, state: null}
     */
    private function toResultItem(array $document): array
    {
        return [
            'id'    => (int)$document['id'],
            'code'  => Str::limit((string)($document['message'] ?? ''), 120),
            'name'  => str_replace('_', ' ', (string)($document['sender_type'] ?? '')),
            'state' => null,
        ];
    }
}
