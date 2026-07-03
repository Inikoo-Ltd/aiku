<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 14:57:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Helpers\Translations\Translate;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use App\Models\Reviews\Review;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class TranslateReview
{
    use AsAction;
    use AsCommand;

    public function handle(Review $review, bool $override = false): Review
    {
        if (!$review->language_id) {
            $review = DetectReviewLanguage::run($review);
        }


        $languages    = Shop::where('is_aiku', true)->where('state', ShopStateEnum::OPEN)->pluck('language_id')->unique();
        $translations = [];
        foreach ($languages as $shopLanguageId) {
            $shopLanguage = Language::find($shopLanguageId);
            $translation  = Translate::run($review->message, $review->shop->language, $shopLanguage);
            if ($translation) {
                $translations[$shopLanguageId] = $translation;
            }
        }

        $currentTranslations     = $review->translations;
        $translations['message'] = $currentTranslations;
        $review->update(['translations' => $translations]);

        return $review;
    }

    public function getCommandSignature(): string
    {
        return 'reviews:translate {--override : Override existing language detections}';
    }

    public function asCommand($command): void
    {
        $override = $command->option('override');


        $reviews = Review::query()->whereIn(
            'scope',
            [ReviewScopeEnum::PRODUCT, ReviewScopeEnum::FAMILY]
        )->orderByDesc('created_at');

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
