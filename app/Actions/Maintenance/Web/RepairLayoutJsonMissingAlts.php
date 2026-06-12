<?php

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\GetWebpageAlt;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Collection as CollectionModel;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Exception;
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

            $alt = null;
            $webpageId = data_get($image, 'link_data.id');

            // 1. Resolve alt using GetWebpageAlt as the highest priority
            if ($webpageId) {
                $webpage = $this->webpages[$webpageId] ??= Webpage::find($webpageId);
                if ($webpage) {
                    $alt = GetWebpageAlt::run($webpage);
                }
            }

            // 2. Fallback to image caption if webpage alt is empty
            if (blank($alt)) {
                $caption = data_get($image, 'caption');
                if (!blank($caption)) {
                    $alt = $caption;
                }
            }

            if (!blank($alt)) {
                $alt = strip_tags($alt);
                $alt = html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $alt = preg_replace('/\s+/', ' ', $alt);
                $alt = trim($alt);
            }

            if (blank($alt)) {
                continue;
            }

            $changes++;

            $command?->line(
                "Webblock: {$item->id} || Webpage ID: " . ($webpageId ?? 'N/A') . " || Image index: {$key} || Webpage subtype : " . ($webpage?->sub_type?->value ?? 'N/A') . " || Alt: {$alt}"
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
                $this->updateWebpage($webpage, $command);
            }
        } else {
            // If database already has the alt value, but the webpage's snapshot is still empty, force synchronization of the snapshot.
            if ($apply && $modelHasWebBlocks->webpage) {
                $webpage = $modelHasWebBlocks->webpage;
                if ($this->isSnapshotStale($webpage, $item)) {
                    $command?->line("[SYNC SNAPSHOT] webpage:{$webpage->id} web_block:{$item->id}");
                    $this->updateWebpage($webpage, $command);
                }
            }
        }

        return $changes;
    }

    public function updateWebpage(Webpage $webpage, Command $command)
    {
        UpdateWebpageContent::run($webpage);

        if ($webpage->state != WebpageStateEnum::LIVE) {
            return;
        }

        if ($webpage->is_dirty) {
            $model = $webpage->model;
            $shouldPublish = false;

            if ($model instanceof Product) {
                if (in_array($model->state, [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING], true)) {
                    $shouldPublish = true;
                }
            } elseif ($model instanceof ProductCategory) {
                if (in_array($model->state, [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING], true)) {
                    $shouldPublish = true;
                }
            } elseif ($model instanceof CollectionModel) {
                if ($model->state === CollectionStateEnum::ACTIVE) {
                    $shouldPublish = true;
                }
            } else {
                // For pages with no model or storefront, publish if dirty
                $shouldPublish = true;
            }

            if ($shouldPublish) {
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

        try {
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());
            return;
        }

        ModelHasWebBlocks::where('website_id', $website->id)
            ->with(['webBlock', 'webpage'])
            ->chunk(100, function ($items) use ($command, &$total) {
                foreach ($items as $item) {
                    $total += $this->handle($item, false, $command);
                }
            });

        $command->info("Applied {$total} missing alt repairs.");
    }
}
