<?php

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Website\Layouts\FetchUsedProductWebBlock;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreProductWebpageDefaultWebBlocks
{
    use AsAction;
    use WithStoreWebpage;

    public function handle(Webpage $webpage): Webpage
    {
        $this->createWebBlockFromSavedTemplate($webpage, WebBlockTemplateEnum::PRODUCT, FetchUsedProductWebBlock::run($webpage->website));
        $this->createWebBlock($webpage, 'luigi-item-alternatives-1');
        $this->createWebBlock($webpage, 'luigi-trends-1');
        $this->createWebBlock($webpage, 'recommendation-customer-recently-bought-1');
        $this->createWebBlock($webpage, 'luigi-last-seen-1');

        return $webpage->refresh();
    }
}
