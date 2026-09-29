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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Copies what a shop already says into its knowledge base, so the AI answers from short, current
 * entries instead of fetching whole pages: each section of the returns, delivery and terms pages,
 * what the shop's settings say (delivery prices per zone, blocked countries, offers, payment
 * methods) and, for dropshipping, the /docs guides. Run every night and on demand; entries staff
 * wrote are never touched.
 */
class HydrateChatKnowledge
{
    use AsAction;

    public string $commandSignature = 'chat:hydrate-knowledge {--s|shop= : Only this shop slug}';

    private const array PAGES = [WebpageSubTypeEnum::RETURNS, WebpageSubTypeEnum::SHIPPING, WebpageSubTypeEnum::TERMS_AND_CONDITIONS];

    private const int MAX_BODY = 1500;

    public function handle(Shop $shop): int
    {
        $now     = now();
        $entries = [
            ...$this->pageSections($shop),
            ...collect(GetChatShopFacts::make()->shopFacts($shop))->map(fn (array $fact) => $fact + ['kind' => 'shop_fact', 'source_type' => 'shop_settings', 'source_id' => (string) $shop->id, 'url' => null])->all(),
            ...$this->guides($shop),
        ];

        DB::transaction(function () use ($shop, $entries, $now) {
            ChatKnowledgeEntry::where('shop_id', $shop->id)->where('is_manual', false)->delete();

            foreach ($entries as $entry) {
                ChatKnowledgeEntry::create($entry + [
                    'group_id'        => $shop->group_id,
                    'organisation_id' => $shop->organisation_id,
                    'shop_id'         => $shop->id,
                    'hydrated_at'     => $now,
                ]);
            }
        });

        return count($entries);
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
