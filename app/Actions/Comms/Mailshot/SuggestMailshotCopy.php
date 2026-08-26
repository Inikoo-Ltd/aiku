<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Aug 2026 12:00:00 Central European Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Helpers\AI\AskToAi;
use App\Actions\OrgAction;
use App\Models\Comms\Mailshot;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class SuggestMailshotCopy extends OrgAction
{
    public function handle(Mailshot $mailshot): ?array
    {
        $email  = $mailshot->email;
        $source = $email?->liveSnapshot?->compiled_layout ?: json_encode($email?->unpublishedSnapshot?->layout);

        $content = trim(mb_substr(strip_tags((string) $source), 0, 6000));
        if (blank($content)) {
            return null;
        }

        $prompt = 'You write email marketing copy. Based on the newsletter content below, respond with ONLY a JSON object, no markdown fences: '
            .'{"subject": "...", "preview_text": "...", "name": "..."}. '
            .'Write in the same language as the content. subject: engaging, under 60 characters. '
            .'preview_text: complements the subject, under 110 characters. name: short internal reference name for this mailshot. '
            ."\n\nContent:\n".$content;

        $answer = AskToAi::make()->handle($prompt);
        if (!$answer) {
            return null;
        }

        $answer = trim(preg_replace('/^```(json)?|```$/m', '', $answer));
        $result = json_decode($answer, true);

        if (!is_array($result) || blank($result['subject'] ?? null)) {
            return null;
        }

        return [
            'subject'      => (string) $result['subject'],
            'preview_text' => (string) ($result['preview_text'] ?? ''),
            'name'         => (string) ($result['name'] ?? ''),
        ];
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): ?array
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot);
    }

    public function jsonResponse(?array $suggestion): JsonResponse
    {
        if (!$suggestion) {
            return response()->json(['message' => __('Could not generate a suggestion')], 422);
        }

        return response()->json($suggestion);
    }
}
