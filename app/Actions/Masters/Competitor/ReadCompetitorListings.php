<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 17:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Models\Masters\Competitor;
use App\Models\Masters\CompetitorProduct;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads the listing page of competitor products with the browser the weekly search uses (our trade login
 * when the competitor has one), and keeps the part of the page about the product, for the AI check on the
 * matches: assistants in the cloud are turned away by these websites, and the feed only keeps a short
 * description. Every product asked for is stamped as read, also when the page could not be opened, so a
 * listing that cannot be read is judged on what is stored instead of being asked for again every day.
 */
class ReadCompetitorListings implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public int $jobUniqueFor = 1800;

    public const int CLIP = 5000;

    private const int BEFORE_THE_NAME = 400;

    public function getJobUniqueId(Competitor $competitor): string
    {
        return (string) $competitor->id;
    }

    /**
     * @param  array<int, int>  $competitorProductIds
     */
    public function handle(Competitor $competitor, array $competitorProductIds): int
    {
        $products = CompetitorProduct::where('competitor_id', $competitor->id)->whereIn('id', $competitorProductIds)->whereNotNull('url')->get()->values();
        if ($products->isEmpty()) {
            return 0;
        }

        $read = 0;
        try {
            $result = ResearchCompetitorPrices::make()->browse($competitor, $products->pluck('url')->all());
            if (isset($result['cookies'])) {
                $competitor->update(['cookies' => $result['cookies']]);
            }

            foreach ($result['pages'] ?? [] as $index => $page) {
                $readable = !empty($page['text']) && !($page['blocked'] ?? false) && (int) ($page['status'] ?? 200) < 400;
                $products[$index]->update(['page_text' => $readable ? static::excerpt($page['text'], $products[$index]->name) : null, 'page_read_at' => now()]);
                $read += $readable ? 1 : 0;
            }
        } finally {
            CompetitorProduct::whereIn('id', $products->pluck('id'))->whereNull('page_read_at')->update(['page_read_at' => now()]);
        }

        return $read;
    }

    /**
     * The page from a little before the product's name on, so the menus above it are left out; the whole
     * start of the page when the name is not on it as written.
     */
    public static function excerpt(string $text, string $name): string
    {
        $words    = implode(' ', array_slice(preg_split('/\s+/', trim($name)), 0, 3));
        $position = $words === '' ? false : mb_stripos($text, $words);

        return trim(mb_substr($text, $position === false ? 0 : max(0, $position - self::BEFORE_THE_NAME), self::CLIP));
    }
}
