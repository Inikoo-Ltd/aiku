<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithNoDescription;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\Helpers\Translations\GetCatalogueTranslationBrief;
use App\Actions\Web\Website\BreakWebsiteCache;
use App\Actions\Web\Website\Cloudflare\PurgeCloudflareWebsitePages;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Fills the name and descriptions of every active product of a shop in one more language, on the
 * product only, never on its master. A product whose code and unit content (trade units and
 * units) match a product of a shop written in that language gets that shop's copy as it is,
 * without the AI; every other text is machine translated from the shop's own. How each text was made is kept in data.translation_sources.<locale>, so machine text can be
 * found and redone with --force, and German a person wrote (no marker) is never overwritten. A product
 * whose translation is rejected keeps its copies, is marked failed and is left alone until --retry-failed.
 * Customer bundles are the customer's own and never filled.
 *
 * --clean-shop-text cleans the shop's own-language descriptions instead (the language argument is the shop's):
 * the fields matching RESELLER_WORDING go to the AI with the clean check plus a lost-number check, are written on
 * the product only (never the master) and marked per field in data.translation_sources.<shop locale>; an applied run
 * breaks the website cache once at the end. --redo-cleaned then retranslates only the machine-made fields whose
 * shop text was cleaned after they were made, marking each field it looked at in translation_sources.<locale>.redo.
 *
 * --fill-from-shop=<shop> fills the shop's EMPTY description fields (never names, never a field with text) from the
 * same pack in another shop written in the same language, cleaning a borrowed text that carries reseller wording
 * first; written like --clean-shop-text and marked borrowed / borrowed_cleaned per field. --redo-cleaned then
 * translates those fields into the languages already filled.
 */
class FillShopProductTranslations
{
    use AsAction;
    use WithAIGateway;

    public const array FIELDS = ['name', 'description_title', 'description', 'description_extra'];

    public const string COPIED = 'copied';

    public const string MACHINE = 'machine';

    public const string CLEANED = 'copied_cleaned';

    public const string SHOP_TEXT_CLEANED = 'cleaned';

    public const string SHOP_TEXT_CHECKED = 'checked';

    public const string SHOP_TEXT_FAILED = 'clean_failed';

    public const string REDONE = 'done';

    public const string REDO_FAILED = 'failed';

    public const string REDO_KEPT = 'kept';

    public const string BORROWED = 'borrowed';

    public const string BORROWED_CLEANED = 'borrowed_cleaned';

    public const string BORROW_FAILED = 'borrow_failed';

    public const string SHOP_SPECIFIC = 'deliver(y|ies|ed)|(^|[^p])shipp(ing|ed)|dispatch|postage|courier|(business|working) days|uk mainland|mainland uk|(within|outside( of)?|across|throughout) (the )?(uk|united kingdom|eu|europe)|uk (orders|customers|delivery|stock)|£ ?[0-9]|€ ?[0-9]|[0-9] ?€|\$ ?[0-9]|[0-9] ?(gbp|eur)([^a-z]|$)|(^|[^a-z])(prices?|priced|rrp)([^a-z]|$)';

    public const string RESELLER_WORDING = 'customers|you can sell|your customer|your (e-?shop|shop|store|range)|wholesale|retailer|stock (up|a range|them|these)|profit|margin|awgifts|aw gifts|ancient wisdom|drop ?ship|(^|[^a-z])presents';

    private const array CLEANABLE_FIELDS = ['description_title', 'description', 'description_extra'];

    private const array INLINE_TAGS = ['strong', 'b', 'em', 'i', 'u', 'span'];

    private const array VOID_TAGS = ['br', 'img', 'hr', 'wbr'];

    private const string IMPERIAL_EQUIVALENT = '/(?<=\dmm|\dcm|\dm|\d[\s\x{00A0}]mm|\d[\s\x{00A0}]cm|\d[\s\x{00A0}]m)\s*\(\s*(?:approx\.?|ca\.?)?\s*\d[\d.,]*(?:\s*[x×]\s*\d[\d.,]*)*\s*(?:inch(?:es)?|in|ft|feet|foot|"|”|\')(?![a-z])\s*\)|(?<=\dmm|\dcm|\dm|\d[\s\x{00A0}]mm|\d[\s\x{00A0}]cm|\d[\s\x{00A0}]m)\s*\/\s*\d[\d.,]*\s*(?:inch(?:es)?|in|ft|feet|foot|"|”|\')(?![a-z])|(?<=\dkg|\dg|\d[\s\x{00A0}]kg|\d[\s\x{00A0}]g)\s*\(\s*(?:approx\.?|ca\.?)?\s*\d[\d.,]*(?:\s*[x×]\s*\d[\d.,]*)*\s*(?:oz|ounces?|lbs?|pounds?)(?![a-z])\s*\)|(?<=\dkg|\dg|\d[\s\x{00A0}]kg|\d[\s\x{00A0}]g)\s*\/\s*\d[\d.,]*\s*(?:oz|ounces?|lbs?|pounds?)(?![a-z])|(?<=\dml|\dl|\d[\s\x{00A0}]ml|\d[\s\x{00A0}]l)\s*\(\s*(?:approx\.?|ca\.?)?\s*\d[\d.,]*(?:\s*[x×]\s*\d[\d.,]*)*\s*(?:fl\.?\s*oz|fluid\s+ounces?)(?![a-z])\s*\)|(?<=\dml|\dl|\d[\s\x{00A0}]ml|\d[\s\x{00A0}]l)\s*\/\s*\d[\d.,]*\s*(?:fl\.?\s*oz|fluid\s+ounces?)(?![a-z])/iu';

    public string $commandSignature = 'products:fill-translations
        {shop : shop id or slug}
        {language : language code, e.g. de}
        {--source-shop= : shop id or slug to copy from (default: the open shop of the same organisation written in that language)}
        {--limit= : stop after this many products}
        {--chunk=50 : products per batch}
        {--apply : write the texts (without it nothing is saved, but texts to translate still go to the AI and are paid for)}
        {--force : redo products whose texts this command already made}
        {--retry-failed : only the products whose translation was rejected in an earlier run}
        {--clean-copies : only the copied descriptions: the AI removes what speaks to a reseller and changes nothing else}
        {--clean-shop-text : the shop own-language descriptions matching the reseller wording: the AI removes it and our own branding, nothing else (language = the shop language)}
        {--redo-cleaned : only the machine-made fields whose shop text was cleaned, and the empty ones whose shop text was borrowed, after they were made, translated from the new text (with --retry-failed: only the redos that were rejected)}
        {--fill-from-shop= : shop id or slug written in the same language: fill the empty description fields of this shop from the same pack there (language = the shop language; with --retry-failed: only the borrowed texts whose cleaning was rejected)}
        {--model= : OpenRouter model (default: the catalogue translation model)}';

