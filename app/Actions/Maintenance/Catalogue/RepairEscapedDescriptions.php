<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Helpers\Translations\Translate;
use App\Models\Catalogue\Collection as CatalogueCollection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEscapedDescriptions
{
    use AsAction;

    private const FIELDS = ['description', 'description_title', 'description_extra'];

    private const MODELS = [
        Product::class,
        ProductCategory::class,
        CatalogueCollection::class,
    ];

    public function handle(Model $model, bool $apply): array
    {
        $changes = [];

        foreach (self::FIELDS as $field) {
            $scalar = $model->getAttributes()[$field] ?? null;
            if (is_string($scalar) && ($clean = Translate::stripJsonEscapes($scalar)) !== $scalar) {
                $changes[$field] = $clean;
            }

            $i8nField = $field.'_i8n';
            if (!in_array($i8nField, $model->translatable ?? [])) {
                continue;
            }

            $translations = $model->getTranslations($i8nField);
            $cleaned      = array_map(fn ($value) => is_string($value) ? Translate::stripJsonEscapes($value) : $value, $translations);

            if ($cleaned !== $translations) {
                $changes[$i8nField] = $cleaned;
            }
        }

        if ($changes && $apply) {
            $model->updateQuietly($changes);
        }

        return $changes;
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
