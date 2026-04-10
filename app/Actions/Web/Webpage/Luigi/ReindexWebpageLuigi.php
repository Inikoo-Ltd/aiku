<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:50:05 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Luigi;

use App\Actions\OrgAction;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\ActionRequest;

class ReindexWebpageLuigi extends OrgAction implements ShouldBeUnique
{
    public string $jobQueue = 'low-priority';

    public int $jobTries = 1;

    public function getJobUniqueId(Webpage $webpage): string
    {
        return $webpage->id;
    }

    /**
     * @throws \Exception
     */
    public function handle(Webpage $webpage): array
    {
        $msg = __('Reindexing running in the background');
        ReindexWebpageLuigiData::dispatch($webpage->id);

        if ($webpage->sub_type == WebpageSubTypeEnum::FAMILY && $webpage->model instanceof ProductCategory) {
            /** @var ProductCategory $family */
            $family = $webpage->model;
            $count  = 0;
            foreach ($family->getActiveProducts() as $product) {
                ReindexWebpageLuigiData::dispatch($product->webpage->id)->delay(5);
                $count++;
            }
            $msg = __('Family reindexing including product pages running in the background.').' ('.$count.' '.__('products').')';
        } elseif ($webpage->sub_type == WebpageSubTypeEnum::PRODUCT && $webpage->model instanceof Product) {
            $msg = __('Product reindexing running in the background');
        }

        return [
            'status'  => 'success',
            'message' => $msg,
        ];
    }


    public function jsonResponse(array $response): array
    {
        return $response;
    }

    /**
     * @throws \Exception
     */
    public function asController(Webpage $webpage, ActionRequest $request): array
    {
        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage);
    }
}