    private array $stats = [];

    private float $cost = 0;

    private array $briefs = [];

    private ?string $lastFailure = null;

    private bool $lastFailureWasRejection = false;

    private ?Shop $sourceShop = null;

    private bool $cleaningShopText = false;

    private bool $wroteShopText = false;

    private bool $borrowing = false;

    /**
     * @return array{stats: array<string, int>, cost: float}
     */
    public function handle(Shop $shop, Language $language, ?Shop $sourceShop = null, bool $apply = false, bool $force = false, ?int $limit = null, int $chunk = 50, ?string $model = null, ?callable $report = null, bool $retryFailed = false, bool $cleanCopies = false, bool $cleanShopText = false, bool $redoCleaned = false, ?Shop $fillFromShop = null): array
    {
        $this->stats = ['copied' => 0, 'machine' => 0, 'mixed' => 0, 'cleaned' => 0, 'unchanged' => 0, 'nothing_to_translate' => 0, 'failed' => 0];
        $this->cost  = 0;
        $this->sourceShop     = $sourceShop;
        $this->cleaningShopText = $cleanShopText || $fillFromShop !== null;
        $this->wroteShopText    = false;
        $this->borrowing        = $fillFromShop !== null;
        $model ??= config('auto-translations.drivers.catalogue.model');
        $locale     = $language->code;
        $shopLocale = $shop->language->code;
        $processed  = 0;

        if ($cleanShopText && $locale !== $shopLocale) {
            throw new \InvalidArgumentException("--clean-shop-text cleans the shop's own language: pass $shopLocale");
        }
        if ($fillFromShop && ($locale !== $shopLocale || $fillFromShop->language_id !== $shop->language_id || $fillFromShop->id === $shop->id)) {
            throw new \InvalidArgumentException("--fill-from-shop takes another shop written in $shopLocale, and the language $shopLocale");
        }

        $query = Product::where('shop_id', $shop->id)
            ->where('state', ProductStateEnum::ACTIVE)
            ->where('is_bundle', false)
            ->whereNull('deleted_at');

        if ($fillFromShop) {
            $query->where(function ($query) use ($locale, $retryFailed, $fillFromShop) {
                foreach (self::CLEANABLE_FIELDS as $field) {
                    $query->orWhereRaw(
                        "(coalesce(regexp_replace(products.$field, '<[^>]*>|&nbsp;|\\s', '', 'g'), '') = '' and coalesce(products.data->'translation_sources'->?->'fields'->>?, '') ".($retryFailed ? '=' : '<>').' ?'
                        ." and lower(products.code) in (select lower(src.code) from products src where src.shop_id = ? and src.state = ? and not src.is_bundle and src.deleted_at is null and coalesce(regexp_replace(src.$field, '<[^>]*>|&nbsp;|\\s', '', 'g'), '') <> ''))",
                        [$locale, $field, self::BORROW_FAILED, $fillFromShop->id, ProductStateEnum::ACTIVE->value]
                    );
                }
            });
        } elseif ($cleanShopText) {
            $query->where(function ($query) use ($locale) {
                foreach (self::CLEANABLE_FIELDS as $field) {
                    $query->orWhereRaw("($field ~* ? and data->'translation_sources'->?->'fields'->>? is null)", [self::RESELLER_WORDING, $locale, $field]);
                }
            });
        } elseif ($redoCleaned) {
            $query->where(function ($query) use ($locale, $shopLocale, $retryFailed) {
                foreach (self::CLEANABLE_FIELDS as $field) {
                    foreach ([[[self::SHOP_TEXT_CLEANED], self::MACHINE, 'cleaned_at'], [[self::BORROWED, self::BORROWED_CLEANED], null, 'borrowed_at']] as [$shopMarks, $translatedMark, $since]) {
                        $query->orWhereRaw(
                            "(data->'translation_sources'->?->'fields'->>? in (".implode(', ', array_fill(0, count($shopMarks), '?')).') and '
                            .($translatedMark ? "data->'translation_sources'->?->'fields'->>? = ? and " : "data->'translation_sources'->? is not null and data->'translation_sources'->?->'fields'->>? is null and ")
                            .($retryFailed
                                ? "data->'translation_sources'->?->'redo'->>? = '".self::REDO_FAILED."'"
                                : "data->'translation_sources'->?->'redo'->>? is null and coalesce(data->'translation_sources'->?->>'at', '') < data->'translation_sources'->?->>'$since'")
                            .')',
                            array_merge([$shopLocale, $field], $shopMarks, $translatedMark ? [$locale, $field, $translatedMark] : [$locale, $locale, $field], [$locale, $field], $retryFailed ? [] : [$locale, $shopLocale])
                        );
                    }
                }
            });
        } elseif ($cleanCopies) {
            $query->where(function ($query) use ($locale) {
                foreach (self::CLEANABLE_FIELDS as $field) {
                    $query->orWhereRaw("data->'translation_sources'->?->'fields'->>? = ?", [$locale, $field, self::COPIED]);
                }
            })->whereRaw("data->'translation_sources'->?->>'clean_failed' is null", [$locale]);
        } elseif ($retryFailed) {
            $query->whereRaw("data->'translation_sources'->?->>'failed' is not null", [$locale]);
        } elseif ($force) {
            $query->where(fn ($query) => $query->whereRaw('NOT (coalesce(name_i8n, \'{}\') ?? ?)', [$locale])->orWhereRaw("data->'translation_sources' ?? ?", [$locale]));
        } else {
            $query->whereRaw('NOT (coalesce(name_i8n, \'{}\') ?? ?)', [$locale])->whereRaw("NOT (coalesce(data->'translation_sources', '{}') ?? ?)", [$locale]);
        }

        $query->chunkById($chunk, function (Collection $products) use ($sourceShop, $language, $locale, $shopLocale, $apply, $model, $limit, $report, $cleanCopies, $cleanShopText, $redoCleaned, $retryFailed, $fillFromShop, &$processed) {
            $siblings     = match (true) {
                $fillFromShop !== null                                     => $this->siblings($products, $fillFromShop, activeOnly: true),
                $sourceShop && !$cleanCopies && !$cleanShopText && !$redoCleaned => $this->siblings($products, $sourceShop),
                default                                                    => collect(),
            };
            $this->briefs = [];

            foreach ($products as $product) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }
                $processed++;
                match (true) {
                    $fillFromShop !== null => $this->fillFromShop($product, $siblings->get(strtolower($product->code)), $language, $locale, $retryFailed, $apply, $model, $report),
                    $cleanShopText => $this->cleanShopText($product, $language, $locale, $apply, $model, $report),
                    $cleanCopies   => $this->cleanProduct($product, $language, $locale, $apply, $model, $report),
                    $redoCleaned   => $this->redoCleaned($product, $language, $locale, $shopLocale, $retryFailed, $apply, $model, $report),
                    default        => $this->fillProduct($product, $siblings->get(strtolower($product->code)), $language, $locale, $apply, $model, $report),
                };
            }

            return $limit === null || $processed < $limit;
        });

        if ($apply && $this->wroteShopText && $fillFromShop) {
            ShopHydrateProductsWithNoDescription::dispatch($shop);
        }
        if ($apply && $this->wroteShopText && $shop->website) {
            BreakWebsiteCache::run($shop->website, null);
            PurgeCloudflareWebsitePages::purgeNow($shop->website);
        }

        return ['stats' => $this->stats, 'cost' => round($this->cost, 4)];
    }

    private function fillProduct(Product $product, ?Product $sibling, Language $language, string $locale, bool $apply, string $model, ?callable $report): void
    {
        $fields      = [];
        $copies      = [];
        $sources     = [];
        foreach (self::FIELDS as $field) {
            $copy  = $sibling ? trim((string) $sibling->{$field}) : '';
            $shopText = trim((string) $product->{$field});
            if (!$this->isOurs($product, $field, $locale) || Arr::get($product->data, "translation_sources.$locale.fields.$field") === self::CLEANED) {
                continue;
            }
            if ($copy !== '' && $copy !== $shopText) {
                $copies[$field]  = $copy;
                $sources[$field] = self::COPIED;
            } elseif ($shopText !== '') {
                $fields[$field]  = ['translate' => $shopText];
                $sources[$field] = self::MACHINE;
            }
        }

        if (!$sources) {
            $this->stats['nothing_to_translate']++;

            return;
        }

        $translated = $fields ? $this->askWithRetry($fields, $product, $language, $model) : [];
        $failure    = $translated === null ? $this->lastFailure : null;
        $texts      = $copies + ($translated ?? []);
        $fields    += array_map(fn (string $copy) => ['copy' => $copy], $copies);

        if ($failure !== null) {
            $this->stats['failed']++;
            $report && $report($product, $fields, null, $sources, $failure);
        } else {
            $kinds = array_unique(array_values($sources));
            $this->stats[count($kinds) > 1 ? 'mixed' : $kinds[0]]++;
            $report && $report($product, $fields, $texts, $sources);
        }

        if (!$apply) {
            return;
        }

        $product = $product->fresh();
        $texts   = array_filter($texts, fn (string $field) => $this->isOurs($product, $field, $locale), ARRAY_FILTER_USE_KEY);
        foreach ($texts as $field => $text) {
            $product->setTranslation($field.'_i8n', $locale, $text);
        }
        $data = $product->data ?? [];
        $existing = (array) Arr::get($data, "translation_sources.$locale", []);
        data_set($data, "translation_sources.$locale", array_filter(['failed' => $failure]) + [
            'fields'          => Arr::only($sources, array_keys($texts)) + Arr::get($existing, 'fields', []),
            'hashes'          => array_map('md5', $texts) + Arr::get($existing, 'hashes', []),
            'from_product_id' => in_array(self::COPIED, $sources) ? $sibling->id : Arr::get($existing, 'from_product_id'),
            'model'           => in_array(self::MACHINE, Arr::only($sources, array_keys($texts))) ? $model : null,
            'at'              => now()->toIso8601String(),
        ] + Arr::except($existing, 'failed'));
        $product->data = $data;
        $product->save();
    }

    /**
     * Copied descriptions were written for trade buyers: the AI removes what speaks to a reseller and keeps the
     * rest word for word. A cleaned field is marked copied_cleaned and is not sent again; a rejected answer leaves
     * the copy as it was and marks the product clean_failed, while a call that failed (timeout, provider error) marks
     * nothing, so the next run tries it again.
     */
    private function cleanProduct(Product $product, Language $language, string $locale, bool $apply, string $model, ?callable $report): void
    {
        $sources = (array) Arr::get($product->data, "translation_sources.$locale.fields", []);
        $fields  = [];
        foreach (self::CLEANABLE_FIELDS as $field) {
            $text = trim((string) $product->getTranslation($field.'_i8n', $locale, false));
            if (($sources[$field] ?? null) === self::COPIED && $text !== '' && $this->isOurs($product, $field, $locale)) {
                $fields[$field] = ['clean' => $text];
            }
        }

        $texts     = $fields ? $this->askWithRetry($fields, $product, $language, $model) : null;
        $failure   = $fields ? ($texts === null ? $this->lastFailure : null) : 'nothing of ours to clean';
        $transient = $fields && $failure !== null && !$this->lastFailureWasRejection;

        $this->stats[$failure === null ? 'cleaned' : ($fields ? 'failed' : 'nothing_to_translate')]++;
        $report && $report($product, $fields, $texts, array_fill_keys(array_keys($fields), self::CLEANED), $failure);

        if (!$apply || $transient) {
            return;
        }

        $product = $product->fresh();
        $data    = $product->data ?? [];
        if ($failure !== null) {
            data_set($data, "translation_sources.$locale.clean_failed", $failure);
        } else {
            foreach ($texts as $field => $text) {
                if (!$this->isOurs($product, $field, $locale)) {
                    continue;
                }
                $product->setTranslation($field.'_i8n', $locale, $text);
                data_set($data, "translation_sources.$locale.fields.$field", self::CLEANED);
                data_set($data, "translation_sources.$locale.hashes.$field", md5($text));
            }
            data_set($data, "translation_sources.$locale.cleaned_at", now()->toIso8601String());
        }
        $product->data = $data;
        $product->save();
    }

    /**
     * Fills each empty description field (null, blank or only markup) from the same pack in a shop written in the same
     * language. A borrowed text carrying reseller wording is cleaned first with the --clean-shop-text checks; a
     * rejected answer fills nothing and marks that field borrow_failed (tried again only with --retry-failed), a failed
     * call marks nothing. Saved like --clean-shop-text, on the product only; an error on one product never stops the run.
     */
    private function fillFromShop(Product $product, ?Product $sibling, Language $language, string $locale, bool $retryFailed, bool $apply, string $model, ?callable $report): void
    {
        $marks   = (array) Arr::get($product->data, "translation_sources.$locale.fields", []);
        $copies  = [];
        $toClean = [];
        foreach (self::CLEANABLE_FIELDS as $field) {
            $text = trim((string) $sibling?->{$field});
            if (!$sibling || !$this->isBlankText($product->{$field}) || $this->isBlankText($text) || (($marks[$field] ?? null) === self::BORROW_FAILED) !== $retryFailed) {
                continue;
            }
            if (preg_match('/'.self::RESELLER_WORDING.'|'.self::SHOP_SPECIFIC.'/iu', $text)) {
                $toClean[$field] = ['clean' => $text];
            } else {
                $copies[$field] = $text;
            }
        }

        if (!$copies && !$toClean) {
            $this->stats['nothing_to_translate']++;

            return;
        }

        $cleaned   = $toClean ? $this->askWithRetry($toClean, $product, $language, $model) : [];
        $failure   = $cleaned === null ? $this->lastFailure : null;
        $transient = $failure !== null && !$this->lastFailureWasRejection;
        $texts     = $copies + ($cleaned ?? []);

        $written = $texts;
        if ($apply && ($texts || !$transient)) {
            try {
                $written = $this->writeBorrowed($product, $sibling, $locale, $texts, array_keys($cleaned ?? []), $transient ? null : $failure, array_keys($toClean));
            } catch (Throwable $exception) {
                $failure = $exception->getMessage();
                $written = null;
            }
        }

        $this->stats[$failure !== null ? 'failed' : ($cleaned ? 'cleaned' : 'copied')]++;
        $report && $report(
            $product,
            array_map(fn (string $text) => ['copy' => $text], $copies) + $toClean,
            $written,
            array_fill_keys(array_keys($copies), self::BORROWED) + array_fill_keys(array_keys($cleaned ?? []), self::BORROWED_CLEANED),
            $failure
        );
    }

    /**
     * @param array<string, string> $texts
     * @param list<string> $cleanedFields
     * @param list<string> $sentToClean
     */
    /**
     * @return array<string, string> the texts written, by field
     */
    private function writeBorrowed(Product $product, Product $sibling, string $locale, array $texts, array $cleanedFields, ?string $failure, array $sentToClean): array
    {
        $product = $product->fresh();
        $data    = $product->data ?? [];
        $written = [];
        foreach ($texts as $field => $text) {
            if (!$this->isBlankText($product->{$field})) {
                continue;
            }
            $written[$field] = $text;
            Arr::forget($data, "translation_sources.$locale.failures.$field");
            $product->{$field} = $text;
            $product->setTranslation($field.'_i8n', $locale, $text);
            data_set($data, "translation_sources.$locale.fields.$field", in_array($field, $cleanedFields, true) ? self::BORROWED_CLEANED : self::BORROWED);
            data_set($data, "translation_sources.$locale.hashes.$field", md5($text));
            data_set($data, "translation_sources.$locale.borrowed_from.$field", $sibling->id);
            data_set($data, "translation_sources.$locale.borrowed_at", now()->toIso8601String());
            $this->wroteShopText = true;
        }
        if ($failure !== null) {
            $failedField = Arr::first($sentToClean, fn (string $field) => str_starts_with($failure, "$field:")) ?? $sentToClean[0];
            data_set($data, "translation_sources.$locale.fields.$failedField", self::BORROW_FAILED);
            data_set($data, "translation_sources.$locale.failures.$failedField", $failure);
        }
        $product->data = $data;
        $product->save();

        return $written;
    }

    /**
     * The text without the sentences that make a claim only the source shop can make (delivery, prices), whose
     * numbers a cleaned borrowed text may lose with them.
     */
    private function withoutShopClaims(string $text): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+|\n+/u', html_entity_decode(preg_replace('/<[^>]*>/', "\n", $text), ENT_QUOTES | ENT_HTML5));

        return implode("\n", array_filter($sentences, fn (string $sentence) => !preg_match('/'.self::SHOP_SPECIFIC.'/iu', $sentence)));
    }

    private function isBlankText(?string $text): bool
    {
        return preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5)) === '';
    }

    /**
     * Retranslates, from the cleaned shop text, the machine-made fields whose shop text was cleaned after they were
     * made, and translates the fields left empty whose shop text was borrowed after the last fill. Each field looked at is marked in translation_sources.<locale>.redo: done, kept (a person corrected the
     * German, left alone) or failed (rejected, selected again only with --retry-failed). A failed call marks nothing.
     */
    private function redoCleaned(Product $product, Language $language, string $locale, string $shopLocale, bool $retryFailed, bool $apply, string $model, ?callable $report): void
    {
        $translated = (array) Arr::get($product->data, "translation_sources.$locale", []);
        $cleaned    = (array) Arr::get($product->data, "translation_sources.$shopLocale", []);
        $fields     = [];
        $kept       = [];
        foreach (self::CLEANABLE_FIELDS as $field) {
            $redo      = Arr::get($translated, "redo.$field");
            $shopMark  = Arr::get($cleaned, "fields.$field");
            $wasMachine = $shopMark === self::SHOP_TEXT_CLEANED && Arr::get($translated, "fields.$field") === self::MACHINE;
            $wasEmpty   = in_array($shopMark, [self::BORROWED, self::BORROWED_CLEANED], true) && Arr::has($product->data ?? [], "translation_sources.$locale") && Arr::get($translated, "fields.$field") === null;
            $since      = $wasMachine ? 'cleaned_at' : 'borrowed_at';
            if ((!$wasMachine && !$wasEmpty)
                || ($retryFailed ? $redo !== self::REDO_FAILED : ($redo !== null || (string) Arr::get($translated, 'at') >= (string) Arr::get($cleaned, $since)))) {
                continue;
            }
            if ($this->isOurs($product, $field, $locale)) {
                $fields[$field] = ['translate' => trim((string) $product->{$field})];
            } else {
                $kept[] = $field;
            }
        }

        $texts     = $fields ? $this->askWithRetry($fields, $product, $language, $model) : [];
        $failure   = $texts === null ? $this->lastFailure : null;
        $transient = $failure !== null && !$this->lastFailureWasRejection;

        $this->stats[$failure !== null ? 'failed' : ($fields ? 'machine' : 'nothing_to_translate')]++;
        $report && $report($product, $fields, $texts, array_fill_keys(array_keys($fields), self::MACHINE) + array_fill_keys($kept, self::REDO_KEPT), $failure);

        if (!$apply || $transient || (!$fields && !$kept)) {
            return;
        }

        $product = $product->fresh();
        $data    = $product->data ?? [];
        foreach ($kept as $field) {
            data_set($data, "translation_sources.$locale.redo.$field", self::REDO_KEPT);
        }
        foreach (array_keys($fields) as $field) {
            if ($failure !== null) {
                data_set($data, "translation_sources.$locale.redo.$field", self::REDO_FAILED);
            } elseif (!$this->isOurs($product, $field, $locale)) {
                data_set($data, "translation_sources.$locale.redo.$field", self::REDO_KEPT);
            } else {
                $product->setTranslation($field.'_i8n', $locale, $texts[$field]);
                data_set($data, "translation_sources.$locale.fields.$field", self::MACHINE);
                data_set($data, "translation_sources.$locale.hashes.$field", md5($texts[$field]));
                data_set($data, "translation_sources.$locale.redo.$field", self::REDONE);
            }
        }
        data_set($data, "translation_sources.$locale.redone_at", now()->toIso8601String());
        $product->data = $data;
        $product->save();
    }

    /**
     * The shop's own text, matched by RESELLER_WORDING, is live and copied into new portfolios. A cleaned field is
     * written on the product only, its plain column and its shop-locale translation in one audited save, never through
     * UpdateProduct, which would copy it into the master's English and from there over the other English shops. Each
     * field is marked cleaned, checked (returned unchanged) or clean_failed (rejected, with its reason) and never sent
     * again; a failed call marks nothing. A field whose text changed while the AI was answering is left alone, and an
     * error on one product is reported and the run goes on.
     */
    private function cleanShopText(Product $product, Language $language, string $locale, bool $apply, string $model, ?callable $report): void
    {
        $marks  = (array) Arr::get($product->data, "translation_sources.$locale.fields", []);
        $fields = [];
        foreach (self::CLEANABLE_FIELDS as $field) {
            $text = trim((string) $product->{$field});
            if (!isset($marks[$field]) && preg_match('/'.self::RESELLER_WORDING.'/iu', $text)) {
                $fields[$field] = ['clean' => $text];
            }
        }

        if (!$fields) {
            $this->stats['nothing_to_translate']++;

            return;
        }

        $texts     = $this->askWithRetry($fields, $product, $language, $model);
        $failure   = $texts === null ? $this->lastFailure : null;
        $transient = $failure !== null && !$this->lastFailureWasRejection;
        $changes   = $texts === null ? [] : array_filter($texts, fn (string $text, string $field) => $text !== $fields[$field]['clean'], ARRAY_FILTER_USE_BOTH);

        if ($apply && !$transient) {
            try {
                $this->writeShopText($product, $locale, $fields, $texts, $changes, $failure);
            } catch (Throwable $exception) {
                $failure = $exception->getMessage();
            }
        }

        $this->stats[$failure !== null ? 'failed' : ($changes ? 'cleaned' : 'unchanged')]++;
        $report && $report($product, $fields, $failure === null ? $texts : null, array_map(fn (string $field) => isset($changes[$field]) ? self::SHOP_TEXT_CLEANED : self::SHOP_TEXT_CHECKED, array_combine(array_keys($fields), array_keys($fields))), $failure);
    }

    /**
     * @param array<string, array{clean: string}> $fields
     * @param array<string, string>|null $texts
     * @param array<string, string> $changes
     */
    private function writeShopText(Product $product, string $locale, array $fields, ?array $texts, array $changes, ?string $failure): void
    {
        $product = $product->fresh();
        $data    = $product->data ?? [];
        if ($failure !== null) {
            $failedField = Arr::first(array_keys($fields), fn (string $field) => str_starts_with($failure, "$field:")) ?? array_key_first($fields);
            data_set($data, "translation_sources.$locale.fields.$failedField", self::SHOP_TEXT_FAILED);
            data_set($data, "translation_sources.$locale.failures.$failedField", $failure);
        } else {
            foreach ($texts as $field => $text) {
                if (trim((string) $product->{$field}) !== $fields[$field]['clean']) {
                    continue;
                }
                if (isset($changes[$field])) {
                    $product->{$field} = $text;
                    $product->setTranslation($field.'_i8n', $locale, $text);
                    data_set($data, "translation_sources.$locale.fields.$field", self::SHOP_TEXT_CLEANED);
                    data_set($data, "translation_sources.$locale.hashes.$field", md5($text));
                    data_set($data, "translation_sources.$locale.cleaned_at", now()->toIso8601String());
                    $this->wroteShopText = true;
                } else {
                    data_set($data, "translation_sources.$locale.fields.$field", self::SHOP_TEXT_CHECKED);
                }
            }
        }
        $product->data = $data;
        $product->save();
    }

    /**
     * A field is ours to (re)write while it is empty or still holds exactly the text this command
     * wrote: German a person wrote, before or after a run, is never overwritten, even with --force.
     */
    private function isOurs(Product $product, string $field, string $locale): bool
    {
        $current = $product->getTranslation($field.'_i8n', $locale, false);

        return blank($current) || Arr::get($product->data, "translation_sources.$locale.hashes.$field") === md5($current);
    }

    /**
     * @return array<string, string>|null
     */
    private function askWithRetry(array $fields, Product $product, Language $language, string $model): ?array
    {
        foreach ([0, 1] as $attempt) {
            try {
                $this->lastFailureWasRejection = false;
                $texts                         = $this->ask($product, $fields, $language, $model);
                $this->lastFailure             = $this->invalidReason($fields, $texts);
                $this->lastFailureWasRejection = true;
                if ($this->lastFailure === null) {
                    return Arr::only($texts, array_keys($fields));
                }
            } catch (RequestException $exception) {
                if (in_array($exception->response->status(), [401, 402, 403])) {
                    throw $exception;
                }
                $this->lastFailure = $exception->getMessage();
            } catch (Throwable $exception) {
                $this->lastFailure = $exception->getMessage();
            }
        }

        return null;
    }

    private function ask(Product $product, array $fields, Language $language, string $model): array
    {
        $response = $this->aiRequest()->timeout(120)->post('chat/completions', array_merge([
            'model'           => $model,
            'temperature'     => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => Arr::has(Arr::first($fields), 'clean') ? $this->cleanPrompt($language, $this->cleaningShopText) : $this->prompt($product, $language)],
                ['role' => 'user', 'content' => json_encode($fields, JSON_UNESCAPED_UNICODE)],
            ],
        ], config('services.openrouter.model_options')[$model] ?? []))->throw()->json();

        $this->cost += (float) $this->aiUsageCost((array) Arr::get($response, 'usage', []));
        $content     = trim(preg_replace('/^```(?:json)?|```$/m', '', (string) Arr::get($response, 'choices.0.message.content', '')));

        return (array) json_decode($content, true);
    }

    private function prompt(Product $product, Language $language): string
    {
        $brief = $this->briefs[$product->family_id ?? 0] ??= GetCatalogueTranslationBrief::run($language, $this->sourceFamily($product));
        return $brief.<<<EOT


This time the text is not for retailers: it is for the product page of an online retail store run by a dropshipper, read by a consumer buying the item.

You get a JSON object whose keys are fields of one product (name, description_title, description, description_extra), each holding {"translate": "..."}: translate it into {$language->name}, written for that consumer.
Keep every HTML tag in the same order, keep placeholders in braces and every number, size and measurement.
Reply with a JSON object with the same keys, each holding only the final {$language->name} text as a string.
EOT;
    }

    private function cleanPrompt(Language $language, bool $shopText = false): string
    {
        $resellerWording = $shopText
            ? 'wholesale, bulk buyers, trade prices, margins and profit, reselling, "for your shop", "your customers", "your range"; keep every pack size, quantity and what the item comes in'
            : 'wholesale, bulk buyers, minimum order, cartons or outer packs as the selling unit, trade prices, margins and profit, "for your shop", "your customers", "your range"';
        $branding = $shopText ? <<<EOT

Also remove our own shop name where it says who sells or presents the item: AWGifts, AW Gifts, Ancient Wisdom, Dropship, Dropshipping, Drop ship and Europe after them. "These AWGifts Dropship Europe Satya incense sticks" becomes "These Satya incense sticks", "from AWGifts - Dropshipping" is removed. Keep the maker's brand and the product's name (Satya, Stamford).
EOT : '';

        $shopClaims = $this->borrowing ? <<<EOT

The text was written for another shop: also remove what only holds there, such as delivery or shipping times, costs and areas ("standard delivery of 2 business days (UK Mainland)"), prices and currency amounts, and that shop's own name. Keep what describes the item, also where it is made ("handmade in the UK") and sizes in any system ("3.5-6.5 UK (36-40 EU)").
EOT : '';

        return <<<EOT
You edit product texts of an online retail store. They are written in {$language->name} and were written for trade buyers; the reader is now the consumer who buys the item.

You get a JSON object whose keys are fields of one product, each holding {"clean": "..."}. Return the same text with only this change: remove or minimally reword what speaks to a reseller, such as {$resellerWording}.
Keep every other word exactly as it is: do not translate, correct, shorten, improve, reorder or add anything, and do not change any number, size, measurement or placeholder in braces. Add no HTML tag; when you remove a whole sentence, remove the tags left empty around it. If nothing speaks to a reseller, return the text unchanged.{$branding}{$shopClaims}
Reply with a JSON object with the same keys, each holding only the final {$language->name} text as a string.
EOT;
    }

    /**
     * The family with the same code in the shop we copy from, whose published names in the target
     * language the brief shows as examples. The product's own family would show English beside English.
     */
    private function sourceFamily(Product $product): ?ProductCategory
    {
        if (!$this->sourceShop || !$product->family) {
            return null;
        }

        return ProductCategory::where('shop_id', $this->sourceShop->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->whereRaw('lower(code) = ?', [strtolower($product->family->code)])
            ->first();
    }

    public function invalidReason(array $fields, array $texts): ?string
    {
        foreach ($fields as $field => $input) {
            $output = Arr::get($texts, $field);
            $source = $input['translate'] ?? $input['clean'];
            if (!is_string($output) || trim($output) === '') {
                return "$field: empty";
            }
            if (preg_match('/<\s*[!?]/', $output) && !preg_match('/<\s*[!?]/', $source)) {
                return "$field: HTML tags changed";
            }
            if (isset($input['clean'])) {
                if ($reason = $this->invalidCleanReason($source, $output)) {
                    return "$field: $reason";
                }
                if ($this->cleaningShopText && $lost = $this->lostNumbers($this->borrowing ? $this->withoutShopClaims($source) : $source, $output, false)) {
                    return "$field: numbers lost (".implode(', ', $lost).')';
                }

                continue;
            }
            if (!$this->sameHtml($source, $output)) {
                return "$field: HTML tags changed";
            }
            if ($this->placeholders($source) !== $this->placeholders($output)) {
                return "$field: placeholders changed";
            }
            if ($lost = $this->lostNumbers($source, $output)) {
                return "$field: numbers lost (".implode(', ', $lost).')';
            }
            if ($output === $source && mb_strlen($source) > 25) {
                return "$field: not translated";
            }
        }

        return null;
    }

    /**
     * A cleaned copy may lose sentences and the tags around them, never gain any: its tags are the source's, in
     * order, with some left out; it adds no number, keeps most of the text and nearly all of its words are the source's.
     */
    private function invalidCleanReason(string $source, string $output): ?string
    {
        $outputTags = $this->tags($output);
        if (preg_match_all('/<\s*\/?\s*[a-z]/i', $output) !== count($outputTags)) {
            return 'HTML tags changed';
        }

        $remaining = array_column($this->tags($source), 'raw');
        foreach (array_column($outputTags, 'raw') as $raw) {
            $position = array_search($raw, $remaining, true);
            if ($position === false) {
                return 'HTML tags changed';
            }
            $remaining = array_slice($remaining, $position + 1);
        }
        if ($this->nests($this->tags($source)) && !$this->nests($outputTags)) {
            return 'HTML tags changed';
        }
        if (array_diff($this->numbers($output, false), $this->numbers($source, false))) {
            return 'numbers added';
        }
        if (array_diff($this->placeholders($output), $this->placeholders($source))) {
            return 'placeholders changed';
        }
        $sourceLength = mb_strlen(strip_tags($source));
        $keptLength   = $this->borrowing ? mb_strlen(trim(preg_replace('/\s+/u', ' ', $this->withoutShopClaims($source)))) : $sourceLength;
        if ($keptLength - mb_strlen(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($output), ENT_QUOTES | ENT_HTML5)))) > max(40, 0.3 * $keptLength)) {
            return 'too much removed';
        }
        if (mb_strlen(strip_tags($output)) > $sourceLength + 20) {
            return 'text added';
        }

        $words       = fn (string $text) => preg_split('/[^\p{L}]+/u', mb_strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5)), -1, PREG_SPLIT_NO_EMPTY);
        $longWords   = fn (string $text) => array_values(array_filter($words($text), fn (string $word) => mb_strlen($word) >= 3));
        $outputWords = $longWords($output);
        $newWords    = array_diff($outputWords, $longWords($source));
        if (count($newWords) > max(3, 0.1 * count($outputWords))) {
            return 'rewritten';
        }

        return null;
    }

    /**
     * Every tag outside inline emphasis must come back character for character and in the same
     * order. Inline emphasis may move, merge or drop, because word order differs between languages,
     * but never grows past the source, is bare or copied exactly from the source, and wraps no block
     * the source does not wrap. Every tag must still nest, and a "<" that opens no complete tag fails.
     * A source that does not nest itself keeps the strict comparison of every tag.
     */
    private function sameHtml(string $source, string $output): bool
    {
        $sourceTags = $this->tags($source);
        $outputTags = $this->tags($output);

        if (preg_match_all('/<\s*\/?\s*[a-z]/i', $output) !== count($outputTags)) {
            return false;
        }

        $raw = fn (array $tags) => array_column($tags, 'raw');
        if (!$this->nests($sourceTags, $sourceWrapsBlock)) {
            return $raw($sourceTags) === $raw($outputTags);
        }

        $isEmphasis = fn (array $tag) => in_array($tag['name'], self::INLINE_TAGS);
        $structure  = fn (array $tags) => $raw(array_values(array_filter($tags, fn (array $tag) => !$isEmphasis($tag))));
        $count      = fn (array $tags) => array_count_values(array_column(array_filter($tags, fn (array $tag) => !$tag['closing'] && $isEmphasis($tag)), 'name'));

        $sourceRaw   = $raw($sourceTags);
        $sourceCount = $count($sourceTags);
        foreach ($outputTags as $tag) {
            if (str_ends_with($tag['raw'], '/>') && !in_array($tag['name'], self::VOID_TAGS) && !in_array($tag['raw'], $sourceRaw)) {
                return false;
            }
            if ($isEmphasis($tag) && !preg_match('/^<\/?'.$tag['name'].'>$/i', $tag['raw']) && !in_array($tag['raw'], $sourceRaw)) {
                return false;
            }
        }
        foreach ($count($outputTags) as $name => $times) {
            if ($times > ($sourceCount[$name] ?? 0)) {
                return false;
            }
        }

        return $structure($sourceTags) === $structure($outputTags)
            && $this->nests($outputTags, $outputWrapsBlock)
            && (!$outputWrapsBlock || $sourceWrapsBlock);
    }

    /**
     * @return list<array{closing: string, name: string, raw: string}>
     */
    private function tags(string $text): array
    {
        preg_match_all('/<\s*(\/?)\s*([a-z][a-z0-9]*)[^<>]*>/i', $text, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match) => [
            'closing' => $match[1],
            'name'    => strtolower($match[2]),
            'raw'     => preg_replace(['/\s+/', '/^<\s*(\/?)\s*([a-z][a-z0-9]*)/i', '/\s*>$/'], [' ', '<$1$2', '>'], $match[0]),
        ], $matches);
    }

    private function nests(array $tags, ?bool &$emphasisWrapsBlock = false): bool
    {
        $open               = [];
        $emphasisWrapsBlock = false;
        foreach ($tags as $tag) {
            if (in_array($tag['name'], self::VOID_TAGS) || str_ends_with($tag['raw'], '/>')) {
                continue;
            }
            if ($tag['closing']) {
                if (array_pop($open) !== $tag['name']) {
                    return false;
                }
                continue;
            }
            if ($tag['name'] !== 'a' && !in_array($tag['name'], self::INLINE_TAGS) && array_intersect($open, self::INLINE_TAGS)) {
                $emphasisWrapsBlock = true;
            }
            $open[] = $tag['name'];
        }

        return $open === [];
    }

    private function placeholders(string $text): array
    {
        preg_match_all('/\{\{?[^{}]+\}\}?/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }

    /**
     * A separator before three digits is read both as a thousands separator (1,000) and as a list
     * (10,50,100ml): a number is lost only when the output misses it under both readings. The imperial
     * equivalent written right after a metric figure of the same kind may be dropped: inches or feet after mm, cm or
     * m ("34 x 18 cm (13.4 x 7.1 inch)", "45cm/18inch"), ounces or pounds after g or kg, fluid ounces after ml or l
     * ("200ml (6.8 fluid ounces)"). The metric figure still has to come back.
     *
     * @return list<string>
     */
    private function lostNumbers(string $source, string $output, bool $imperialMayDrop = true): array
    {
        if ($imperialMayDrop) {
            $source = preg_replace(self::IMPERIAL_EQUIVALENT, ' ', html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5));
        }
        $found = array_merge($this->numbers($output, true), $this->numbers($output, false));
        $lost  = array_values(array_diff($this->numbers($source, true), $found));

        return $lost && array_diff($this->numbers($source, false), $found) ? $lost : [];
    }

    private function numbers(string $text, bool $thousands): array
    {
        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        if ($thousands) {
            $plain = preg_replace('/(?<=\d)[.,\x{00A0}\x{202F}](?=\d{3}(?!\d))/u', '', $plain);
        }
        preg_match_all('/\d+/', $plain, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * The sibling with the same code, preferring an active one, only when it holds the same trade
     * units in the same quantities and the same units: a wholesale pack describes another thing.
     *
     * @return Collection<string, Product>
     */
    private function siblings(Collection $products, Shop $sourceShop, bool $activeOnly = false): Collection
    {
        $candidates = Product::where('shop_id', $sourceShop->id)
            ->whereNull('deleted_at')
            ->when($activeOnly, fn ($query) => $query->where('state', ProductStateEnum::ACTIVE)->where('is_bundle', false))
            ->whereIn(DB::raw('lower(code)'), $products->map(fn (Product $product) => strtolower($product->code))->all())
            ->orderByRaw("(state = 'active') desc, id desc")
            ->get()
            ->unique(fn (Product $product) => strtolower($product->code))
            ->keyBy(fn (Product $product) => strtolower($product->code));

        $contents = $this->unitContents($products->pluck('id')->merge($candidates->pluck('id'))->all());

        return $products
            ->mapWithKeys(function (Product $product) use ($candidates, $contents) {
                $sibling = $candidates->get(strtolower($product->code));

                return [
                    strtolower($product->code) => $sibling
                        && (float) $sibling->units === (float) $product->units
                        && ($contents[$sibling->id] ?? null) === ($contents[$product->id] ?? null)
                            ? $sibling : null,
                ];
            })
            ->filter();
    }

    /**
     * @return array<int, string>
     */
    private function unitContents(array $productIds): array
    {
        return DB::table('model_has_trade_units')
            ->where('model_type', 'Product')
            ->whereIn('model_id', $productIds)
            ->orderBy('trade_unit_id')
            ->get(['model_id', 'trade_unit_id', 'quantity'])
            ->groupBy('model_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => $row->trade_unit_id.':'.(float) $row->quantity)->implode(','))
            ->all();
    }

    public function sourceShop(Shop $shop, Language $language, ?string $sourceShop): ?Shop
    {
        if ($sourceShop) {
            return $this->findShop($sourceShop);
        }

        $candidates = Shop::where('organisation_id', $shop->organisation_id)
            ->where('language_id', $language->id)
            ->where('state', ShopStateEnum::OPEN)
            ->where('id', '!=', $shop->id)
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function findShop(string $shop): Shop
    {
        return Shop::where('slug', $shop)->when(is_numeric($shop), fn ($query) => $query->orWhere('id', (int) $shop))->firstOrFail();
    }

    /**
     * The part of the text between the first and the last change, as plain text, before and after.
     *
     * @return array{0: string, 1: string}
     */
    public function changedSpan(string $before, string $after): array
    {
        $plain  = fn (string $text) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<[^>]*>/', ' $0', $text)), ENT_QUOTES | ENT_HTML5)));
        $before = $plain($before);
        $after  = $plain($after);
        $prefix = 0;
        $max    = min(mb_strlen($before), mb_strlen($after));
        while ($prefix < $max && mb_substr($before, $prefix, 1) === mb_substr($after, $prefix, 1)) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < $max - $prefix && mb_substr($before, -$suffix - 1, 1) === mb_substr($after, -$suffix - 1, 1)) {
            $suffix++;
        }

        return [
            trim(mb_substr($before, $prefix, mb_strlen($before) - $prefix - $suffix)),
            trim(mb_substr($after, $prefix, mb_strlen($after) - $prefix - $suffix)),
        ];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shop       = $this->findShop($command->argument('shop'));
        $language   = Language::where('code', $command->argument('language'))->firstOrFail();
        $sourceShop = $this->sourceShop($shop, $language, $command->option('source-shop'));
        $apply      = (bool) $command->option('apply');

        $command->info(($apply ? 'Writing' : 'Dry run (nothing saved)').": {$shop->code} -> {$language->code}, copying from ".($sourceShop?->code ?? 'no shop, machine translation only'));

        $result = $this->handle(
            $shop,
            $language,
            $sourceShop,
            $apply,
            (bool) $command->option('force'),
            $command->option('limit') !== null ? (int) $command->option('limit') : null,
            (int) $command->option('chunk'),
            $command->option('model'),
            function (Product $product, array $fields, ?array $texts, array $sources, ?string $failure = null) use ($command) {
                $command->line("<comment>{$product->code}</comment> ".($texts === null ? "<error>FAILED</error> $failure" : implode(', ', array_unique(array_intersect_key($sources, $texts))).($failure !== null ? " <error>FAILED</error> $failure" : '')));
                if ($command->getOutput()->isVerbose() && $texts !== null) {
                    foreach ($texts as $field => $text) {
                        if (isset($fields[$field]['clean'])) {
                            [$removed, $now] = $this->changedSpan($fields[$field]['clean'], $text);
                            $command->line($removed === '' && $now === '' ? "  $field: unchanged" : "  $field: <fg=red>- $removed</>\n  $field: <fg=green>+ $now</>");
                        } else {
                            $command->line("  $field: ".mb_strimwidth(strip_tags(Arr::first($fields[$field])), 0, 160, '…'));
                            $command->line("  => ".mb_strimwidth(strip_tags($text), 0, 160, '…'));
                        }
                    }
                }
            },
            (bool) $command->option('retry-failed'),
            (bool) $command->option('clean-copies'),
            (bool) $command->option('clean-shop-text'),
            (bool) $command->option('redo-cleaned'),
            $command->option('fill-from-shop') ? $this->findShop($command->option('fill-from-shop')) : null
        );

        $command->table(['source', 'products'], collect($result['stats'])->map(fn ($count, $source) => [$source, $count])->values()->all());
        $command->info('AI cost: $'.number_format($result['cost'], 4));

        return Command::SUCCESS;
    }
}
