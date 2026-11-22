<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\Product\FillShopProductTranslations;
use App\Actions\Helpers\Translations\GetCatalogueTranslationBrief;
use App\Actions\Web\Website\BreakWebsiteCache;
use App\Actions\Web\Website\Cloudflare\PurgeCloudflareWebsitePages;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as LaravelCollection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fills the name and descriptions of the live departments, sub-departments, families and collections of a shop in
 * one more language, machine translated from the shop's own text with the AI and checks of products:fill-translations,
 * on the shop's record only, never on its master. The same-code texts of the shop written in that language are not
 * copied: in AW Dropship Europe 566 of 701 of them are another text written for wholesale buyers (22 Nov 2026), while
 * the shop's own speaks to its own audience. The translator is shown that shop's name for the same code, and for a family
 * how its products are named there, as naming examples instead.
 * How each text was made is kept in data.translation_sources.<locale> (fields, hashes, model, at, failed), so a run
 * resumes where it stopped, --force redoes only text this command wrote, and text a person wrote is never overwritten.
 */
class FillShopCategoryTranslations
{
    use AsAction;

    public const array CATEGORY_STATES = [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING];

    public const array CATEGORY_TYPES = [ProductCategoryTypeEnum::DEPARTMENT, ProductCategoryTypeEnum::SUB_DEPARTMENT, ProductCategoryTypeEnum::FAMILY];

    public string $commandSignature = 'product-categories:fill-translations
        {shop : shop id or slug}
        {language : language code, e.g. de}
        {--source-shop= : shop id or slug written in that language whose same-code family the translator is shown for naming (default: the open shop of the same organisation written in that language)}
        {--limit= : stop after this many departments, sub-departments, families and collections}
        {--chunk=50 : per batch}
        {--apply : write the texts (without it nothing is saved, but the texts still go to the AI and are paid for)}
        {--force : redo the texts this command already made}
        {--retry-failed : only the ones whose translation was rejected in an earlier run}
        {--model= : OpenRouter model (default: the catalogue translation model)}';

    private array $stats = [];

    private array $briefs = [];

    /**
     * @return array{stats: array<string, int>, cost: float}
     */
    public function handle(Shop $shop, Language $language, ?Shop $sourceShop = null, bool $apply = false, bool $force = false, ?int $limit = null, int $chunk = 50, ?string $model = null, ?callable $report = null, bool $retryFailed = false): array
    {
        $this->stats  = ['machine' => 0, 'nothing_to_translate' => 0, 'failed' => 0];
        $this->briefs = [];
        $translator   = new FillShopProductTranslations();
        $model      ??= config('auto-translations.drivers.catalogue.model');
        $locale       = $language->code;
        $processed    = 0;
        $wrote        = false;

        $queries = [
            ProductCategory::where('shop_id', $shop->id)->whereIn('type', self::CATEGORY_TYPES)->whereIn('state', self::CATEGORY_STATES),
            Collection::where('shop_id', $shop->id)->where('state', CollectionStateEnum::ACTIVE),
        ];

        foreach ($queries as $query) {
            $query->whereNull('deleted_at');
            match (true) {
                $retryFailed => $query->whereRaw("data->'translation_sources'->?->>'failed' is not null", [$locale]),
                $force       => $query,
                default      => $query->whereRaw("NOT (coalesce(data->'translation_sources', '{}') ?? ?)", [$locale]),
            };

            $query->chunkById($chunk, function (LaravelCollection $models) use ($translator, $sourceShop, $language, $locale, $apply, $model, $limit, $report, &$processed, &$wrote) {
                foreach ($models as $category) {
                    if ($limit !== null && $processed >= $limit) {
                        return false;
                    }
                    $processed++;
                    $wrote = $this->fill($category, $translator, $sourceShop, $language, $locale, $apply, $model, $report) || $wrote;
                }

                return true;
            });
        }

        if ($apply && $wrote && $shop->website) {
            BreakWebsiteCache::run($shop->website, null);
            PurgeCloudflareWebsitePages::purgeNow($shop->website);
        }

        return ['stats' => $this->stats, 'cost' => round($translator->cost(), 4)];
    }

    /**
     * @return bool whether a text was written
     */
    private function fill(ProductCategory|Collection $category, FillShopProductTranslations $translator, ?Shop $sourceShop, Language $language, string $locale, bool $apply, string $model, ?callable $report): bool
    {
        $fields = [];
        foreach (FillShopProductTranslations::FIELDS as $field) {
            $text = trim((string) $category->{$field});
            if ($text !== '' && $this->isOurs($category, $field, $locale)) {
                $fields[$field] = ['translate' => $text];
            }
        }

        if (!$fields) {
            $this->stats['nothing_to_translate']++;
            $report && $report($category, [], [], null);

            return false;
        }

        $texts               = $translator->askWithRetry($fields, $category, $language, $model, $this->prompt($category, $sourceShop, $language));
        [$failure, $refused] = $texts === null ? $translator->lastFailure() : [null, false];

        $this->stats[$failure === null ? 'machine' : 'failed']++;
        $report && $report($category, $fields, $texts, $failure);

        if (!$apply || ($failure !== null && !$refused)) {
            return false;
        }

        $category = $category->fresh();
        $data     = $category->data ?? [];
        $sources  = Arr::except((array) Arr::get($data, "translation_sources.$locale", []), 'failed');
        $wrote    = false;
        foreach ($failure === null ? $texts : [] as $field => $text) {
            if (!$this->isOurs($category, $field, $locale)) {
                continue;
            }
            $category->{$field.'_i8n'}  = array_merge($this->translations($category, $field), [$locale => $text]);
            $sources['fields'][$field] = FillShopProductTranslations::MACHINE;
            $sources['hashes'][$field] = md5($text);
            $sources['model']          = $model;
            $wrote                     = true;
        }
        data_set($data, "translation_sources.$locale", array_filter(['failed' => $failure]) + ['at' => now()->toIso8601String()] + $sources);
        $category->data = $data;
        $category->save();

        return $wrote;
    }

