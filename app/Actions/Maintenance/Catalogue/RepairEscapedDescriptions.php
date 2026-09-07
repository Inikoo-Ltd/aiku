<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Helpers\Translations\Translate;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigiData;
use App\Models\Catalogue\Collection as CatalogueCollection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairEscapedDescriptions
{
    use AsAction;

    private const FIELDS = ['description', 'description_title', 'description_extra'];

    private const MODELS = [
        MasterAsset::class,
        MasterProductCategory::class,
        MasterCollection::class,
        TradeUnit::class,
        Product::class,
        ProductCategory::class,
        CatalogueCollection::class,
    ];

    public array $skipped = [];

    public array $retranslated = [];

    public array $failed = [];

    public array $unstripped = [];

    public ?string $driver = null;

    private array $languages = [];

    private ?array $englishShopIds = null;

    public function handle(Model $model, bool $apply, bool $retranslate = false): array
    {
        $attributes = $model->getAttributes();
        $changes    = [];

        foreach (self::FIELDS as $field) {
            $source = $retranslate ? $this->englishSource($model, $field) : null;

            $scalar = $attributes[$field] ?? null;
            if (is_string($scalar) && str_contains($scalar, '\\')) {
                if (Translate::hasOnlyJsonEchoEscapes($scalar)) {
                    if (($clean = Translate::stripJsonEscapes($scalar)) !== $scalar) {
                        $changes[$field] = $clean;
                    } else {
                        $this->unstripped[] = $this->label($model, $field);
                    }
                } elseif (($rewritten = $this->rewrite($model, $field, $source, $this->shopLanguage($model), $apply)) !== null) {
                    $changes[$field] = $rewritten;
                }
            }

            $i8nField = $field.'_i8n';
            $raw      = $attributes[$i8nField] ?? null;
            if (!is_string($raw) || !json_validate($raw)) {
                continue;
            }

            $translations = json_decode($raw, true);
            if (!is_array($translations)) {
                continue;
            }

            $cleaned = $translations;
            foreach ($translations as $locale => $value) {
                if (!is_string($value) || !str_contains($value, '\\')) {
                    continue;
                }

                if (Translate::hasOnlyJsonEchoEscapes($value)) {
                    $cleaned[$locale] = Translate::stripJsonEscapes($value);
                    /* Escapes that pass the guard but survive the strip - a lone surrogate, a null -
                       leave the row damaged. Without this they are reported by neither bucket. */
                    if ($cleaned[$locale] === $value) {
                        $this->unstripped[] = $this->label($model, $i8nField.'.'.$locale);
                    }
                    continue;
                }

                $rewritten = $this->rewrite($model, $i8nField.'.'.$locale, $source, $this->language($locale), $apply);
                if ($rewritten !== null) {
                    $cleaned[$locale] = $rewritten;
                }
            }

            if ($cleaned !== $translations) {
                $changes[$i8nField] = json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        if ($changes && $apply) {
            $model->newQuery()->whereKey($model->getKey())->update($changes);
            $this->refreshDownstream($model);
        }

        return $changes;
    }

    /**
     * A field whose backslashes are not unambiguously JSON echoes cannot be unpicked, only
     * regenerated from the English source. Returns the replacement text, or null when there is
     * nothing to regenerate from, when the run is a dry one, or when the driver gave nothing usable.
     */
    private function rewrite(Model $model, string $label, ?string $source, ?Language $language, bool $apply): ?string
    {
        if ($source === null || !$language) {
            $this->skipped[] = $this->label($model, $label);

            return null;
        }

        if (!$apply) {
            $this->retranslated[] = $this->label($model, $label);

            return null;
        }

        /* gpt-5-nano reasons before it writes, costing ~25s a call against ~4s for the configured
           default. Translation needs no reasoning, so the default wins unless --driver says otherwise. */
        $translated = Translate::run($source, $this->language('en'), $language, $this->driver ?? config('auto-translations.default_driver'));

        if (!self::isUsableTranslation($translated, $source, $language->code)) {
            $this->failed[] = $this->label($model, $label);

            return null;
        }

        $this->retranslated[] = $this->label($model, $label);

        return $translated;
    }

    /**
     * The driver swallows its own failures and hands the source straight back, which is only a
     * legitimate result for an English variant. Anything still carrying a backslash is the very
     * damage being repaired, so it never gets written back.
     */
    public static function isUsableTranslation(?string $translated, string $source, string $languageCode): bool
    {
        if ($translated === null || $translated === '' || str_contains($translated, '\\')) {
            return false;
        }

        return $translated !== $source || str_starts_with($languageCode, 'en');
    }

    public function englishSource(Model $model, string $field): ?string
    {
        return match (true) {
            $model instanceof Product             => $this->cleanText($model->masterProduct?->{$field}) ?? $this->englishSibling($model, $field),
            $model instanceof ProductCategory     => $this->cleanText($model->masterProductCategory?->{$field}),
            $model instanceof CatalogueCollection => $this->cleanText($model->masterCollection?->{$field}),
            default                               => $this->cleanText($model->getAttributes()[$field] ?? null),
        };
    }

    private function cleanText(mixed $text): ?string
    {
        return is_string($text) && $text !== '' && !str_contains($text, '\\') ? $text : null;
    }

    /**
     * A product with no master at all falls back to the same product code in an English-language
     * shop, because codes are shared across the group's shops. The English shops disagree with each
     * other, so the lowest shop id wins, which is the UK catalogue.
     *
     * A product that does have a master is left alone even when the master's own field is empty:
     * its master is the stated source of truth, and a sibling's copy is a guess about a row someone
     * has already deliberately linked elsewhere.
     */
    private function englishSibling(Product $product, string $field): ?string
    {
        if ($product->master_product_id !== null) {
            return null;
        }

        /* languages.code carries a nondeterministic collation, which postgres refuses to LIKE against. */
        $this->englishShopIds ??= Shop::whereIn(
            'language_id',
            Language::pluck('id', 'code')->filter(fn ($id, $code) => str_starts_with($code, 'en'))->values()
        )->pluck('id')->all();

        return $this->cleanText(
            Product::where('code', $product->code)
                ->whereKeyNot($product->getKey())
                ->whereIn('shop_id', $this->englishShopIds)
                ->whereNotNull($field)
                ->where($field, '<>', '')
                ->whereRaw($field.' not like ?', ['%\\\\%'])
                ->orderBy('shop_id')
                ->orderBy('id')
                ->value($field)
        );
    }

    private function shopLanguage(Model $model): ?Language
    {
        return method_exists($model, 'shop') ? $model->shop?->language : null;
    }

    private function language(string $code): ?Language
    {
        return $this->languages[$code] ??= Language::where('code', $code)->first();
    }

    private function label(Model $model, string $field): string
    {
        return class_basename($model).' '.$model->getKey().' '.$field;
    }

    /**
     * The rows are written raw to keep the master->child re-translation cascade out of a repair run,
     * so the caches and indexes that a normal description change would invalidate are broken by hand.
     */
    private function refreshDownstream(Model $model): void
    {
        if (method_exists($model, 'searchable')) {
            $model->searchable();
        }

        $webpage = $model->webpage ?? null;
        if (!$webpage) {
            return;
        }

        BreakWebpageCache::dispatch($webpage, true);
        ReindexWebpageLuigiData::dispatch($webpage->id)->delay(60);
    }

    public string $commandSignature = 'repair:escaped_descriptions {--apply : Persist the changes, otherwise only report} {--retranslate : Regenerate fields that cannot be unpicked from their English source} {--driver= : Translation driver, defaults to the configured default}';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();
        $apply        = (bool)$command->option('apply');
        $retranslate  = (bool)$command->option('retranslate');
        $this->driver = $command->option('driver') ?: null;

        foreach (self::MODELS as $modelClass) {
            $stripped = 0;
            $before   = [count($this->retranslated), count($this->failed), count($this->skipped)];

            $modelClass::where(function ($query) {
                foreach (self::FIELDS as $field) {
                    $query->orWhereRaw($field.' like ?', ['%\\\\%'])
                        ->orWhereRaw($field.'_i8n::text like ?', ['%\\\\\\\\%']);
                }
            })->orderBy('id')->chunkById(200, function (Collection $models) use ($command, $apply, $retranslate, &$stripped) {
                foreach ($models as $model) {
                    $changes = $this->handle($model, $apply, $retranslate);
                    if ($changes) {
                        $stripped++;
                        /* A re-translating run spends seconds a field, so it reports as it goes
                           rather than leaving the operator staring at nothing until the class ends. */
                        $command->line('  '.$this->label($model, implode(', ', array_keys($changes))));
                    }
                }
            });

            $command->line(sprintf(
                '%s: %d row(s) rewritten, %d field(s) re-translated, %d failed, %d without a clean source',
                class_basename($modelClass),
                $stripped,
                count($this->retranslated) - $before[0],
                count($this->failed) - $before[1],
                count($this->skipped) - $before[2],
            ));
        }

        $buckets = [
            'failed'     => 'translation gave nothing usable',
            'unstripped' => 'escapes passed the guard but survived the strip, still damaged',
            'skipped'    => 'no clean English source, left untouched',
        ];

        foreach ($buckets as $bucket => $reason) {
            if ($this->{$bucket}) {
                $command->warn(count($this->{$bucket}).' field(s), '.$reason.':');
                foreach (array_slice($this->{$bucket}, 0, 50) as $label) {
                    $command->line('  '.$label);
                }
            }
        }

        if (!$apply) {
            $command->info('Dry run.'.($retranslate ? '' : ' Add --retranslate to regenerate the fields that cannot be unpicked.').' Re-run with --apply to persist.');
        }
    }
}
