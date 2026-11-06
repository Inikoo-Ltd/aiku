<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 18:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\Helpers\AI\AskToAi;
use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Masters\Competitor\UI\WithCompetitorPriceComparison;
use App\Models\Masters\Competitor;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The competitor dossier: a headless browser opens the competitor's public homepage and its shipping,
 * returns and terms pages, the AI pulls the facts out of them (each with the page it came from) and
 * writes a short briefing against our numbers, and every refresh is kept as a snapshot so what changed
 * since the one before is shown. Public pages only, never the trade login. Weekly, when a competitor is
 * added, and from the Refresh dossier button.
 */
class FetchCompetitorIntel implements ShouldBeUnique
{
    use AsAction;
    use WithCompetitorPriceComparison;

    public string $commandSignature = 'masters:competitor-intel {--competitor= : Only this competitor id} {--sync : Run in this process instead of queueing}';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 900;

    public int $jobUniqueFor = 900;

    private const int HOMEPAGE_CLIP = 6000;

    private const int PAGE_CLIP = 5000;

    private const array NOT_FOUND = ['', 'not found', 'none', 'null', 'n/a', 'unknown'];

    public const array CHANGE_LABELS = [
        'free_shipping_threshold' => 'Free-shipping threshold',
        'shipping_costs'          => 'Shipping costs',
        'delivery_times'          => 'Delivery times',
        'minimum_order'           => 'Minimum order',
        'returns_window'          => 'Returns window',
        'offer_added'             => 'Offer added',
        'offer_ended'             => 'Offer ended',
        'payment_added'           => 'Payment method added',
        'payment_removed'         => 'Payment method removed',
        'phones'                  => 'Phone',
        'emails'                  => 'Email',
        'address'                 => 'Address',
        'platform'                => 'Platform',
        'number_products'         => 'Products listed',
        'share_on_sale'           => 'Share of products on sale',
    ];

    public function getJobUniqueId(Competitor $competitor): string
    {
        return (string) $competitor->id;
    }

    public function handle(Competitor $competitor): ?array
    {
        $fetchedAt = now();
        $result    = $this->browse($competitor);

        $error = match (true) {
            isset($result['error']) && !$result['homepage'] => $result['error'],
            !$result['homepage']                             => __('The browser did not answer'),
            $result['homepage']['blocked']                   => __('The website asked to prove we are not a robot'),
            ($result['homepage']['status'] ?? 0) >= 400      => __('The website answered :status', ['status' => $result['homepage']['status']]),
            default                                          => null,
        };

        if ($error) {
            $competitor->update(['intel_error' => mb_substr($error, 0, 500)]);

            return null;
        }

        if ($result['screenshot'] ?? null) {
            $this->saveScreenshot($competitor, $result['screenshot']);
        }

        $pages = collect($result['pages'])->filter(fn ($page) => filled($page['text'] ?? null))->values()->all();
        $facts = $this->extractFacts($competitor, $result['homepage'], $pages);
        if ($facts === null) {
            $competitor->update(['intel_error' => __('The AI did not answer')]);

            return null;
        }

        $sources  = [$result['homepage']['final_url'], ...array_map(fn ($page) => $page['final_url'] ?? $page['url'], $pages)];
        $facts    = static::withKnownSources($facts, $sources);
        $section  = fn (string $key): array => is_array($facts[$key] ?? null) ? $facts[$key] : [];
        $numbers  = static::numbers($competitor);
        $previous = $competitor->intelSnapshots()->latest('fetched_at')->latest('id')->first();

        $intel = [
            'homepage'  => Arr::only($result['homepage'], ['final_url', 'title', 'description', 'platform', 'logo', 'banners', 'coupons']),
            'identity'  => static::identity($result['homepage'], $section('identity')),
            'shipping'  => static::facts($section('shipping'), ['costs', 'zones', 'free_shipping_threshold', 'delivery_times']),
            'minimum_order' => static::facts($section('minimum_order'), ['value']),
            'returns'   => static::facts($section('returns'), ['window']),
            'payments'  => ['methods' => static::stringList($section('payments')['methods'] ?? []), 'source' => static::source($section('payments'))],
            'offers'    => static::offers($facts['offers'] ?? []),
            'numbers'   => $numbers,
            'pages'     => array_map(fn ($page) => ['url' => $page['final_url'] ?? $page['url'], 'title' => $page['title'] ?? '', 'text' => mb_substr($page['text'], 0, 8000)], $pages),
        ];

        $changes = static::changes($previous?->intel ?? [], $intel);
        $brief   = $this->brief($competitor, $intel, $changes);
        $intel  += $brief['briefing'] === null ? Arr::only($previous?->intel ?? [], ['briefing', 'positioning']) + $brief : $brief;

        $competitor->intelSnapshots()->create(['fetched_at' => $fetchedAt, 'intel' => $intel, 'changes' => $changes]);
        $competitor->update(['intel' => $intel, 'intel_fetched_at' => $fetchedAt, 'intel_error' => null]);

        return $intel;
    }

