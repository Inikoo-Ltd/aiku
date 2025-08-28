<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2025 16:06:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepairUnpublishedProductsWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Webpage $webpage, Command $command): void
    {
        if ($webpage->model_type == 'Product') {
            $this->processProductWebpages($webpage, $command);
        }
    }

    protected function processProductWebpages(Webpage $webpage, Command $command): void
    {
        /** @var Product $product */
        $product = $webpage->model;

        if (!$product->is_main) {
            // Delete Webpage
            return;
        }

        if (in_array($product->state, [
            ProductStateEnum::ACTIVE,
            ProductStateEnum::DISCONTINUING
        ])) {
            $command->line($webpage->website->domain.' '.'Webpage '.$webpage->code.' publishing after upgrade');
            PublishWebpage::make()->action(
                $webpage,
                [
                    'comment' => 'Initial commit',
                ]
            );
        }
    }

    public string $commandSignature = 'repair:unpublished_products_webpages';

    public function asCommand(Command $command): void
    {
        // Process webpages in chunks to save memory
        DB::table('webpages')
            ->select('id')
            ->where('state', 'in_process')
            ->where('model_type', 'Product')
            ->orderBy('id')
            ->chunk(100, function ($webpagesID) use ($command) {
                foreach ($webpagesID as $webpageID) {
                    $webpage = Webpage::find($webpageID->id);
                    if ($webpage) {
                        $this->handle($webpage, $command);
                    }
                }
            });
    }

}
