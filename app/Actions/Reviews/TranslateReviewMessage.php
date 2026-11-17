<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Reviews;

use App\Actions\Helpers\Translations\Translate;
use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesProducts;
use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use App\Models\Helpers\Language;
use App\Models\Reviews\Review;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A review's message in one language, translated once and kept in reviews.translations.message beside the shop
 * languages TranslateReview fills, so the supplier portal can show reviews in a supplier's language. The key is
 * written on its own, so a TranslateReview running at the same time keeps it. The backfill command takes only the
 * reviews the portal can quote (GetAgentSalesProducts::publicReviews); any other review is translated when first shown.
 */
class TranslateReviewMessage implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'translate';

    public string $commandSignature = 'reviews:translate-supplier-portal
        {--language=* : Supplier portal language codes to translate into; all of them when left out}
        {--apply : Queue the translations; without it only the counts are shown}';

    public function getJobUniqueId(Review $review, Language $language): string
    {
        return $review->id.'-'.$language->id;
    }

    public function handle(Review $review, Language $language): ?string
    {
        $stored = data_get($review->translations, 'message.'.$language->id);
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $message = trim((string) $review->message);
        if ($message === '') {
            return null;
        }

        $source = $review->language ?? $review->shop->language;
        if ($source->id === $language->id) {
            return $review->message;
        }

        $translated = Translate::run($review->message, $source, $language);
        if (trim($translated) === '' || $translated === $review->message) {
            return null;
        }

        DB::update(
            "update reviews set translations = coalesce(translations, '{}'::jsonb) || jsonb_build_object('message', coalesce(translations->'message', '{}'::jsonb) || jsonb_build_object(?::text, ?::text)) where id = ?",
            [(string) $language->id, $translated, $review->id]
        );

        return $translated;
    }

    /**
     * @param  array<int, string>  $codes
     *
     * @return Collection<int, Language>
     */
    public function portalLanguages(array $codes = []): Collection
    {
        $portal = ShowSupplierPortalLogin::languages();

        return Language::whereIn('code', $codes === [] ? $portal : array_intersect($codes, $portal))->get();
    }

    public function missing(Language $language): Builder
    {
        return Review::query()
            ->whereIn('id', GetAgentSalesProducts::make()->publicReviews()->select('review.id'))
            ->whereRaw("trim(coalesce(message, '')) <> ''")
            ->where(fn (Builder $query) => $query->whereNull('language_id')->orWhere('language_id', '<>', $language->id))
            ->whereRaw("coalesce(translations->'message'->>?, '') = ''", [(string) $language->id]);
    }

    public function asCommand(Command $command): int
    {
        $languages = $this->portalLanguages($command->option('language'));
        if ($languages->isEmpty()) {
            $command->error('No supplier portal language matches. Use any of: '.implode(', ', ShowSupplierPortalLogin::languages()));

            return 1;
        }

        $apply = (bool) $command->option('apply');
        $rows  = $languages->map(fn (Language $language) => [$language->code, $language->name, $this->missing($language)->count()]);
        $command->table(['Code', 'Language', 'Reviews to translate'], $rows->all());
        $total = $rows->sum(fn (array $row) => $row[2]);

        if (!$apply) {
            $command->info("Would queue $total translations.");
            $command->line('Nothing was queued. Run again with --apply to queue them on the translate queue.');

            return 0;
        }

        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->start();
        foreach ($languages as $language) {
            $this->missing($language)->chunkById(500, function (Collection $reviews) use ($language, $progressBar) {
                foreach ($reviews as $review) {
                    TranslateReviewMessage::dispatch($review, $language);
                    $progressBar->advance();
                }
            });
        }
        $progressBar->finish();
        $command->newLine();
        $command->info("Queued $total translations on the translate queue.");

        return 0;
    }
}
