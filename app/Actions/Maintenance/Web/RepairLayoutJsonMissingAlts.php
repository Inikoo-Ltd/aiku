<?php

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection as CollectionModel;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairLayoutJsonMissingAlts
{
    use AsAction;
    use WithActionUpdate;

    public function handle(ModelHasWebBlocks $modelHasWebBlocks, bool $apply = true, ?Command $command = null): int
    {
        $item     = $modelHasWebBlocks->webBlock;
        $changed  = false;
        $webpages = [];
        $changes  = 0;

        if (!$item) {
            return 0;
        }

        $currentImages = data_get($item->layout, 'data.fieldValue.value.images', []);
        foreach ($currentImages as $key => $image) {
            if (!blank(data_get($image, 'properties.alt'))) {
                continue;
            }

            $webpageId = data_get($image, 'link_data.id');

            if (!$webpageId) {
                continue;
            }

            $webpage = $webpages[$webpageId] ??= Webpage::find($webpageId);

            if (!$this->canUseWebpageModelForAlt($webpage)) {
                continue;
            }

            $alt = $webpage->model->name;
            $changes++;

            $command?->line(
                ($apply ? '[APPLY] ' : '[DRY] ')
                ."model_has_web_blocks:{$modelHasWebBlocks->id} web_block:{$item->id} image:{$key} "
                ."link:{$this->getWebpageModelLabel($webpage)} alt: {$alt}"
            );

            data_set(
                $currentImages[$key],
                'properties.alt',
                $alt
            );

            $changed = true;
        }

        if ($changed && $apply) {
            data_set($item->layout, 'data.fieldValue.value.images', $currentImages);
            $item->update(['layout' => $item->layout]);

            if (!$modelHasWebBlocks->webpage) {
                return $changes;
            }

            UpdateWebpageContent::run($modelHasWebBlocks->webpage);
        }

        return $changes;
    }

    private function canUseWebpageModelForAlt(?Webpage $webpage): bool
    {
        $model = $webpage?->model;

        if ($model instanceof Product || $model instanceof CollectionModel) {
            return true;
        }

        return $model instanceof ProductCategory
            && in_array($model->type, [
                ProductCategoryTypeEnum::DEPARTMENT,
                ProductCategoryTypeEnum::SUB_DEPARTMENT,
                ProductCategoryTypeEnum::FAMILY,
            ], true);
    }

    private function getWebpageModelLabel(Webpage $webpage): string
    {
        $model = $webpage->model;

        if ($model instanceof ProductCategory) {
            return $model->type->value.':'.$model->id;
        }

        return class_basename($model).':'.$model->id;
    }

    public string $commandSignature = 'repair:layout_json_missing_alts {--apply : Persist repaired layout JSON. Without this option, only preview changes.}';

    public function asCommand(Command $command): void
    {
        $apply = (bool)$command->option('apply');
        $total = 0;

        if (!$apply) {
            $command->warn('Preview mode only. Run with --apply to persist repaired alts.');
        }

        ModelHasWebBlocks::with(['webBlock', 'webpage'])->chunk(100, function ($items) use ($apply, $command, &$total) {
            foreach ($items as $item) {
                $total += $this->handle($item, $apply, $command);
            }
        });

        $command->info(($apply ? 'Applied' : 'Previewed')." {$total} missing alt repairs.");
    }
}
