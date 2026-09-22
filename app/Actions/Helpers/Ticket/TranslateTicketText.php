<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Translations\DetectLanguageWithAI;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class TranslateTicketText extends OrgAction
{
    /**
     * @return array{text: string, language: string|null}
     */
    public function handle(string $cacheKey, ?string $text, User $user): array
    {
        $language = $user->language;
        $body     = trim((string)$text);

        if ($body === '') {
            return ['text' => '', 'language' => null];
        }

        return Cache::remember($cacheKey.':'.$language->id, now()->addDay(), function () use ($body, $language) {
            $from = DetectLanguageWithAI::run($body);

            if (!$from || $from->id === $language->id) {
                return ['text' => $body, 'language' => $from?->name];
            }

            return [
                'text'     => Translate::run($body, $from, $language),
                'language' => $from->name,
            ];
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() instanceof User;
    }

    public function asController(TicketComment $ticketComment, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromGroup($ticketComment->ticket->group, $request);

        return response()->json($this->handle(
            'ticket-comment-translation:'.$ticketComment->id.':'.$ticketComment->updated_at?->timestamp,
            $ticketComment->body,
            $request->user()
        ));
    }

    public function inTicket(Ticket $ticket, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromGroup($ticket->group, $request);

        return response()->json($this->handle(
            'ticket-description-translation:'.$ticket->id.':'.$ticket->updated_at?->timestamp,
            $ticket->description,
            $request->user()
        ));
    }
}
