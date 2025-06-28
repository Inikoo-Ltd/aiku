<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairWebpageSeoData
{
    use WithActionUpdate;
    use WithOrganisationSource;


    /**
     * @throws \Exception
     */
    public function handle(Webpage $webpage, Command $command): void
    {
        if ($webpage->model_type == 'Product') {
            /** @var Product $product */
            $product = $webpage->model;
            $webpage->update([
                'breadcrumb_label' => $product->name
            ]);
        } elseif ($webpage->model_type == 'ProductCategory') {
            /** @var ProductCategory $productCategory */
            $productCategory = $webpage->model;
            $webpage->update([
                'breadcrumb_label' => $productCategory->name
            ]);
        } elseif ($webpage->model_type == 'Collection') {
            /** @var \App\Models\Catalogue\Collection $collection */
            $collection = $webpage->model;
            $webpage->update([
                'breadcrumb_label' => $collection->name
            ]);
        }

        $seoData = $webpage->seo_data;

        if ($structuredData = Arr::pull($seoData, 'structured_data')) {
            $structuredData = json_decode($structuredData, true);
            if (is_array($structuredData)) {
                $webpage->update(
                    [
                        'structured_data' => $structuredData
                    ]
                );
            }
        }
        if ($seoTitle = Arr::pull($seoData, 'meta_title')) {
            $webpage->update(
                [
                    'seo_title' => $seoTitle
                ]
            );
        }

        if ($seoDescription = Arr::pull($seoData, 'meta_description')) {
            $webpage->update(
                [
                    'seo_description' => $seoDescription
                ]
            );
        }

        $webpage->refresh();
        $auSource = $webpage->source_id;
        if ($auSource && !$webpage->seo_description) {
            $this->setSource($webpage->organisation);
            $auSource = explode(':', $auSource);

            $auData = DB::connection('aurora')->table('Page Store Dimension')
                ->select('Webpage Meta Description')
                ->where('Page Key', $auSource[1])
                ->first();
            if ($auData) {
                $webpage->update(
                    [
                        'seo_description' => $auData->{'Webpage Meta Description'}
                    ]
                );
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function setSource(Organisation $organisation): void
    {
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);
    }

    public string $commandSignature = 'repair:webpage_seo_data {webpage?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('webpage')) {
            $webpage = Webpage::find($command->argument('webpage'));
            $this->handle($webpage, $command);
        } else {
            $count = Webpage::count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            Webpage::orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar, $command) {
                    foreach ($models as $model) {
                        $this->handle($model, $command);
                        $bar->advance();
                    }
                });
        }
    }

}
