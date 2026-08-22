<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Actions\Helpers\Translations\Translate;
use App\Events\StaffMessageSent;
use App\Models\Chat\StaffMessage;
use App\Models\Chat\StaffMessageTranslation;
use App\Models\Helpers\Language;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateStaffMessage
{
    use AsAction;

    public string $jobQueue = 'translate-chat';
    public int $jobTries = 1;

    public function handle(int $messageId): void
    {
        $message = StaffMessage::with('conversation.participants')->find($messageId);
        if (!$message || !$message->language_id || $message->body === '') {
            return;
        }

        $from = Language::find($message->language_id);
        $targetLanguageIds = $message->conversation->participants
            ->pluck('language_id')
            ->filter()
            ->unique()
            ->reject(fn (int $languageId) => $languageId === $message->language_id);

        if ($targetLanguageIds->isEmpty()) {
            return;
        }

        foreach ($targetLanguageIds as $languageId) {
            $translated = Translate::run($message->body, $from, Language::find($languageId));
            if ($translated === $message->body) {
                continue;
            }
            StaffMessageTranslation::upsert(
                [['staff_message_id' => $message->id, 'language_id' => $languageId, 'body' => $translated, 'created_at' => now(), 'updated_at' => now()]],
                ['staff_message_id', 'language_id'],
                ['body', 'updated_at']
            );
        }

        if ($message->translations()->exists()) {
            StaffMessageSent::dispatch($message, 'staff-message-translated');
        }
    }
}
