<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 14:57:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Helpers\Translations\Translate;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use App\Models\Reviews\Review;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class TranslateReply
{
    use AsAction;
    use AsCommand;

    public function handle(Review $review, bool $override = false): Review
    {
        if (!$review->replied) {
            return $review;
        }


        if (!$review->reply_language_id) {
            $review = DetectReviewReplyLanguage::run($review);
        }


        $languages    = Shop::where('is_aiku', true)->where('state', ShopStateEnum::OPEN)->pluck('language_id')->unique();
        $translations = [];
        foreach ($languages as $shopLanguageId) {
            $shopLanguage = Language::find($shopLanguageId);
            $translation  = Translate::run($review->reply_message, $review->shop->language, $shopLanguage);
            if ($translation) {
                $translations[$shopLanguageId] = $translation;
            }
        }

        $currentTranslations = $review->translations;


        $translations['reply_message'] = $currentTranslations;

        $review->update(['translations' => $translations]);

        return $review;
    }

    public function getCommandSignature(): string
    {
        return 'reviews:translate-reply-languages {--override : Override existing language detections}';
    }

    public function asCommand($command): void
    {
        $override = $command->option('override');

        $reviews = Review::query();


        $reviews->whereNotNull('reply_message');


        $totalReviews = $reviews->count();

        $command->info("Processing $totalReviews reviews...");

        
        $progressBar = $command->getOutput()->createProgressBar($totalReviews);
        $progressBar->setFormat('very_verbose');
        $progressBar->start();

        $processed = 0;

        $reviews->chunk(100, function ($reviewsChunk) use (&$processed, &$detected, $override, $progressBar) {
            foreach ($reviewsChunk as $review) {
                $this->handle($review, $override);

                $processed++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();

        $command->info("Processed: $processed reviews");
    }

}
