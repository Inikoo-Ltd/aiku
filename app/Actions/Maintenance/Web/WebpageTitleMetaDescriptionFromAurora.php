<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Mar 2026 23:54:05 Malaysia Time, Sanur, Bali, Indonesia
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

class WebpageTitleMetaDescriptionFromAurora
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
                                    'title' => $auData->{'Webpage Browser Title'}
                                ]
                            );
                        }

                        if ($auData->{'Webpage Meta Description'} != '') {
                            $webpage->update(
                                [
                                    'meta_description' => $auData->{'Webpage Meta Description'}
                                ]
                            );
                        }
                    }
                }
            }, 'id');
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:update_webpage_title_meta_description_from_aurora {shop}';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();
        $this->handle($shop, $command);

        return 0;
    }

}
