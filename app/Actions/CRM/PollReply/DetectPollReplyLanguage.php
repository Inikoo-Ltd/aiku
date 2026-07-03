<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 14:57:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\PollReply;

use App\Actions\Helpers\Translations\DetectLanguage;
use App\Models\CRM\PollReply;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class DetectPollReplyLanguage
{
    use AsAction;
    use AsCommand;

    public function handle(PollReply $pollReply, bool $override = false): PollReply
    {
        if ($pollReply->language_id && !$override) {
            return $pollReply;
        }

        $language = DetectLanguage::run($pollReply->value, $pollReply->poll->shop->language);
        if ($language) {
            $pollReply->update(
                [
                    'language_id' => $language->id,
                ]
            );
        }

        return $pollReply;
    }

    public function getCommandSignature(): string
    {
        return 'poll_replies:detect_language {--override : Override existing language detections}';
    }

    public function asCommand($command): void
    {
        $override = $command->option('override');

        $pollReplies = PollReply::query();

        if (!$override) {
            $pollReplies->whereNull('language_id');
        }

        $totalReviews = $pollReplies->count();

        $command->info("Processing $totalReviews poll replies...");

        $progressBar = $command->getOutput()->createProgressBar($totalReviews);
        $progressBar->start();

        $processed = 0;
        $detected  = 0;

        $pollReplies->chunk(100, function ($pollRepliesChunk) use (&$processed, &$detected, $override, $progressBar) {
            foreach ($pollRepliesChunk as $pollReply) {
                $originalLanguageId = $pollReply->language_id;
                $this->handle($pollReply, $override);

                /** @noinspection PhpConditionAlreadyCheckedInspection */
                if ($pollReply->language_id && $pollReply->language_id !== $originalLanguageId) {
                    $detected++;
                }

                $processed++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();

        $command->info("Processed: $processed poll replies.");
        $command->info("Languages detected: $detected poll replies.");
    }

}
