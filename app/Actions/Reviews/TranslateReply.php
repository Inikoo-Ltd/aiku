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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateReply implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave-low-priority';


    public function getJobUniqueId(Review $review, bool $override = false): string
    {
        return $review->id.'-'.($override ? 'o' : 'n');
    }


    public function handle(Review $review, bool $override = false): Review
    {
        if (!$review->replied) {
            return $review;
        }


        if (!$review->reply_language_id) {
            $review = DetectReviewReplyLanguage::run($review);
        }


        $languages    = Shop::where('is_aiku', true)->where('state', ShopStateEnum::OPEN)->pluck('language_id')->unique();
        $existing     = $review->translations['reply_message'] ?? [];
        $translations = [];
        foreach ($languages as $shopLanguageId) {
            if (!$override && !empty($existing[$shopLanguageId])) {
                $translations[$shopLanguageId] = $existing[$shopLanguageId];

                continue;
            }
            $shopLanguage = Language::find($shopLanguageId);
            $translation  = Translate::run($review->reply_message, $review->shop->language, $shopLanguage);
            if ($translation) {
                $translations[$shopLanguageId] = $translation;
            }
        }

        $currentTranslations = $review->translations;

        $currentTranslations['reply_message'] = $translations;

        $review->update(['translations' => $currentTranslations]);

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

        $reviews = Review::query()->whereNotNull('reply_message')
            ->orderByDesc('created_at');


        $totalReviews = $reviews->count();

        $command->info("Processing $totalReviews reviews...");


        $progressBar = $command->getOutput()->createProgressBar($totalReviews);
        $progressBar->setFormat('very_verbose');
        $progressBar->start();

        $processed = 0;

        $reviews->chunk(100, function ($reviewsChunk) use (&$processed, &$detected, $override, $progressBar) {
            foreach ($reviewsChunk as $review) {
                TranslateReply::dispatch($review, $override);
                $processed++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();

        $command->info("Processed: $processed reviews");
    }

}
