<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 02:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Iris\Docs\ShowIrisDocs;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\Web\Webpage;
use App\Actions\Helpers\AI\EmbedTexts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Copies what a shop already says into its knowledge base, so the AI answers from short, current
 * entries instead of fetching whole pages: each section of the returns, delivery and terms pages,
 * what the shop's settings say (delivery prices per zone, blocked countries, offers, payment
 * methods) and, for dropshipping, the /docs guides. Run every hour and on demand; entries staff
 * wrote are never touched.
 *
 * Every entry of the shop is embedded, so the AI shopping assistant finds the ones that answer a
 * question by meaning (SearchShopKnowledge). The copied entries are deleted and created again on
 * every run, so an entry whose text did not change keeps the embedding it had (found by a hash of
 * its title and body) and is never embedded twice. The embeddings API is never called inside the
 * transaction: embedMissing runs after it, also for staff notes, verified facts and learned rules,
 * and leaves what it cannot embed for the next run.
 */
class HydrateChatKnowledge
{
    use AsAction;

    public string $commandSignature = 'chat:hydrate-knowledge {--s|shop= : Only this shop slug}';

    private const array PAGES = [WebpageSubTypeEnum::RETURNS, WebpageSubTypeEnum::SHIPPING, WebpageSubTypeEnum::TERMS_AND_CONDITIONS];

    private const int MAX_BODY = 1500;

    private const int EMBED_CHUNK = 50;

    private const int MAX_EMBEDDED_TEXT = 2000;

    public function handle(Shop $shop): int
    {
        $now     = now();
        $entries = [
            ...$this->pageSections($shop),
            ...collect(GetChatShopFacts::make()->shopFacts($shop))->map(fn (array $fact) => $fact + ['kind' => 'shop_fact', 'source_type' => 'shop_settings', 'source_id' => (string) $shop->id, 'url' => null])->all(),
            ...$this->guides($shop),
        ];

        DB::transaction(function () use ($shop, $entries, $now) {
            $copied = ChatKnowledgeEntry::where('shop_id', $shop->id)->whereIn('source_type', ['webpage', 'shop_settings', 'docs']);

            $kept = (clone $copied)->whereNotNull('embedding')->get(['title', 'body', 'embedding'])
                ->mapWithKeys(fn (ChatKnowledgeEntry $entry) => [$this->textHash($entry->title, $entry->body) => $entry->embedding])
                ->all();

            $copied->delete();

            foreach ($entries as $entry) {
                ChatKnowledgeEntry::create($entry + [
                    'group_id'        => $shop->group_id,
                    'organisation_id' => $shop->organisation_id,
                    'shop_id'         => $shop->id,
                    'hydrated_at'     => $now,
                    'embedding'       => $kept[$this->textHash($entry['title'], $entry['body'])] ?? null,
                ]);
            }
        });

        $this->embedMissing($shop);

        return count($entries);
    }

    /**
     * Embeds every entry that applies to the shop (its own, its organisation's and the group's) and
     * has no embedding yet, in chunks of 50 texts. When the embeddings call fails or there is no key
     * the entries stay without one and the next run tries again; it never throws.
     *
     * @return int how many entries got an embedding
     */
    public function embedMissing(Shop $shop): int
    {
        $embedded = 0;

        try {
            ChatKnowledgeEntry::forShop($shop)->whereNull('embedding')->select(['chat_knowledge_entries.id', 'chat_knowledge_entries.title', 'chat_knowledge_entries.body'])->orderBy('chat_knowledge_entries.id')->get()
                ->chunk(self::EMBED_CHUNK)
                ->each(function ($entries) use (&$embedded) {
                    $entries = $entries->values();
                    $vectors = EmbedTexts::run($entries->map(fn (ChatKnowledgeEntry $entry) => mb_substr($entry->title."\n".$entry->body, 0, self::MAX_EMBEDDED_TEXT))->all(), true);

                    if ($vectors === null) {
                        return;
                    }

                    foreach ($entries as $index => $entry) {
                        if (isset($vectors[$index])) {
                            $entry->update(['embedding' => $vectors[$index]]);
                            $embedded++;
                        }
                    }
                });
        } catch (Throwable $exception) {
            report($exception);
        }

        return $embedded;
    }

    private function textHash(string $title, string $body): string
    {
        return sha1($title."\n".$body);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageSections(Shop $shop): array
    {
        if (!$shop->website) {
            return [];
        }

        return Webpage::where('website_id', $shop->website->id)
            ->whereIn('sub_type', self::PAGES)
            ->where('state', WebpageStateEnum::LIVE)
            ->get()
            ->flatMap(fn (Webpage $webpage) => collect(self::sections($webpage->published_layout ?? [], (string) $webpage->title))
                ->map(fn (array $section) => $section + [
                    'kind'        => 'policy',
                    'url'         => $webpage->canonical_url ?: 'https://'.ltrim($shop->website->domain, '/').'/'.$webpage->url,
                    'source_type' => 'webpage',
                    'source_id'   => (string) $webpage->id,
                ]))
            ->values()
            ->all();
    }

    /**
     * A page cut at its headings into sections of plain text, each titled by its heading.
     *
     * @param  array<mixed>  $layout
     * @return array<int, array{title: string, body: string}>
     */
    public static function sections(array $layout, string $pageTitle): array
    {
        $html = [];

        array_walk_recursive($layout, function ($value) use (&$html) {
            if (is_string($value) && str_contains($value, '<')) {
                $html[] = $value;
            }
        });

        $parts    = preg_split('/(<h[1-4][^>]*>.*?<\/h[1-4]>)/is', implode("\n", $html), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $sections = [];
        $title    = $pageTitle;

        foreach ($parts as $part) {
            if (preg_match('/^<h[1-4]/i', $part)) {
                $title = trim(html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5)) ?: $pageTitle;

                continue;
            }

            $body = GetShopPageText::text([$part]);

            if (mb_strlen($body) >= 40) {
                foreach (mb_str_split($body, self::MAX_BODY) as $index => $chunk) {
                    $sections[] = ['title' => mb_substr($pageTitle.' · '.$title.($index ? ' ('.($index + 1).')' : ''), 0, 250), 'body' => $chunk];
                }
            }
        }

        return $sections;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function guides(Shop $shop): array
    {
        if ($shop->type !== ShopTypeEnum::DROPSHIPPING || !$shop->website?->domain) {
            return [];
        }

        return collect(ShowIrisDocs::make()->handle($shop->website))
            ->map(fn (array $doc) => [
                'kind'        => 'guide',
                'title'       => mb_substr($doc['title'], 0, 250),
                'body'        => $doc['summary'],
                'url'         => 'https://'.ltrim($shop->website->domain, '/').$doc['url'],
                'source_type' => 'docs',
                'source_id'   => $doc['slug'],
            ])
            ->all();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shops = Shop::where('state', ShopStateEnum::OPEN)
            ->when($command->option('shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        foreach ($shops as $shop) {
            $command->info($shop->slug.': '.$this->handle($shop).' entries');
        }

        return 0;
    }
}
