<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 14:57:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Helpers\Translations\DetectLanguage;
use App\Models\Reviews\Review;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class DetectReviewReplyLanguage
{
    use AsAction;
    use AsCommand;

    public function handle(Review $review, bool $override = false): Review
    {
        if ($review->reply_language_id && !$override) {
            return $review;
        }

        $language = DetectLanguage::run($review->reply_message, $review->shop->language);

        if ($language) {
            $review->update(
                [
                    'reply_language_id' => $language->id,
                ]
            );
        }
        return $review;

    }

    public function getCommandSignature(): string
    {
        return 'reviews:detect-reply-languages {--override : Override existing language detections}';
    }

    public function asCommand($command): void
    {
        $override = $command->option('override');

        $reviews = Review::query();

        if (!$override) {
            $reviews->whereNull('reply_language_id')->whereNotNull('reply_message');
        }

        $totalReviews = $reviews->count();

        $command->info("Processing $totalReviews reviews...");

        $progressBar = $command->getOutput()->createProgressBar($totalReviews);
        $progressBar->start();

        $processed = 0;
        $detected  = 0;

        $reviews->chunk(100, function ($reviewsChunk) use (&$processed, &$detected, $override, $progressBar) {
            foreach ($reviewsChunk as $review) {
                $originalLanguageId = $review->reply_language_id;
                $this->handle($review, $override);

                /** @noinspection PhpConditionAlreadyCheckedInspection */
                if ($review->reply_language_id && $review->reply_language_id !== $originalLanguageId) {
                    $detected++;
                }

                $processed++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();

        $command->info("Processed: $processed reviews");
        $command->info("Languages detected: $detected reviews");
    }

}
