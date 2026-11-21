<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Normalizer;

/**
 * Keeps slack_messages.search_vector: the readable text of a message (FormatSlackMessages::plainText, so bot blocks,
 * attachments and mention names count, plus file names), lower-cased here because the database lower-cases ASCII
 * only, with accents removed and a second copy split at every non-letter, so "104233" finds "ORD-104233".
 * Built with the `simple` configuration: staff write in several languages and no stemmer fits them all.
 * Saving a message refreshes it; this command fills the rows that have none, 500 at a time, and can be stopped and
 * run again.
 */
class RefreshSlackMessageSearch
{
    use AsAction;

    public string $commandSignature = 'slack:refresh-message-search {--all : rebuild every message, not only those without a search entry}';

    public const int CHUNK = 500;

    private const int MAX_CHARACTERS = 50000;

    /**
     * @param  Collection<int, SlackMessage>  $messages  loaded with id, text and data
     */
    public function handle(Collection $messages, bool $onlyMissing = false): int
    {
        if ($messages->isEmpty()) {
            return 0;
        }

        $nodes = FormatSlackMessages::make()->handle($messages);
        $rows  = $messages->map(fn (SlackMessage $message) => [
            'id'   => $message->id,
            'body' => self::searchBody(self::readableText($nodes[$message->id] ?? [], $message->data ?? [])),
        ])->values()->all();

        return DB::update(
            "UPDATE slack_messages m SET search_vector = to_tsvector('simple', extensions.remove_accents(v.body))
            FROM jsonb_to_recordset(?::jsonb) AS v(id bigint, body text)
            WHERE m.id = v.id".($onlyMissing ? ' AND m.search_vector IS NULL' : ''),
            [json_encode($rows, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)]
        );
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $data
     */
    public static function readableText(array $nodes, array $data): string
    {
        $fileNames = collect(is_array($data['files'] ?? null) ? $data['files'] : [])
            ->filter(fn ($file) => is_array($file))
            ->map(fn (array $file) => implode(' ', array_unique(array_filter([$file['name'] ?? null, $file['title'] ?? null], fn ($value) => is_string($value) && $value !== ''))))
            ->filter()
            ->implode("\n");

        return self::clean(trim(FormatSlackMessages::plainText($nodes)."\n".$fileNames));
    }

    public static function clean(string $text): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', mb_scrub($text, 'UTF-8'));
    }

    /**
     * Lower case and without accents, the form both the stored text and a query are compared in.
     */
    public static function normalise(string $text): string
    {
        $decomposed = Normalizer::normalize(mb_strtolower(self::clean($text)), Normalizer::FORM_D);

        return (string) preg_replace('/\p{Mn}+/u', '', $decomposed === false ? mb_strtolower($text) : $decomposed);
    }

    private static function searchBody(string $text): string
    {
        $lower = mb_substr(mb_strtolower($text), 0, self::MAX_CHARACTERS);

        return $lower."\n".preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $all   = (bool) $command->option('all');
        $done  = 0;
        $query = SlackMessage::withTrashed()->select(['id', 'text', 'data']);
        if (!$all) {
            $query->whereNull('search_vector');
        }

        $query->chunkById(self::CHUNK, function (Collection $messages) use ($all, &$done, $command) {
            $done += $this->handle($messages, onlyMissing: !$all);
            $command->info("Messages indexed: $done");
        });

        $command->info("Done, messages indexed: $done, still without a search entry: ".SlackMessage::withTrashed()->whereNull('search_vector')->count());

        return 0;
    }
}
