<?php

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Collection;
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

    // Cache webpages on class level to avoid reset on each handle() call
    private array $webpages = [];

    public function handle(ModelHasWebBlocks $modelHasWebBlocks, bool $apply = true, ?Command $command = null): int
    {
        $item     = $modelHasWebBlocks->webBlock;
        $changed  = false;
        $changes  = 0;

        if (!$item) {
            return 0;
        }

        $currentImages = data_get($item->layout, 'data.fieldValue.value.images', []);
        foreach ($currentImages as $key => $image) {
            if (!blank(data_get($image, 'properties.alt'))) {
                continue;
            }

            // 1. Take caption first as the highest priority
            $caption = data_get($image, 'caption');
            $alt = null;

            if (!blank($caption)) {
                $alt = $caption;
            } else {
                // 2. If caption is empty, fallback to model name or webpage title
                $webpageId = data_get($image, 'link_data.id');

                if ($webpageId) {
                    $webpage = $this->webpages[$webpageId] ??= Webpage::find($webpageId);

                    if ($webpage) {

                        $alt=GetWebpageAlt::run($webpage->id);


                        if ($this->canUseWebpageModelForAlt($webpage)) {
                            $modelName = $webpage->model?->name;
                            if (!blank($modelName)) {
                                $alt = $modelName;
                            }
                        }

                        // Fallback to Webpage Title if model name is empty or invalid
                        if (blank($alt) && !blank($webpage->title)) {
                            $alt = $webpage->title;
                        }
                    }
                }
            }

            if (!blank($alt)) {
                $alt = strip_tags($alt);
                $alt = html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $alt = preg_replace('/\s+/', ' ', $alt);
                $alt = trim($alt);
            }

            // If no suitable alt is found, skip
            if (blank($alt)) {
                continue;
            }

            $changes++;


//            $command?->line(
//                "[APPLY] model_has_web_blocks:    {$modelHasWebBlocks->webpage->canonical_url}  {$modelHasWebBlocks->id}   web_block:{$item->id} image_position:{$key} alt: {$alt}"
//
//            );
            $command?->line(
                "alt: {$alt}"

            );

            data_set(
                $currentImages[$key],
                'properties.alt',
                $alt
            );

            $changed = true;
        }

        if ($changed && $apply) {
            // Copy to local variable to ensure Eloquent dirty detection works correctly
            $layout = $item->layout;
            data_set($layout, 'data.fieldValue.value.images', $currentImages);
            $item->layout = $layout;
            $item->save();

            // Update snapshot for all webpages using this WebBlock
            foreach ($item->webpages as $webpage) {
                $this->updateWebpage($webpage,$command);
            }
        } else {
            // If database already has the alt value, but the webpage's snapshot is still empty, force synchronization of the snapshot.
            if ($apply && $modelHasWebBlocks->webpage) {
                $webpage = $modelHasWebBlocks->webpage;
                if ($this->isSnapshotStale($webpage, $item)) {
                    $command?->line("[SYNC SNAPSHOT] webpage:{$webpage->id} web_block:{$item->id}");
                    $this->updateWebpage($webpage,$command);
                }
            }
        }

        return $changes;
    }


    public function updateWebpage(Webpage $webpage,Command $command)
    {
        UpdateWebpageContent::run($webpage);

        if($webpage->state!=WebpageStateEnum::LIVE){
            return;
        }

        if ($webpage->is_dirty) {



            if (in_array($family->state, [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])) {
                $command->line('Webpage '.$webpage->code.' is dirty, publishing after upgrade');
                PublishWebpage::make()->action(
                    $webpage,
                    [
                        'comment' => 'publish after upgrade',
                    ]
                );
            }
        }


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

    private function isSnapshotStale(Webpage $webpage, $item): bool
    {
        $snapshot = $webpage->unpublishedSnapshot;
        if (!$snapshot) {
            return false;
        }

        $blocks = data_get($snapshot->layout, 'web_blocks', []);
        foreach ($blocks as $block) {
            if (data_get($block, 'web_block.id') == $item->id) {
                $snapshotImages = data_get($block, 'web_block.layout.data.fieldValue.value.images', []);
                $dbImages = data_get($item->layout, 'data.fieldValue.value.images', []);

                foreach ($dbImages as $key => $dbImg) {
                    $dbAlt = data_get($dbImg, 'properties.alt');
                    $snapAlt = data_get($snapshotImages, "{$key}.properties.alt");

                    // If alt is present in DB, but empty in snapshot
                    if (!blank($dbAlt) && blank($snapAlt)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public string $commandSignature = 'repair:layout_json_missing_alts {website}';

    public function asCommand(Command $command): void
    {
        $total = 0;

        ModelHasWebBlocks::with(['webBlock', 'webpage'])->chunk(100, function ($items) use ($command, &$total) {
            foreach ($items as $item) {
                $total += $this->handle($item, false, $command);
            }
        });

        $command->info("Applied {$total} missing alt repairs.");
    }
}
