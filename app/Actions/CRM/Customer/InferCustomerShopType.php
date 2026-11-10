<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Helpers\AI\AskToAi;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeSourceEnum;
use App\Exceptions\AICreditException;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Works out the shop type of a wholesale customer that has none, with a cheap language model.
 *
 * Never overwrites: a customer with a shop type is not asked, and the inferred type is only saved
 * (source inferred) when the customer still has none after the model answers.
 *
 * Sent to the model: the company name unless every word of it is in the contact name (a sole trader
 * who typed their own name; a business name that contains a person's name, like "Handmade by Wendy",
 * is still sent), the host of the website, the country, their own registration answers and the names
 * of the families they buy most. Never the contact name, the email or any address line. The command
 * skips a shop whose purchases are not worked out yet, because an attempt is recorded for good. Without a business name, website or
 * registration answer the model is not asked: what a shop buys alone does not tell its type.
 *
 * Each attempt that got an answer (or found no evidence) is recorded in data.shop_type_inference,
 * so the customer is not asked again; a failed call (no credit, no reply, unusable reply) is not
 * recorded and the customer is retried on a later run.
 */
class InferCustomerShopType
{
    use AsAction;

    public string $commandSignature = 'customers:infer-shop-types
        {--shop= : shop slug, default every open B2B shop}
        {--customer= : customer id, run now}
        {--limit=500 : how many customers to queue or dry-run}
        {--dry-run : ask and print, save nothing}';

    public string $commandDescription = 'Guess the shop type of active wholesale customers that have none, from their business name, website and what they buy';

    public string $jobQueue = 'low-priority';

    public int $jobTries = 1;

    public const string MODEL = 'deepseek/deepseek-v4.1-flash';

    public const float MIN_CONFIDENCE = 0.8;

    public const int TOP_FAMILIES = 8;

    public const int ACTIVE_MONTHS = 24;

    /**
     * @return array{shop_type: ?string, confidence: ?float, saved: bool, reason: string}
     */
    public function handle(Customer $customer, bool $dryRun = false): array
    {
        if ($customer->shop_type !== null) {
            return $this->result(null, null, false, 'already_set');
        }

        $businessName = $this->getBusinessName($customer);
        $website      = $this->getWebsiteHost($customer);
        $profile      = $this->getBusinessProfile($customer);

        if ($businessName === null && $website === null && $profile === '') {
            if (!$dryRun) {
                $this->recordAttempt($customer, 'no_evidence', null);
            }

            return $this->result(null, null, false, 'no_evidence');
        }

        try {
            $reply = AskToAi::run($this->prompt($customer, $businessName, $website, $profile), self::MODEL);
        } catch (AICreditException) {
            $reply = null;
        }

        $answer = $this->parseReply($reply);

        if ($answer === null) {
            return $this->result(null, null, false, 'no_reply');
        }

        [$shopType, $confidence] = $answer;

        if ($shopType === 'unknown') {
            $reason   = 'unknown';
            $shopType = null;
        } elseif ($confidence < self::MIN_CONFIDENCE) {
            $reason = 'low_confidence';
        } else {
            $reason = 'inferred';
        }

        $saved = false;

        if (!$dryRun) {
            if ($reason === 'inferred') {
                $current = Customer::find($customer->id);
                if ($current && $current->shop_type === null) {
                    UpdateCustomer::make()->action($current, [
                        'shop_type'        => $shopType,
                        'shop_type_source' => CustomerShopTypeSourceEnum::INFERRED->value,
                    ]);
                    $saved = true;
                }
            }

            $this->recordAttempt($customer, $shopType ?? 'unknown', $confidence);
        }

        return $this->result($shopType, $confidence, $saved, $reason);
    }

    /**
     * @return array{shop_type: ?string, confidence: ?float, saved: bool, reason: string}
     */
    private function result(?string $shopType, ?float $confidence, bool $saved, string $reason): array
    {
        return ['shop_type' => $shopType, 'confidence' => $confidence, 'saved' => $saved, 'reason' => $reason];
    }

    private function getBusinessName(Customer $customer): ?string
    {
        $companyName = trim((string) $customer->company_name);
        $words       = fn (string $text): array => array_filter(preg_split('/[^a-z0-9]+/', mb_strtolower(Str::ascii($text))));
        $companyWords = $words($companyName);

        if (!$companyWords || !array_diff($companyWords, $words((string) $customer->contact_name))) {
            return null;
        }

        return $companyName;
    }

    private function getWebsiteHost(Customer $customer): ?string
    {
        $website = trim((string) $customer->contact_website);

        if ($website === '') {
            return null;
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $website)) {
            $website = 'http://'.$website;
        }

        $host = parse_url($website, PHP_URL_HOST);