    private function browse(Competitor $competitor): array
    {
        try {
            $process = Process::timeout(600)
                ->input(json_encode(['user_agent' => ResearchCompetitorPrices::USER_AGENT, 'website' => $competitor->website]))
                ->run(['node', resource_path('node/competitor-intel.cjs')]);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage(), 'homepage' => null, 'pages' => []];
        }

        $result = json_decode($process->output(), true);

        return (is_array($result) ? $result : []) + ['error' => trim($process->errorOutput()) ?: __('The browser did not answer'), 'homepage' => null, 'pages' => []];
    }

    private function saveScreenshot(Competitor $competitor, string $base64): void
    {
        $path = tempnam(sys_get_temp_dir(), 'competitor').'.jpg';
        file_put_contents($path, base64_decode($base64));

        try {
            SaveModelImage::run(model: $competitor, imageData: ['path' => $path, 'originalName' => Str::slug($competitor->name).'-homepage.jpg', 'extension' => 'jpg']);
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Null when the AI did not answer: the dossier keeps its last content rather than storing empties.
     */
    private function extractFacts(Competitor $competitor, array $homepage, array $pages): ?array
    {
        $texts = "PAGE {$homepage['final_url']} (homepage)\n".mb_substr($homepage['text'], 0, self::HOMEPAGE_CLIP)."\n\nANNOUNCEMENTS: ".implode(' | ', $homepage['banners'] ?? []);
        foreach ($pages as $page) {
            $texts .= "\n\nPAGE ".($page['final_url'] ?? $page['url'])."\n".mb_substr($page['text'], 0, self::PAGE_CLIP);
        }

        $prompt = <<<PROMPT
You are reading the public website of {$competitor->name} ({$competitor->website}), a competitor that sells to {$competitor->sells_to->value} customers, prices in {$competitor->currency->code}.
Pull out the facts below using only what the pages say. Where a page does not say, write "not found". Never guess or infer, and keep each value short: the fact itself, with amounts, in under 30 words. Every fact carries the URL of the PAGE it came from, copied exactly from the PAGE lines.

Reply with JSON only:
{"shipping":{"costs":"what delivery costs, with amounts","zones":"countries or regions they ship to","free_shipping_threshold":"order value above which delivery is free","delivery_times":"how long delivery takes","source":"url"},
"minimum_order":{"value":"minimum order value or quantity","source":"url"},
"returns":{"window":"days to return and the conditions, briefly","source":"url"},
"payments":{"methods":["card","PayPal",...],"source":"url"},
"offers":[{"text":"a site-wide offer: banner promotion, coupon code, volume or tier discount, newsletter or first-order discount, loyalty scheme","kind":"banner|coupon|volume|newsletter|loyalty|other","source":"url"}],
"identity":{"legal_name":"registered company name if stated","address":"postal address if stated","country":"country of the business","source":"url"}}

$texts
PROMPT;

        $answer = AskToAi::run($prompt, config('services.competitor_intel.extraction_model'), ['response_format' => ['type' => 'json_object']]);

        return $answer === null ? null : static::json($answer);
    }

    /**
     * Only the pages that were read can be a source, so a made-up link is never shown.
     *
     * @param  array<int, string>  $sources
     */
    public static function withKnownSources(array $facts, array $sources): array
    {
        $known = array_map(fn ($url) => rtrim($url, '/'), $sources);
        array_walk_recursive($facts, function (&$value, $key) use ($known) {
            if ($key === 'source' && is_string($value) && !in_array(rtrim($value, '/'), $known, true)) {
                $value = null;
            }
        });

        return $facts;
    }

    /**
     * @return array{briefing: string|null, positioning: string|null}
     */
    private function brief(Competitor $competitor, array $intel, array $changes): array
    {
        $facts = json_encode(Arr::only($intel, ['identity', 'shipping', 'minimum_order', 'returns', 'payments', 'offers', 'numbers']) + ['announcements' => $intel['homepage']['banners'] ?? []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $changed = $changes
            ? collect($changes)->map(fn ($change) => '- '.__(self::CHANGE_LABELS[$change['key']]).': '.($change['from'] ?? '—').' → '.($change['to'] ?? '—'))->implode("\n")
            : 'nothing since the last refresh';

        $prompt = <<<PROMPT
You write a competitor briefing for Tomas, who sets our prices, and David, who runs the company. Plain English, no jargon, no bullet points, only what the facts below support.
The competitor is {$competitor->name} ({$competitor->website}), sells to {$competitor->sells_to->value} customers, prices in {$competitor->currency->code}. "numbers" are what we measured from their catalogue and our confirmed product matches: median_difference is how much dearer (positive) or cheaper (negative) they are than us per unit, in percent; share_on_sale is the share of their products on offer right now.

Reply with JSON only: {"briefing":"4 to 6 sentences: who they are, how they price against us, how much they rely on offers, their shipping and terms, and anything that changed","positioning":"one line on how they position themselves, e.g. premium, cheap and cheerful, trade-only"}

Facts:
$facts

Changed since the last refresh:
$changed
PROMPT;

        $answer = static::json(AskToAi::run($prompt, config('services.competitor_intel.briefing_model'), ['response_format' => ['type' => 'json_object']]));

        return [
            'briefing'    => static::clean($answer['briefing'] ?? null),
            'positioning' => static::clean($answer['positioning'] ?? null),
        ];
    }

    private static function json(?string $answer): array
    {
        $decoded = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', trim((string) $answer))), true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function clean(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = Str::squish((string) $value);

        return in_array(mb_strtolower(rtrim($value, '.!')), self::NOT_FOUND, true) ? null : mb_substr($value, 0, 2500);
    }

    private static function source(array $fact): ?string
    {
        $source = static::clean($fact['source'] ?? null);

        return $source && str_starts_with($source, 'http') ? $source : null;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private static function facts(array $fact, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn ($key) => [$key => static::clean($fact[$key] ?? null)])->all() + ['source' => static::source($fact)];
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $values): array
    {
        return collect(is_array($values) ? $values : [$values])->map(fn ($value) => static::clean($value))->filter()->unique()->values()->all();
    }

    /**
     * @return array<int, array{text: string, kind: string, source: string|null}>
     */
    private static function offers(mixed $offers): array
    {
        return collect(is_array($offers) ? $offers : [])
            ->map(fn ($offer) => is_array($offer) ? [
                'text'   => static::clean($offer['text'] ?? null),
                'kind'   => in_array($offer['kind'] ?? null, ['banner', 'coupon', 'volume', 'newsletter', 'loyalty'], true) ? $offer['kind'] : 'other',
                'source' => static::source($offer),
            ] : null)
            ->filter(fn ($offer) => $offer && $offer['text'])
            ->unique('text')
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * What the company publishes about itself: its structured data first, then its contact links,
     * then what the AI read in the text.
     */
    public static function identity(array $homepage, array $fromText): array
    {
        $organisation = collect($homepage['json_ld'] ?? [])->first(fn ($item) => preg_match('/Organization|LocalBusiness|Store|Corporation/i', json_encode($item['@type'] ?? ''))) ?? [];
        $address      = $organisation['address'] ?? null;
        $addressLine  = is_array($address)
            ? Str::squish(implode(', ', array_filter([$address['streetAddress'] ?? null, $address['addressLocality'] ?? null, $address['addressRegion'] ?? null, $address['postalCode'] ?? null])))
            : (is_string($address) ? $address : '');

        return [
            'name'       => static::clean($organisation['name'] ?? $homepage['og_site_name'] ?? null),
            'legal_name' => static::clean($organisation['legalName'] ?? $fromText['legal_name'] ?? null),
            'phones'     => static::stringList(array_merge($homepage['phones'] ?? [], (array) ($organisation['telephone'] ?? []))),
            'emails'     => static::stringList(array_merge($homepage['emails'] ?? [], (array) ($organisation['email'] ?? []))),
            'address'    => static::clean($addressLine ?: ($fromText['address'] ?? null)),
            'country'    => static::clean((is_array($address) ? ($address['addressCountry'] ?? null) : null) ?? $fromText['country'] ?? null),
            'socials'    => array_filter($homepage['socials'] ?? [], fn ($url) => is_string($url) && preg_match('#^https?://#i', $url)),
            'source'     => static::source($fromText),
        ];
    }

    /**
     * Our side of the dossier: their catalogue as we read it, and how our confirmed matches compare.
     *
     * Product feeds and website searches do not say what is on offer, so their share on sale is unknown, not 0%.
     *
     * @return array{number_products: int, number_on_sale: int|null, share_on_sale: int|null, average_discount: float|null, price_min: float|null, price_max: float|null, number_confirmed: int, median_difference: int|null}
     */
    public static function numbers(Competitor $competitor): array
    {
        $sale  = ImportCompetitorFeed::readsOffers($competitor->feed_url)
            ? ImportCompetitorFeed::saleNumbers($competitor)
            : ['number_products' => $competitor->products()->count(), 'number_on_sale' => null, 'average_discount' => null];
        $range = $competitor->products()->selectRaw('min(price) as price_min, max(price) as price_max')->first();
        $sql   = (new static())->competitorPriceSql($competitor->group);

        $confirmed = DB::table('master_asset_competitor_products')
            ->join('competitor_products', 'competitor_products.id', '=', 'master_asset_competitor_products.competitor_product_id')
            ->join('master_assets', 'master_assets.id', '=', 'master_asset_competitor_products.master_asset_id')
            ->join('competitors', 'competitors.id', '=', 'competitor_products.competitor_id')
            ->where('competitors.id', $competitor->id)
            ->where('master_asset_competitor_products.status', 'confirmed')
            ->where('master_asset_competitor_products.is_same_item', true)
            ->selectRaw("count(*) as number_confirmed, percentile_cont(0.5) within group (order by {$sql['difference']}) as median_difference")
            ->first();

        return $sale + [
            'share_on_sale'     => $sale['number_products'] && $sale['number_on_sale'] !== null ? (int) round(100 * $sale['number_on_sale'] / $sale['number_products']) : null,
            'price_min'         => $range->price_min === null ? null : (float) $range->price_min,
            'price_max'         => $range->price_max === null ? null : (float) $range->price_max,
            'number_confirmed'  => (int) $confirmed->number_confirmed,
            'median_difference' => $confirmed->median_difference === null ? null : (int) round($confirmed->median_difference),
        ];
    }

    /**
     * What matters that differs from the refresh before, in plain words. The AI words a fact differently
     * every refresh, so a fact counts as changed when its numbers (amounts, days, thresholds) differ, or
     * its text when it has none; payment methods and phones are compared by their canonical form.
     *
     * @return array<int, array{key: string, from: string|null, to: string|null}>
     */
    public static function changes(array $previous, array $current): array
    {
        if (!$previous) {
            return [];
        }

        $changes = [];
        $field   = function (string $key, string $path) use (&$changes, $previous, $current) {
            [$from, $to] = [Arr::get($previous, $path), Arr::get($current, $path)];
            [$fromSignature, $toSignature] = [static::signature($from), static::signature($to)];
            if ($fromSignature !== '' && $toSignature !== '' && $fromSignature !== $toSignature) {
                $changes[] = ['key' => $key, 'from' => $from, 'to' => $to];
            }
        };
        $lists = function (string $addedKey, string $removedKey, string $path, callable $signature) use (&$changes, $previous, $current) {
            $from = collect(data_get($previous, $path, []))->keyBy($signature);
            $to   = collect(data_get($current, $path, []))->keyBy($signature);
            if ($from->isEmpty() || $to->isEmpty()) {
                return;
            }
            foreach ($to->diffKeys($from) as $added) {
                $changes[] = ['key' => $addedKey, 'from' => null, 'to' => $added['text'] ?? $added];
            }
            foreach ($from->diffKeys($to) as $removed) {
                $changes[] = ['key' => $removedKey, 'from' => $removed['text'] ?? $removed, 'to' => null];
            }
        };

        $field('free_shipping_threshold', 'shipping.free_shipping_threshold');
        $field('shipping_costs', 'shipping.costs');
        $field('delivery_times', 'shipping.delivery_times');
        $field('minimum_order', 'minimum_order.value');
        $field('returns_window', 'returns.window');
        $lists('offer_added', 'offer_ended', 'offers', fn ($offer) => static::offerSignature($offer));
        $lists('payment_added', 'payment_removed', 'payments.methods', fn ($method) => static::paymentSignature($method));
        $lists('phones', 'phones', 'identity.phones', fn ($phone) => preg_replace('/\D/', '', $phone));
        $lists('emails', 'emails', 'identity.emails', fn ($email) => mb_strtolower(trim($email)));
        $field('address', 'identity.address');
        $field('platform', 'homepage.platform');

        $products = [Arr::get($previous, 'numbers.number_products'), Arr::get($current, 'numbers.number_products')];
        if ($products[0] && abs($products[1] - $products[0]) / $products[0] > 0.1) {
            $changes[] = ['key' => 'number_products', 'from' => (string) $products[0], 'to' => (string) $products[1]];
        }

        $share = [Arr::get($previous, 'numbers.share_on_sale'), Arr::get($current, 'numbers.share_on_sale')];
        if ($share[0] !== null && $share[1] !== null && abs($share[1] - $share[0]) > 10) {
            $changes[] = ['key' => 'share_on_sale', 'from' => $share[0].'%', 'to' => $share[1].'%'];
        }

        return $changes;
    }

    /**
     * The numbers in a fact, sorted, or its squished text when it has none; null and "not found" are the same.
     * A page that timed out or a fact the AI missed this time is empty, and an empty side is not a change.
     */
    public static function signature(mixed $value): string
    {
        $text = mb_strtolower(Str::squish(is_array($value) ? implode(' ', $value) : (string) $value));
        if ($text === '') {
            return '';
        }

        preg_match_all('/\d+(?:[.,]\d+)?/', preg_replace('/(\d),(\d{3})(?!\d)/', '$1$2', $text), $numbers);
        if ($numbers[0]) {
            $numbers = array_unique(array_map(fn ($number) => (float) str_replace(',', '.', $number), $numbers[0]));
            sort($numbers);

            return 'n:'.implode(',', $numbers);
        }

        return preg_replace('/[^\p{L}\p{N} ]/u', '', $text);
    }

    /**
     * An offer is the same offer while its amounts match, whatever kind the AI files it under this time;
     * one without an amount ("sign up for news and offers") is one per kind, so rewording never counts
     * as a new offer.
     */
    public static function offerSignature(mixed $offer): string
    {
        $signature = static::signature(is_array($offer) ? ($offer['text'] ?? '') : $offer);

        return str_starts_with($signature, 'n:') ? $signature : (is_array($offer) ? ($offer['kind'] ?? 'other') : 'other');
    }

    /**
     * The AI calls the same cards "Visa and Mastercard" one week and "credit/debit cards" the next, so every
     * card is one method; only a method appearing or going away (PayPal, Klarna, bank transfer) is a change.
     */
    private const array PAYMENT_NAMES = [
        'paypal' => 'paypal', 'apple pay' => 'apple pay', 'google pay' => 'google pay', 'klarna' => 'klarna', 'clearpay' => 'clearpay', 'afterpay' => 'afterpay', 'shop pay' => 'shop pay',
        'direct debit' => 'direct debit', 'bank transfer' => 'bank transfer', 'bacs' => 'bank transfer', 'invoice' => 'invoice', 'cash' => 'cash', 'cheque' => 'cheque', 'bitcoin' => 'crypto', 'crypto' => 'crypto',
        'visa' => 'card', 'mastercard' => 'card', 'amex' => 'card', 'american express' => 'card', 'maestro' => 'card', 'stripe' => 'card', 'debit' => 'card', 'credit' => 'card', 'card' => 'card',
    ];

    public static function paymentSignature(mixed $method): string
    {
        $text = mb_strtolower(Str::squish((string) $method));
        foreach (self::PAYMENT_NAMES as $name => $signature) {
            if (str_contains($text, $name)) {
                return $signature;
            }
        }

        return $text;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $competitors = Competitor::query()
            ->when($command->option('competitor'), fn ($query, $id) => $query->where('id', $id))
            ->get();

        foreach ($competitors as $competitor) {
            if ($command->option('sync')) {
                $intel = $this->handle($competitor);
                $command->info("$competitor->name: ".($intel ? count($intel['pages']).' pages read, '.count($competitor->intelSnapshots()->latest('fetched_at')->latest('id')->first()->changes).' changes' : $competitor->refresh()->intel_error));
            } else {
                static::dispatch($competitor);
                $command->info("$competitor->name: queued");
            }
        }

        return 0;
    }
}
