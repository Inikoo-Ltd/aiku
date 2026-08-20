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
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

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

    public function handle(Model $model, bool $apply): array
    {
        $attributes = $model->getAttributes();
        $changes    = [];

        foreach (self::FIELDS as $field) {
            $scalar = $attributes[$field] ?? null;
            if (is_string($scalar) && ($clean = Translate::stripJsonEscapes($scalar)) !== $scalar) {
                $changes[$field] = $clean;
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

            $cleaned = array_map(
                fn ($value) => is_string($value) ? Translate::stripJsonEscapes($value) : $value,
                $translations
            );

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

    public string $commandSignature = 'repair:escaped_descriptions {--apply : Persist the changes, otherwise only report}';

    public function asCommand(Command $command): void
    {
        $apply = (bool)$command->option('apply');

        foreach (self::MODELS as $modelClass) {
            $repaired = 0;

            $modelClass::where(function ($query) {
                foreach (self::FIELDS as $field) {
                    $query->orWhereRaw($field.' like ?', ['%\\\\%'])
                        ->orWhereRaw($field.'_i8n::text like ?', ['%\\\\\\\\%']);
                }
            })->orderBy('id')->chunkById(200, function (Collection $models) use ($apply, &$repaired) {
                foreach ($models as $model) {
                    if ($this->handle($model, $apply)) {
                        $repaired++;
                    }
                }
            });

            $command->line(class_basename($modelClass).': '.$repaired.($apply ? ' repaired' : ' would be repaired'));
        }

        if (!$apply) {
            $command->info('Dry run. Re-run with --apply to persist.');
        }
    }
}
