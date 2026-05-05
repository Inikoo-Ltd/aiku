<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 14:32:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */




/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRelatedProductsFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Shop $shop, ?Command $command = null): void
    {
        $organisation             = $shop->organisation;
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);


        Webpage::query()
            ->where(
                'website_id',
                $shop->website->id
            )
            ->whereNotNull('source_id')

            //    ->where('id',31890)
            ->orderBy('id')
            ->chunkById(1000, function ($webpages) use ($command) {
                foreach ($webpages as $webpage) {
                    $sourceData = explode(':', $webpage->source_id);

                    $auData = DB::connection('aurora')->table('Page Store Dimension')
                        ->where('Page Key', $sourceData[1])
                        ->first();

                    if ($auData) {
                        if ($auData->{'browser_title'} != '') {
                            $webpage->update(
                                [
                                    'title' => $auData->{'browser_title'}
                                ]
                            );
                        }

                        if ($auData->{'Webpage Meta Description'} != '') {
                            $webpage->update(
                                [
                                    'description' => $auData->{'Webpage Meta Description'}
                                ]
                            );
                        }
                    }
                }
            }, 'id');
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:get_aurora_related_products';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($shop, $command);

        return 0;
    }

}
