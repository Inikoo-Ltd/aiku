<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Enums\Masters\Competitor\CompetitorStatusEnum;
use App\Models\Masters\Competitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Reads a competitor's product feed (a CSV or tab separated file they publish for their trade
 * customers) into competitor_products, then queues the matching of our products against it.
 * Columns are found by their header, so a feed with the usual names needs no setup.
 */
class ImportCompetitorFeed
{
    use AsAction;

    public string $commandSignature = 'masters:competitor-feeds {--competitor= : Only this competitor id} {--no-match : Only read the feeds} {--sync : Match in this process instead of queueing}';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 900;

    public const array COLUMNS = [
        'code'          => '/^(item ?no|item ?number|item ?code|sku|code|product ?code|reference)$/i',
        'name'          => '/^(item ?name|name|title|product ?name)$/i',
        'price'         => '/^(price|trade ?price|wholesale ?price|unit ?price)$/i',
        'rrp'           => '/^(rrp|retail ?price|msrp|recommended ?retail ?price)$/i',
        'barcode'       => '/^(barcode|ean|ean13|gtin|upc)$/i',
        'minimum_order' => '/^(min ?order ?qty|minimum ?order|moq|min ?qty)$/i',
        'image_url'     => '/^(url ?links|image|image ?url|image ?link|picture)$/i',
        'url'           => '/^(url|link|product ?url|product ?link)$/i',
        'discontinued'  => '/^discontinued$/i',
    ];

    public function handle(Competitor $competitor): int
    {
        $startedAt = now();

        try {
            $rows = static::parse(Http::timeout(120)->get($competitor->feed_url)->throw()->body());
        } catch (Throwable $exception) {
            $competitor->update(['status' => CompetitorStatusEnum::ERROR, 'last_error' => mb_substr($exception->getMessage(), 0, 500), 'fetched_at' => now()]);

            return 0;
        }

        if (!$rows) {
            $competitor->update(['status' => CompetitorStatusEnum::ERROR, 'last_error' => __('The feed has no products or its columns were not recognised'), 'fetched_at' => now()]);

            return 0;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $competitor->products()->upsert(
                array_map(fn (array $row) => $row + [
                    'competitor_id' => $competitor->id,
                    'fetched_at'    => $startedAt,
                    'created_at'    => $startedAt,
                    'updated_at'    => $startedAt,
                ], $chunk),
                ['competitor_id', 'code'],
                ['url', 'image_url', 'name', 'barcode', 'price', 'rrp', 'minimum_order', 'fetched_at', 'updated_at']
            );
        }

        $competitor->products()->where('fetched_at', '<', $startedAt)->delete();

        $competitor->update([
            'status'          => CompetitorStatusEnum::OK,
            'last_error'      => null,
            'fetched_at'      => $startedAt,
            'number_products' => $competitor->products()->count(),
        ]);

        return count($rows);
    }

    /**
     * Products with no code, name or price and discontinued ones are left out.
     *
     * @return array<int, array{code: string, name: string, price: float, rrp: float|null, barcode: string|null, minimum_order: int|null, image_url: string|null, url: string|null}>
     */
    public static function parse(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $content)));
        if (count($lines) < 2) {
            return [];
        }

        $delimiter = collect(["\t", ';', ','])->sortByDesc(fn ($candidate) => substr_count($lines[0], $candidate))->first();
        $header    = array_map('trim', str_getcsv(array_shift($lines), $delimiter, '"', ''));

        $positions = [];
        foreach (self::COLUMNS as $field => $pattern) {
            $position = collect($header)->search(fn ($title) => preg_match($pattern, $title));
            if ($position !== false) {
                $positions[$field] = $position;
            }
        }

        if (!isset($positions['code'], $positions['name'], $positions['price'])) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line, $delimiter, '"', '');
            $cell  = fn (string $field) => isset($positions[$field]) ? trim($cells[$positions[$field]] ?? '') : '';
            $price = (float) str_replace(',', '.', $cell('price'));

            if ($cell('code') === '' || $cell('name') === '' || $price <= 0 || strtolower($cell('discontinued')) === 'true') {
                continue;
            }

            $rows[$cell('code')] = [
                'code'          => mb_substr($cell('code'), 0, 255),
                'name'          => mb_substr($cell('name'), 0, 500),
                'price'         => $price,
                'rrp'           => (float) str_replace(',', '.', $cell('rrp')) ?: null,
                'barcode'       => mb_substr($cell('barcode'), 0, 255) ?: null,
                'minimum_order' => (int) $cell('minimum_order') ?: null,
                'image_url'     => str_starts_with($cell('image_url'), 'http') ? $cell('image_url') : null,
                'url'           => str_starts_with($cell('url'), 'http') ? $cell('url') : null,
            ];
        }

        return array_values($rows);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $competitors = Competitor::whereNotNull('feed_url')
            ->when($command->option('competitor'), fn ($query, $id) => $query->where('id', $id))
            ->get();

        foreach ($competitors as $competitor) {
            $command->info("$competitor->name: ".$this->handle($competitor).' products in the feed');

            if (!$command->option('no-match') && $competitor->status === CompetitorStatusEnum::OK) {
                $command->info("$competitor->name: ".MatchCompetitorProducts::make()->queue($competitor, sync: (bool) $command->option('sync')).' products to match');
            }
        }

        return 0;
    }
}
