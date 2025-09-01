<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Aug 2025 08:25:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWebpageCanonicalUrl implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Webpage $webpage): string
    {
        return (string)$webpage->id;
    }

    public function handle(Webpage $webpage): void
    {
        $canonicalUrl = $webpage->url ?? '';
        if ($webpage->model_type) {
            if ($webpage->model_type == 'ProductCategory') {
                $canonicalUrl = $this->getProductCategoryCanonicalUrl($webpage);
            } elseif ($webpage->model_type == 'Product') {
                $canonicalUrl = $this->getProductCanonicalUrl($webpage);
            }
        }

        $canonicalUrl = 'https://'.$webpage->website->domain.'/'.$canonicalUrl;

        $webpage->update([
            'canonical_url' => $canonicalUrl,
        ]);
    }

    protected function getProductCanonicalUrl(Webpage $webpage): string
    {
        /** @var Product $product */
        $product = $webpage->model;

        $url = '';
        // If product belongs to a family with a live webpage, base URL on that
        if ($product->family && $product->family->webpage && $product->family->webpage->state != WebpageStateEnum::CLOSED) {
            $url = $this->getProductCategoryCanonicalUrl($product->family->webpage);
        } elseif ($product->subDepartment && $product->subDepartment->webpage && $product->subDepartment->webpage->state != WebpageStateEnum::CLOSED) {
            $url = $this->getProductCategoryCanonicalUrl($product->subDepartment->webpage);
        } elseif ($product->department && $product->department->webpage && $product->department->webpage->state != WebpageStateEnum::CLOSED) {
            $url = $this->getProductCategoryCanonicalUrl($product->department->webpage);
        }
        $url .= $webpage->url;

        return $this->trimTrailingSlash($url);
    }

    protected function getProductCategoryCanonicalUrl(Webpage $webpage): string
    {
        $url = '';
        /** @var ProductCategory $productCategory */
        $productCategory = $webpage->model;


        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $url = $this->trimTrailingSlash($webpage->url);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            if ($departmentUrl = $this->getDepartmentUrl($productCategory)) {
                $url .= rtrim($departmentUrl, '/').'/';
            }
            $url .= $webpage->url;

            $url = $this->trimTrailingSlash($url);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            if ($departmentUrl = $this->getDepartmentUrl($productCategory)) {
                $url .= rtrim($departmentUrl, '/').'/';
            }
            if ($subDepartmentUrl = $this->getSubDepartmentUrl($productCategory)) {
                $url .= rtrim($subDepartmentUrl, '/').'/';
            }
            $url .= $webpage->url;

            $url = $this->trimTrailingSlash($url);
        }

        return $url;
    }

    protected function getSubDepartmentUrl(ProductCategory $model): string
    {
        $url           = '';
        $subDepartment = $model->subDepartment;
        if ($subDepartment && $subDepartment->webpage && $subDepartment->webpage->state != WebpageStateEnum::CLOSED) {
            $url = $subDepartment->webpage->url;
        }

        return $url;
    }

    protected function getDepartmentUrl(ProductCategory $model): string
    {
        $url        = '';
        $department = $model->department;
        if ($department && $department->webpage && $department->webpage->state != WebpageStateEnum::CLOSED) {
            $url = $department->webpage->url;
        }

        return $url;
    }

    private function trimTrailingSlash(?string $url): string
    {
        if (!$url) {
            return '';
        }
        // Preserve root '/'
        if ($url === '/') {
            return $url;
        }

        return rtrim($url, '/');
    }

    public string $commandSignature = 'webpage:update_canonical';

    public function asCommand(Command $command): void
    {
        $startTime = microtime(true);
        $processed = 0;

        // Determine total for the progress bar
        $total = Webpage::count();
        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->setRedrawFrequency(100);
        $progressBar->start();

        Webpage::query()
            ->with(['website', 'model' => function ($m) {
                // We will lazily touch nested relations in helpers; eager-load direct model to reduce N+1
            }])
            ->select(['id','website_id','model_type','model_id','slug','url'])
            ->orderBy('id')
            ->chunkById(500, function ($webpages) use (&$processed, $progressBar) {
                foreach ($webpages as $webpage) {
                    $this->handle($webpage);
                    $processed++;
                    $progressBar->advance();
                }
            }, 'id');

        $progressBar->finish();
        $command->newLine(2);

        $duration = microtime(true) - $startTime;
        $human = gmdate('H:i:s', (int) $duration);
        $command->info("Processed $processed webpages in $human.");
    }

}
