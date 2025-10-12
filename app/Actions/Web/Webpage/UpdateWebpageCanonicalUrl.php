<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Aug 2025 08:25:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWebpageCanonicalUrl implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Webpage $webpage): string
    {
        return (string)$webpage->id;
    }

    public function handle(Webpage $webpage, $updateChildren = true): string
    {
        $canonicalUrl = match ($webpage->type) {
            WebpageTypeEnum::CATALOGUE => $this->getWebpageTypeCatalogue($webpage),
            WebpageTypeEnum::STOREFRONT => '',
            WebpageTypeEnum::BLOG => 'blog/'.$webpage->url,
            default => $webpage->url
        };


        $canonicalUrl = 'https://www.'.$webpage->website->domain.'/'.$canonicalUrl;

        $canonicalUrl = $this->trimTrailingSlash($canonicalUrl);
        $canonicalUrl = replaceUrlSubdomain($canonicalUrl, $webpage->website->is_migrating ? 'v2' : 'www');

        $oldCanonicalUrl = $webpage->canonical_url;
        $webpage->update([
            'canonical_url' => $canonicalUrl,
        ]);

        if ($oldCanonicalUrl != $canonicalUrl) {
            $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_canonicals_'.$webpage->id;
            Cache::forget($key);
        }

        if ($updateChildren) {
            $this->updateChildrenCanonicalUrls($webpage);
        }

        return $canonicalUrl;
    }

    protected function updateChildrenCanonicalUrls(Webpage $webpage): void
    {
        $model = $webpage->model;
        if ($model instanceof ProductCategory) {
            foreach ($model->getProducts() as $product) {
                $webpage = $product->webpage;
                if ($webpage) {
                    UpdateWebpageCanonicalUrl::dispatch($webpage, false)->delay(2);
                }
            }
            foreach ($model->getFamilies() as $family) {
                $webpage = $family->webpage;
                if ($webpage) {
                    UpdateWebpageCanonicalUrl::dispatch($webpage, false)->delay(2);
                }
            }

            foreach ($model->getSubDepartments() as $subDepartment) {
                $webpage = $subDepartment->webpage;
                if ($webpage) {
                    UpdateWebpageCanonicalUrl::dispatch($webpage, false)->delay(2);
                }
            }

            foreach ($model->collections as $collection) {
                $webpage = $collection->webpage;
                if ($webpage) {
                    UpdateWebpageCanonicalUrl::dispatch($webpage, false)->delay(2);
                }
            }
        }
    }

    protected function getWebpageTypeCatalogue(Webpage $webpage): string
    {
        $canonicalUrl = $webpage->url ?? '';
        if ($webpage->model_type) {
            if ($webpage->model_type == 'ProductCategory') {
                $canonicalUrl = $this->getProductCategoryCanonicalUrl($webpage);
            } elseif ($webpage->model_type == 'Product') {
                $canonicalUrl = $this->getProductCanonicalUrl($webpage);
            } elseif ($webpage->model_type == 'Collection') {
                $canonicalUrl = $this->getCollectionCanonicalUrl($webpage);
            }
        }

        return $canonicalUrl;
    }

    protected function getCollectionCanonicalUrl(Webpage $webpage): string
    {
        /** @var Collection $collection */
        $collection = $webpage->model;

        $done = false;
        $url  = '';


        foreach ($collection->subDepartments as $subDepartment) {
            if ($subDepartment->webpage && $subDepartment->webpage->state != WebpageStateEnum::CLOSED) {
                $url = $this->getProductCategoryCanonicalUrl($subDepartment->webpage);

                $done = true;
                break;
            }
        }

        if (!$done) {
            foreach ($collection->departments as $department) {
                if ($department->webpage && $department->webpage->state != WebpageStateEnum::CLOSED) {
                    $url = $this->getProductCategoryCanonicalUrl($department->webpage);
                    break;
                }
            }
        }

        $url .= '/'.$webpage->url;

        return $this->trimTrailingSlash($url);
    }

    protected function getProductCanonicalUrl(Webpage $webpage): string
    {
        /** @var Product $product */
        $product = $webpage->model;

        $url = '';
        if ($product->family && $product->family->webpage && $product->family->webpage->state != WebpageStateEnum::CLOSED) {
            $url = $this->getProductCategoryCanonicalUrl($product->family->webpage);
        } elseif ($product->subDepartment && $product->subDepartment->webpage && $product->subDepartment->webpage->state != WebpageStateEnum::CLOSED) {
            $url = $this->getProductCategoryCanonicalUrl($product->subDepartment->webpage);
        } elseif ($product->department && $product->department->webpage && $product->department->webpage->state != WebpageStateEnum::CLOSED) {
            $url = $this->getProductCategoryCanonicalUrl($product->department->webpage);
        }
        $url .= '/'.$webpage->url;

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

    public string $commandSignature = 'webpage:update_canonical {type?} {slug?}';

    public function asCommand(Command $command): int
    {
        $debug = false;
        $query = DB::table('webpages')->select('id');
        if ($command->argument('type')) {
            if (in_array($command->argument('type'), ['page', 'webpage', 'p'])) {
                $query->where('slug', $command->argument('slug'));
                $debug = true;
            } elseif (in_array($command->argument('type'), ['website', 'w'])) {
                $website = Website::where('slug', $command->argument('slug'))->first();
                $query->where('website_id', $website->id);
            }
        }

        $startTime = microtime(true);
        $processed = 0;

        // Determine total for the progress bar
        $total       = $query->count();
        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->setRedrawFrequency(1);
        $progressBar->start();

        $query->orderBy('id')
            ->chunkById(200, function ($webpages) use (&$processed, $progressBar, $command, $debug) {
                foreach ($webpages as $webpageID) {
                    $webpage = Webpage::find($webpageID->id);
                    if ($webpage) {
                        $this->handle($webpage, false);
                        if ($debug) {
                            $command->info($webpage->id.' '.$webpage->url.' '.$webpage->canonical_url);
                        }
                    }

                    $processed++;
                    $progressBar->advance();
                }
            }, 'id');

        $progressBar->finish();
        $command->newLine(2);

        $duration = microtime(true) - $startTime;
        $human    = gmdate('H:i:s', (int)$duration);
        $command->info("Processed $processed webpages in $human.");

        return 0;
    }

}
