<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Helpers\Ticket\StoreTicket;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChatMessage;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTicketFromWhatsapp
{
    use AsAction;

    /**
     * @param  string  $body  The message text with the `/ticket` prefix already stripped
     */
    public function handle(Shop $shop, MetaChatMessage $metaChatMessage, User $reporter, string $body): Ticket
    {
        [$subject, $description] = $this->splitBody($body);

        $modelData = [
            'type'            => TicketTypeEnum::HELP->value,
            'kind'            => TicketKindEnum::BUG->value,
            'subject'         => $subject,
            'description'     => $description,
            'organisation_id' => $shop->organisation_id,
            'shop_id'         => $shop->id,
            'reporter_type'   => 'User',
            'reporter_id'     => $reporter->id,
            'model_type'      => 'MetaChatSession',
            'model_id'        => $metaChatMessage->meta_chat_session_id,
            'data'            => [
                'whatsapp' => [
                    'phone_number'         => $metaChatMessage->metaChatSession?->phone_number,
                    'meta_message_id'      => $metaChatMessage->meta_message_id,
                    'meta_chat_session_id' => $metaChatMessage->meta_chat_session_id,
                ],
            ],
        ];

        if (preg_match('#https?://[^\s<>|]+#', $body, $match)) {
            $modelData['reference_url'] = rtrim($match[0], '>.,)');
        }

        return StoreTicket::make()->action($shop->group, array_filter($modelData, fn ($value) => $value !== null));
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function splitBody(string $body): array
    {
        $parts       = preg_split('/\R/', trim($body), 2);
        $subject     = Str::limit(trim($parts[0]), 255, '');
        $description = trim($parts[1] ?? '');

        return [$subject, $description ?: null];
    }
}