    private function prompt(ProductCategory|Collection $category, ?Shop $sourceShop, Language $language): string
    {
        $kind    = $category instanceof Collection ? 'collection' : str_replace('_', '-', $category->type->value);
        $sibling = $sourceShop
            ? $category::query()->where('shop_id', $sourceShop->id)->whereNull('deleted_at')->whereRaw('lower(code) = ?', [strtolower($category->code)])
                ->when($category instanceof ProductCategory, fn (Builder $query) => $query->where('type', $category->type))
                ->first()
            : null;
        $family  = $sibling instanceof ProductCategory && $sibling->type === ProductCategoryTypeEnum::FAMILY ? $sibling : null;
        $brief   = $this->briefs[$family->id ?? 0] ??= GetCatalogueTranslationBrief::run($language, $family);
        $naming  = $sibling ? "\nOur {$language->name} shop calls the same $kind \"".strip_tags($sibling->name).'": follow its wording where it names the same thing, but translate this name, which may differ.' : '';

        return $brief.<<<EOT


This time the text is for the page of a $kind (a group of products) on this web shop. Keep who it speaks to and what it says: translate it, do not rewrite it for another reader.$naming

You get a JSON object whose keys are fields of the $kind (name, description_title, description, description_extra), each holding {"translate": "..."}: translate it into {$language->name}.
Keep every HTML tag in the same order, keep placeholders in braces and every number, size and measurement.
Reply with a JSON object with the same keys, each holding only the final {$language->name} text as a string.
EOT;
    }

    /**
     * Read from the raw column: collections have no translatable trait, and a category's would fall back to another locale.
     *
     * @return array<string, string>
     */
    private function translations(Model $category, string $field): array
    {
        return (array) json_decode($category->getAttributes()[$field.'_i8n'] ?? '{}', true);
    }

    /**
     * A field is ours to (re)write while it is empty or still holds exactly the text this command wrote.
     */
    private function isOurs(Model $category, string $field, string $locale): bool
    {
        $current = $this->translations($category, $field)[$locale] ?? null;

        return blank($current) || Arr::get($category->data, "translation_sources.$locale.hashes.$field") === md5($current);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $findShop   = fn (string $shop) => Shop::where('slug', $shop)->when(is_numeric($shop), fn (Builder $query) => $query->orWhere('id', (int) $shop))->firstOrFail();
        $shop       = $findShop($command->argument('shop'));
        $language   = Language::where('code', $command->argument('language'))->firstOrFail();
        $sourceShop = FillShopProductTranslations::make()->sourceShop($shop, $language, $command->option('source-shop'));
        $apply      = (bool) $command->option('apply');

        $command->info(($apply ? 'Writing' : 'Dry run (nothing saved, the AI is still paid)').": {$shop->code} -> {$language->code}, naming examples from ".($sourceShop?->code ?? 'no shop'));

        $result = $this->handle(
            $shop,
            $language,
            $sourceShop,
            $apply,
            (bool) $command->option('force'),
            $command->option('limit') !== null ? (int) $command->option('limit') : null,
            (int) $command->option('chunk'),
            $command->option('model'),
            function (ProductCategory|Collection $category, array $fields, ?array $texts, ?string $failure) use ($command) {
                $kind = $category instanceof Collection ? 'collection' : $category->type->value;
                $command->line("<comment>$kind {$category->code}</comment> ".match (true) {
                    $failure !== null => "<error>FAILED</error> $failure",
                    !$fields          => 'nothing to translate',
                    default           => 'machine',
                });
                if ($command->getOutput()->isVerbose() && $texts) {
                    foreach ($texts as $field => $text) {
                        $command->line("  $field: ".mb_strimwidth(strip_tags($fields[$field]['translate']), 0, 160, '…'));
                        $command->line('  => '.mb_strimwidth(strip_tags($text), 0, 160, '…'));
                    }
                }
            },
            (bool) $command->option('retry-failed')
        );

        $command->table(['result', 'count'], collect($result['stats'])->map(fn ($count, $result) => [$result, $count])->values()->all());
        $command->info('AI cost: $'.number_format($result['cost'], 4));

        return Command::SUCCESS;
    }
}