        return is_string($host) && str_contains($host, '.') ? mb_strtolower($host) : null;
    }

    private function getBusinessProfile(Customer $customer): string
    {
        return DB::table('poll_replies')
            ->join('polls', 'polls.id', '=', 'poll_replies.poll_id')
            ->leftJoin('poll_options', 'poll_options.id', '=', 'poll_replies.poll_option_id')
            ->where('poll_replies.customer_id', $customer->id)
            ->get(['polls.label as question', 'poll_options.label as option', 'poll_replies.value'])
            ->map(fn ($reply) => '- '.$reply->question.': '.mb_substr(trim((string) ($reply->option ?? $reply->value)), 0, 300))
            ->filter(fn ($line) => !str_ends_with($line, ': '))
            ->implode("\n");
    }

    private function getTopFamilies(Customer $customer): Collection
    {
        return DB::table('customer_product_purchases')
            ->join('product_categories', 'product_categories.id', '=', 'customer_product_purchases.family_id')
            ->where('customer_product_purchases.customer_id', $customer->id)
            ->groupBy('product_categories.id', 'product_categories.name')
            ->orderByRaw('sum(customer_product_purchases.orders_12m) desc, sum(customer_product_purchases.spend_12m) desc')
            ->limit(self::TOP_FAMILIES)
            ->pluck('product_categories.name');
    }

    private function prompt(Customer $customer, ?string $businessName, ?string $website, string $profile): string
    {
        $country  = data_get($customer->location, 0) ?: 'not given';
        $families = $this->getTopFamilies($customer)->map(fn ($name) => '- '.$name)->implode("\n") ?: 'none yet';

        $businessName ??= 'not given';
        $website      ??= 'not given';
        $profile      = $profile ?: 'not given';

        return <<<PROMPT
You classify the kind of business of a wholesale customer of a gift and wellness wholesaler.

Allowed values:
- crystal_new_age: crystals, spiritual, esoteric, tarot, witchcraft, new age shops
- gift_shop: gift, homeware, card, souvenir, museum or visitor-attraction shops
- spa_wellness: spas, salons, beauty and massage therapists, yoga and holistic practitioners
- garden_centre: garden centres, nurseries, farm shops
- online_seller: businesses selling mainly online, on marketplaces or by dropshipping
- market_trader: market stalls, fairs, festivals, events traders
- other: a clear business that fits none of these, e.g. pharmacy, hotel, cafe, charity

Business name: $businessName
Website: $website
Country: $country

Their own answers when they signed up:
$profile

Product families they order most:
$families

Base the judgement mainly on the business name, the website and their own words. What they buy only supports it and is never enough on its own. Answer unknown whenever the name does not say what kind of business it is.

Reply with JSON only, confidence from 0 to 1, shop_type one of the allowed values or unknown: {"shop_type":"gift_shop","confidence":0.9}
PROMPT;
    }

    /**
     * @return array{0: string, 1: float}|null
     */
    private function parseReply(?string $reply): ?array
    {
        $answer = json_decode(trim((string) preg_replace('/^```(json)?|```$/m', '', (string) $reply)), true);

        if (!is_array($answer)) {
            return null;
        }

        $shopType   = $answer['shop_type'] ?? null;
        $confidence = $answer['confidence'] ?? null;

        if (!is_string($shopType) || !($shopType === 'unknown' || CustomerShopTypeEnum::tryFrom($shopType)) || !is_numeric($confidence)) {
            return null;
        }

        return [$shopType, max(0.0, min(1.0, (float) $confidence))];
    }

    private function recordAttempt(Customer $customer, string $result, ?float $confidence): void
    {
        DB::update(
            "update customers set data = jsonb_set(coalesce(data, '{}'::jsonb), '{shop_type_inference}', cast(? as jsonb)) where id = ?",
            [
                json_encode([
                    'at'         => now()->toIso8601String(),
                    'result'     => $result,
                    'confidence' => $confidence,
                    'model'      => self::MODEL,
                ]),
                $customer->id,
            ]
        );
    }

    private function candidates(Collection $shops): Builder
    {
        return Customer::query()
            ->whereIn('shop_id', $shops->pluck('id'))
            ->where('last_invoiced_at', '>=', now()->subMonths(self::ACTIVE_MONTHS))
            ->whereNull('shop_type')
            ->whereNull('data->shop_type_inference')
            ->orderByDesc('last_invoiced_at')
            ->orderByDesc('id');
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        if ($customerId = $command->option('customer')) {
            $command->line(json_encode($this->handle(Customer::findOrFail($customerId), (bool) $command->option('dry-run')), JSON_PRETTY_PRINT));

            return 0;
        }

        $shops = Shop::where('type', ShopTypeEnum::B2B)
            ->where('state', ShopStateEnum::OPEN)
            ->when($command->option('shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        $ready = $shops->filter(fn (Shop $shop) => DB::table('customer_product_purchases')->where('shop_id', $shop->id)->exists());
        foreach ($shops->diff($ready) as $shop) {
            $command->warn("$shop->slug skipped: what its customers buy is not worked out yet, run range-advisor:rebuild-sales first");
        }
        $shops = $ready;

        $candidates = $this->candidates($shops)->limit((int) $command->option('limit'));

        if ($command->option('dry-run')) {
            $labels = CustomerShopTypeEnum::labels();
            $rows   = [];
            $totals = [];

            foreach ($candidates->get() as $customer) {
                $result = $this->handle($customer, true);
                $totals[$result['reason']] = ($totals[$result['reason']] ?? 0) + 1;

                $rows[] = [
                    $customer->reference,
                    $this->getBusinessName($customer) ?? '-',
                    $result['reason'] === 'inferred' ? $labels[$result['shop_type']] : $result['reason'].($result['shop_type'] ? ' ('.$labels[$result['shop_type']].')' : ''),
                    $result['confidence'] ?? '-',
                ];
            }

            $command->table(['Reference', 'Business name', 'Result', 'Confidence'], $rows);
            $command->info(collect($totals)->map(fn ($count, $reason) => "$reason: $count")->implode(', ') ?: 'No candidates');

            return 0;
        }

        $queued = 0;
        $candidates->pluck('id')->chunk(500)->each(function (Collection $ids) use (&$queued) {
            foreach (Customer::whereIn('id', $ids)->get() as $customer) {
                self::dispatch($customer);
                $queued++;
            }
        });

        $command->info("Queued $queued customers");

        return 0;
    }
}
