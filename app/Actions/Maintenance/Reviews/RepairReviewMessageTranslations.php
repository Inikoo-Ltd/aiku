<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-16h-15m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Reviews;

use App\Models\Reviews\Review;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rewrites translations.message keyed by language id on the reviews where it is not (INI-283): an empty list that
 * TranslateReview saved when there was nothing to translate, a list of single-language objects the supplier portal
 * appended to on every view because it could not find the translation it had stored, or an object holding entries
 * that are not language id to text. The first text of each language is kept (Review::messageTranslations). The
 * language keys some older rows hold at the top of translations are left as they are: on some rows they translate
 * the reply, not the message. Only translations is written, so updated_at, audits and the search index are untouched.
 * Dry run unless --apply.
 */
class RepairReviewMessageTranslations
{
    use AsAction;

    public string $commandSignature = 'repair:review_message_translations
        {--apply : Rewrite the reviews; without it only the counts are shown}';

    public function handle(Review $review): void
    {
        DB::transaction(function () use ($review) {
            $review = Review::withTrashed()->lockForUpdate()->findOrFail($review->id);

            $translations            = $review->translations ?? [];
            $translations['message'] = (object) $review->messageTranslations();

            DB::table('reviews')->where('id', $review->id)->update(['translations' => json_encode($translations)]);
        });
    }

    public function reviewsToRepair(): Builder
    {
        return Review::withTrashed()->where(fn (Builder $query) => $query
            ->whereRaw("jsonb_typeof(translations->'message') <> 'object'")
            ->orWhereRaw(
                "exists (select 1 from jsonb_each(case when jsonb_typeof(translations->'message') = 'object' then translations->'message' else '{}'::jsonb end) as entry
                    where entry.key !~ '^[0-9]+$' or jsonb_typeof(entry.value) <> 'string')"
            ));
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shapes = $this->reviewsToRepair()
            ->toBase()
            ->selectRaw(
                "case
                    when translations->'message' = '[]'::jsonb then 'Empty list'
                    when jsonb_typeof(translations->'message') = 'array' then 'List of single-language objects'
                    when jsonb_typeof(translations->'message') = 'object' then 'Object with entries that are not language id to text'
                    else 'Not a JSON object'
                end as shape, count(*) as reviews"
            )
            ->groupBy('shape')
            ->orderBy('shape')
            ->get();

        $total = (int) $shapes->sum('reviews');
        $command->table(['translations.message', 'Reviews'], $shapes->map(fn ($row) => [$row->shape, $row->reviews])->all());

        if (!$command->option('apply')) {
            $command->info("Would repair $total reviews.");
            $command->line('Nothing was changed. Run again with --apply to rewrite them.');

            return 0;
        }

        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->start();
        $this->reviewsToRepair()->chunkById(500, function (Collection $reviews) use ($progressBar) {
            foreach ($reviews as $review) {
                $this->handle($review);
                $progressBar->advance();
            }
        });
        $progressBar->finish();
        $command->newLine();
        $command->info("Repaired $total reviews.");

        return 0;
    }
}
